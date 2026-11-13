<?php
/**
 * The call bar and the "Let's talk" sheet on the front end.
 *
 * Design: Scout features DESIGN.md 5.9 (screen 4b). The bar shows on phones
 * only. "Book a time" opens the site's booking page rather than offering
 * times inline, because the booking calendars we use do not share open
 * times with other sites.
 *
 * @package Scout_Contact
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scout_Contact {

	const OPTION = 'scout_contact';

	public static function boot(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render' ), 20 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * The phone number in Business info (Scout Core), read live each time so a
	 * change there shows here at once. '' when Scout Core is not active.
	 */
	public static function business_phone(): string {
		$biz = array();
		if ( function_exists( 'scout_core_business' ) ) {
			$biz = (array) scout_core_business();
		} elseif ( class_exists( 'Scout_Core_Business' ) ) {
			$biz = (array) Scout_Core_Business::get();
		}
		return trim( (string) ( $biz['phone'] ?? '' ) );
	}

	/**
	 * The phone field is an optional override: blank means "use Business
	 * info", which settings() fills in live at display time.
	 */
	public static function defaults(): array {
		return array(
			'enabled'   => 1,
			'phone'     => '',
			'text'      => '',
			'text_off'  => 0,
			'book_url'  => function_exists( 'scout_booking_url' ) ? (string) scout_booking_url() : '',
			'label'     => "Let's talk",
			'title'     => "Let's talk",
			'subtitle'  => "Pick whatever's easiest for you.",
			'text_note' => '',
			'book_note' => '',
			'photos'    => array(),
			'hide_on'   => 'card, links, review, book',
			'sets'      => array(),
		);
	}

	/** What is saved on this screen, with blanks left blank (the form shows these). */
	public static function saved(): array {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/** What the bar uses: the saved values, with a blank phone read from Business info. */
	public static function settings(): array {
		$s = self::saved();
		if ( '' === trim( (string) $s['phone'] ) ) {
			$s['phone'] = self::business_phone();
		}
		return $s;
	}

	/** A dialable number: digits only, with +1 added to ten-digit US numbers. */
	public static function dial( string $number ): string {
		$digits = preg_replace( '/\D+/', '', $number );
		if ( '' === $digits ) {
			return '';
		}
		if ( 10 === strlen( $digits ) ) {
			$digits = '1' . $digits;
		}
		return '+' . $digits;
	}

	/** What the bar offers on this page: call, text and book links ('' when off). */
	public static function actions(): array {
		$s    = self::settings();
		$call = self::dial( (string) $s['phone'] );
		$text = $s['text_off'] ? '' : self::dial( '' !== trim( (string) $s['text'] ) ? (string) $s['text'] : (string) $s['phone'] );
		return array(
			'call' => $call,
			'text' => $text,
			'book' => esc_url_raw( (string) $s['book_url'] ),
		);
	}

	/** Should the bar show on the page being viewed? */
	public static function active(): bool {
		static $active = null;
		if ( null !== $active ) {
			return $active;
		}
		$s      = self::settings();
		$active = ! is_admin() && ! empty( $s['enabled'] );
		if ( $active ) {
			if ( null === self::current_set() ) {
				$a      = self::actions();
				$active = '' !== $a['call'] || '' !== $a['book'];
			} else {
				$active = (bool) self::view()['options'];
			}
		}
		if ( $active ) {
			$path  = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$first = strtolower( explode( '/', $path )[0] ?? '' );
			$hide  = array_filter( array_map( static fn( $p ) => strtolower( trim( $p, " /\t" ) ), explode( ',', (string) $s['hide_on'] ) ) );
			if ( '' !== $first && in_array( $first, $hide, true ) ) {
				$active = false;
			}
		}
		/** Filter: turn the bar off (or on) for the current page. */
		$active = (bool) apply_filters( 'scout_contact_active', $active );
		return $active;
	}

	/* ------------------------------------------------------------------
	 * Button sets (0.3.0). A set swaps the bar's buttons on some pages:
	 * the pages it names (and their child pages) and/or the addresses that
	 * start with a path such as /bodywork/. The first set that matches wins;
	 * pages with no match keep the default buttons. Decided on the server
	 * from the address alone, so a page cache keeps working.
	 * ------------------------------------------------------------------ */

	/** The kinds of button a set can hold, in the order the screen lists them. */
	const TYPES = array( 'call', 'text', 'book', 'email', 'directions', 'link' );

	/** Most buttons one set can hold. */
	const MAX_BUTTONS = 8;

	/** "/Bodywork" or "https://site/bodywork/x" becomes "/bodywork/" or "/bodywork/x/". */
	public static function clean_path( string $path ): string {
		$path = trim( $path );
		if ( '' === $path ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $path ) ) {
			$path = (string) parse_url( $path, PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		}
		$path = strtolower( (string) preg_replace( '#[^A-Za-z0-9/_.~%-]#', '', $path ) );
		$path = trim( $path, '/' );
		return '' === $path ? '/' : '/' . $path . '/';
	}

	/** One button's own link, cleaned for its kind ('' means "use the site's"). */
	public static function clean_link( string $type, string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		if ( 'call' === $type || 'text' === $type ) {
			$url = (string) preg_replace( '/^(tel|sms):/i', '', $url );
			return '' === self::dial( $url ) ? '' : sanitize_text_field( $url );
		}
		if ( 'email' === $type ) {
			return (string) sanitize_email( (string) preg_replace( '/^mailto:/i', '', $url ) );
		}
		return (string) esc_url_raw( $url, array( 'http', 'https' ) );
	}

	/**
	 * A set in its stored shape, or null when it holds nothing at all. Also
	 * cleans a set handed over by the scout_contact_button_set filter.
	 *
	 * @param mixed $set Raw set.
	 */
	public static function clean_set( $set ): ?array {
		if ( ! is_array( $set ) ) {
			return null;
		}
		$paths = $set['paths'] ?? array();
		if ( is_string( $paths ) ) {
			$paths = preg_split( '/[\s,]+/', $paths );
		}
		$buttons = array();
		foreach ( (array) ( $set['buttons'] ?? array() ) as $b ) {
			if ( is_string( $b ) ) {
				$b = array( 'type' => $b );
			}
			if ( ! is_array( $b ) ) {
				continue;
			}
			$type = strtolower( preg_replace( '/[^a-z]/i', '', (string) ( $b['type'] ?? '' ) ) );
			if ( ! in_array( $type, self::TYPES, true ) ) {
				continue;
			}
			$label = sanitize_text_field( (string) ( $b['label'] ?? '' ) );
			$url   = self::clean_link( $type, (string) ( $b['url'] ?? '' ) );
			if ( 'link' === $type && ( '' === $label || '' === $url ) ) {
				continue; // A custom button needs its own wording and link.
			}
			$buttons[] = array(
				'type'  => $type,
				'label' => $label,
				'url'   => $url,
			);
		}
		$out = array(
			'name'     => sanitize_text_field( (string) ( $set['name'] ?? '' ) ),
			'pages'    => array_values( array_unique( array_filter( array_map( 'absint', (array) ( $set['pages'] ?? array() ) ) ) ) ),
			'children' => ! empty( $set['children'] ) ? 1 : 0,
			'paths'    => array_values( array_unique( array_filter( array_map( array( __CLASS__, 'clean_path' ), array_map( 'strval', (array) $paths ) ) ) ) ),
			'no_text'  => ! empty( $set['no_text'] ) ? 1 : 0,
			'label'    => sanitize_text_field( (string) ( $set['label'] ?? '' ) ),
			'buttons'  => array_slice( $buttons, 0, self::MAX_BUTTONS ),
		);
		if ( '' === $out['name'] && ! $out['pages'] && ! $out['paths'] && ! $out['buttons'] ) {
			return null;
		}
		return $out;
	}

	/**
	 * The first set that matches a page, or null for the default buttons.
	 *
	 * @param array  $sets      Stored sets, in order.
	 * @param int    $page_id   The page being shown (0 when it is not a single page or post).
	 * @param int[]  $ancestors Its parent pages.
	 * @param string $path      The address path, e.g. "/bodywork/deep-tissue/".
	 */
	public static function match_set( array $sets, int $page_id, array $ancestors, string $path ): ?array {
		$path = self::clean_path( '' === $path ? '/' : $path );
		foreach ( $sets as $set ) {
			if ( ! is_array( $set ) ) {
				continue;
			}
			$pages = array_map( 'intval', (array) ( $set['pages'] ?? array() ) );
			if ( $page_id && $pages ) {
				if ( in_array( $page_id, $pages, true ) ) {
					return $set;
				}
				if ( ! empty( $set['children'] ) && array_intersect( array_map( 'intval', $ancestors ), $pages ) ) {
					return $set;
				}
			}
			foreach ( (array) ( $set['paths'] ?? array() ) as $prefix ) {
				$prefix = (string) $prefix;
				if ( '' !== $prefix && 0 === strpos( $path, $prefix ) ) {
					return $set;
				}
			}
		}
		return null;
	}

	/** The set for the page being viewed, or null for the default buttons. */
	public static function current_set(): ?array {
		static $done = false, $set = null;
		if ( $done ) {
			return $set;
		}
		$done    = true;
		$sets    = array_values( array_filter( (array) ( self::saved()['sets'] ?? array() ), 'is_array' ) );
		$page_id = 0;
		$parents = array();
		if ( function_exists( 'is_singular' ) && is_singular() ) {
			$page_id = (int) get_queried_object_id();
			$parents = $page_id ? array_map( 'intval', (array) get_post_ancestors( $page_id ) ) : array();
		}
		$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$pick = $sets ? self::match_set( $sets, $page_id, $parents, $path ) : null;
		/**
		 * Filter: pick the button set for the current page.
		 *
		 * Return a set (same shape as a saved one), a saved set's name, or null
		 * for the default buttons.
		 *
		 * @param array|null $pick    The first saved set that matched, or null.
		 * @param array      $sets    Every saved set, in order.
		 * @param array      $context page_id (0 when not a single page) and path.
		 */
		$pick = apply_filters( 'scout_contact_button_set', $pick, $sets, array( 'page_id' => $page_id, 'path' => self::clean_path( '' === $path ? '/' : $path ) ) );
		if ( is_string( $pick ) && '' !== trim( $pick ) ) {
			$name = strtolower( trim( $pick ) );
			$pick = null;
			foreach ( $sets as $one ) {
				if ( strtolower( (string) ( $one['name'] ?? '' ) ) === $name ) {
					$pick = $one;
					break;
				}
			}
		}
		$set = is_array( $pick ) ? self::clean_set( $pick ) : null;
		return $set;
	}

	/** The Maps search for the address in Business info ('' without one). */
	public static function directions_url(): string {
		$biz = array();
		if ( function_exists( 'scout_core_business' ) ) {
			$biz = (array) scout_core_business();
		} elseif ( class_exists( 'Scout_Core_Business' ) ) {
			$biz = (array) Scout_Core_Business::get();
		}
		$lat = trim( (string) ( $biz['latitude'] ?? '' ) );
		$lng = trim( (string) ( $biz['longitude'] ?? '' ) );
		$parts = array_filter( array_map( static fn( $k ) => trim( (string) ( $biz[ $k ] ?? '' ) ), array( 'street', 'city', 'region', 'postal' ) ) );
		if ( '' === trim( (string) ( $biz['street'] ?? '' ) ) ) {
			if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
				return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $lat . ',' . $lng );
			}
			return ''; // A city alone is not somewhere to drive to.
		}
		$name = trim( (string) ( $biz['name'] ?? '' ) );
		return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( trim( ( '' !== $name ? $name . ', ' : '' ) . implode( ', ', $parts ) ) );
	}

	/** The email address in Business info ('' without one). */
	public static function business_email(): string {
		$biz = array();
		if ( function_exists( 'scout_core_business' ) ) {
			$biz = (array) scout_core_business();
		} elseif ( class_exists( 'Scout_Core_Business' ) ) {
			$biz = (array) Scout_Core_Business::get();
		}
		return trim( (string) ( $biz['email'] ?? '' ) );
	}

	/**
	 * One set button as a sheet option: key, link, title, note, track event.
	 * Null when the button has nowhere to go.
	 */
	public static function option( array $b, array $s, bool $no_text = false ): ?array {
		$type  = (string) ( $b['type'] ?? '' );
		$label = trim( (string) ( $b['label'] ?? '' ) );
		$url   = trim( (string) ( $b['url'] ?? '' ) );
		switch ( $type ) {
			case 'call':
				$shown = '' !== $url ? $url : (string) $s['phone'];
				$num   = self::dial( $shown );
				return '' === $num ? null : array( 'call', 'tel:' . $num, '' !== $label ? $label : __( 'Call us now', 'scout-contact' ), $shown, 'contact_call', $num );
			case 'text':
				if ( $no_text || ( '' === $url && ! empty( $s['text_off'] ) ) ) {
					return null;
				}
				$num = self::dial( '' !== $url ? $url : ( '' !== trim( (string) $s['text'] ) ? (string) $s['text'] : (string) $s['phone'] ) );
				return '' === $num ? null : array( 'text', 'sms:' . $num, '' !== $label ? $label : __( 'Send a text', 'scout-contact' ), '' !== trim( (string) $s['text_note'] ) ? (string) $s['text_note'] : __( 'Opens a message on your phone.', 'scout-contact' ), 'contact_text', $num );
			case 'book':
				$link = esc_url_raw( '' !== $url ? $url : (string) $s['book_url'] );
				return '' === $link ? null : array( 'book', $link, '' !== $label ? $label : __( 'Book a time', 'scout-contact' ), '' !== trim( (string) $s['book_note'] ) ? (string) $s['book_note'] : __( 'Pick a time that works for you.', 'scout-contact' ), 'contact_book', '' );
			case 'email':
				$mail = '' !== $url ? $url : self::business_email();
				return '' === $mail ? null : array( 'email', 'mailto:' . $mail, '' !== $label ? $label : __( 'Send an email', 'scout-contact' ), $mail, 'contact_email', '' );
			case 'directions':
				$link = esc_url_raw( '' !== $url ? $url : self::directions_url() );
				return '' === $link ? null : array( 'directions', $link, '' !== $label ? $label : __( 'Get directions', 'scout-contact' ), __( 'Opens a map.', 'scout-contact' ), 'contact_directions', '' );
			case 'link':
				$link = esc_url_raw( $url );
				return ( '' === $link || '' === $label ) ? null : array( 'link', $link, $label, '', 'contact_link', '' );
		}
		return null;
	}

	/**
	 * What the bar shows on this page: the Call and Text numbers for the bar,
	 * the main button's wording, the sheet's options, and whether the main
	 * button links straight to a single option instead of opening the sheet.
	 */
	public static function view(): array {
		$s   = self::settings();
		$set = self::current_set();
		if ( null === $set ) {
			$a       = self::actions();
			$options = array();
			if ( '' !== $a['call'] ) {
				$options[] = array( 'call', 'tel:' . $a['call'], __( 'Call us now', 'scout-contact' ), (string) $s['phone'], 'contact_call', $a['call'] );
			}
			if ( '' !== $a['text'] ) {
				$options[] = array( 'text', 'sms:' . $a['text'], __( 'Send a text', 'scout-contact' ), '' !== trim( (string) $s['text_note'] ) ? (string) $s['text_note'] : __( 'Opens a message on your phone.', 'scout-contact' ), 'contact_text', $a['text'] );
			}
			if ( '' !== $a['book'] ) {
				$options[] = array( 'book', $a['book'], __( 'Book a time', 'scout-contact' ), '' !== trim( (string) $s['book_note'] ) ? (string) $s['book_note'] : __( 'Pick a time that works for you.', 'scout-contact' ), 'contact_book', '' );
			}
			return array(
				'call'    => $a['call'],
				'text'    => $a['text'],
				'label'   => (string) $s['label'],
				'options' => $options,
				'direct'  => false,
			);
		}
		$buttons = $set['buttons'] ? $set['buttons'] : array( array( 'type' => 'call' ), array( 'type' => 'text' ), array( 'type' => 'book' ) );
		$options = array();
		$call    = '';
		$text    = '';
		foreach ( $buttons as $b ) {
			$opt = self::option( (array) $b, $s, ! empty( $set['no_text'] ) );
			if ( null === $opt ) {
				continue;
			}
			if ( 'call' === $opt[0] && '' === $call ) {
				$call = $opt[5];
			}
			if ( 'text' === $opt[0] && '' === $text ) {
				$text = $opt[5];
			}
			$options[] = $opt;
		}
		$direct = 1 === count( $options );
		$label  = '' !== $set['label'] ? $set['label'] : ( $direct ? $options[0][2] : (string) $s['label'] );
		return array(
			'call'    => $call,
			'text'    => $text,
			'label'   => $label,
			'options' => $options,
			'direct'  => $direct,
		);
	}

	public static function body_class( array $classes ): array {
		if ( self::active() ) {
			$classes[] = 'scc-on';
		}
		return $classes;
	}

	public static function assets(): void {
		if ( ! self::active() ) {
			return;
		}
		// The bar is on screen from the first paint, so its styles can't load late,
		// and a separate file held up the first paint (PageSpeed: 150 ms). The
		// stylesheet is about 5 KB, so it prints inline in <head> instead.
		$css = (string) @file_get_contents( plugin_dir_path( SCOUT_CONTACT_FILE ) . 'assets/contact.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( '' !== $css && apply_filters( 'scout_contact_inline_css', true ) ) {
			wp_register_style( 'scout-contact', false, array(), SCOUT_CONTACT_VERSION );
			wp_enqueue_style( 'scout-contact' );
			wp_add_inline_style( 'scout-contact', $css );
		} else {
			wp_enqueue_style( 'scout-contact', SCOUT_CONTACT_URL . 'assets/contact.css', array(), SCOUT_CONTACT_VERSION );
		}
		$look = self::design_css();
		if ( '' !== $look ) {
			wp_add_inline_style( 'scout-contact', $look );
		}
		wp_enqueue_script( 'scout-contact', SCOUT_CONTACT_URL . 'assets/contact.js', array(), SCOUT_CONTACT_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	}

	/**
	 * The bar's look from DESIGN.md's "### Feature: call buttons" section, laid
	 * over the stylesheet. Empty when the theme has no such section (or Scout
	 * Core is older than 1.11.0), so the bar stays dark glass. Keys: bar,
	 * bar_text, bar_blur, bar_line, bar_shadow, primary_fill, primary_text,
	 * secondary_border, label_font, label_size, label_tracking, label_case.
	 */
	public static function design_css( ?array $f = null, ?array $tokens = null ): string {
		if ( null === $f ) {
			if ( ! function_exists( 'scout_design_feature' ) ) {
				return '';
			}
			$f      = scout_design_feature( 'call buttons' );
			$tokens = function_exists( 'scout_design' ) ? scout_design() : array();
		}
		if ( ! $f ) {
			return '';
		}
		$v = static function ( string $key ) use ( $f ): string {
			$value = isset( $f[ $key ] ) && is_scalar( $f[ $key ] ) ? trim( (string) $f[ $key ] ) : '';
			return (string) preg_replace( '/[^\w\s#,.()%"\'-]/', '', $value ); // Nothing that could close the style tag.
		};
		$rules = array();
		$bar   = $v( 'bar' );
		if ( '' !== $bar ) {
			$light  = self::is_light( $bar );
			$text   = '' !== $v( 'bar_text' ) ? $v( 'bar_text' ) : ( $light ? (string) ( $tokens['colors']['text'] ?? '#1D1D1F' ) : '#F5F5F7' );
			$hover  = $light ? 'rgba(0,0,0,.05)' : 'rgba(255,255,255,.08)';
			$shadow = '' !== $v( 'bar_shadow' ) ? $v( 'bar_shadow' ) : ( $light ? '0 8px 24px rgba(0,0,0,.12)' : '' );
			$rules[] = '.scc-bar{background:' . $bar . ( '' !== $shadow ? ';box-shadow:' . $shadow : '' ) . '}';
			$rules[] = '.scc-bar .scc-btn,.scc-bar .scc-btn:hover,.scc-bar .scc-btn:focus-visible{color:' . $text . '}';
			$rules[] = '.scc-bar .scc-btn:hover,.scc-bar .scc-btn:focus-visible{background:' . $hover . '}';
		} elseif ( '' !== $v( 'bar_text' ) ) {
			$rules[] = '.scc-bar .scc-btn,.scc-bar .scc-btn:hover,.scc-bar .scc-btn:focus-visible{color:' . $v( 'bar_text' ) . '}';
		}
		if ( '' !== $v( 'bar_blur' ) ) {
			$rules[] = '.scc-bar{-webkit-backdrop-filter:blur(' . $v( 'bar_blur' ) . ');backdrop-filter:blur(' . $v( 'bar_blur' ) . ')}';
		}
		if ( '' !== $v( 'bar_line' ) ) {
			$rules[] = '.scc-bar{border-color:' . $v( 'bar_line' ) . '}';
		}
		$main = array();
		if ( '' !== $v( 'primary_fill' ) ) {
			$main[] = 'background:' . $v( 'primary_fill' );
		}
		if ( '' !== $v( 'primary_text' ) ) {
			$main[] = 'color:' . $v( 'primary_text' );
		}
		if ( $main ) {
			$rules[] = '.scc-bar .scc-main{' . implode( ';', $main ) . '}';
		}
		if ( '' !== $v( 'secondary_border' ) ) {
			$rules[] = '.scc-bar .scc-btn{box-shadow:inset 0 0 0 1px ' . $v( 'secondary_border' ) . '}'; // A border would change the button size.
		}
		$label = array();
		$font  = $v( 'label_font' );
		if ( '' !== $font ) {
			$label[] = 'font-family:' . $font;
		}
		if ( '' !== $v( 'label_size' ) ) {
			$label[] = 'font-size:' . $v( 'label_size' );
		}
		// "label_font: label" takes the label role's case and tracking unless the section says otherwise.
		$role     = (array) ( $tokens['fonts']['label'] ?? array() );
		$is_label = '' !== $font && class_exists( 'Scout_Core_Design' ) && $role && Scout_Core_Design::font_stack( $role ) === (string) ( $f['label_font'] ?? '' );
		$tracking = '' !== $v( 'label_tracking' ) ? $v( 'label_tracking' ) : ( $is_label ? (string) ( $role['tracking'] ?? '' ) : '' );
		$case     = '' !== $v( 'label_case' ) ? $v( 'label_case' ) : ( $is_label ? (string) ( $role['case'] ?? '' ) : '' );
		if ( '' !== $tracking ) {
			$label[] = 'letter-spacing:' . preg_replace( '/[^\w.%-]/', '', $tracking );
		}
		if ( in_array( strtolower( $case ), array( 'upper', 'uppercase' ), true ) ) {
			$label[] = 'text-transform:uppercase';
		} elseif ( in_array( strtolower( $case ), array( 'none', 'lower', 'lowercase', 'capitalize' ), true ) ) {
			$label[] = 'text-transform:' . ( 'lower' === strtolower( $case ) ? 'lowercase' : strtolower( $case ) );
		}
		if ( $label ) {
			$rules[] = '.scc-bar .scc-btn span,.scc-bar .scc-main{' . implode( ';', $label ) . '}';
		}
		return implode( '', $rules );
	}

	/** Is a hex or rgb()/rgba() color light? Transparency is ignored: the bar is nearly opaque. */
	private static function is_light( string $color ): bool {
		$rgb = null;
		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color, $m ) ) {
			$h   = 3 === strlen( $m[1] ) ? preg_replace( '/(.)/', '$1$1', $m[1] ) : $m[1];
			$rgb = array( hexdec( substr( $h, 0, 2 ) ), hexdec( substr( $h, 2, 2 ) ), hexdec( substr( $h, 4, 2 ) ) );
		} elseif ( preg_match( '/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i', $color, $m ) ) {
			$rgb = array( (int) $m[1], (int) $m[2], (int) $m[3] );
		}
		if ( ! $rgb ) {
			return false;
		}
		$lin = array_map(
			static function ( int $c ): float {
				$s = $c / 255;
				return $s <= 0.03928 ? $s / 12.92 : pow( ( $s + 0.055 ) / 1.055, 2.4 );
			},
			$rgb
		);
		return ( 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2] ) > 0.4;
	}

	private static function icon( string $name ): string {
		$paths = array(
			'call' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
			'text' => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/>',
		);
		return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	/** A sheet or main-button link, escaped for its kind. */
	private static function href( string $link ): string {
		return preg_match( '/^(tel|sms):/', $link ) ? esc_attr( $link ) : esc_url( $link );
	}

	public static function render(): void {
		if ( ! self::active() ) {
			return;
		}
		$s       = self::settings();
		$v       = self::view();
		$options = $v['options'];
		$photos  = array_slice( array_filter( array_map( 'absint', (array) $s['photos'] ) ), 0, 3 );
		?>
<div class="scc" data-scc>
	<nav class="scc-bar" aria-label="<?php esc_attr_e( 'Contact', 'scout-contact' ); ?>">
		<?php if ( '' !== $v['call'] ) : ?>
		<a class="scc-btn" href="<?php echo esc_attr( 'tel:' . $v['call'] ); ?>" data-scout-track="contact_call" data-scout-label="Call bar"><?php echo self::icon( 'call' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Call', 'scout-contact' ); ?></span></a>
		<?php endif; ?>
		<?php if ( '' !== $v['text'] ) : ?>
		<a class="scc-btn" href="<?php echo esc_attr( 'sms:' . $v['text'] ); ?>" data-scout-track="contact_text" data-scout-label="Call bar"><?php echo self::icon( 'text' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Text', 'scout-contact' ); ?></span></a>
		<?php endif; ?>
		<?php
		// One option: the main button goes straight to it, with no sheet to open.
		if ( $v['direct'] ) {
			echo '<a class="scc-main" href="' . self::href( $options[0][1] ) . '" data-scout-track="' . esc_attr( $options[0][4] ) . '" data-scout-label="Call bar">' . esc_html( $v['label'] ) . "</a>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			echo '<button class="scc-main" type="button" aria-haspopup="dialog" aria-controls="scc-sheet" data-scc-open>' . esc_html( $v['label'] ) . "</button>\n";
		}
		?>
	</nav>
<?php if ( ! $v['direct'] ) : ?>
	<dialog id="scc-sheet" class="scc-sheet" aria-labelledby="scc-title">
		<div class="scc-grab" aria-hidden="true"></div>
		<div class="scc-head">
			<?php if ( $photos ) : ?>
			<span class="scc-faces" aria-hidden="true">
				<?php foreach ( $photos as $id ) { echo wp_get_attachment_image( $id, array( 96, 96 ), false, array( 'alt' => '', 'loading' => 'lazy' ) ); } ?>
			</span>
			<?php endif; ?>
			<div class="scc-titles">
				<h2 id="scc-title"><?php echo esc_html( (string) $s['title'] ); ?></h2>
				<?php if ( '' !== trim( (string) $s['subtitle'] ) ) : ?><p><?php echo esc_html( (string) $s['subtitle'] ); ?></p><?php endif; ?>
			</div>
			<button class="scc-close" type="button" data-scc-close aria-label="<?php esc_attr_e( 'Close', 'scout-contact' ); ?>"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
		</div>
		<ol class="scc-opts">
			<?php foreach ( $options as $i => $opt ) : ?>
			<li><a class="scc-opt<?php echo 'book' === $opt[0] ? ' is-book' : ''; ?>" href="<?php echo self::href( $opt[1] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" data-scout-track="<?php echo esc_attr( $opt[4] ); ?>" data-scout-label="Lets talk sheet">
				<span class="scc-n" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span>
				<span class="scc-txt"><strong><?php echo esc_html( $opt[2] ); ?></strong><?php if ( '' !== $opt[3] ) : ?><span><?php echo esc_html( $opt[3] ); ?></span><?php endif; ?></span>
			</a></li>
			<?php endforeach; ?>
		</ol>
	</dialog>
<?php endif; ?>
</div>
		<?php
	}
}
