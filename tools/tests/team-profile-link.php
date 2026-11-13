<?php
// Scout People (scout-team): the move to People keeps live pages the same, credential
// lines need a source to show, the business card fields (card phone, card email,
// intro, why talk to them, tags) never reach the public,
// "needs a look" names what is missing, Add a person saves like the edit screen,
// Send them a link is hashed, single use, expiring and never touches the
// live profile until approved, "Written by" credits people with or without a
// login and changes nothing when empty, and the REST API hides what visitors
// may not see. Runs without WordPress:
//   php tools/tests/people.php
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }
define( 'ABSPATH', __DIR__ . '/' );

// The few WordPress functions the code under test reaches.
$GLOBALS['meta']  = array();
$GLOBALS['opts']  = array();
$GLOBALS['thumb'] = array();
function add_action() {}
function add_filter() {}
function add_shortcode() {}
function apply_filters( $t, $v ) { return $v; }
function __( $s ) { return $s; }
function esc_html__( $s ) { return esc_html( $s ); }
function esc_html_e( $s ) { echo esc_html( $s ); }
function esc_attr_e( $s ) { echo esc_attr( $s ); }
function checked( $a, $b = true, $echo = true ) { $r = (string) $a === (string) $b ? ' checked="checked"' : ''; if ( $echo ) { echo $r; } return $r; }
function _n( $one, $many, $n ) { return 1 === (int) $n ? $one : $many; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s ) { return (string) $s; }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_email( $s ) { return filter_var( trim( (string) $s ), FILTER_VALIDATE_EMAIL ) ? trim( (string) $s ) : ''; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function absint( $n ) { return abs( (int) $n ); }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_list_pluck( $list, $field ) { return array_map( static fn( $r ) => is_object( $r ) ? $r->$field : $r[ $field ], (array) $list ); }
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function get_post_status( $id ) { return $GLOBALS['status'][ $id ] ?? 'publish'; }
function get_post_thumbnail_id( $id ) { return $GLOBALS['thumb'][ $id ] ?? 0; }
function has_post_thumbnail( $id ) { return ! empty( $GLOBALS['thumb'][ $id ] ); }
function get_the_title( $id ) { return $GLOBALS['titles'][ $id ] ?? 'Dana Lee'; }
function get_permalink( $id ) { return 'https://firm.test/team/' . ( isset( $GLOBALS['titles'][ $id ] ) ? strtolower( str_replace( ' ', '-', $GLOBALS['titles'][ $id ] ) ) : 'dana-lee' ) . '/'; }
function get_bloginfo( $k ) { return 'Firm Site'; }
function home_url( $p = '' ) { return 'https://firm.test' . $p; }
function get_the_modified_date( $f, $id ) { return '2026-11-05T00:00:00+00:00'; }
// For Add a person and Send them a link.
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) { define( 'MINUTE_IN_SECONDS', 60 ); define( 'HOUR_IN_SECONDS', 3600 ); define( 'DAY_IN_SECONDS', 86400 ); }
$GLOBALS['transients'] = array();
$GLOBALS['updated']    = array();
function update_post_meta( $id, $k, $v ) { $GLOBALS['meta'][ $id ][ $k ] = $v; return true; }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['meta'][ $id ][ $k ] ); return true; }
function wp_salt( $s = 'auth' ) { return 'test-salt-' . $s; }
function add_query_arg( $k, $v = null, $url = '' ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['transients'][ $k ] = $v; return true; }
function get_transient( $k ) { return $GLOBALS['transients'][ $k ] ?? false; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); return true; }
function get_current_user_id() { return 1; }
function current_user_can( $cap ) { return $GLOBALS['caps'][ $cap ] ?? true; }
function get_taxonomy( $t ) { return null; }
function esc_attr__( $s ) { return esc_attr( $s ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_nonce_field( $a, $n, $r = true, $echo = true ) { return '<input type="hidden" name="' . $n . '" value="nonce">'; }
function wp_date( $f, $t ) { return gmdate( 'Y-m-d', (int) $t ); }
function wp_update_post( $a ) { $GLOBALS['updated'][] = $a; return $a['ID']; }
function wp_attachment_is_image( $id ) { return 77 === (int) $id; }
function set_post_thumbnail( $id, $att ) { $GLOBALS['thumb'][ $id ] = $att; return true; }
function wp_get_post_parent_id( $id ) { return $GLOBALS['parent'][ $id ] ?? 0; }
function wp_delete_attachment( $id, $force = false ) { $GLOBALS['deleted'][] = $id; return true; }
function get_edit_post_link( $id, $c = 'display' ) { return 'https://firm.test/wp-admin/post.php?post=' . $id . '&action=edit'; }
// For Written by and the REST API.
class WP_Post { public $ID = 0; public $post_type = 'post'; public $post_status = 'publish'; public $post_author = 0; public function __construct( array $f = array() ) { foreach ( $f as $k => $v ) { $this->$k = $v; } } }
class WP_Error { public $code; public $data; public function __construct( $code = '', $msg = '', $data = array() ) { $this->code = $code; $this->data = $data; } }
class Fake_Response { private $d; public function __construct( array $d ) { $this->d = $d; } public function get_data() { return $this->d; } public function set_data( $d ) { $this->d = $d; } }
$GLOBALS['wp_posts'] = array();
function get_post( $id = null ) { return null === $id ? ( $GLOBALS['current_post'] ?? null ) : ( $GLOBALS['wp_posts'][ (int) $id ] ?? null ); }
function get_post_type( $id ) { return $GLOBALS['types'][ (int) $id ] ?? ( isset( $GLOBALS['meta'][ (int) $id ]['_scout_team'] ) ? 'scout_team' : 'post' ); }
function get_post_field( $f, $id ) { return isset( $GLOBALS['wp_posts'][ (int) $id ] ) ? $GLOBALS['wp_posts'][ (int) $id ]->$f : ''; }
function is_admin() { return ! empty( $GLOBALS['is_admin'] ); }
function is_author() { return ! empty( $GLOBALS['is_author'] ); }
function in_the_loop() { return ! empty( $GLOBALS['in_loop'] ); }
function is_singular( $t = '' ) { return isset( $GLOBALS['singular'] ) && ( '' === $t || in_array( $GLOBALS['singular'], (array) $t, true ) ); }
function get_queried_object_id() { return $GLOBALS['queried'] ?? 0; }
function count_user_posts( $user, $types = 'post', $public = false ) { $n = 0; foreach ( $GLOBALS['wp_posts'] as $p ) { if ( (int) $p->post_author === (int) $user && in_array( $p->post_type, (array) $types, true ) && 'publish' === $p->post_status ) { $n++; } } return $n; }
function get_posts( $a ) {
	$out = array();
	foreach ( $GLOBALS['meta'] as $id => $m ) {
		if ( isset( $a['meta_key'] ) && ( ! isset( $m[ $a['meta_key'] ] ) || ( isset( $a['meta_value'] ) && $m[ $a['meta_key'] ] !== $a['meta_value'] ) ) ) {
			continue;
		}
		$out[] = $id;
	}
	return $out;
}

require dirname( __DIR__, 2 ) . '/scout-team/includes/class-scout-team.php';
require dirname( __DIR__, 2 ) . '/scout-team/includes/class-scout-team-front.php';
require dirname( __DIR__, 2 ) . '/scout-team/includes/class-scout-team-schema.php';
require dirname( __DIR__, 2 ) . '/scout-team/includes/class-scout-team-changes.php';
require dirname( __DIR__, 2 ) . '/scout-team/includes/class-scout-team-editor.php';
require dirname( __DIR__, 2 ) . '/scout-team/includes/class-scout-team-link.php';
require dirname( __DIR__, 2 ) . '/scout-team/includes/class-scout-team-add.php';
require dirname( __DIR__, 2 ) . '/scout-team/includes/class-scout-team-byline.php';
$T='Scout_Team';
// Defaults unchanged.
$e=$T::empty_profile(); ok(0===$e['link_page'] && ''===$e['link_url'] && 0===$e['no_page'] && array()===$e['licenses'],'new fields default off');
ok($T::clean_link('/about/')==='/about/' && $T::clean_link('//evil.com')==='' && $T::clean_link('javascript:x')==='' && $T::clean_link('https://ex.com/a')==='https://ex.com/a','clean_link');
$GLOBALS['meta'][50]['_scout_team']=$T::sanitize_profile(array('role'=>'Owner'));
ok($T::profile_url(50)==='https://firm.test/team/dana-lee/','default: own team page');
$GLOBALS['meta'][50]['_scout_team']=$T::sanitize_profile(array('link_url'=>'/about/','shows'=>array('bio'=>1,'team'=>1)));
ok($T::profile_url(50)==='https://firm.test/about/','chosen path used');
ok($T::page_public($T::profile(50)),'page still public without No team page');
$GLOBALS['meta'][50]['_scout_team']=$T::sanitize_profile(array('link_url'=>'/about/','no_page'=>1,'shows'=>array('bio'=>1)));
ok(!$T::page_public($T::profile(50)) && $T::profile_url(50)==='https://firm.test/about/','no page: hidden, link kept');
$GLOBALS['meta'][50]['_scout_team']=$T::sanitize_profile(array('no_page'=>1,'shows'=>array('bio'=>1)));
ok($T::profile_url(50)==='','no page and no link: no address');
$GLOBALS['meta'][50]['_scout_team']=$T::sanitize_profile(array('shows'=>array('team'=>1)));
ok($T::profile_url(50)==='','bio off as before: no address');
// licenses
$p=$T::sanitize_profile(array('licenses'=>array(array('name'=>'Licensed Massage Therapist','number'=>'1234','issuer'=>'State Board','url'=>'https://board.example/1234','source'=>'board site'),array('name'=>'No source'))));
ok(2===count($p['licenses']) && 1===count($T::public_profile($T::shape($p))['licenses']),'unsourced license hidden');
$GLOBALS['meta'][51]['_scout_team']=$p;
$node=Scout_Team_Schema::person(51);
$lic=null; foreach((array)($node['hasCredential']??array()) as $c){ if(($c['identifier']??'')==='1234') $lic=$c; }
ok($lic && $lic['recognizedBy']['name']==='State Board' && $lic['credentialCategory']==='license' && $lic['url']==='https://board.example/1234','license schema '.json_encode($lic));
ok($node['url']==='https://firm.test/team/dana-lee/','schema url default');
echo $fail?"FAILED\n":"All new checks passed\n";
