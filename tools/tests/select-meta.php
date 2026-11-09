<?php
/**
 * Select fields must keep their value on every write path.
 *
 * Run on a fresh WordPress install with Scout Core active and
 * fixture-fieldset.php as a mu-plugin:
 *
 *   wp eval-file tools/tests/select-meta.php
 *
 * Exits non-zero on the first failure. CI: Lint workflow, "WordPress tests".
 *
 * @package Scout_Core
 */

$failures = 0;

$check = function ( $label, $expected, $actual ) use ( &$failures ) {
	if ( $expected === $actual ) {
		echo "ok    {$label}\n";
		return;
	}
	++$failures;
	echo "FAIL  {$label}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n";
};

$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $admin ? $admin[0]->ID : 1 );

/* Testimonial (type registry path, Scout_Core_Meta::register_all). */
$t = wp_insert_post( array( 'post_type' => 'testimonial', 'post_status' => 'publish', 'post_title' => 'Test author' ) );

update_post_meta( $t, 'scout_source', 'google' );
$check( 'update_post_meta keeps a valid key', 'google', get_post_meta( $t, 'scout_source', true ) );

update_post_meta( $t, 'scout_source', 'Google' );
$check( 'update_post_meta stores the canonical key for a case variant', 'google', get_post_meta( $t, 'scout_source', true ) );

update_post_meta( $t, 'scout_source', ' YELP ' );
$check( 'update_post_meta ignores case and surrounding space', 'yelp', get_post_meta( $t, 'scout_source', true ) );

update_post_meta( $t, 'scout_source', 'myspace' );
$check( 'update_post_meta rejects a value not in the options', '', get_post_meta( $t, 'scout_source', true ) );

$t2 = wp_insert_post( array( 'post_type' => 'testimonial', 'post_status' => 'publish', 'post_title' => 'Test author 2' ) );
add_post_meta( $t2, 'scout_source', 'facebook', true );
$check( 'add_post_meta keeps a valid key', 'facebook', get_post_meta( $t2, 'scout_source', true ) );

// WordPress only exposes and accepts REST meta on types that support
// custom-fields. Scout Core adds it to every registry type with fields.
$check( 'testimonial supports custom-fields', true, post_type_supports( 'testimonial', 'custom-fields' ) );
$check( 'faq (no fields) does not get custom-fields', false, post_type_supports( 'faq', 'custom-fields' ) );

$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/testimonial/' . $t2 ) );
$data     = $response->get_data();
$check( 'REST read of a testimonial includes meta', 'facebook', isset( $data['meta']['scout_source'] ) ? $data['meta']['scout_source'] : null );

$request = new WP_REST_Request( 'POST', '/wp/v2/testimonial/' . $t2 );
$request->set_body_params( array( 'meta' => array( 'scout_source' => 'direct' ) ) );
$response = rest_do_request( $request );
$check( 'REST write to a testimonial succeeds', 200, $response->get_status() );
$check( 'REST write to a testimonial keeps a valid key', 'direct', get_post_meta( $t2, 'scout_source', true ) );

$request = new WP_REST_Request( 'POST', '/wp/v2/testimonial/' . $t2 );
$request->set_body_params( array( 'meta' => array( 'scout_source' => 'Yelp' ) ) );
rest_do_request( $request );
$check( 'REST write to a testimonial stores the canonical key', 'yelp', get_post_meta( $t2, 'scout_source', true ) );

/* Non-select controls on the same type still sanitize as before. */
update_post_meta( $t, 'scout_rating', '5' );
$check( 'number field unaffected', '5', get_post_meta( $t, 'scout_rating', true ) );

/* Page fieldset path (Scout_Core_Fieldsets::register_meta). */
$p = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Test page' ) );
update_post_meta( $p, 'scout_test_layout', 'narrow' );
$check( 'fieldset select keeps a valid key', 'narrow', get_post_meta( $p, 'scout_test_layout', true ) );
update_post_meta( $p, 'scout_test_layout', 'Wide' );
$check( 'fieldset select stores the canonical key', 'wide', get_post_meta( $p, 'scout_test_layout', true ) );

$request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $p );
$request->set_body_params( array( 'meta' => array( 'scout_test_layout' => 'narrow' ) ) );
$response = rest_do_request( $request );
$check( 'REST write to a page succeeds', 200, $response->get_status() );
$check( 'REST write keeps a valid select key', 'narrow', get_post_meta( $p, 'scout_test_layout', true ) );

/* Direct sanitizer. */
$check( 'sanitize() with no options rejects', '', Scout_Core_Controls::sanitize( 'select', 'google' ) );
$check( 'non-scalar value rejected', '', Scout_Core_Controls::sanitize( 'select', array( 'google' ), array( 'google' => 'Google' ) ) );

wp_delete_post( $t, true );
wp_delete_post( $t2, true );
wp_delete_post( $p, true );

if ( $failures ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}
echo "\nall passed\n";
