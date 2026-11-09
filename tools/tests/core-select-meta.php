<?php
// Scout Core: select fields keep their value when written through
// update_post_meta / add_post_meta / REST, which run the registered
// sanitize_callback. Runs without WordPress:
//   php tools/tests/core-select-meta.php
// The same checks against a real install: tools/tests/select-meta.php.
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['meta'] = array();
function add_action() {}
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function current_user_can() { return true; }
function register_post_meta( $type, $key, $args ) { $GLOBALS['meta'][ $type ][ $key ] = $args; return true; }
require __DIR__ . '/../../scout-core/includes/controls.php';
require __DIR__ . '/../../scout-core/includes/registry.php';
require __DIR__ . '/../../scout-core/includes/fieldsets.php';
require __DIR__ . '/../../scout-core/includes/meta.php';
require __DIR__ . '/../../scout-core/includes/universal-types.php';
require __DIR__ . '/fixture-fieldset-register.php';

scout_core_register_universal_types();
scout_core_test_register_fieldset();
Scout_Core_Meta::register_all();
Scout_Core_Fieldsets::register_meta();

// What update_post_meta does: run the registered sanitize_callback.
$save = static function ( $type, $key, $value ) {
	$cb = $GLOBALS['meta'][ $type ][ $key ]['sanitize_callback'] ?? null;
	return $cb ? $cb( $value, $key, 'post' ) : 'NOT REGISTERED';
};

// Testimonial source (type registry path).
ok( 'google' === $save( 'testimonial', 'scout_source', 'google' ), 'testimonial source "google" is kept' );
ok( 'google' === $save( 'testimonial', 'scout_source', 'Google' ), 'testimonial source "Google" is stored as the key "google"' );
ok( 'yelp' === $save( 'testimonial', 'scout_source', ' YELP ' ), 'case and surrounding space are ignored' );
ok( '' === $save( 'testimonial', 'scout_source', 'myspace' ), 'a value not in the options is rejected' );
ok( '' === $save( 'testimonial', 'scout_source', array( 'google' ) ), 'a non-scalar value is rejected' );
ok( '5' === $save( 'testimonial', 'scout_rating', '5' ), 'other controls on the type are unchanged' );

// Page fieldset path.
ok( 'narrow' === $save( 'page', 'scout_test_layout', 'narrow' ), 'fieldset select "narrow" is kept' );
ok( 'wide' === $save( 'page', 'scout_test_layout', 'Wide' ), 'fieldset select "Wide" is stored as the key "wide"' );

// A sanitized value saved again stays the same (the meta box saves run it twice).
$once = $save( 'testimonial', 'scout_source', 'Facebook' );
ok( 'facebook' === $once && $once === $save( 'testimonial', 'scout_source', $once ), 'sanitizing twice gives the same key' );

// Integer-like option keys.
ok( '5' === Scout_Core_Controls::sanitize( 'select', 5, array( 5 => 'Five' ) ), 'an integer option key matches' );

exit( $fail ? 1 : 0 );
