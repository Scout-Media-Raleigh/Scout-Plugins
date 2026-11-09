<?php
/**
 * Members-only events, when Scout Events is active. An event marked members
 * only stays on the public calendar with a "Members only" tag; members get
 * the ticket button and everyone else a link to become a member. The members'
 * account page lists the members-only events coming up.
 *
 * With "Keep members-only events off the public calendar" on (Settings), a
 * members-only event and its sessions leave every list, search, feed, the
 * REST API and the sitemap for anyone who cannot see members-only content.
 * Its page shows the members note and is kept out of search engines, like a
 * members-only page, and its Event schema does not print. Members, and the
 * people who run the site, still see it everywhere with its tag. A ticket
 * holder opening their ticket link still gets their ticket.
 *
 * @package Scout_Members
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scout_Members_Events {

	public static function boot(): void {
		if ( ! class_exists( 'Scout_Events' ) ) {
			return;
		}
		add_action( 'add_meta_boxes_' . Scout_Events::EVENT, array( __CLASS__, 'box' ) );
		add_action( 'save_post_' . Scout_Events::EVENT, array( __CLASS__, 'save' ) );
		add_filter( 'scout_events_detail_rows', array( __CLASS__, 'rows' ), 10, 2 );
		add_filter( 'scout_events_ticket_html', array( __CLASS__, 'ticket' ), 10, 2 );
		add_filter( 'scout_events_card_tags', array( __CLASS__, 'tags' ), 10, 2 );
		add_action( 'scout_events_imported_event', array( __CLASS__, 'imported' ), 10, 2 );
		add_filter( 'scout_members_account_extra', array( __CLASS__, 'account' ) );
		add_filter( 'scout_events_signup_allowed', array( __CLASS__, 'signup_allowed' ), 10, 2 );
		// Keep members-only events off the public calendar (off unless Settings turns it on).
		add_action( 'pre_get_posts', array( __CLASS__, 'keep_out' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'sitemap' ), 10, 2 );
		add_filter( 'get_previous_post_where', array( __CLASS__, 'adjacent' ), 10, 5 );
		add_filter( 'get_next_post_where', array( __CLASS__, 'adjacent' ), 10, 5 );
		add_filter( 'scout_events_event_schema', array( __CLASS__, 'schema' ), 99, 2 );
		// After Scout Events builds the event page (20) and puts a ticket on top (21).
		add_filter( 'the_content', array( __CLASS__, 'gate_page' ), 99 );
		add_filter( 'get_the_excerpt', array( __CLASS__, 'excerpt' ), 20, 2 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_filter( 'get_post_metadata', array( __CLASS__, 'seo_meta' ), 10, 3 );
		add_action( 'template_redirect', array( __CLASS__, 'no_calendar_file' ), 5 );
	}

	public static function box(): void {
		add_meta_box( 'scout-members-only', __( 'Members only', 'scout-members' ), array( __CLASS__, 'render_box' ), Scout_Events::EVENT, 'side' );
	}

	public static function render_box( $post ): void {
		wp_nonce_field( 'scout_members_event_' . $post->ID, '_sm_event' );
		echo '<p><label><input type="checkbox" name="sm_members_only" value="1" ' . checked( Scout_Members::is_members_only( (int) $post->ID ), true, false ) . '> ' . esc_html__( 'Only members can come', 'scout-members' ) . '</label></p>';
		$note = Scout_Members::settings()['hide_events']
			? __( 'Only members see it on the calendar and in search. Anyone else with the link sees how to join.', 'scout-members' )
			: __( 'It stays on the calendar with a Members only tag, and everyone else sees how to join.', 'scout-members' );
		echo '<p class="description">' . esc_html( $note ) . '</p>';
	}

	public static function save( $post_id ): void {
		if ( ! isset( $_POST['_sm_event'] ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_sm_event'] ) ), 'scout_members_event_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		Scout_Members::set_members_only( (int) $post_id, ! empty( $_POST['sm_members_only'] ) );
	}

	public static function rows( $rows, $id ): array {
		$rows = (array) $rows;
		if ( Scout_Members::is_members_only( (int) $id ) ) {
			$rows[] = array( __( 'Who can come', 'scout-members' ), esc_html__( 'Members only', 'scout-members' ) );
		}
		return $rows;
	}

	public static function ticket( $html, $id ): string {
		if ( ! Scout_Members::is_members_only( (int) $id ) || Scout_Members::can_see() ) {
			return (string) $html;
		}
		$m = is_user_logged_in() ? Scout_Members::member( get_current_user_id() ) : null;
		if ( $m && 'applied' === $m['status'] ) {
			return '<p class="se-cta sm-cta"><span class="se-soon">' . esc_html__( 'Members only. This opens once your membership application is approved.', 'scout-members' ) . '</span></p>';
		}
		if ( $m && 'approved' === $m['status'] ) {
			return '<p class="se-cta sm-cta"><span class="se-soon">' . esc_html__( 'Members only. This opens as soon as your dues are paid.', 'scout-members' ) . '</span> <a class="se-button sm-button wp-element-button" href="' . esc_url( Scout_Members::page_url() ) . '">' . esc_html__( 'See your membership', 'scout-members' ) . '</a></p>';
		}
		$out = '<p class="se-cta sm-cta"><span class="se-soon">' . esc_html__( 'This event is for members.', 'scout-members' ) . '</span> <a class="se-button sm-button wp-element-button" href="' . esc_url( Scout_Members::page_url() ) . '">' . esc_html__( 'Become a member', 'scout-members' ) . '</a>';
		if ( ! is_user_logged_in() ) {
			$out .= ' <a class="sm-signin" href="' . esc_url( wp_login_url( (string) get_permalink( (int) $id ) ) ) . '">' . esc_html__( 'Sign in', 'scout-members' ) . '</a>';
		}
		return $out . '</p>';
	}

	/** Sign-ups on a members-only event are for members (and the people who run the site). */
	public static function signup_allowed( $ok, $id ): bool {
		return (bool) $ok && ( ! Scout_Members::is_members_only( (int) $id ) || Scout_Members::can_see() );
	}

	public static function tags( $tags, $id ): array {
		$tags = (array) $tags;
		if ( Scout_Members::is_members_only( (int) $id ) ) {
			array_unshift( $tags, __( 'Members only', 'scout-members' ) );
		}
		return $tags;
	}

	/** A members_only column in events.csv: yes, members, 1 or true turn it on; no, 0 or false turn it off. */
	public static function imported( $id, $row ): void {
		$v = strtolower( trim( (string) ( ( (array) $row )['members_only'] ?? '' ) ) );
		if ( in_array( $v, array( 'yes', 'y', '1', 'true', 'members' ), true ) ) {
			Scout_Members::set_members_only( (int) $id, true );
		} elseif ( in_array( $v, array( 'no', 'n', '0', 'false' ), true ) ) {
			Scout_Members::set_members_only( (int) $id, false );
		}
	}

	/** The members-only events coming up, on the member's account page. */
	public static function account( $html ): string {
		$ids = array_values( array_filter( Scout_Events::event_ids( 'upcoming' ), static fn( $id ) => Scout_Members::is_members_only( (int) $id ) ) );
		if ( ! $ids || ! class_exists( 'Scout_Events_Front' ) ) {
			return (string) $html;
		}
		// An H2 by default, so the event cards' own H3 titles sit right under it.
		return (string) $html . Scout_Members::heading( __( 'Members-only events coming up', 'scout-members' ), 'sm-sub' ) . Scout_Events_Front::events( array_slice( $ids, 0, 6 ) );
	}

	/* ---------- off the public calendar ---------- */

	/** Set while the hidden list is being read, so its own query is left alone. */
	private static bool $busy = false;

	/** @var array{events:int[],sessions:int[]}|null */
	private static ?array $hidden = null;

	/** The setting is on and this person cannot see members-only content. */
	public static function hiding(): bool {
		return Scout_Members::settings()['hide_events'] && ! Scout_Members::can_see();
	}

	/**
	 * Published members-only events and the sessions that belong to them, read
	 * once per page view.
	 *
	 * @return array{events:int[],sessions:int[]}
	 */
	public static function hidden_ids(): array {
		if ( null !== self::$hidden ) {
			return self::$hidden;
		}
		self::$busy = true;
		$events     = array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => Scout_Events::EVENT,
					'post_status'    => 'any',
					'meta_key'       => Scout_Members::ONLY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a short list, once per page view.
					'fields'         => 'ids',
					'posts_per_page' => 500,
					'no_found_rows'  => true,
				)
			)
		);
		$sessions   = $events ? array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => Scout_Events::SESSION,
					'post_status'    => 'any',
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- a short list, once per page view.
						array(
							'key'     => '_scout_session_event',
							'value'   => $events,
							'compare' => 'IN',
						),
					),
					'fields'         => 'ids',
					'posts_per_page' => 2000,
					'no_found_rows'  => true,
				)
			)
		) : array();
		self::$busy   = false;
		self::$hidden = array(
			'events'   => $events,
			'sessions' => $sessions,
		);
		return self::$hidden;
	}

	/** A members-only event, or a session of one. */
	public static function is_hidden_post( int $id ): bool {
		$type = get_post_type( $id );
		if ( Scout_Events::EVENT === $type ) {
			return Scout_Members::is_members_only( $id );
		}
		if ( Scout_Events::SESSION === $type ) {
			$event = (int) get_post_meta( $id, '_scout_session_event', true );
			return $event > 0 && Scout_Members::is_members_only( $event );
		}
		return false;
	}

	/** This person should not see this event or session: the setting is on and they are not a member. */
	public static function hidden_for_viewer( int $id ): bool {
		return $id > 0 && self::hiding() && self::is_hidden_post( $id );
	}

	/** Opening their own ticket link (Scout Events 0.17.0 or later) still shows the ticket. */
	private static function ticket_holder( int $id ): bool {
		return Scout_Events::EVENT === get_post_type( $id ) && method_exists( 'Scout_Events', 'ticket_holder' ) && Scout_Events::ticket_holder( $id );
	}

	/**
	 * Every list on the site: the calendar, event cards, upcoming and related
	 * blocks, directories, search, feeds and the REST API. The event's own page
	 * still opens (it shows the members note). The dashboard, scheduled emails
	 * and WP-CLI are left alone, so reminders still go out.
	 *
	 * @param WP_Query $q The query about to run.
	 */
	public static function keep_out( $q ): void {
		if ( self::$busy || ! ( $q instanceof WP_Query ) ) {
			return;
		}
		if ( ( is_admin() && ! wp_doing_ajax() ) || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		if ( $q->is_main_query() && $q->is_singular() ) {
			return;
		}
		$types   = array_filter( (array) $q->get( 'post_type' ) );
		$any     = in_array( 'any', $types, true ) || ( ! $types && $q->is_search() );
		$event   = $any || in_array( Scout_Events::EVENT, $types, true );
		$session = $any || in_array( Scout_Events::SESSION, $types, true );
		if ( ( ! $event && ! $session ) || ! self::hiding() ) {
			return;
		}
		$h   = self::hidden_ids();
		$out = array_merge( $event ? $h['events'] : array(), $session ? $h['sessions'] : array() );
		if ( ! $out ) {
			return;
		}
		$in = array_map( 'intval', (array) $q->get( 'post__in' ) );
		if ( $in ) {
			// WordPress ignores post__not_in when post__in is set, so take them out of the list itself.
			$left = array_values( array_diff( $in, $out ) );
			$q->set( 'post__in', $left ? $left : array( 0 ) );
			return;
		}
		$q->set( 'post__not_in', array_values( array_unique( array_merge( array_map( 'intval', (array) $q->get( 'post__not_in' ) ), $out ) ) ) );
	}

	/**
	 * Previous and next links on event and session pages (the theme's post
	 * navigation, and the rel links in the head) read the database directly,
	 * so they skip hidden ones here.
	 */
	public static function adjacent( $where, $in_same_term = false, $excluded_terms = '', $taxonomy = 'category', $post = null ): string {
		$post = $post instanceof WP_Post ? $post : get_post();
		if ( ! $post || ! in_array( $post->post_type, array( Scout_Events::EVENT, Scout_Events::SESSION ), true ) || ! self::hiding() ) {
			return (string) $where;
		}
		$h   = self::hidden_ids();
		$out = Scout_Events::EVENT === $post->post_type ? $h['events'] : $h['sessions'];
		return $out ? $where . ' AND p.ID NOT IN (' . implode( ',', array_map( 'intval', $out ) ) . ')' : (string) $where;
	}

	/** The sitemap is for search engines, so members-only events stay out of it for everyone. */
	public static function sitemap( array $args, string $type ): array {
		if ( ! Scout_Members::settings()['hide_events'] || ! in_array( $type, array( Scout_Events::EVENT, Scout_Events::SESSION ), true ) ) {
			return $args;
		}
		$h   = self::hidden_ids();
		$out = Scout_Events::EVENT === $type ? $h['events'] : $h['sessions'];
		if ( $out ) {
			$args['post__not_in'] = array_values( array_unique( array_merge( (array) ( $args['post__not_in'] ?? array() ), $out ) ) );
		}
		return $args;
	}

	/** No Event schema for people who cannot see the event. */
	public static function schema( $node, $post_id ): array {
		return self::hidden_for_viewer( (int) $post_id ) ? array() : (array) $node;
	}

	/** The event or session page shows the members note in place of everything Scout Events puts there. */
	public static function gate_page( $content ) {
		$id = (int) get_the_ID();
		if ( ! is_singular( array( Scout_Events::EVENT, Scout_Events::SESSION ) ) || ! in_the_loop() || ! is_main_query() || $id !== (int) get_queried_object_id() ) {
			return $content;
		}
		if ( ! self::hidden_for_viewer( $id ) || self::ticket_holder( $id ) ) {
			return $content;
		}
		return Scout_Members_Front::gate( Scout_Events::EVENT === get_post_type( $id ) ? __( 'This event is for members.', 'scout-members' ) : __( 'This is part of an event for members.', 'scout-members' ) );
	}

	public static function excerpt( $excerpt, $post = null ) {
		$id = $post instanceof WP_Post ? (int) $post->ID : (int) get_the_ID();
		return self::hidden_for_viewer( $id ) ? '' : $excerpt;
	}

	/** Search engines skip the event's page, like a members-only page. */
	public static function robots( array $robots ): array {
		if ( Scout_Members::settings()['hide_events'] && is_singular( array( Scout_Events::EVENT, Scout_Events::SESSION ) ) && self::is_hidden_post( (int) get_queried_object_id() ) ) {
			$robots['noindex'] = true;
		}
		return $robots;
	}

	/**
	 * Scout's SEO and AI-search plugins read "hide from search" and the
	 * description directly. A hidden event gets "hide" always, and "A page for
	 * members." as its description for anyone who cannot see it.
	 */
	public static function seo_meta( $value, $object_id, $meta_key ) {
		$noindex = defined( 'SCOUT_SEO_META_NOINDEX' ) ? SCOUT_SEO_META_NOINDEX : '';
		$desc    = defined( 'SCOUT_SEO_META_DESC' ) ? SCOUT_SEO_META_DESC : '';
		if ( null !== $value || '' === (string) $meta_key || ! in_array( $meta_key, array( $noindex, $desc ), true ) || is_admin() ) {
			return $value;
		}
		$id = (int) $object_id;
		if ( ! Scout_Members::settings()['hide_events'] || ! self::is_hidden_post( $id ) ) {
			return $value;
		}
		if ( $meta_key === $noindex ) {
			return array( '1' );
		}
		return Scout_Members::can_see() ? $value : array( __( 'A page for members.', 'scout-members' ) );
	}

	/** The event's calendar file is for members too: anyone else goes to the event page and its members note. */
	public static function no_calendar_file(): void {
		if ( ! isset( $_GET['se_ics'] ) || ! is_singular( Scout_Events::EVENT ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public file.
			return;
		}
		$id = (int) get_queried_object_id();
		if ( self::hidden_for_viewer( $id ) && ! self::ticket_holder( $id ) ) {
			wp_safe_redirect( (string) get_permalink( $id ) );
			exit;
		}
	}
}
