<?php
/**
 * Review totals and reviews from the client theme's scout-setup.json.
 *
 * Scout keeps track of each client's review profiles, so the theme carries
 * what Scout last saw, and Scout Build's "Fill from theme" hands it here:
 *
 * "reviews": {
 *   "totals": { "checked": "2026-11-09",
 *               "profiles": { "google": { "rating": 4.9, "count": 52, "url": "https://..." } } },
 *   "items":  [ { "source": "google", "author": "Pat R.", "rating": 5, "date": "2026-10-02",
 *                 "text": "...", "url": "https://..." } ]
 * }
 *
 * Totals: the newest check wins. A platform's totals change only when
 * "checked" is a real date, not in the future, and newer than the last time
 * that platform was checked (a save on Review settings counts as a check).
 * The saved stamp moves to the checked date, so Site status still says how
 * long ago someone actually looked.
 *
 * Reviews: each one is added once, matched on source, reviewer name and date,
 * including reviews in the trash. Existing reviews are never edited or removed,
 * so a review the client hid stays hidden.
 *
 * While Google is connected and syncing, Google's own numbers and reviews win,
 * and the theme's Google totals and Google reviews are skipped.
 *
 * Plain logic only, so it runs without WordPress (tools/tests/reviews-from-theme.php).
 *
 * @package Scout_Reviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scout_Reviews_Theme {

	/** Marks a review that came from the theme. */
	const META_FROM_THEME = '_scout_review_from_theme';

	/**
	 * Midnight of a YYYY-MM-DD date in the site's time zone, or 0 when the date
	 * is not a real one.
	 */
	public static function day( string $date ): int {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return 0;
		}
		try {
			$day = new DateTimeImmutable( $date . ' 00:00:00', self::zone() );
		} catch ( Exception $e ) {
			return 0;
		}
		return $day->getTimestamp();
	}

	/** True when a YYYY-MM-DD date is after today in the site's time zone. */
	public static function is_future( string $date ): bool {
		$today = ( new DateTimeImmutable( '@' . time() ) )->setTimezone( self::zone() )->format( 'Y-m-d' );
		return $date > $today;
	}

	private static function zone(): DateTimeZone {
		return function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
	}

	/**
	 * Apply the theme's totals where they are newer.
	 *
	 * @param array $totals { checked: YYYY-MM-DD, profiles: { slug: { rating, count, url } } }.
	 * @return string[] The platforms whose totals changed.
	 */
	public static function apply_totals( array $totals ): array {
		$date = (string) ( $totals['checked'] ?? '' );
		$when = self::day( $date );
		if ( ! $when || self::is_future( $date ) || empty( $totals['profiles'] ) || ! is_array( $totals['profiles'] ) ) {
			return array();
		}
		$sources = Scout_Reviews_Sources::all();
		$rows    = array();
		foreach ( $totals['profiles'] as $slug => $in ) {
			$slug = sanitize_key( (string) $slug );
			if ( ! is_array( $in ) || ! isset( $sources[ $slug ] ) || 'direct' === $slug ) {
				continue;
			}
			if ( 'google' === $slug && Scout_Reviews_Google::is_ready() ) {
				continue; // Google's own numbers come in every day.
			}
			if ( $when <= Scout_Reviews_Settings::checked_at( $slug ) ) {
				continue; // Someone checked more recently.
			}
			$rating = round( (float) ( $in['rating'] ?? 0 ), 1 );
			$row    = array(
				'rating' => ( $rating >= 1 && $rating <= 5 && ! empty( $sources[ $slug ]['stars'] ) ) ? $rating : 0,
				'count'  => max( 0, (int) ( $in['count'] ?? 0 ) ),
			);
			$url = esc_url_raw( trim( (string) ( $in['url'] ?? '' ) ), array( 'https', 'http' ) );
			if ( '' !== $url ) {
				$row['url'] = $url;
			}
			if ( ! $row['rating'] && ! $row['count'] && ! isset( $row['url'] ) ) {
				continue;
			}
			$rows[ $slug ] = $row;
		}
		Scout_Reviews_Settings::set_totals( $rows, $when );
		return array_keys( $rows );
	}

	/**
	 * Add the theme's reviews that are not on the site yet.
	 *
	 * @param array $items List of { source, author, rating, date, text, url }.
	 * @return int How many were added.
	 */
	public static function add_items( array $items ): int {
		$sources = Scout_Reviews_Sources::all();
		$synced  = Scout_Reviews_Google::is_ready();
		$added   = 0;
		foreach ( $items as $in ) {
			if ( ! is_array( $in ) ) {
				continue;
			}
			$source = sanitize_key( (string) ( $in['source'] ?? '' ) );
			$author = sanitize_text_field( (string) ( $in['author'] ?? '' ) );
			$text   = sanitize_textarea_field( (string) ( $in['text'] ?? '' ) );
			$date   = (string) ( $in['date'] ?? '' );
			$date   = self::day( $date ) ? $date : '';
			if ( ! isset( $sources[ $source ] ) || '' === $author || '' === $text ) {
				continue;
			}
			if ( 'google' === $source && $synced ) {
				continue; // The Google sync brings these in itself.
			}
			if ( self::exists( $source, $author, $date ) ) {
				continue;
			}
			$post_id = (int) wp_insert_post(
				wp_slash(
					array(
						'post_type'    => Scout_Reviews_Post_Type::TYPE,
						'post_status'  => 'publish',
						'post_title'   => $author,
						'post_content' => $text,
					)
				)
			);
			if ( ! $post_id ) {
				continue;
			}
			update_post_meta( $post_id, Scout_Reviews_Post_Type::META_SOURCE, $source );
			update_post_meta( $post_id, Scout_Reviews_Post_Type::META_RATING, max( 0, min( 5, (int) ( $in['rating'] ?? 0 ) ) ) );
			update_post_meta( $post_id, Scout_Reviews_Post_Type::META_URL, esc_url_raw( trim( (string) ( $in['url'] ?? '' ) ), array( 'https', 'http' ) ) );
			update_post_meta( $post_id, Scout_Reviews_Post_Type::META_DATE, $date );
			update_post_meta( $post_id, Scout_Reviews_Post_Type::META_FEATURED, false );
			update_post_meta( $post_id, self::META_FROM_THEME, 1 );
			++$added;
		}
		if ( $added ) {
			Scout_Reviews_Query::flush();
		}
		return $added;
	}

	/** A review with this source, reviewer name and date is already here (in any state, the trash too). */
	private static function exists( string $source, string $author, string $date ): bool {
		$ids = get_posts(
			array(
				'post_type'        => Scout_Reviews_Post_Type::TYPE,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array( 'key' => Scout_Reviews_Post_Type::META_SOURCE, 'value' => $source ),
				),
			)
		);
		// WordPress can store "&" in a title as "&amp;", so compare the decoded text.
		$want = html_entity_decode( $author, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		foreach ( (array) $ids as $id ) {
			$have = html_entity_decode( trim( (string) get_post_field( 'post_title', (int) $id ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( 0 === strcasecmp( $have, $want )
				&& (string) get_post_meta( (int) $id, Scout_Reviews_Post_Type::META_DATE, true ) === $date ) {
				return true;
			}
		}
		return false;
	}
}
