<?php
/**
 * The client setup file.
 *
 * A Scout client theme can carry `scout-setup.json` with the client's own
 * facts: team, review page, chat, hours. When the matching feature is on,
 * this fills it in so nobody retypes it in the dashboard. Each section runs
 * again only when its contents change (a new theme version), and it only
 * fills blanks: anything someone already set in WordPress stays as it is.
 * Two exceptions in "reviews": totals replace the saved ones when their
 * "checked" date is newer than the last check, and reviews are only added.
 *
 * Format (every section optional):
 * {
 *   "team":    [ { "name", "role", "bio", "email", "military_branch", "photo", "order" } ],
 *   "reviews": { "business", "headline", "ask", "photo", "card_headline", "google_review_url", "google_place_id",
 *                "totals": { "checked": "YYYY-MM-DD", "profiles": { "google": { "rating", "count", "url" } } },
 *                "items": [ { "source", "author", "rating", "date", "text", "url" } ] },
 *   "chat":    { "name", "team", "notify", "book_url", "work_url", "photo_1", "photo_2" },
 *   "hours":   { "confirmed": true, "days": { "tuesday": [ ["09:00", "17:00"] ] } },
 *   "analytics": { "gtm": "GTM-XXXXXXX" },
 *   "features":  { "use": [ "scout-core", "scout-forms-guard" ], "skip": [ "scout-chat" ] },
 *   "approved_plugins": [ "plugin-folder", { "plugin", "job", "owner", "decided": "YYYY-MM-DD" } ],
 *   "cards":   { "name", "phone", "email", "city", "region", "postal",
 *                "footer", "note", "team_intro", "links_intro", "photo", "spot_heading", "spot_text",
 *                "pick_heading", "spot_label", "spot_sub", "spot_url", "logo", "share_card", "share_links",
 *                "people": [ { "name", "slug", "title", "email", "intro", "pick", "tags", "photo", "share" } ],
 *                "links": [ { "label", "sub", "url" } ] },
 *   "brand":   { "on", "logo", "logo_width", "color", "background", "text", "message", "hide_menus", "off" },
 *   "dashboard": { "simple": { "on", "hide_groups", "hide_screens", "hide_menus" }, "land_on_home",
 *                  "shortcuts": [ { "label", "url", "note" } ], "links": [ { "label", "url", "note" } ] },
 *   "contact": { "sets": [ { "name", "pages", "children", "paths", "no_text", "label", "buttons": [ { "type", "label", "url" } ] } ] },
 *   "booking": { "decline_reasons", "link_mode", "link_url", "link_skip", "schema",
 *                "types": { "<session type id or name>": { "schema", "questions": [ { "label", "required", "kind", "link" } ] } } },
 *   "events":  { "door_role", "members_only": [ "<event slug>" ],
 *                "courses": [ { "name", "description", "photo", "page", "level", "prereq", "mode", "hours", "venue", "street", "city",
 *                               "region", "postal", "country", "address_private", "register_url", "register_label",
 *                               "teacher_names", "schema", "instances": [ ... ], "tiers": [ ... ] } ] }
 * }
 * "team" people also take "link" (a path or full address), "link_page" (a page path), "no_page" and
 * "licenses" (rows of name, number, issuer, url, source). "chat" also takes the outside widget keys:
 * "outside", "outside_src", "outside_async", "outside_defer", "outside_attrs", "outside_config",
 * "outside_config_var", "outside_position", "outside_clearance", "outside_selector".
 * "dashboard" is read live by Scout_Hub_Owner_View, never filled into a plugin.
 * "approved_plugins" is read live by Site status (Scout_Hub_Plugins), never filled into a plugin.
 * "brand" is read live by Scout_Hub_Brand (login screen, admin bar, footer, menus, Dashboard message).
 * Having the section at all turns that layer on; a theme without it changes nothing.
 * "photo" values are a path inside the theme (assets/img/joy.webp) or a full https:// address.
 *
 * Facts only (Site Standard §5). The look lives in the theme's DESIGN.md, which
 * the plugins read themselves: the review page from "### Feature: review page"
 * (Scout Reviews 0.8.0+). Build no longer copies the old look keys (reviews:
 * background, text_color, accent, heading_font, body_font, label_font; cards:
 * mode, accent, font). Values already saved on a site stay; look_keys() lists
 * the ones a theme still carries, for the Fill from theme notice.
 *
 * @package Scout_Build
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scout_Hub_Setup {

	const FILE  = 'scout-setup.json';
	const DONE  = 'scout_hub_setup_done';  // section => hash of what was applied.
	const MEDIA = 'scout_hub_setup_media'; // theme path => attachment ID.

	public static function boot(): void {
		add_action( 'admin_init', array( __CLASS__, 'run' ), 30 );
	}

	/** The theme's setup file (child theme first), or an empty array. */
	public static function data(): array {
		foreach ( array_unique( array( get_stylesheet_directory(), get_template_directory() ) ) as $dir ) {
			$path = trailingslashit( $dir ) . self::FILE;
			if ( is_readable( $path ) ) {
				$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
				return is_array( $json ) ? $json : array();
			}
		}
		return array();
	}

	/**
	 * Which features this client needs, from the setup file's "features" section:
	 * "use" in install order, "skip" for ones this client doesn't need. Features
	 * the file doesn't name are neither.
	 *
	 * @return array{use: string[], skip: string[]}
	 */
	public static function feature_plan(): array {
		$f    = (array) ( self::data()['features'] ?? array() );
		$use  = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) ( $f['use'] ?? array() ) ) ) ) );
		$skip = array_values( array_diff( array_unique( array_filter( array_map( 'sanitize_key', (array) ( $f['skip'] ?? array() ) ) ) ), $use ) );
		return array( 'use' => $use, 'skip' => $skip );
	}

	/**
	 * The outside plugins this site has said yes to, from "approved_plugins"
	 * (Site Standard rule 34). Each entry mirrors a dated entry in the site's
	 * decisions file: either the plugin's folder name ("wp-rocket") or an object
	 * { "plugin", "job", "owner", "decided" }. Site status reads it; nothing is filled.
	 *
	 * @param array|null $data The setup file, or null to read the theme's.
	 * @return array<string, array{job:string, owner:string, decided:string}> Folder name => entry.
	 */
	public static function approved_plugins( ?array $data = null ): array {
		$data = null === $data ? self::data() : $data;
		$out  = array();
		foreach ( (array) ( $data['approved_plugins'] ?? array() ) as $entry ) {
			$entry = is_array( $entry ) ? $entry : array( 'plugin' => $entry );
			$slug  = is_scalar( $entry['plugin'] ?? null ) ? sanitize_key( (string) $entry['plugin'] ) : '';
			if ( '' === $slug ) {
				continue;
			}
			$out[ $slug ] = array(
				'job'     => is_scalar( $entry['job'] ?? null ) ? (string) $entry['job'] : '',
				'owner'   => is_scalar( $entry['owner'] ?? null ) ? (string) $entry['owner'] : '',
				'decided' => is_scalar( $entry['decided'] ?? null ) ? (string) $entry['decided'] : '',
			);
		}
		return $out;
	}

	public static function run(): void {
		if ( ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
			return;
		}
		$data = self::data();
		if ( ! $data ) {
			return;
		}
		$done = (array) get_option( self::DONE, array() );
		$jobs = array(
			'business' => array( __CLASS__, 'business' ),
			'team'    => array( __CLASS__, 'team' ),
			'reviews' => array( __CLASS__, 'reviews' ),
			'chat'    => array( __CLASS__, 'chat' ),
			'hours'   => array( __CLASS__, 'hours' ),
			'analytics' => array( __CLASS__, 'analytics' ),
			'cards'   => array( __CLASS__, 'cards' ),
			'contact' => array( __CLASS__, 'contact' ),
			'booking' => array( __CLASS__, 'booking' ),
			'events'  => array( __CLASS__, 'events' ),
		);
		$changed = false;
		$filled  = array();
		foreach ( $jobs as $section => $job ) {
			if ( empty( $data[ $section ] ) || ! is_array( $data[ $section ] ) ) {
				continue;
			}
			$hash = md5( (string) wp_json_encode( $data[ $section ] ) . self::hash_extra( $section, $data[ $section ] ) );
			if ( ( $done[ $section ] ?? '' ) === $hash ) {
				continue;
			}
			// A job returns false while its feature is off, so it runs once the feature is turned on.
			if ( call_user_func( $job, $data[ $section ] ) ) {
				$done[ $section ] = $hash;
				$changed          = true;
				$filled[]         = $section;
			}
		}
		if ( $changed ) {
			update_option( self::DONE, $done, false );
			/**
			 * The setup file filled something in. The Site log listens.
			 *
			 * @param string[] $filled The sections that ran: business, team, reviews, chat, hours, analytics, cards, contact, booking, events.
			 */
			do_action( 'scout_hub_setup_done', $filled );
		}
	}

	/* ---------- sections ---------- */

	/**
	 * Business info (Scout Core). Fills blank fields only, so anything typed on
	 * Business info stays. "same_as" takes a list of profile links.
	 */
	private static function business( array $in ): bool {
		if ( ! class_exists( 'Scout_Core_Business' ) ) {
			return false;
		}
		$keys = array_diff( array_keys( Scout_Core_Business::fields() ), array( 'hours' ) );
		$fill = array();
		foreach ( $keys as $key ) {
			if ( ! isset( $in[ $key ] ) ) {
				continue;
			}
			if ( 'same_as' === $key ) {
				$links = array();
				foreach ( (array) $in[ $key ] as $url ) {
					$url = esc_url_raw( trim( (string) $url ), array( 'https' ) );
					if ( '' !== $url ) {
						$links[] = $url;
					}
				}
				$fill[ $key ] = implode( "\n", $links );
			} elseif ( in_array( $key, array( 'email', 'notice_email' ), true ) ) {
				$fill[ $key ] = sanitize_email( (string) $in[ $key ] );
			} else {
				$fill[ $key ] = sanitize_text_field( (string) $in[ $key ] );
			}
		}
		self::fill_option( Scout_Core_Business::OPTION, $fill );
		return true;
	}

	private static function team( array $people ): bool {
		if ( ! post_type_exists( 'scout_team' ) || ! class_exists( 'Scout_Team' ) ) {
			return false;
		}
		foreach ( $people as $p ) {
			$name = sanitize_text_field( (string) ( $p['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$found = get_posts(
				array(
					'post_type'   => 'scout_team',
					'title'       => $name,
					'post_status' => 'any',
					'numberposts' => 1,
					'fields'      => 'ids',
				)
			);
			$id    = $found ? (int) $found[0] : 0;
			if ( ! $id ) {
				$id = (int) wp_insert_post(
					array(
						'post_type'    => 'scout_team',
						'post_status'  => 'publish',
						'post_title'   => $name,
						'post_content' => (string) ( $p['bio'] ?? '' ),
						'menu_order'   => (int) ( $p['order'] ?? 0 ),
					)
				);
				if ( ! $id ) {
					continue;
				}
			}
			// Fill only the blanks of an existing profile.
			$profile = Scout_Team::profile( $id );
			$email   = (string) ( $p['email'] ?? '' );
			$user    = '' !== $email ? get_user_by( 'email', $email ) : false;
			$add     = array(
				'role'            => (string) ( $p['role'] ?? '' ),
				'bio'             => (string) ( $p['bio'] ?? '' ),
				'email'           => $email,
				'user'            => $user ? $user->ID : 0,
				'military_branch' => (string) ( $p['military_branch'] ?? '' ),
			);
			// Profile link, No team page and licenses (Scout Team 0.5.0). An older Scout Team drops them.
			if ( ! empty( $p['link'] ) ) {
				$add['link_url'] = (string) $p['link'];
			}
			if ( ! empty( $p['link_page'] ) ) {
				$page              = get_page_by_path( trim( (string) $p['link_page'], '/' ) );
				$add['link_page'] = $page ? (int) $page->ID : 0;
			}
			if ( ! empty( $p['no_page'] ) ) {
				$add['no_page'] = 1;
			}
			if ( ! empty( $p['licenses'] ) && is_array( $p['licenses'] ) ) {
				$add['licenses'] = array_values( array_filter( $p['licenses'], 'is_array' ) );
			}
			foreach ( $add as $key => $value ) {
				if ( empty( $profile[ $key ] ) && ! empty( $value ) ) {
					$profile[ $key ] = $value;
				}
			}
			// The sanitizer takes these two lists as one-per-line text.
			foreach ( array( 'memberships', 'same_as' ) as $list ) {
				$profile[ $list ] = implode( "\n", (array) $profile[ $list ] );
			}
			update_post_meta( $id, Scout_Team::META, Scout_Team::sanitize_profile( $profile ) );
			if ( ! has_post_thumbnail( $id ) && ! empty( $p['photo'] ) ) {
				$att = self::attachment( (string) $p['photo'] );
				if ( $att ) {
					set_post_thumbnail( $id, $att );
				}
			}
		}
		return true;
	}

	/**
	 * Extra text in the reviews section's run hash, so the section runs again
	 * when something outside the file changes what it would do:
	 * - "|defaults-blank": once after 0.4.4, when untouched defaults started counting as blank.
	 * - "|totals-waiting": the totals carry a future "checked" date. When that day
	 *   comes the text drops out, the hash changes and the totals apply.
	 * - "|reviews-old": the file has totals, reviews or a place ID but Scout Reviews
	 *   is too old to take them. Once it updates, the section runs again.
	 */
	private static function reviews_hash_extra( array $in ): string {
		$extra = '|defaults-blank';
		$old   = ( ( ! empty( $in['totals'] ) || ! empty( $in['items'] ) ) && ! class_exists( 'Scout_Reviews_Theme' ) )
			|| ( ! empty( $in['google_place_id'] ) && ! class_exists( 'Scout_Reviews_Places' ) );
		if ( $old ) {
			$extra .= '|reviews-old';
		} elseif ( is_array( $in['totals'] ?? null ) && class_exists( 'Scout_Reviews_Theme' )
			&& Scout_Reviews_Theme::is_future( (string) ( $in['totals']['checked'] ?? '' ) ) ) {
			$extra .= '|totals-waiting';
		}
		return $extra;
	}

	private static function reviews( array $in ): bool {
		if ( ! class_exists( 'Scout_Reviews_Requests' ) ) {
			return false;
		}
		$keys = array( 'business', 'headline', 'ask', 'card_headline' ); // Facts only: the look comes from DESIGN.md.
		$fill = array_intersect_key( $in, array_flip( $keys ) );
		if ( ! empty( $in['photo'] ) ) {
			$fill['photo'] = self::url( (string) $in['photo'] );
		}
		// A setting still on the plugin's own default counts as blank, so the client's words win
		// on sites where the review page was saved before the theme carried them.
		self::fill_option( Scout_Reviews_Requests::OPTION, array_map( 'sanitize_text_field', $fill ), Scout_Reviews_Requests::defaults() );

		$write = esc_url_raw( (string) ( $in['google_review_url'] ?? '' ), array( 'https' ) );
		if ( '' !== $write && class_exists( 'Scout_Reviews_Settings' ) ) {
			$saved = get_option( Scout_Reviews_Settings::OPTION, array() );
			$saved = is_array( $saved ) ? $saved : array();
			if ( empty( $saved['profiles']['google']['write_url'] ) ) {
				$saved['profiles']['google']['write_url'] = $write;
				update_option( Scout_Reviews_Settings::OPTION, $saved );
			}
		}

		// Totals and reviews Scout keeps track of (Scout Reviews 0.6.0+). The newest check wins
		// for totals; reviews are only ever added. See Scout_Reviews_Theme.
		if ( class_exists( 'Scout_Reviews_Theme' ) ) {
			if ( ! empty( $in['totals'] ) && is_array( $in['totals'] ) ) {
				Scout_Reviews_Theme::apply_totals( $in['totals'] );
			}
			if ( ! empty( $in['items'] ) && is_array( $in['items'] ) ) {
				Scout_Reviews_Theme::add_items( array_values( $in['items'] ) );
			}
		}
		// The place ID for the daily Google check (Scout Reviews 0.7.0+). Not a secret; the API key never comes from the theme.
		if ( ! empty( $in['google_place_id'] ) && class_exists( 'Scout_Reviews_Places' ) ) {
			Scout_Reviews_Places::fill_place_id( (string) $in['google_place_id'] );
		}
		return true;
	}

	private static function chat( array $in ): bool {
		if ( ! class_exists( 'Scout_Chat' ) ) {
			return false;
		}
		$fill = array();
		foreach ( array( 'name', 'team', 'notify', 'book_url', 'work_url', 'photo_1', 'photo_2' ) as $key ) {
			if ( empty( $in[ $key ] ) ) {
				continue;
			}
			$value = (string) $in[ $key ];
			if ( 'notify' === $key ) {
				$value = sanitize_email( $value );
			} elseif ( 0 === strpos( $key, 'photo_' ) ) {
				$value = self::url( $value );
			} elseif ( '_url' === substr( $key, -4 ) ) {
				$value = esc_url_raw( $value );
			} else {
				$value = sanitize_text_field( $value );
			}
			$fill[ $key ] = $value;
		}
		$fill = array_merge( $fill, self::chat_outside( $in ) );
		// A box still on its default counts as blank, so the file can switch the outside widget on.
		self::fill_option( Scout_Chat::OPTION, $fill, array( 'outside' => '0', 'outside_async' => '0', 'outside_defer' => '0', 'outside_position' => 'right', 'outside_clearance' => '86' ) );
		return true;
	}

	/**
	 * The outside chat widget keys (Scout Chat 0.4.0), checked the way Chat checks
	 * them on save. A script address that fails the check leaves the widget off.
	 */
	private static function chat_outside( array $in ): array {
		if ( ! method_exists( 'Scout_Chat', 'outside_url' ) ) {
			return array();
		}
		$fill = array();
		$src  = Scout_Chat::outside_url( (string) ( $in['outside_src'] ?? '' ) );
		if ( '' !== $src ) {
			$fill['outside_src'] = $src;
			if ( ! empty( $in['outside'] ) ) {
				$fill['outside'] = '1';
			}
		}
		foreach ( array( 'outside_async', 'outside_defer' ) as $key ) {
			if ( ! empty( $in[ $key ] ) ) {
				$fill[ $key ] = '1';
			}
		}
		$attrs = $in['outside_attrs'] ?? '';
		if ( is_array( $attrs ) ) {
			$lines = array();
			foreach ( $attrs as $name => $value ) {
				$lines[] = is_int( $name ) ? (string) $value : $name . '=' . ( is_scalar( $value ) ? (string) $value : '' );
			}
			$attrs = implode( "\n", $lines );
		}
		$clean = array();
		foreach ( Scout_Chat::outside_attrs( (string) $attrs ) as $name => $value ) {
			$clean[] = $name . '=' . $value;
		}
		if ( $clean ) {
			$fill['outside_attrs'] = implode( "\n", $clean );
		}
		$config = $in['outside_config'] ?? '';
		$config = is_array( $config ) ? (string) wp_json_encode( $config ) : trim( (string) $config );
		if ( '' !== $config && strlen( $config ) <= 4000 && is_array( json_decode( $config, true ) ) ) {
			$fill['outside_config'] = $config;
		}
		$var = Scout_Chat::outside_var( (string) ( $in['outside_config_var'] ?? '' ) );
		if ( '' !== $var ) {
			$fill['outside_config_var'] = $var;
		}
		if ( 'left' === ( $in['outside_position'] ?? '' ) ) {
			$fill['outside_position'] = 'left';
		}
		if ( isset( $in['outside_clearance'] ) && is_numeric( $in['outside_clearance'] ) ) {
			$fill['outside_clearance'] = (string) max( 0, min( 400, (int) $in['outside_clearance'] ) );
		}
		$sel = Scout_Chat::outside_selector( (string) ( $in['outside_selector'] ?? '' ) );
		if ( '' !== $sel ) {
			$fill['outside_selector'] = $sel;
		}
		return $fill;
	}

	private static function hours( array $in ): bool {
		if ( ! class_exists( 'Scout_Hours' ) ) {
			return false;
		}
		// Hours are all or nothing: only fill a site that has none saved yet.
		$saved = get_option( Scout_Hours::OPTION, array() );
		if ( is_array( $saved ) && ! empty( $saved['days'] ) ) {
			return true;
		}
		$days = array();
		foreach ( (array) ( $in['days'] ?? array() ) as $day => $ranges ) {
			$day = sanitize_key( (string) $day );
			$out = array();
			foreach ( (array) $ranges as $r ) {
				if ( is_array( $r ) && 2 === count( $r ) ) {
					$out[] = array( sanitize_text_field( (string) $r[0] ), sanitize_text_field( (string) $r[1] ) );
				}
			}
			$days[ $day ] = array(
				'open'   => (bool) $out,
				'ranges' => $out,
			);
		}
		update_option(
			Scout_Hours::OPTION,
			array(
				'confirmed' => ! empty( $in['confirmed'] ),
				'bar'       => 'off',
				'days'      => $days,
			)
		);
		return true;
	}

	private static function analytics( array $in ): bool {
		if ( ! class_exists( 'Scout_Analytics' ) ) {
			return false;
		}
		$gtm = strtoupper( trim( (string) ( $in['gtm'] ?? '' ) ) );
		if ( Scout_Analytics::valid_gtm( $gtm ) ) {
			self::fill_option( Scout_Analytics::OPTION, array( 'gtm' => $gtm ) );
		}
		return true;
	}

	private static function cards( array $in ): bool {
		if ( ! class_exists( 'Scout_Cards_Settings' ) ) {
			return false;
		}
		$keys = array( 'name', 'phone', 'email', 'city', 'region', 'postal', 'footer', 'note', 'team_intro', 'links_intro', 'pick_heading', 'spot_heading', 'spot_text', 'spot_label', 'spot_sub', 'spot_url' );
		$fill = array();
		foreach ( $keys as $key ) {
			if ( ! isset( $in[ $key ] ) || '' === (string) $in[ $key ] ) {
				continue;
			}
			$value = (string) $in[ $key ];
			if ( 'email' === $key ) {
				$value = sanitize_email( $value );
			} elseif ( in_array( $key, array( 'note', 'team_intro', 'links_intro', 'spot_text' ), true ) ) {
				$value = sanitize_textarea_field( $value );
			} else {
				$value = sanitize_text_field( $value );
			}
			$fill[ $key ] = $value;
		}
		// The card's untouched defaults count as blank. So does the site admin's email: on client
		// sites that is often whoever set WordPress up, not the business (one client site, Oct 15).
		$defaults          = Scout_Cards_Settings::defaults();
		$defaults['email'] = (string) get_option( 'admin_email' );
		self::fill_option( Scout_Cards_Settings::OPTION, $fill, $defaults );

		$saved = get_option( Scout_Cards_Settings::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$dirty = false;
		// The photo, logo and share pictures. A picture this file put there earlier (from the
		// theme) still counts as blank, so a new theme version can swap it; one picked by hand stays.
		$ours = array_map( 'intval', array_values( (array) get_option( self::MEDIA, array() ) ) );
		foreach ( array( 'photo' => 'photo_id', 'logo' => 'logo_id', 'share_card' => 'share_card_id', 'share_links' => 'share_links_id' ) as $from => $key ) {
			$current = (int) ( $saved[ $key ] ?? 0 );
			if ( ( ! $current || in_array( $current, $ours, true ) ) && ! empty( $in[ $from ] ) ) {
				$att = self::attachment( (string) $in[ $from ] );
				if ( $att && $att !== $current ) {
					$saved[ $key ] = $att;
					$dirty         = true;
				}
			}
		}
		// People cards, filled only while there are none.
		if ( ! empty( $in['people'] ) && is_array( $in['people'] ) && empty( $saved['people'] ) ) {
			$people = array();
			foreach ( $in['people'] as $p ) {
				$name = sanitize_text_field( (string) ( $p['name'] ?? '' ) );
				if ( '' === $name ) {
					continue;
				}
				$row = array(
					'name'     => $name,
					'slug'     => sanitize_title( (string) ( $p['slug'] ?? $name ) ),
					'title'    => sanitize_text_field( (string) ( $p['title'] ?? '' ) ),
					'email'    => sanitize_email( (string) ( $p['email'] ?? '' ) ),
					'intro'    => sanitize_textarea_field( (string) ( $p['intro'] ?? '' ) ),
					'pick'     => sanitize_textarea_field( (string) ( $p['pick'] ?? '' ) ),
					'tags'     => sanitize_text_field( (string) ( $p['tags'] ?? '' ) ),
					'photo_id' => ! empty( $p['photo'] ) ? self::attachment( (string) $p['photo'] ) : 0,
					'share_id' => ! empty( $p['share'] ) ? self::attachment( (string) $p['share'] ) : 0,
				);
				foreach ( array( 'heading', 'text', 'label', 'sub', 'url' ) as $k ) {
					$row[ 'spot_' . $k ] = sanitize_text_field( (string) ( $p[ 'spot_' . $k ] ?? '' ) );
				}
				$people[] = $row;
			}
			if ( $people ) {
				$saved['people'] = $people;
				$dirty           = true;
			}
		}
		// The links list is filled only while it is empty or still the plugin's two starter rows.
		if ( ! empty( $in['links'] ) && is_array( $in['links'] ) && ( empty( $saved['links'] ) || $saved['links'] == $defaults['links'] ) ) { // phpcs:ignore Universal.Operators.StrictComparisons -- compares row arrays.
			$rows = array();
			foreach ( $in['links'] as $row ) {
				$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
				$url   = (string) ( $row['url'] ?? '' );
				if ( '' === $label || '' === $url ) {
					continue;
				}
				$rows[] = array(
					'label'    => $label,
					'sub'      => sanitize_text_field( (string) ( $row['sub'] ?? '' ) ),
					'url'      => 0 === strpos( $url, '/' ) ? sanitize_text_field( $url ) : esc_url_raw( $url ),
					'external' => 0 === strpos( $url, '/' ) ? 0 : 1,
				);
			}
			if ( $rows ) {
				$saved['links'] = $rows;
				$dirty          = true;
			}
		}
		if ( $dirty ) {
			update_option( Scout_Cards_Settings::OPTION, $saved );
		}
		return true;
	}

	/**
	 * Call bar button sets (Scout Call Buttons 0.3.0). Filled only while the site
	 * has no sets. "pages" takes page IDs or page paths ("bodywork" or
	 * "/bodywork/deep-tissue/"); a path that names no page is left out.
	 */
	private static function contact( array $in ): bool {
		if ( ! class_exists( 'Scout_Contact' ) || ! method_exists( 'Scout_Contact', 'clean_set' ) ) {
			return false;
		}
		if ( empty( $in['sets'] ) || ! is_array( $in['sets'] ) ) {
			return true;
		}
		$saved = get_option( Scout_Contact::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		if ( ! empty( $saved['sets'] ) ) {
			return true; // Sets made in WordPress stay as they are.
		}
		$sets = array();
		foreach ( $in['sets'] as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$raw['pages'] = self::page_ids( (array) ( $raw['pages'] ?? array() ) );
			$set          = Scout_Contact::clean_set( $raw );
			if ( null === $set ) {
				continue;
			}
			if ( '' === $set['name'] ) {
				$set['name'] = sprintf( 'Button set %d', count( $sets ) + 1 );
			}
			$sets[] = $set;
			if ( count( $sets ) >= 20 ) {
				break;
			}
		}
		if ( $sets ) {
			$saved['sets'] = $sets;
			update_option( Scout_Contact::OPTION, $saved );
		}
		return true;
	}

	/**
	 * Scout Booking settings (0.4.0): the decline reasons, where Book buttons go,
	 * the sessions skipped for it, the Service schema switch, and per session
	 * type its schema choice and questions (a "kind" of "agree" is the tick box
	 * to agree, with an optional "link"). Blanks and untouched defaults only:
	 * the reasons while they are the starting list, the link while it is the
	 * booking page, a type's questions while it has none.
	 */
	private static function booking( array $in ): bool {
		if ( ! class_exists( 'Scout_Booking' ) || ! method_exists( 'Scout_Booking', 'clean_reasons' ) ) {
			return false;
		}
		$saved   = get_option( Scout_Booking::OPTION, array() );
		$saved   = is_array( $saved ) ? $saved : array();
		$current = Scout_Booking::settings();
		$types   = Scout_Booking::types();
		$change  = array();

		if ( isset( $in['decline_reasons'] ) ) {
			$reasons = Scout_Booking::clean_reasons( is_array( $in['decline_reasons'] ) ? array_map( 'strval', $in['decline_reasons'] ) : (string) $in['decline_reasons'] );
			if ( $reasons && $current['decline_reasons'] === Scout_Booking::default_reasons() ) {
				$change['decline_reasons'] = $reasons;
			}
		}
		$mode = sanitize_key( (string) ( $in['link_mode'] ?? '' ) );
		if ( in_array( $mode, array( 'url', 'off' ), true ) && 'page' === $current['link_mode'] ) {
			$url = Scout_Booking::clean_link( (string) ( $in['link_url'] ?? '' ) );
			if ( 'off' === $mode || '' !== $url ) {
				$change['link_mode'] = $mode;
			}
		}
		if ( ! empty( $in['link_url'] ) && '' === $current['link_url'] ) {
			$url = Scout_Booking::clean_link( (string) $in['link_url'] );
			if ( '' !== $url ) {
				$change['link_url'] = $url;
			}
		}
		if ( ! empty( $in['link_skip'] ) && ! $current['link_skip'] ) {
			$skip = array();
			foreach ( (array) $in['link_skip'] as $name ) {
				$id = self::booking_type( $types, (string) $name );
				if ( '' !== $id ) {
					$skip[] = $id;
				}
			}
			if ( $skip ) {
				$change['link_skip'] = array_values( array_unique( $skip ) );
			}
		}
		if ( ! empty( $in['schema'] ) && ! $current['schema'] ) {
			$change['schema'] = 1;
		}
		if ( $change ) {
			update_option( Scout_Booking::OPTION, array_merge( $saved, $change ), false );
		}

		// Per session type: its schema choice while still "auto", and its questions while it has none.
		$dirty = false;
		foreach ( (array) ( $in['types'] ?? array() ) as $name => $t ) {
			$id = is_array( $t ) ? self::booking_type( $types, (string) $name ) : '';
			if ( '' === $id ) {
				continue;
			}
			$type = $types[ $id ];
			if ( in_array( $t['schema'] ?? '', array( 'on', 'off' ), true ) && 'auto' === $type['schema'] ) {
				$type['schema'] = $t['schema'];
				$dirty          = true;
			}
			if ( ! empty( $t['questions'] ) && is_array( $t['questions'] ) && ! $type['questions'] ) {
				foreach ( $t['questions'] as $q ) {
					if ( ! is_array( $q ) ) {
						continue;
					}
					$kind                = sanitize_key( (string) ( $q['kind'] ?? 'short' ) );
					$type['questions'][] = array(
						'label'    => (string) ( $q['label'] ?? '' ),
						'required' => ! empty( $q['required'] ),
						'long'     => 'long' === $kind,
						'agree'    => 'agree' === $kind,
						'link'     => (string) ( $q['link'] ?? '' ),
					);
				}
				$dirty = true;
			}
			$types[ $id ] = $type;
		}
		if ( $dirty ) {
			Scout_Booking::save_types( $types );
			do_action( 'scout_booking_types_saved' );
		}
		return true;
	}

	/** A session type's id from its id or its name (any case), or ''. */
	private static function booking_type( array $types, string $name ): string {
		$name = trim( $name );
		$id   = preg_replace( '/[^a-z0-9]/', '', strtolower( $name ) );
		if ( '' !== $id && isset( $types[ $id ] ) ) {
			return $id;
		}
		foreach ( $types as $tid => $t ) {
			if ( '' !== $name && 0 === strcasecmp( (string) $t['name'], $name ) ) {
				return (string) $tid;
			}
		}
		return '';
	}

	/**
	 * Scout Events (0.18.0): the Door helper role, events only for members or
	 * invited guests (by slug), and course records. A course is added once,
	 * by name, and never edited from here after that.
	 */
	private static function events( array $in ): bool {
		if ( ! class_exists( 'Scout_Events' ) ) {
			return false;
		}
		if ( ! empty( $in['door_role'] ) && method_exists( 'Scout_Events_People', 'sync_role' ) ) {
			$saved = get_option( Scout_Events::OPTION, array() );
			$saved = is_array( $saved ) ? $saved : array();
			if ( empty( $saved['door_role'] ) ) {
				$saved['door_role'] = 1;
				update_option( Scout_Events::OPTION, $saved );
				Scout_Events_People::sync_role( true );
			}
		}
		foreach ( self::event_ids( (array) ( $in['members_only'] ?? array() ) ) as $id ) {
			$raw = get_post_meta( $id, '_scout_event', true );
			$raw = is_array( $raw ) ? $raw : array();
			if ( empty( $raw['members_only'] ) && array_key_exists( 'members_only', Scout_Events::empty_event() ) ) {
				$raw['members_only'] = 1;
				update_post_meta( $id, '_scout_event', Scout_Events::sanitize_event( $raw ) );
			}
		}
		if ( ! empty( $in['courses'] ) && is_array( $in['courses'] ) && class_exists( 'Scout_Events_Courses' ) && post_type_exists( Scout_Events_Courses::TYPE ) ) {
			foreach ( $in['courses'] as $c ) {
				$name = is_array( $c ) ? sanitize_text_field( (string) ( $c['name'] ?? '' ) ) : '';
				if ( '' === $name ) {
					continue;
				}
				$found = get_posts(
					array(
						'post_type'   => Scout_Events_Courses::TYPE,
						'title'       => $name,
						'post_status' => 'any',
						'numberposts' => 1,
						'fields'      => 'ids',
					)
				);
				if ( $found ) {
					continue;
				}
				$id = (int) wp_insert_post(
					array(
						'post_type'    => Scout_Events_Courses::TYPE,
						'post_status'  => 'publish',
						'post_title'   => $name,
						'post_content' => wp_kses_post( (string) ( $c['description'] ?? '' ) ),
					)
				);
				if ( ! $id ) {
					continue;
				}
				if ( ! empty( $c['page'] ) ) {
					$c['page'] = self::page_ids( array( $c['page'] ) )[0] ?? 0;
				}
				unset( $c['teachers_team'], $c['teachers_speaker'] ); // Post IDs differ per site; typed names travel.
				Scout_Events_Courses::save( $id, $c );
				if ( ! empty( $c['photo'] ) ) {
					$att = self::attachment( (string) $c['photo'] );
					if ( $att ) {
						set_post_thumbnail( $id, $att );
					}
				}
			}
		}
		return true;
	}

	/** Event post IDs from slugs, published or not. */
	private static function event_ids( array $slugs ): array {
		$ids = array();
		foreach ( $slugs as $slug ) {
			$slug = sanitize_title( (string) $slug );
			if ( '' === $slug ) {
				continue;
			}
			$found = get_posts(
				array(
					'post_type'   => Scout_Events::EVENT,
					'name'        => $slug,
					'post_status' => 'any',
					'numberposts' => 1,
					'fields'      => 'ids',
				)
			);
			if ( $found ) {
				$ids[] = (int) $found[0];
			}
		}
		return $ids;
	}

	/** Page IDs from IDs or page paths ("bodywork", "/bodywork/deep-tissue/"). */
	private static function page_ids( array $pages ): array {
		$ids = array();
		foreach ( $pages as $page ) {
			if ( is_int( $page ) || ctype_digit( (string) $page ) ) {
				$ids[] = (int) $page;
				continue;
			}
			$found = get_page_by_path( trim( (string) $page, '/' ) );
			if ( $found ) {
				$ids[] = (int) $found->ID;
			}
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Extra text in a section's run hash, so a section runs again when something
	 * outside the file changes what it would do: the reviews rules above, and
	 * for booking and events, the session types or events the file names that
	 * exist now (a session type made later fills in once it exists).
	 */
	private static function hash_extra( string $section, array $in ): string {
		if ( 'reviews' === $section ) {
			return self::reviews_hash_extra( $in );
		}
		if ( 'booking' === $section && class_exists( 'Scout_Booking' ) && method_exists( 'Scout_Booking', 'types' ) ) {
			$types = Scout_Booking::types();
			$found = array();
			foreach ( array_merge( array_keys( (array) ( $in['types'] ?? array() ) ), array_map( 'strval', (array) ( $in['link_skip'] ?? array() ) ) ) as $name ) {
				$found[] = self::booking_type( $types, (string) $name );
			}
			return '|types:' . implode( ',', array_filter( $found ) );
		}
		if ( 'events' === $section && class_exists( 'Scout_Events' ) ) {
			return '|events:' . implode( ',', self::event_ids( (array) ( $in['members_only'] ?? array() ) ) );
		}
		return '';
	}

	/* ---------- helpers ---------- */

	/** Look keys Build no longer copies, by section. They belong in DESIGN.md. */
	const LOOK_KEYS = array(
		'reviews' => array( 'background', 'text_color', 'accent', 'heading_font', 'body_font', 'label_font' ),
		'cards'   => array( 'mode', 'accent', 'font' ),
	);

	/**
	 * The look keys a setup file still carries, as "section.key" ("reviews.accent").
	 *
	 * @param array|null $data The setup file, or null to read the theme's.
	 * @return string[]
	 */
	public static function look_keys( ?array $data = null ): array {
		$data = null === $data ? self::data() : $data;
		$out  = array();
		foreach ( self::LOOK_KEYS as $section => $keys ) {
			$in = is_array( $data[ $section ] ?? null ) ? $data[ $section ] : array();
			foreach ( $keys as $key ) {
				if ( isset( $in[ $key ] ) && '' !== (string) $in[ $key ] ) {
					$out[] = $section . '.' . $key;
				}
			}
		}
		return $out;
	}

	/** One plain sentence about the look keys a setup file still carries, or ''. */
	public static function look_notice( ?array $data = null ): string {
		$keys = self::look_keys( $data );
		if ( ! $keys ) {
			return '';
		}
		return sprintf(
			/* translators: %s: setup file keys such as reviews.accent, cards.font. */
			__( 'The setup file still has look settings (%s). Build no longer copies them: the look comes from the theme\'s DESIGN.md. What is already saved stays.', 'scout-build' ),
			implode( ', ', $keys )
		);
	}

	/** Set keys that are missing or blank in an option; leave everything else. */
	/**
	 * Fill blank settings. A setting that still holds the plugin's own default
	 * (from $defaults) also counts as blank, since nobody chose it.
	 */
	private static function fill_option( string $option, array $fill, array $defaults = array() ): void {
		$saved = get_option( $option, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$dirty = false;
		foreach ( $fill as $key => $value ) {
			$current = isset( $saved[ $key ] ) ? (string) $saved[ $key ] : '';
			$default = isset( $defaults[ $key ] ) && is_scalar( $defaults[ $key ] ) ? (string) $defaults[ $key ] : null;
			$blank   = '' === $current || ( null !== $default && 0 === strcasecmp( $current, $default ) );
			if ( '' !== $value && $blank && 0 !== strcasecmp( $current, (string) $value ) ) {
				$saved[ $key ] = $value;
				$dirty         = true;
			}
		}
		if ( $dirty ) {
			update_option( $option, $saved );
		}
	}

	/** A theme path becomes a Media Library item once; a full address is used as is. */
	private static function attachment( string $photo ): int {
		if ( preg_match( '#^https?://#', $photo ) ) {
			$id = attachment_url_to_postid( $photo );
			return $id ? (int) $id : 0;
		}
		$path  = ltrim( str_replace( '..', '', $photo ), '/' );
		$media = (array) get_option( self::MEDIA, array() );
		if ( ! empty( $media[ $path ] ) && get_post( (int) $media[ $path ] ) ) {
			return (int) $media[ $path ];
		}
		$file = get_stylesheet_directory() . '/' . $path;
		if ( ! is_readable( $file ) ) {
			$file = get_template_directory() . '/' . $path;
		}
		if ( ! is_readable( $file ) ) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$up = wp_upload_bits( basename( $file ), null, (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
		if ( ! empty( $up['error'] ) ) {
			return 0;
		}
		$type = wp_check_filetype( $up['file'] );
		$id   = wp_insert_attachment(
			array(
				'post_mime_type' => (string) $type['type'],
				'post_title'     => sanitize_file_name( pathinfo( $file, PATHINFO_FILENAME ) ),
				'post_status'    => 'inherit',
			),
			$up['file']
		);
		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $up['file'] ) );
		$media[ $path ] = (int) $id;
		update_option( self::MEDIA, $media, false );
		return (int) $id;
	}

	private static function url( string $photo ): string {
		if ( preg_match( '#^https?://#', $photo ) ) {
			return esc_url_raw( $photo );
		}
		$id = self::attachment( $photo );
		return $id ? (string) wp_get_attachment_url( $id ) : '';
	}
}
