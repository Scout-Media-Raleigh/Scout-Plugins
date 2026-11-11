<?php
/**
 * Scout Core 1.15.0 map: the place comes from Business info, nothing loads from
 * Google before the tap, and the inner picture stays. No WordPress needed.
 * Run: php tools/tests/core-map.php
 */
define( 'ABSPATH', __DIR__ );
$GLOBALS['biz'] = array();
function add_shortcode( $t, $c ) {}
function shortcode_atts( $d, $a ) { return array_merge( $d, array_intersect_key( $a, $d ) ); }
function do_shortcode( $c ) { return $c; }
function esc_url( $u ) { return htmlspecialchars( $u, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function get_bloginfo( $k ) { return 'Site'; }
function scout_core_business() { return $GLOBALS['biz']; }
require dirname( __DIR__, 2 ) . '/scout-core/includes/map.php';

$fail = 0;
function check( $ok, $what ) { global $fail; echo ( $ok ? 'ok   ' : 'FAIL ' ) . $what . "\n"; if ( ! $ok ) { $fail++; } }

check( '' === Scout_Core_Map::shortcode( array(), null ), 'no business facts: prints nothing' );
check( '<img>' === Scout_Core_Map::shortcode( array(), '<img>' ), 'no business facts: keeps the picture' );

$GLOBALS['biz'] = array( 'name' => 'Chicago Prime Steakhouse', 'street' => '1444 E. Algonquin Rd', 'city' => 'Schaumburg', 'region' => 'IL', 'postal' => '60173' );
check( 'Chicago Prime Steakhouse, 1444 E. Algonquin Rd, Schaumburg, IL 60173' === Scout_Core_Map::place(), 'place from the address' );
$out = Scout_Core_Map::shortcode( array(), '<img src="x.webp" alt="">' );
check( false !== strpos( $out, '<img src="x.webp"' ), 'inner picture kept' );
check( false !== strpos( $out, 'data-scout-map="https://www.google.com/maps?q=' ), 'embed address waits in a data attribute' );
check( false === strpos( $out, '<iframe' ), 'no iframe before the tap' );
check( false !== strpos( $out, 'data-scout-track="map_open"' ), 'tap is tracked' );
check( false !== strpos( $out, 'maps/search/?api=1&amp;query=' ), 'button works as a plain link' );
check( 1 === substr_count( $out, 'scout-map-js' ), 'script printed once' );
check( false === strpos( Scout_Core_Map::shortcode( array(), '' ), 'scout-map-js' ), 'script not printed twice' );
check( false !== strpos( Scout_Core_Map::shortcode( array(), '' ), 'scout-map-plain' ), 'empty shortcode gets the plain panel' );

$GLOBALS['biz'] = array( 'name' => 'X', 'latitude' => '42.05', 'longitude' => '-88.03' );
check( '42.05,-88.03' === Scout_Core_Map::place(), 'place from latitude and longitude when no street' );

exit( $fail ? 1 : 0 );
