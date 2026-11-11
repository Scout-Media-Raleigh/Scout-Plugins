<?php
// Stub harness for Scout Forms Guard's List-field check (layer 5, CVE-2026-103039).
// Run: php tools/tests/forms-guard-serialized.php
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
$GLOBALS['t'] = array( 'filters' => array() );
function add_filter( ...$a ) {}
function add_action( ...$a ) {}
function apply_filters( $tag, $value, ...$a ) { return isset( $GLOBALS['t']['filters'][ $tag ] ) ? $GLOBALS['t']['filters'][ $tag ] : $value; }
function do_action( ...$a ) {}
function wp_unslash( $v ) { return $v; }
function home_url() { return 'https://example.test/'; }
function wp_parse_url( $u, $c ) { return parse_url( $u, $c ); }
function get_option( $k, $d = false ) { return $d; }
function update_option( ...$a ) {}
function wp_date( $f ) { return gmdate( $f ); }
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_basename( $f ) { return basename( $f ); }
function register_activation_hook( ...$a ) {}
function is_admin() { return false; }
require dirname( __DIR__, 2 ) . '/scout-forms-guard/scout-forms-guard.php';
$fail = 0;
function ok( $c, $m ) { global $fail; echo ( $c ? 'PASS ' : 'FAIL ' ) . $m . "\n"; if ( ! $c ) { $fail++; } }
$G = 'Scout_Forms_Guard';

$bad = array(
	'O:8:"stdClass":0:{}',
	'a:1:{i:0;O:8:"Evil_Gad":1:{s:1:"x";s:1:"y";}}',
	'prefix;O:3:"Foo":0:{}',
	'C:11:"ArrayObject":0:{}',
	'O:__PHP_Incomplete_Class:1',
);
$good = array(
	'Panel upgrade',
	'2 outlets, 1 switch',
	'Ordered 3 of item no. 4:15',
	'Open at 9:30',
	'Size: 10:1 ratio',
	'Colonel: a fine man',
	'',
);
foreach ( $bad as $b ) { ok( $G::has_serialized( $b ), 'serialized: ' . substr( $b, 0, 40 ) ); }
foreach ( $good as $g ) { ok( ! $G::has_serialized( $g ), 'plain: ' . substr( $g, 0, 40 ) ); }
ok( $G::has_serialized( array( array( 'Item', 'O:8:"stdClass":0:{}' ) ) ), 'nested rows are checked' );
ok( ! $G::has_serialized( array( array( 'Item', '2' ), array( 'Item 2', '4' ) ) ), 'normal multi-column rows pass' );
ok( ! $G::has_serialized( 12 ), 'a number passes' );

$list   = (object) array( 'id' => 7, 'type' => 'list', 'inputType' => '' );
$pcf    = (object) array( 'id' => 8, 'type' => 'post_custom_field', 'inputType' => 'list' );
$text   = (object) array( 'id' => 9, 'type' => 'text', 'inputType' => '' );
$form   = array( 'id' => 1, 'fields' => array( $list, $pcf, $text ) );
ok( $G::list_payload_is_unsafe( $form, array( 'input_7' => array( 'O:8:"stdClass":0:{}' ) ) ), 'a List field with an object is refused' );
ok( $G::list_payload_is_unsafe( $form, array( 'input_8' => array( 'a:1:{i:0;s:1:"x";}' ) ) ), 'a Post Custom Field set to List is refused' );
ok( ! $G::list_payload_is_unsafe( $form, array( 'input_9' => 'O:8:"stdClass":0:{}' ) ), 'a text field is not this check (not a List)' );
ok( ! $G::list_payload_is_unsafe( $form, array( 'input_7' => array( 'Panel', 'Switch' ), 'input_9' => 'hello' ) ), 'normal List answers pass' );
ok( ! $G::list_payload_is_unsafe( $form, array() ), 'no input passes' );
$GLOBALS['t']['filters']['scout_forms_guard_block_serialized'] = false;
ok( ! $G::list_payload_is_unsafe( $form, array( 'input_7' => array( 'O:8:"stdClass":0:{}' ) ) ), 'the off switch lets it through' );
exit( $fail ? 1 : 0 );
