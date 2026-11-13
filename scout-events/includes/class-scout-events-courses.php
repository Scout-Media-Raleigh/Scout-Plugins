<?php
/**
 * Courses: a training program that runs over several dates (a multi-weekend
 * course, a certification, a series of classes), with price tiers and a
 * registration link that may go to an outside booking site.
 *
 *   [scout_course id="12"]                 the course's dates, prices and register button
 *   [scout_course course="level-one"]      the same, by the course's address word
 *   [scout_course id="12" show="dates"]    dates and the button only
 *   [scout_course id="12" title="yes"]     with the course name as an H2 (pages with several courses)
 *
 * A course is its own record next to events, not a kind of event. An event is
 * one dated gathering with sign-ups, tickets, a calendar file and a place on
 * the events calendar. A course is a program that runs one or more times, each
 * run a date range, and sells places by price tier. Keeping it apart means a
 * course never lands on the events calendar or in the ticket flows by
 * accident, and each run can still point at existing events as its sessions
 * (a weekend that is also listed as an event).
 *
 * Courses have no pages of their own. The owner picks the page that shows the
 * course ("Shown on"), and the shortcode can sit on any page. The Course node
 * (with one CourseInstance per run) joins Scout Core's graph on every page
 * that shows the course, the same way the Event node does.
 *
 * @package Scout_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scout_Events_Courses {

	const TYPE  = 'scout_course';
	const META  = '_scout_course';
	const PAGE  = '_scout_course_page';
	const NONCE = '_se_course_box';

	/** Blank rows offered under the saved ones, so no script is needed to add a row. */
	const BLANK_ROWS = 2;

	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'register' ), 5 );
		add_shortcode( 'scout_course', array( __CLASS__, 'shortcode' ) );
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'classic' ), 10, 2 );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
		add_action( 'edit_form_after_title', array( __CLASS__, 'after_title' ) );
		add_action( 'save_post_' . self::TYPE, array( __CLASS__, 'save_box' ), 10, 2 );
		add_filter( 'parent_file', array( __CLASS__, 'highlight' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'front_assets' ), 11 );
		add_filter( 'manage_' . self::TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}

	/** Courses show on ordinary pages, so they have no address of their own. */
	public static function register(): void {
		register_post_type(
			self::TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Courses', 'scout-events' ),
					'singular_name'      => __( 'Course', 'scout-events' ),
					'add_new'            => __( 'Add course', 'scout-events' ),
					'add_new_item'       => __( 'Add course', 'scout-events' ),
					'edit_item'          => __( 'Edit course', 'scout-events' ),
					'search_items'       => __( 'Search courses', 'scout-events' ),
					'all_items'          => __( 'Courses', 'scout-events' ),
					'not_found'          => __( 'No courses yet.', 'scout-events' ),
					'not_found_in_trash' => __( 'No courses in the trash.', 'scout-events' ),
					'featured_image'     => __( 'Course photo', 'scout-events' ),
					'set_featured_image' => __( 'Set course photo', 'scout-events' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			)
		);
	}

	/* ---------- choices ---------- */

	/** How people take part, as schema.org courseMode values. */
	public static function modes(): array {
		return array(
			'onsite'  => __( 'In person', 'scout-events' ),
			'online'  => __( 'Online', 'scout-events' ),
			'blended' => __( 'In person and online', 'scout-events' ),
		);
	}

	const MODE_SCHEMA = array(
		'onsite'  => 'Onsite',
		'online'  => 'Online',
		'blended' => 'Blended',
	);

	public static function availabilities(): array {
		return array(
			'onsale'  => __( 'On sale', 'scout-events' ),
			'notyet'  => __( 'Not on sale yet', 'scout-events' ),
			'soldout' => __( 'Sold out', 'scout-events' ),
		);
	}

	/* ---------- the data ---------- */

	public static function empty_course(): array {
		return array(
			'level'            => '',
			'prereq'           => '',
			'mode'             => 'onsite',
			'hours'            => '',
			'venue'            => '',
			'street'           => '',
			'city'             => '',
			'region'           => '',
			'postal'           => '',
			'country'          => 'US',
			'address_private'  => 0,
			'instances'        => array(),
			'tiers'            => array(),
			'register_url'     => '',
			'register_label'   => '',
			'teachers_team'    => array(),
			'teachers_speaker' => array(),
			'teacher_names'    => array(),
			'page'             => 0,
			'schema'           => 1,
		);
	}

	public static function course( int $post_id ): array {
		$raw = get_post_meta( $post_id, self::META, true );
		return self::sanitize( is_array( $raw ) ? $raw : array() );
	}

	public static function sanitize( array $in ): array {
		$c   = self::empty_course();
		$ids = static fn( $list ) => array_values( array_unique( array_filter( array_map( 'absint', (array) $list ) ) ) );

		foreach ( array( 'level', 'venue', 'street', 'city', 'region', 'postal', 'register_label' ) as $k ) {
			$c[ $k ] = sanitize_text_field( (string) ( $in[ $k ] ?? '' ) );
		}
		$c['prereq']  = sanitize_textarea_field( (string) ( $in['prereq'] ?? '' ) );
		$country      = strtoupper( sanitize_text_field( (string) ( $in['country'] ?? 'US' ) ) );
		$c['country'] = preg_match( '/^[A-Z]{2}$/', $country ) ? $country : 'US';
		$mode         = (string) ( $in['mode'] ?? '' );
		$c['mode']    = isset( self::modes()[ $mode ] ) ? $mode : 'onsite';
		$hours        = preg_replace( '/[^0-9.]/', '', (string) ( $in['hours'] ?? '' ) );
		$c['hours']   = ( '' !== $hours && is_numeric( $hours ) && (float) $hours > 0 ) ? rtrim( rtrim( number_format( min( 9999, (float) $hours ), 1, '.', '' ), '0' ), '.' ) : '';
		$c['address_private'] = empty( $in['address_private'] ) ? 0 : 1;
		$c['register_url']    = esc_url_raw( trim( (string) ( $in['register_url'] ?? '' ) ), array( 'http', 'https' ) );
		$c['teachers_team']    = $ids( $in['teachers_team'] ?? array() );
		$c['teachers_speaker'] = $ids( $in['teachers_speaker'] ?? array() );
		$names = $in['teacher_names'] ?? array();
		if ( is_string( $names ) ) {
			$names = explode( "\n", $names );
		}
		$c['teacher_names'] = array_values( array_unique( array_filter( array_map( static fn( $n ) => sanitize_text_field( (string) $n ), (array) $names ) ) ) );
		$c['page']          = absint( $in['page'] ?? 0 );
		$c['schema']        = array_key_exists( 'schema', $in ) && empty( $in['schema'] ) ? 0 : 1;

		foreach ( (array) ( $in['instances'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$r = array(
				'label'      => sanitize_text_field( (string) ( $row['label'] ?? '' ) ),
				'start_date' => Scout_Events::clean_date( (string) ( $row['start_date'] ?? '' ) ),
				'start_time' => Scout_Events::clean_time( (string) ( $row['start_time'] ?? '' ) ),
				'end_date'   => Scout_Events::clean_date( (string) ( $row['end_date'] ?? '' ) ),
				'end_time'   => Scout_Events::clean_time( (string) ( $row['end_time'] ?? '' ) ),
				'events'     => $ids( $row['events'] ?? array() ),
			);
			if ( '' !== $r['end_date'] && '' !== $r['start_date'] && $r['end_date'] < $r['start_date'] ) {
				$r['end_date'] = '';
			}
			// A row needs a start date or at least one event; an empty row is dropped.
			if ( '' === $r['start_date'] && ! $r['events'] ) {
				continue;
			}
			$c['instances'][] = $r;
		}

		foreach ( (array) ( $in['tiers'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$avail = (string) ( $row['availability'] ?? '' );
			$r     = array(
				'name'         => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
				'price'        => Scout_Events::clean_price( (string) ( $row['price'] ?? '' ) ),
				'until'        => Scout_Events::clean_date( (string) ( $row['until'] ?? '' ) ),
				'availability' => isset( self::availabilities()[ $avail ] ) ? $avail : 'onsale',
			);
			if ( '' === $r['price'] && '' === $r['name'] ) {
				continue;
			}
			$c['tiers'][] = $r;
		}
		return $c;
	}

	public static function save( int $post_id, array $c ): void {
		$c = self::sanitize( $c );
		update_post_meta( $post_id, self::META, $c );
		update_post_meta( $post_id, self::PAGE, $c['page'] );
	}

	/**
	 * A run of the course with its dates worked out: its own dates, or the
	 * first start and last end of the events linked to it.
	 *
	 * @return array{label:string,start:?DateTimeImmutable,end:?DateTimeImmutable,timed:bool,events:int[],range:array}
	 */
	public static function instance( array $row ): array {
		$events = array_values( array_filter( $row['events'], static fn( $id ) => Scout_Events::EVENT === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) );
		$range  = array(
			'start_date' => $row['start_date'],
			'start_time' => $row['start_time'],
			'end_date'   => $row['end_date'],
			'end_time'   => $row['end_time'],
		);
		if ( '' === $row['start_date'] && $events ) {
			$first = null;
			$last  = null;
			foreach ( $events as $id ) {
				$e = Scout_Events::event( $id );
				$s = Scout_Events::start( $e );
				$f = Scout_Events::end( $e );
				if ( $s && ( ! $first || $s < $first[0] ) ) {
					$first = array( $s, $e );
				}
				if ( $f && ( ! $last || $f > $last[0] ) ) {
					$last = array( $f, $e );
				}
			}
			if ( $first ) {
				$range['start_date'] = $first[1]['start_date'];
				$range['start_time'] = $first[1]['start_time'];
			}
			if ( $last ) {
				$range['end_date'] = '' !== $last[1]['end_date'] ? $last[1]['end_date'] : $last[1]['start_date'];
				$range['end_time'] = $last[1]['end_time'];
				if ( $range['end_date'] === $range['start_date'] ) {
					$range['end_date'] = '';
				}
			}
		}
		$full = $range + array(
			'status'       => 'scheduled',
			'availability' => 'none',
		);
		return array(
			'label'  => $row['label'],
			'start'  => Scout_Events::start( $full ),
			'end'    => '' !== $range['start_date'] ? Scout_Events::end( $full ) : null,
			'timed'  => '' !== $range['start_time'],
			'events' => $events,
			'range'  => $range,
		);
	}

	/** The runs that have not finished yet, soonest first. */
	public static function upcoming_instances( array $c ): array {
		$now = Scout_Events::now();
		$out = array();
		foreach ( $c['instances'] as $row ) {
			$i = self::instance( $row );
			if ( ! $i['start'] || ( $i['end'] && $i['end'] < $now ) ) {
				continue;
			}
			$out[] = $i;
		}
		usort( $out, static fn( $a, $b ) => $a['start'] <=> $b['start'] );
		return $out;
	}

	/** Price tiers still open: no end date, or an end date today or later. */
	public static function current_tiers( array $c ): array {
		$today = Scout_Events::now()->format( 'Y-m-d' );
		return array_values( array_filter( $c['tiers'], static fn( $t ) => '' === $t['until'] || $t['until'] >= $today ) );
	}

	/** True when the street, city and state or ZIP code are all there and the address is public. */
	public static function public_address( array $c ): bool {
		return ! $c['address_private'] && Scout_Events::full_address( $c );
	}

	/** The published courses shown on a page: picked under "Shown on", or placed with the shortcode. */
	public static function on_page( int $page_id ): array {
		if ( ! $page_id ) {
			return array();
		}
		$ids = array_map(
			'intval',
			get_posts(
				array(
					'post_type'        => self::TYPE,
					'post_status'      => 'publish',
					'posts_per_page'   => 50,
					'fields'           => 'ids',
					'meta_key'         => self::PAGE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- small table.
					'meta_value'       => $page_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- small table.
					'orderby'          => array(
						'menu_order' => 'ASC',
						'title'      => 'ASC',
					),
					'no_found_rows'    => true,
					'suppress_filters' => false,
				)
			)
		);
		$content = (string) get_post_field( 'post_content', $page_id );
		if ( false !== strpos( $content, '[scout_course' ) && preg_match_all( '/' . get_shortcode_regex( array( 'scout_course' ) ) . '/', $content, $m ) ) {
			foreach ( $m[3] as $attr_text ) {
				$id = self::find( (array) shortcode_parse_atts( $attr_text ), $page_id );
				if ( $id ) {
					$ids[] = $id;
				}
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/** The course a shortcode means: id="", course="" (its address word), or the first course shown on this page. */
	private static function find( array $atts, int $page_id = 0 ): int {
		$want = (string) ( $atts['id'] ?? ( $atts['course'] ?? '' ) );
		$id   = absint( $want );
		if ( ! $id && '' !== $want ) {
			$found = get_page_by_path( sanitize_title( $want ), OBJECT, self::TYPE );
			$id    = $found ? (int) $found->ID : 0;
		}
		if ( ! $id && '' === $want && $page_id ) {
			$picked = get_posts(
				array(
					'post_type'      => self::TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => self::PAGE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- small table.
					'meta_value'     => $page_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- small table.
					'no_found_rows'  => true,
				)
			);
			$id = $picked ? (int) $picked[0] : 0;
		}
		return ( $id && self::TYPE === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) ? $id : 0;
	}

	/** The anchor the shortcode puts on the course, so a page with several courses links to each. */
	public static function anchor( int $id ): string {
		$slug = sanitize_title( (string) get_post_field( 'post_name', $id ) );
		return 'course-' . ( '' !== $slug ? $slug : (string) $id );
	}

	/* ---------- on the site ---------- */

	public static function front_assets(): void {
		$post = get_post();
		if ( $post && has_shortcode( (string) $post->post_content, 'scout_course' ) ) {
			wp_enqueue_style( 'scout-events' );
		}
	}

	public static function shortcode( $atts ): string {
		$atts = (array) $atts;
		$id   = self::find( $atts, (int) get_queried_object_id() );
		if ( ! $id ) {
			return '';
		}
		if ( ! wp_style_is( 'scout-events', 'registered' ) ) {
			wp_register_style( 'scout-events', SCOUT_EVENTS_URL . 'assets/scout-events.css', array(), SCOUT_EVENTS_VERSION );
		}
		wp_enqueue_style( 'scout-events' );
		$only_dates = 'dates' === ( $atts['show'] ?? '' );
		$title      = in_array( strtolower( (string) ( $atts['title'] ?? '' ) ), array( 'yes', '1', 'true' ), true );
		return self::panel( $id, $only_dates, $title );
	}

	/** The course panel: dates, place, level, prices, teachers and the register button. */
	public static function panel( int $id, bool $only_dates = false, bool $title = false ): string {
		$c      = self::course( $id );
		$runs   = self::upcoming_instances( $c );
		$tiers  = self::current_tiers( $c );
		$anchor = self::anchor( $id );
		$out    = '<div class="se-details se-course" id="' . esc_attr( $anchor ) . '">';
		if ( $title ) {
			$out .= '<h2 class="se-course-title">' . esc_html( get_the_title( $id ) ) . '</h2>';
		}

		$rows = array();
		if ( $runs ) {
			$when = '<ul class="se-course-runs">';
			foreach ( $runs as $run ) {
				$e     = $run['range'] + array( 'status' => 'scheduled' );
				$dates = Scout_Events::dates_label( $e );
				$times = Scout_Events::times_label( $e );
				$when .= '<li>' . ( '' !== $run['label'] ? '<span class="se-course-run-name">' . esc_html( $run['label'] ) . '</span><br>' : '' ) . esc_html( $dates ) . ( '' !== $times ? '<br><span class="se-meta">' . esc_html( $times ) . '</span>' : '' );
				if ( $run['events'] ) {
					$when .= '<ul class="se-course-sessions">';
					foreach ( $run['events'] as $eid ) {
						$ev    = Scout_Events::event( $eid );
						$when .= '<li><a href="' . esc_url( get_permalink( $eid ) ) . '">' . esc_html( get_the_title( $eid ) ) . '</a> <span class="se-meta">' . esc_html( Scout_Events::dates_label( $ev ) ) . '</span></li>';
					}
					$when .= '</ul>';
				}
				$when .= '</li>';
			}
			$rows[] = array( __( 'Dates', 'scout-events' ), $when . '</ul>' );
		} else {
			$rows[] = array( __( 'Dates', 'scout-events' ), esc_html__( 'New dates are coming soon.', 'scout-events' ) );
		}

		if ( ! $only_dates ) {
			$where = self::where_html( $c );
			if ( '' !== $where ) {
				$rows[] = array( __( 'Where', 'scout-events' ), $where );
			}
			if ( '' !== $c['level'] ) {
				$rows[] = array( __( 'Level', 'scout-events' ), esc_html( $c['level'] ) );
			}
			if ( '' !== $c['prereq'] ) {
				$rows[] = array( __( 'Before you join', 'scout-events' ), nl2br( esc_html( $c['prereq'] ) ) );
			}
			if ( $tiers ) {
				$list = '<ul class="se-course-tiers">';
				foreach ( $tiers as $t ) {
					$price = '' === $t['price'] ? '' : ( 0.0 === (float) $t['price'] ? __( 'Free', 'scout-events' ) : Scout_Events::money( $t['price'] ) );
					$bits  = array_filter( array( esc_html( $t['name'] ), '' !== $price ? '<strong>' . esc_html( $price ) . '</strong>' : '' ) );
					$note  = '';
					if ( 'soldout' === $t['availability'] ) {
						$note = __( 'Sold out', 'scout-events' );
					} elseif ( 'notyet' === $t['availability'] ) {
						$note = __( 'Not on sale yet', 'scout-events' );
					} elseif ( '' !== $t['until'] ) {
						/* translators: %s: last day this price is offered. */
						$note = sprintf( __( 'Until %s', 'scout-events' ), Scout_Events::day_label( $t['until'] ) );
					}
					$list .= '<li>' . implode( ' ', $bits ) . ( '' !== $note ? ' <span class="se-meta">' . esc_html( $note ) . '</span>' : '' ) . '</li>';
				}
				$rows[] = array( __( 'Price', 'scout-events' ), $list . '</ul>' );
			}
			$teachers = self::teacher_names_html( $c );
			if ( '' !== $teachers ) {
				$rows[] = array( __( 'Taught by', 'scout-events' ), $teachers );
			}
		}

		$out .= self::facts( (array) apply_filters( 'scout_events_course_rows', $rows, $id, $c ) );
		$out .= (string) apply_filters( 'scout_events_course_button', self::button( $id, $c, $runs, $tiers ), $id, $c );
		return $out . '</div>';
	}

	/** The register button, or a short line in its place. */
	private static function button( int $id, array $c, array $runs, array $tiers ): string {
		if ( ! $runs || '' === $c['register_url'] ) {
			return '';
		}
		$states = array_unique( wp_list_pluck( $tiers, 'availability' ) );
		if ( $tiers && ! in_array( 'onsale', $states, true ) ) {
			$text = in_array( 'notyet', $states, true ) ? __( 'Registration is not open yet.', 'scout-events' ) : __( 'Sold out', 'scout-events' );
			return '<p class="se-cta"><span class="se-soon">' . esc_html( $text ) . '</span></p>';
		}
		$label = '' !== $c['register_label'] ? $c['register_label'] : __( 'Register', 'scout-events' );
		return '<p class="se-cta"><a class="se-button wp-element-button" href="' . esc_url( $c['register_url'] ) . '" data-scout-track="course_register_click" data-scout-label="' . esc_attr( get_the_title( $id ) ) . '">' . esc_html( $label ) . '</a></p>';
	}

	private static function where_html( array $c ): string {
		$where = array();
		if ( 'online' !== $c['mode'] ) {
			if ( '' !== $c['venue'] ) {
				$where[] = esc_html( $c['venue'] );
			}
			if ( $c['address_private'] ) {
				// A private address never prints: the venue and city, and the line the events use.
				$city = Scout_Events::city_label( $c );
				if ( '' !== $city ) {
					$where[] = esc_html( $city );
				}
				if ( $where ) {
					$where[] = '<span class="se-address-note">' . esc_html( Scout_Events::settings()['address_note'] ) . '</span>';
				}
			} else {
				$addr = Scout_Events::address_label( $c );
				if ( '' !== $addr ) {
					$map     = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( trim( $c['venue'] . ' ' . $addr ) );
					$where[] = esc_html( $addr ) . ' <a class="se-map" href="' . esc_url( $map ) . '" rel="noopener" data-scout-track="directions_click">' . esc_html__( 'Directions', 'scout-events' ) . '</a>';
				}
			}
		}
		if ( 'onsite' !== $c['mode'] ) {
			$where[] = esc_html__( 'Online', 'scout-events' );
		}
		return implode( '<br>', $where );
	}

	private static function teacher_names_html( array $c ): string {
		$names = array();
		foreach ( self::teachers( $c ) as $t ) {
			$names[] = '' !== ( $t['url'] ?? '' ) ? '<a href="' . esc_url( $t['url'] ) . '">' . esc_html( $t['name'] ) . '</a>' : esc_html( $t['name'] );
		}
		return implode( ', ', $names );
	}

	/** A small definition list, the same markup as the event details. Values arrive already escaped. */
	private static function facts( array $rows ): string {
		if ( ! $rows ) {
			return '';
		}
		$out = '<dl class="se-facts">';
		foreach ( $rows as $r ) {
			$out .= '<div><dt>' . esc_html( $r[0] ) . '</dt><dd>' . $r[1] . '</dd></div>';
		}
		return $out . '</dl>';
	}

	/**
	 * The teachers in order: team members, then speakers, then typed names.
	 * Each is the Person node's fields, with the page address when there is one.
	 *
	 * @return array<int,array>
	 */
	public static function teachers( array $c ): array {
		$out = array();
		foreach ( $c['teachers_team'] as $pid ) {
			if ( ! class_exists( 'Scout_Team' ) || Scout_Team::POST_TYPE !== get_post_type( $pid ) || 'publish' !== get_post_status( $pid ) ) {
				continue;
			}
			$node = class_exists( 'Scout_Team_Schema' ) ? Scout_Team_Schema::person( $pid ) : array();
			$node = array_intersect_key( $node, array_flip( array( '@type', '@id', 'name', 'url', 'jobTitle', 'image', 'hasCredential', 'sameAs', 'worksFor' ) ) );
			$out[] = $node + array(
				'@type' => 'Person',
				'name'  => self::text( get_the_title( $pid ) ),
			);
		}
		foreach ( $c['teachers_speaker'] as $pid ) {
			if ( Scout_Events::SPEAKER !== get_post_type( $pid ) || 'publish' !== get_post_status( $pid ) ) {
				continue;
			}
			$node  = Scout_Events_Schema::person( $pid );
			$out[] = array_intersect_key( $node, array_flip( array( '@type', '@id', 'name', 'url', 'jobTitle', 'image', 'sameAs' ) ) );
		}
		foreach ( $c['teacher_names'] as $name ) {
			$out[] = array(
				'@type' => 'Person',
				'name'  => $name,
			);
		}
		return $out;
	}

	private static function text( string $s ): string {
		return html_entity_decode( wp_strip_all_tags( $s ), ENT_QUOTES, 'UTF-8' );
	}

	/* ---------- schema ---------- */

	/**
	 * The Course node with one CourseInstance per upcoming run, following
	 * Google's course guidelines: name, description and provider on the
	 * Course; courseMode, dates, instructor and (only for a public venue with
	 * a full street address) location on each run; offers per price tier.
	 *
	 * @param int  $id      Course post ID.
	 * @param int  $page_id The page showing it.
	 * @param bool $several More than one course on that page, so each url carries its anchor.
	 */
	public static function schema( int $id, int $page_id, bool $several = false ): array {
		$c = self::course( $id );
		if ( ! $c['schema'] || 'publish' !== get_post_status( $id ) ) {
			return array();
		}
		$page = (string) get_permalink( $page_id );
		$url  = $several ? $page . '#' . self::anchor( $id ) : $page;
		$core = class_exists( 'Scout_Schema' ) && function_exists( 'scout_core_business' );
		$node = array(
			'@type'    => 'Course',
			'@id'      => $page . '#' . self::anchor( $id ),
			'name'     => self::text( get_the_title( $id ) ),
			'url'      => $url,
			'provider' => $core
				? array( '@id' => home_url( '/#business' ) )
				: array(
					'@type' => 'Organization',
					'name'  => self::text( get_bloginfo( 'name' ) ),
					'url'   => home_url( '/' ),
				),
		);
		$desc = has_excerpt( $id ) ? get_the_excerpt( $id ) : wp_trim_words( (string) get_post_field( 'post_content', $id ), 50, '' );
		$desc = trim( self::text( (string) $desc ) );
		if ( '' !== $desc ) {
			$node['description'] = $desc;
		}
		if ( has_post_thumbnail( $id ) ) {
			$image = wp_get_attachment_image_url( (int) get_post_thumbnail_id( $id ), 'full' );
			if ( $image ) {
				$node['image'] = $image;
			}
		}
		if ( '' !== $c['level'] ) {
			$node['educationalLevel'] = $c['level'];
		}
		if ( '' !== $c['prereq'] ) {
			$node['coursePrerequisites'] = $c['prereq'];
		}

		$offers = array();
		foreach ( self::current_tiers( $c ) as $t ) {
			if ( '' === $t['price'] ) {
				continue;
			}
			$o = array(
				'@type'         => 'Offer',
				'category'      => 0.0 === (float) $t['price'] ? 'Free' : 'Paid',
				'price'         => (float) $t['price'],
				'priceCurrency' => Scout_Events::settings()['currency'],
				'availability'  => Scout_Events_Schema::AVAILABILITY[ $t['availability'] ],
				'url'           => '' !== $c['register_url'] ? $c['register_url'] : $url,
			);
			if ( '' !== $t['name'] ) {
				$o = array( 'name' => $t['name'] ) + $o;
			}
			if ( '' !== $t['until'] ) {
				$until = Scout_Events::moment( $t['until'], '23:59' );
				$o['priceValidUntil'] = $t['until'];
				if ( $until ) {
					$o['validThrough'] = $until->format( 'c' );
				}
			}
			$offers[] = $o;
		}
		if ( $offers ) {
			$node['offers'] = 1 === count( $offers ) ? $offers[0] : $offers;
		}

		$teachers  = self::teachers( $c );
		$instances = array();
		foreach ( self::upcoming_instances( $c ) as $run ) {
			$inst = array(
				'@type'      => 'CourseInstance',
				'courseMode' => self::MODE_SCHEMA[ $c['mode'] ],
				'startDate'  => $run['timed'] ? $run['start']->format( 'c' ) : $run['start']->format( 'Y-m-d' ),
			);
			if ( '' !== $run['label'] ) {
				$inst = array( '@type' => 'CourseInstance', 'name' => $run['label'] ) + $inst;
			}
			if ( $run['end'] && ( '' !== $run['range']['end_date'] || '' !== $run['range']['end_time'] ) ) {
				$inst['endDate'] = '' !== $run['range']['end_time'] ? $run['end']->format( 'c' ) : $run['end']->format( 'Y-m-d' );
			}
			if ( '' !== $c['hours'] ) {
				$inst['courseWorkload'] = 'PT' . $c['hours'] . 'H';
			}
			$place = self::place( $c, $run['events'] );
			if ( $place ) {
				$inst['location'] = $place;
			}
			if ( $teachers ) {
				$inst['instructor'] = 1 === count( $teachers ) ? $teachers[0] : $teachers;
			}
			$subs = array();
			foreach ( $run['events'] as $eid ) {
				$e = Scout_Events::event( $eid );
				$s = Scout_Events::start( $e );
				if ( ! $s ) {
					continue;
				}
				$sub = array(
					'@type'     => $e['schema_type'],
					'@id'       => get_permalink( $eid ) . '#event',
					'name'      => self::text( get_the_title( $eid ) ),
					'url'       => get_permalink( $eid ),
					'startDate' => '' !== $e['start_time'] ? $s->format( 'c' ) : $s->format( 'Y-m-d' ),
				);
				$f = Scout_Events::end( $e );
				if ( $f && ( '' !== $e['end_date'] || '' !== $e['end_time'] ) ) {
					$sub['endDate'] = '' !== $e['end_time'] ? $f->format( 'c' ) : $f->format( 'Y-m-d' );
				}
				$subs[] = $sub;
			}
			if ( $subs ) {
				$inst['subEvent'] = $subs;
			}
			$instances[] = $inst;
		}
		if ( $instances ) {
			$node['hasCourseInstance'] = 1 === count( $instances ) ? $instances[0] : $instances;
		}
		return (array) apply_filters( 'scout_events_course_schema', $node, $id, $page_id );
	}

	/**
	 * The run's Place: the course's own venue when its full street address is
	 * public, else the first linked event whose full address is public. Never
	 * a private address, and nothing for an online course.
	 */
	private static function place( array $c, array $event_ids ): array {
		if ( 'online' === $c['mode'] ) {
			return array();
		}
		if ( '' !== $c['venue'] || '' !== $c['street'] ) {
			return self::public_address( $c ) ? self::place_node( $c ) : array();
		}
		foreach ( $event_ids as $eid ) {
			$e = Scout_Events::event( $eid );
			if ( 'online' !== $e['mode'] && Scout_Events::full_address( $e ) && ! Scout_Events::address_private( $eid, $e ) ) {
				return self::place_node( $e );
			}
		}
		return array();
	}

	private static function place_node( array $a ): array {
		$place = array( '@type' => 'Place' );
		if ( '' !== $a['venue'] ) {
			$place['name'] = $a['venue'];
		}
		$place['address'] = array( '@type' => 'PostalAddress' ) + array_filter(
			array(
				'streetAddress'   => $a['street'],
				'addressLocality' => $a['city'],
				'addressRegion'   => $a['region'],
				'postalCode'      => $a['postal'],
				'addressCountry'  => $a['country'],
			)
		);
		return $place;
	}

	/**
	 * The Course nodes for the page being viewed, keyed for Scout Core's
	 * graph, plus an ItemList when the page shows more than one course.
	 */
	public static function page_nodes(): array {
		if ( ! is_singular() ) {
			return array();
		}
		$page_id = (int) get_queried_object_id();
		if ( 'publish' !== get_post_status( $page_id ) ) {
			return array();
		}
		$ids     = self::on_page( $page_id );
		$several = count( $ids ) > 1;
		$nodes   = array();
		foreach ( $ids as $id ) {
			$node = self::schema( $id, $page_id, $several );
			if ( $node ) {
				$nodes[ 'scout_course_' . $id ] = $node;
			}
		}
		if ( count( $nodes ) > 1 ) {
			$items = array();
			$pos   = 1;
			foreach ( $nodes as $node ) {
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $pos++,
					'url'      => $node['url'],
					'item'     => array( '@id' => $node['@id'] ),
				);
			}
			$nodes['scout_course_list'] = array(
				'@type'           => 'ItemList',
				'@id'             => get_permalink( $page_id ) . '#courses',
				'itemListElement' => $items,
			);
		}
		return $nodes;
	}

	/* ---------- edit screen ---------- */

	public static function classic( $use, $post_type ) {
		return self::TYPE === $post_type ? false : $use;
	}

	public static function title_placeholder( $text, $post ) {
		return ( $post instanceof WP_Post && self::TYPE === $post->post_type ) ? __( 'Course name, with no prices or sale words', 'scout-events' ) : $text;
	}

	/** The Events edit-screen look on the course screens too. */
	public static function assets(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && self::TYPE === $screen->post_type ) {
			Scout_Admin_UI::enqueue( SCOUT_EVENTS_URL . 'assets/scout-admin.css' );
			wp_enqueue_style( 'scout-events-admin', SCOUT_EVENTS_URL . 'assets/events-admin.css', array( 'scout-admin' ), SCOUT_EVENTS_VERSION );
		}
	}

	/** Keep the Events menu open while editing a course. */
	public static function highlight( $file ) {
		global $submenu_file;
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && self::TYPE === $screen->post_type ) {
			$submenu_file = 'edit.php?post_type=' . self::TYPE; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			return Scout_Events_Admin::SLUG;
		}
		return $file;
	}

	public static function after_title( $post ): void {
		if ( self::TYPE !== $post->post_type ) {
			return;
		}
		echo '<div class="se-top">';
		self::render( $post );
		echo '</div>';
		echo '<h2 class="se-editor-head">' . esc_html__( 'About the course', 'scout-events' ) . '<span>' . esc_html__( 'What people learn and who it is for. The excerpt, or the start of this, is the description Google reads.', 'scout-events' ) . '</span></h2>';
	}

	private static function render( WP_Post $post ): void {
		$c = self::course( (int) $post->ID );
		wp_nonce_field( 'scout_course_save_' . $post->ID, self::NONCE );
		$n = 'scout_course';

		$events = get_posts(
			array(
				'post_type'      => Scout_Events::EVENT,
				'post_status'    => array( 'publish', 'draft', 'future', 'pending', 'private' ),
				'posts_per_page' => 200,
				'meta_key'       => '_scout_event_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- small table, sorted list.
				'orderby'        => 'meta_value',
				'order'          => 'DESC',
			)
		);
		$instances = array_merge( $c['instances'], array_fill( 0, self::BLANK_ROWS, array( 'label' => '', 'start_date' => '', 'start_time' => '', 'end_date' => '', 'end_time' => '', 'events' => array() ) ) );
		$tiers     = array_merge( $c['tiers'], array_fill( 0, self::BLANK_ROWS, array( 'name' => '', 'price' => '', 'until' => '', 'availability' => 'onsale' ) ) );
		?>
		<div class="se-form">
			<fieldset class="se-group">
				<legend><?php esc_html_e( 'The course', 'scout-events' ); ?></legend>
				<div class="se-grid">
					<?php
					self::input( 'text', "{$n}[level]", 'se-c-level', __( 'Level (optional)', 'scout-events' ), $c['level'], __( 'For example, Beginner, or Level 2.', 'scout-events' ) );
					self::input( 'text', "{$n}[hours]", 'se-c-hours', __( 'Total hours (optional)', 'scout-events' ), $c['hours'], __( 'Class time across every date.', 'scout-events' ) );
					?>
				</div>
				<p class="se-field se-wide">
					<label for="se-c-prereq"><?php esc_html_e( 'Who it is for, or what to have done first (optional)', 'scout-events' ); ?></label>
					<textarea id="se-c-prereq" name="<?php echo esc_attr( $n ); ?>[prereq]" rows="3"><?php echo esc_textarea( $c['prereq'] ); ?></textarea>
				</p>
				<p class="se-field se-wide">
					<label for="se-c-page"><?php esc_html_e( 'Shown on', 'scout-events' ); ?></label>
					<?php
					wp_dropdown_pages(
						array(
							'name'              => $n . '[page]',
							'id'                => 'se-c-page',
							'selected'          => (int) $c['page'],
							'show_option_none'  => esc_html__( 'Choose the page about this course', 'scout-events' ),
							'option_none_value' => '0',
							'post_status'       => array( 'publish', 'draft', 'private' ),
						)
					);
					?>
					<span class="se-help">
						<?php
						/* translators: %d: the course's id. */
						echo esc_html( sprintf( __( 'Google reads the course on this page. Put [scout_course id="%d"] on it to show the dates and register button.', 'scout-events' ), (int) $post->ID ) );
						?>
					</span>
				</p>
			</fieldset>

			<fieldset class="se-group">
				<legend><?php esc_html_e( 'Dates', 'scout-events' ); ?></legend>
				<p class="se-help"><?php esc_html_e( 'One row for each time the course runs, from its first day to its last. Or leave the dates empty and pick the events that are its sessions, and the dates come from them. Rows that have ended stay saved and leave the page.', 'scout-events' ); ?></p>
				<?php foreach ( $instances as $i => $row ) : ?>
					<div class="se-course-row">
						<div class="se-grid">
							<?php
							$base = "{$n}[instances][{$i}]";
							$idb  = 'se-c-run-' . $i;
							self::input( 'text', "{$base}[label]", "{$idb}-label", __( 'Name (optional)', 'scout-events' ), $row['label'], __( 'For example, Spring 2027.', 'scout-events' ) );
							self::input( 'date', "{$base}[start_date]", "{$idb}-sd", __( 'Starts on', 'scout-events' ), $row['start_date'] );
							self::input( 'time', "{$base}[start_time]", "{$idb}-st", __( 'At (optional)', 'scout-events' ), $row['start_time'] );
							self::input( 'date', "{$base}[end_date]", "{$idb}-ed", __( 'Ends on', 'scout-events' ), $row['end_date'] );
							self::input( 'time', "{$base}[end_time]", "{$idb}-et", __( 'At (optional)', 'scout-events' ), $row['end_time'] );
							?>
						</div>
						<?php if ( $events ) : ?>
							<p class="se-field se-wide">
								<label for="<?php echo esc_attr( $idb . '-events' ); ?>"><?php esc_html_e( 'Its sessions on the events calendar (optional)', 'scout-events' ); ?></label>
								<select id="<?php echo esc_attr( $idb . '-events' ); ?>" name="<?php echo esc_attr( $base ); ?>[events][]" multiple size="4">
									<?php foreach ( $events as $ev ) : ?>
										<?php $dates = Scout_Events::dates_label( Scout_Events::event( (int) $ev->ID ) ); ?>
										<option value="<?php echo (int) $ev->ID; ?>" <?php selected( in_array( (int) $ev->ID, $row['events'], true ) ); ?>><?php echo esc_html( get_the_title( $ev ) . ( '' !== $dates ? ' (' . $dates . ')' : '' ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</fieldset>

			<fieldset class="se-group">
				<legend><?php esc_html_e( 'Where', 'scout-events' ); ?></legend>
				<?php self::choose( "{$n}[mode]", 'se-c-mode', __( 'How people take part', 'scout-events' ), self::modes(), $c['mode'] ); ?>
				<div class="se-grid">
					<?php
					self::input( 'text', "{$n}[venue]", 'se-c-venue', __( 'Venue name', 'scout-events' ), $c['venue'] );
					self::input( 'text', "{$n}[street]", 'se-c-street', __( 'Street address', 'scout-events' ), $c['street'] );
					self::input( 'text', "{$n}[city]", 'se-c-city', __( 'City', 'scout-events' ), $c['city'] );
					self::input( 'text', "{$n}[region]", 'se-c-region', __( 'State', 'scout-events' ), $c['region'] );
					self::input( 'text', "{$n}[postal]", 'se-c-postal', __( 'ZIP code', 'scout-events' ), $c['postal'] );
					self::input( 'text', "{$n}[country]", 'se-c-country', __( 'Country code', 'scout-events' ), $c['country'] );
					?>
				</div>
				<p class="se-field se-wide">
					<label for="se-c-private"><input type="checkbox" id="se-c-private" name="<?php echo esc_attr( $n ); ?>[address_private]" value="1" aria-describedby="se-c-private-help" <?php checked( (bool) $c['address_private'] ); ?>> <?php esc_html_e( 'Keep the address private', 'scout-events' ); ?></label>
					<span class="se-help" id="se-c-private-help"><?php esc_html_e( 'The page shows the venue and city only, and Google gets no place for the course. Leave the venue empty to use the place of the events picked as sessions, when theirs is public.', 'scout-events' ); ?></span>
				</p>
			</fieldset>

			<fieldset class="se-group">
				<legend><?php esc_html_e( 'Prices and registration', 'scout-events' ); ?></legend>
				<div class="se-grid">
					<?php
					self::input( 'url', "{$n}[register_url]", 'se-c-reg', __( 'Registration link', 'scout-events' ), $c['register_url'], __( 'Where the button goes: an application form, a booking site or a checkout.', 'scout-events' ) );
					self::input( 'text', "{$n}[register_label]", 'se-c-reg-label', __( 'Button words (optional)', 'scout-events' ), $c['register_label'], __( 'Leave empty for "Register".', 'scout-events' ) );
					?>
				</div>
				<p class="se-help"><?php esc_html_e( 'One row for each price, such as an early price and the full price. A price leaves the page after its last day.', 'scout-events' ); ?></p>
				<?php foreach ( $tiers as $i => $row ) : ?>
					<div class="se-grid se-course-row">
						<?php
						$base = "{$n}[tiers][{$i}]";
						$idb  = 'se-c-tier-' . $i;
						self::input( 'text', "{$base}[name]", "{$idb}-name", __( 'Price name', 'scout-events' ), $row['name'], __( 'For example, Early price.', 'scout-events' ) );
						self::input( 'text', "{$base}[price]", "{$idb}-price", __( 'Price', 'scout-events' ), $row['price'], __( '0 for free.', 'scout-events' ) );
						self::input( 'date', "{$base}[until]", "{$idb}-until", __( 'Last day (optional)', 'scout-events' ), $row['until'] );
						self::choose( "{$base}[availability]", "{$idb}-avail", __( 'This price is', 'scout-events' ), self::availabilities(), $row['availability'] );
						?>
					</div>
				<?php endforeach; ?>
			</fieldset>

			<fieldset class="se-group">
				<legend><?php esc_html_e( 'Taught by', 'scout-events' ); ?></legend>
				<?php
				$people = array();
				if ( class_exists( 'Scout_Team' ) ) {
					foreach ( get_posts( array( 'post_type' => Scout_Team::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) ) as $p ) {
						$people[] = array( 'teachers_team', (int) $p->ID, get_the_title( $p ), in_array( (int) $p->ID, $c['teachers_team'], true ) );
					}
				}
				foreach ( get_posts( array( 'post_type' => Scout_Events::SPEAKER, 'post_status' => 'publish', 'posts_per_page' => 500, 'orderby' => 'title', 'order' => 'ASC' ) ) as $p ) {
					$people[] = array( 'teachers_speaker', (int) $p->ID, get_the_title( $p ), in_array( (int) $p->ID, $c['teachers_speaker'], true ) );
				}
				?>
				<?php if ( $people ) : ?>
					<div class="se-people">
						<?php foreach ( $people as $p ) : ?>
							<?php $pid = 'se-c-' . $p[0] . '-' . $p[1]; ?>
							<label for="<?php echo esc_attr( $pid ); ?>">
								<input type="checkbox" id="<?php echo esc_attr( $pid ); ?>" name="<?php echo esc_attr( $n . '[' . $p[0] . '][]' ); ?>" value="<?php echo (int) $p[1]; ?>" <?php checked( $p[3] ); ?>>
								<?php echo esc_html( $p[2] ); ?>
								<span class="se-note"><?php echo esc_html( 'teachers_team' === $p[0] ? __( '(team)', 'scout-events' ) : __( '(speaker)', 'scout-events' ) ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<p class="se-field se-wide">
					<label for="se-c-names"><?php esc_html_e( 'Other teachers, one name per line', 'scout-events' ); ?></label>
					<textarea id="se-c-names" name="<?php echo esc_attr( $n ); ?>[teacher_names]" rows="2"><?php echo esc_textarea( implode( "\n", $c['teacher_names'] ) ); ?></textarea>
				</p>
			</fieldset>

			<fieldset class="se-group">
				<legend><?php esc_html_e( 'Google', 'scout-events' ); ?></legend>
				<p class="se-field se-wide">
					<input type="hidden" name="<?php echo esc_attr( $n ); ?>[schema]" value="0">
					<label for="se-c-schema"><input type="checkbox" id="se-c-schema" name="<?php echo esc_attr( $n ); ?>[schema]" value="1" <?php checked( (bool) $c['schema'] ); ?>> <?php esc_html_e( 'Show this course on Google', 'scout-events' ); ?></label>
					<span class="se-help"><?php esc_html_e( 'Google reads the name, description, dates, place, prices and teachers from the page it is shown on. Google lists courses in a group once a site has three or more.', 'scout-events' ); ?></span>
				</p>
			</fieldset>
		</div>
		<?php
	}

	public static function save_box( $post_id, $post ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), 'scout_course_save_' . $post_id ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every key is cleaned in sanitize().
		$raw = isset( $_POST['scout_course'] ) ? (array) wp_unslash( $_POST['scout_course'] ) : array();
		self::save( (int) $post_id, $raw );
	}

	public static function columns( array $cols ): array {
		$out = array();
		foreach ( $cols as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['se_next']  = __( 'Next dates', 'scout-events' );
				$out['se_price'] = __( 'Price', 'scout-events' );
				$out['se_page']  = __( 'Shown on', 'scout-events' );
			}
		}
		unset( $out['date'] );
		return $out;
	}

	public static function column( $col, $post_id ): void {
		$c = self::course( (int) $post_id );
		if ( 'se_next' === $col ) {
			$runs = self::upcoming_instances( $c );
			echo $runs ? esc_html( Scout_Events::dates_label( $runs[0]['range'] ) ) : '<span class="se-note">' . esc_html__( 'No dates coming up', 'scout-events' ) . '</span>';
		} elseif ( 'se_price' === $col ) {
			$prices = array_filter( wp_list_pluck( self::current_tiers( $c ), 'price' ), static fn( $p ) => '' !== $p );
			echo $prices ? esc_html( Scout_Events::money( (string) min( array_map( 'floatval', $prices ) ) ) ) : '';
		} elseif ( 'se_page' === $col ) {
			echo $c['page'] ? '<a href="' . esc_url( (string) get_permalink( $c['page'] ) ) . '">' . esc_html( get_the_title( $c['page'] ) ) . '</a>' : '<span class="se-note">' . esc_html__( 'No page yet', 'scout-events' ) . '</span>';
		}
	}

	private static function input( string $type, string $name, string $id, string $label, string $value, string $help = '' ): void {
		echo '<p class="se-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		printf(
			'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s"%5$s>',
			esc_attr( $type ),
			esc_attr( $id ),
			esc_attr( $name ),
			esc_attr( $value ),
			'' !== $help ? ' aria-describedby="' . esc_attr( $id ) . '-help"' : ''
		);
		if ( '' !== $help ) {
			echo '<span class="se-help" id="' . esc_attr( $id ) . '-help">' . esc_html( $help ) . '</span>';
		}
		echo '</p>';
	}

	private static function choose( string $name, string $id, string $label, array $choices, string $value ): void {
		echo '<p class="se-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $choices as $k => $text ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( (string) $k ), selected( (string) $k, $value, false ), esc_html( $text ) );
		}
		echo '</select></p>';
	}
}
