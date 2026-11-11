<?php
/**
 * Map: a Google map of the business that loads only when a visitor taps it.
 *
 * `[scout_map]` prints a "Show the map" button over whatever the theme puts
 * inside the shortcode (a photo of the building, a drawn map, or nothing). The
 * Google map loads in place on the tap, so the page stays fast and Google's
 * cookies reach only visitors who asked for the map. Without JavaScript, or
 * before the tap, the button is a link that opens Google Maps.
 *
 * The address comes from Business info (street, city, state, postal), or the
 * latitude and longitude when both are set. Nothing to type twice.
 *
 *   [scout_map]                         a button on a plain panel
 *   [scout_map]<img ...>[/scout_map]    the button over the theme's own picture
 *   [scout_map label="Show the map"]    the button's words
 *
 * The look comes from DESIGN.md through the `--scout-*` variables, and a theme
 * can restyle `.scout-map` and `.scout-map-show`. Taps send `map_open` to
 * Scout Analytics through `data-scout-track`.
 *
 * @package Scout_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scout_Core_Map {

	/** @var bool Whether the script and styles were printed on this page. */
	private static $printed = false;

	public static function init(): void {
		add_shortcode( 'scout_map', array( __CLASS__, 'shortcode' ) );
	}

	/** The place Google should show: "lat,lng" when both are set, else the address. */
	public static function place(): string {
		$b   = function_exists( 'scout_core_business' ) ? (array) scout_core_business() : array();
		$lat = trim( (string) ( $b['latitude'] ?? '' ) );
		$lng = trim( (string) ( $b['longitude'] ?? '' ) );
		$name = trim( (string) ( $b['name'] ?? '' ) );
		$addr = trim( implode( ', ', array_filter( array( $b['street'] ?? '', $b['city'] ?? '', trim( ( $b['region'] ?? '' ) . ' ' . ( $b['postal'] ?? '' ) ) ) ) ) );
		if ( '' !== $addr ) {
			return trim( $name . ', ' . $addr, ', ' );
		}
		return ( is_numeric( $lat ) && is_numeric( $lng ) ) ? $lat . ',' . $lng : $name;
	}

	/**
	 * @param array|string $atts    Shortcode attributes.
	 * @param string|null  $content Anything the theme wraps (a picture).
	 */
	public static function shortcode( $atts, $content = null ): string {
		$atts  = shortcode_atts( array( 'label' => 'Show the map' ), (array) $atts, 'scout_map' );
		$place = self::place();
		$inner = null === $content ? '' : do_shortcode( (string) $content );
		if ( '' === $place ) {
			return $inner;
		}
		$q     = rawurlencode( $place );
		$embed = 'https://www.google.com/maps?q=' . $q . '&output=embed';
		$open  = 'https://www.google.com/maps/search/?api=1&query=' . $q;
		$b     = function_exists( 'scout_core_business' ) ? (array) scout_core_business() : array();
		$title = 'Map of ' . ( $b['name'] ?? get_bloginfo( 'name' ) );

		$html  = '<div class="scout-map' . ( '' === trim( $inner ) ? ' scout-map-plain' : '' ) . '" data-scout-map="' . esc_url( $embed ) . '" data-scout-map-title="' . esc_attr( $title ) . '">';
		$html .= $inner;
		$html .= '<a class="scout-map-show" href="' . esc_url( $open ) . '" data-scout-track="map_open" target="_blank" rel="noopener">' . esc_html( $atts['label'] ) . '</a>';
		$html .= '</div>';
		return $html . self::assets();
	}

	/** Printed once per page, the first time the shortcode is used. Small enough to inline. */
	private static function assets(): string {
		if ( self::$printed ) {
			return '';
		}
		self::$printed = true;
		$css = '.scout-map{position:relative;overflow:hidden}'
			. '.scout-map-plain{min-height:320px;background:var(--scout-surface-2,#EDEDF0)}'
			. '.scout-map iframe{display:block;width:100%;height:100%;min-height:320px;border:0}'
			. '.scout-map.is-on>:not(iframe){display:none}'
			. '.scout-map-show{position:absolute;left:16px;bottom:16px;display:inline-flex;align-items:center;min-height:44px;padding:10px 18px;'
			. 'background:var(--scout-accent-fill,#0A78C2);color:var(--scout-on-accent,#fff);font:600 14px/1.2 var(--scout-font-label,system-ui,sans-serif);text-decoration:none;border-radius:var(--scout-radius-control,8px)}'
			. '.scout-map-show:focus-visible{outline:2px solid var(--scout-accent,#0E8FE6);outline-offset:3px}';
		$js = "document.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('.scout-map-show');if(!a)return;"
			. "var m=a.closest('.scout-map');if(!m||m.classList.contains('is-on'))return;e.preventDefault();"
			. "var h=m.offsetHeight;var f=document.createElement('iframe');f.src=m.getAttribute('data-scout-map');f.title=m.getAttribute('data-scout-map-title')||'Map';"
			. "f.setAttribute('allowfullscreen','');f.setAttribute('referrerpolicy','no-referrer-when-downgrade');if(h>200)f.style.height=h+'px';"
			. "m.appendChild(f);m.classList.add('is-on');f.focus();});";
		return '<style id="scout-map-css">' . $css . '</style><script id="scout-map-js">' . $js . '</script>';
	}
}
