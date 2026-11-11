<?php
/**
 * No single word alone on a line (Scout brand rule).
 *
 * Themes ask for balanced headings with text-wrap, which older Safari and
 * Firefox ignore, and many headings come from the database, so on the way out
 * this ties the last two words of each heading with a non-breaking space.
 *
 * Off until a theme opts in, so a live page stays the same on update (Site
 * Standard rule 20):
 *
 *     add_theme_support( 'scout-no-orphans' );
 *     add_theme_support( 'scout-no-orphans', array(
 *         'elements' => array( 'h1', 'h2', 'h3', 'summary' ),  // default: h1 to h6 and summary
 *         'max'      => 22,                                     // longest pair tied, in characters
 *         'skip'     => array( 'footer-head' ),                 // classes left alone
 *     ) );
 *
 * Only headings of three words or more are touched, and only when the last two
 * words are short together, so a tied pair cannot push past a 320px phone. A
 * heading whose last gap is already a non-breaking space is left as it is.
 * Filter scout_core_no_orphans (bool) turns it on or off; filter
 * scout_core_no_orphans_args adjusts the settings.
 *
 * @package Scout_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scout_Core_Typography {

	const DEFAULT_ELEMENTS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'summary' );
	const DEFAULT_MAX      = 22;

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'start' ), 1 );
	}

	/** True when this site's theme asked Core to tie heading words. */
	public static function enabled() {
		return (bool) apply_filters( 'scout_core_no_orphans', function_exists( 'current_theme_supports' ) && current_theme_supports( 'scout-no-orphans' ) );
	}

	/**
	 * The settings: the theme's own, over the defaults.
	 *
	 * @return array{elements:string[],max:int,skip:string[]}
	 */
	public static function args() {
		$given = array();
		if ( function_exists( 'get_theme_support' ) ) {
			$support = get_theme_support( 'scout-no-orphans' );
			if ( is_array( $support ) && isset( $support[0] ) && is_array( $support[0] ) ) {
				$given = $support[0];
			}
		}
		$args = array(
			'elements' => isset( $given['elements'] ) ? (array) $given['elements'] : self::DEFAULT_ELEMENTS,
			'max'      => isset( $given['max'] ) ? (int) $given['max'] : self::DEFAULT_MAX,
			'skip'     => isset( $given['skip'] ) ? (array) $given['skip'] : array(),
		);
		$args = (array) apply_filters( 'scout_core_no_orphans_args', $args );

		$args['elements'] = array_values( array_filter( array_map( 'strtolower', (array) $args['elements'] ), static function ( $t ) {
			return (bool) preg_match( '/^[a-z][a-z0-9]*$/', $t );
		} ) );
		$args['skip']     = array_values( array_filter( array_map( 'strval', (array) $args['skip'] ), 'strlen' ) );
		$args['max']      = max( 0, (int) $args['max'] );
		return $args;
	}

	/** Buffer front-end pages so the finished HTML can be tied. */
	public static function start() {
		if ( ! self::enabled() ) {
			return;
		}
		if ( is_admin() || is_feed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_robots() ) {
			return;
		}
		$args = self::args();
		ob_start(
			static function ( $html ) use ( $args ) {
				return ( is_string( $html ) && false !== stripos( $html, '<html' ) ) ? Scout_Core_Typography::tie( $html, $args ) : $html;
			}
		);
	}

	/**
	 * Tie the last two words of each matching element.
	 *
	 * @param string $html Page or fragment.
	 * @param array  $args From args(); missing keys take the defaults.
	 */
	public static function tie( string $html, array $args = array() ): string {
		$elements = isset( $args['elements'] ) ? (array) $args['elements'] : self::DEFAULT_ELEMENTS;
		$max      = isset( $args['max'] ) ? (int) $args['max'] : self::DEFAULT_MAX;
		$skip     = isset( $args['skip'] ) ? (array) $args['skip'] : array();
		if ( ! $elements || $max < 1 ) {
			return $html;
		}

		// Leave scripts, styles and text areas exactly as they are.
		$parts = preg_split( '#(<(script|style|textarea|pre|code|noscript)\b.*?</\2>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return $html;
		}
		$names = implode( '|', array_map( 'preg_quote', $elements ) );
		$out   = '';
		for ( $i = 0, $n = count( $parts ); $i < $n; $i++ ) {
			$chunk = $parts[ $i ];
			if ( preg_match( '#^<(script|style|textarea|pre|code|noscript)\b#i', $chunk ) ) {
				$out .= $chunk;
				$i++; // Each protected match leaves its tag name as an extra capture.
				continue;
			}
			$out .= (string) preg_replace_callback(
				'#<(' . $names . ')\b([^>]*)>(.*?)</\1>#is',
				static function ( $m ) use ( $max, $skip ) {
					return self::tie_one( $m, $max, $skip );
				},
				$chunk
			);
		}
		return $out;
	}

	/** One element: $m is the full match, the tag, its attributes, its inner HTML. */
	private static function tie_one( array $m, int $max, array $skip ): string {
		if ( $skip && preg_match( '/\bclass\s*=\s*(["\'])(.*?)\1/is', $m[2], $c ) ) {
			$classes = preg_split( '/\s+/', trim( $c[2] ) );
			foreach ( $skip as $s ) {
				foreach ( $classes as $cls ) {
					if ( '' !== $cls && false !== stripos( $cls, $s ) ) {
						return $m[0];
					}
				}
			}
		}
		$text = html_entity_decode( (string) preg_replace( '#<[^>]+>#', '', $m[3] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = trim( $text );
		// Already tied (a theme or an editor got there first): leave it.
		if ( preg_match( '/\x{00A0}\S+\s*$/u', $text ) ) {
			return $m[0];
		}
		$words = preg_split( '/\s+/u', $text );
		$count = count( $words );
		if ( $count < 3 ) {
			return $m[0];
		}
		if ( mb_strlen( $words[ $count - 2 ] . ' ' . $words[ $count - 1 ] ) > $max ) {
			return $m[0];
		}
		// The last run of spaces between words, outside any tag.
		$inner = preg_replace( '#[ \t\r\n]+(?=(?:<[^>]+>)*[^\s<>]+(?:<[^>]+>)*\s*$)#', '&nbsp;', $m[3], 1 );
		if ( null === $inner ) {
			return $m[0];
		}
		return '<' . $m[1] . $m[2] . '>' . $inner . '</' . $m[1] . '>';
	}
}

Scout_Core_Typography::init();
