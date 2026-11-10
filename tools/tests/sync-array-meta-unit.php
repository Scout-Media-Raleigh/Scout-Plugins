<?php
// Site Sync array meta (Scout Core 1.12.0), without WordPress: a `fields` entry whose value
// is a list is compared as canonical JSON and written to post meta as a real array, and every
// scalar path is unchanged. The same cases against a real install: tools/tests/sync-array-meta.php.
//   php tools/tests/sync-array-meta-unit.php
define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'SCOUT_SEO_META_TITLE', '_scout_seo_title' );
define( 'SCOUT_SEO_META_DESC', '_scout_seo_description' );
define( 'SCOUT_SEO_META_NOINDEX', '_scout_seo_noindex' );
$GLOBALS['opts']  = array();
$GLOBALS['trans'] = array();
$GLOBALS['meta']  = array(); // post ID => key => serialized value, as the database holds it.
$GLOBALS['posts'] = array();
$GLOBALS['dir']   = sys_get_temp_dir() . '/scout-sync-unit-' . getmypid();
@mkdir( $GLOBALS['dir'] );

final class WP_Error {
	private $code;
	private $msg;
	public function __construct( $code = '', $msg = '' ) { $this->code = $code; $this->msg = $msg; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->msg; }
}
final class WP_Theme { public function get( $k ) { return '1.0.0'; } }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function add_action() {}
function do_action() {}
function apply_filters( $hook, $value ) { return 'scout_sync_manifest_path' === $hook ? $GLOBALS['dir'] . '/scout-sync.json' : $value; }
function trailingslashit( $p ) { return rtrim( $p, '/' ) . '/'; }
function get_stylesheet_directory() { return $GLOBALS['dir']; }
function get_stylesheet() { return 'synctest'; }
function wp_get_theme() { return new WP_Theme(); }
function home_url() { return 'http://example.test'; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_title( $t ) { return sanitize_key( $t ); }
function sanitize_email( $e ) { return (string) $e; }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function current_user_can() { return true; }
function get_current_user_id() { return 1; }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function wp_slash( $v ) { return is_array( $v ) ? array_map( 'wp_slash', $v ) : ( is_string( $v ) ? addslashes( $v ) : $v ); }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function add_option( $k, $v, $d = '', $a = null ) { if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; } $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_transient( $k ) { return $GLOBALS['trans'][ $k ] ?? false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['trans'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['trans'][ $k ] ); return true; }
function get_posts( $args ) {
	$out = array();
	foreach ( $GLOBALS['posts'] as $id => $p ) {
		if ( $p->post_name === $args['name'] && $p->post_type === $args['post_type'] ) {
			$out[] = $id;
		}
	}
	return $out;
}
function get_page_by_path() { return null; }
function get_post( $id ) { return $GLOBALS['posts'][ $id ] ?? null; }
function wp_update_post( $data ) { foreach ( $data as $k => $v ) { if ( 'ID' !== $k ) { $GLOBALS['posts'][ $data['ID'] ]->$k = wp_unslash( $v ); } } return $data['ID']; }
// Post meta the way WordPress stores it: unslashed, then serialized when not a string.
function update_post_meta( $id, $key, $value ) { $value = wp_unslash( $value ); $GLOBALS['meta'][ $id ][ $key ] = is_string( $value ) ? $value : serialize( $value ); return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $id ][ $key ] ); return true; }
function get_post_meta( $id, $key, $single = false ) {
	if ( ! isset( $GLOBALS['meta'][ $id ][ $key ] ) ) {
		return '';
	}
	$raw = $GLOBALS['meta'][ $id ][ $key ];
	$val = @unserialize( $raw );
	return false === $val && 'b:0;' !== $raw ? $raw : $val;
}
function scout_core_meta_key( $f ) { return 'scout_' . $f; }

require dirname( __DIR__, 2 ) . '/scout-core/includes/sync/class-scout-sync.php';

$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }
function manifest( array $data ) { file_put_contents( $GLOBALS['dir'] . '/scout-sync.json', json_encode( $data ) ); $GLOBALS['trans'] = array(); }
function rows() { $o = array(); foreach ( Scout_Sync::plan()['rows'] as $r ) { $o[ $r['id'] ] = $r; } return $o; }
function state( $id ) { return rows()[ $id ]['state'] ?? 'absent'; }
function auto() { return Scout_Sync::apply( '', 'safe', array(), 'auto' ); }
function page( $id, $slug ) { $GLOBALS['posts'][ $id ] = (object) array( 'ID' => $id, 'post_name' => $slug, 'post_type' => 'page', 'post_title' => ucfirst( $slug ), 'post_content' => '' ); }

page( 10, 'menu' );
page( 11, 'about' );
$id   = 'page/menu#meta:_scout_aeo_faqs';
$v1   = array(
	array( 'q' => 'Do you take reservations?', 'a' => 'Yes, by phone or online.' ),
	array( 'q' => 'Is there parking?', 'a' => 'Free, behind the building. Valet "on weekends" \\ only.' ),
);
$data = array(
	'sync_version' => 1,
	'fields'       => array(
		array( 'post_type' => 'page', 'slug' => 'menu', 'meta' => '_scout_aeo_faqs', 'value' => $v1 ),
		array( 'slug' => 'about', 'field' => 'hero_heading', 'value' => 'Heading v1' ),
		array( 'slug' => 'about', 'meta' => '_scout_extra', 'key' => 'note', 'value' => 'A note' ),
		array( 'slug' => 'about', 'seo' => 'desc', 'value' => array( 'not', 'text' ) ),
	),
);
manifest( $data );

$r = rows();
ok( 'update' === $r[ $id ]['state'], 'an empty FAQ field is a safe update' );
ok( 'Array' !== $r[ $id ]['theme'] && json_decode( $r[ $id ]['theme'], true ) === $v1, 'the theme copy is canonical JSON, not "Array"' );
ok( ! empty( $r[ $id ]['array'] ) && empty( $r['page/about#field:hero_heading']['array'] ), 'only the list row is marked as an array' );
ok( ! isset( $r['page/about#seo:desc'] ) && 1 === count( Scout_Sync::plan()['errors'] ), 'a list on an SEO field is refused and reported' );
ok( Scout_Sync::canon( array( 'b' => 1, 'a' => array( 'z' => 1, 'y' => 2 ) ) ) === Scout_Sync::canon( array( 'a' => array( 'y' => 2, 'z' => 1 ), 'b' => 1 ) ), 'canonical JSON sorts object keys at every level' );
ok( Scout_Sync::canon( array( 'x', 'y' ) ) !== Scout_Sync::canon( array( 'y', 'x' ) ), 'and keeps list order' );
ok( '' === Scout_Sync::canon( array() ) && '' === Scout_Sync::json( array() ), 'an empty list is the same as no field' );

$res = auto();
ok( ! is_wp_error( $res ) && 3 === $res['written'], 'three rows written: ' . ( is_array( $res ) ? $res['written'] : $res->get_error_message() ) );
ok( get_post_meta( 10, '_scout_aeo_faqs', true ) === $v1, 'the FAQ rows are stored as a real array, backslashes intact' );
ok( 0 === strpos( $GLOBALS['meta'][10]['_scout_aeo_faqs'], 'a:2:' ), 'serialized as an array in the database, not the word "Array"' );
ok( 'Heading v1' === get_post_meta( 11, 'scout_hero_heading', true ) && array( 'note' => 'A note' ) === get_post_meta( 11, '_scout_extra', true ), 'scalar fields and scalar keys write as before' );
ok( 'current' === state( $id ) && 'current' === state( 'page/about#field:hero_heading' ), 'everything reads current afterwards' );
ok( 0 === auto()['written'], 'a second run writes nothing' );

update_post_meta( 10, '_scout_aeo_faqs', wp_slash( array_map( static function ( $x ) { return array( 'a' => $x['a'], 'q' => $x['q'] ); }, $v1 ) ) );
ok( 'current' === state( $id ), 'the same rows saved with keys in another order read current' );

$v2                         = array_merge( $v1, array( array( 'q' => 'Kids menu?', 'a' => 'Yes.' ) ) );
$data['fields'][0]['value'] = $v2;
manifest( $data );
ok( 'update' === state( $id ), 'a new question in the theme is a safe update' );
auto();
ok( get_post_meta( 10, '_scout_aeo_faqs', true ) === $v2, 'and syncs' );
$u = Scout_Sync::undo();
ok( 1 === $u['restored'] && Scout_Sync::canon( get_post_meta( 10, '_scout_aeo_faqs', true ) ) === Scout_Sync::canon( $v1 ), 'undo puts the earlier rows back as an array' );
ok( 'conflict' === state( $id ), 'after undo the rows wait for a person, as every undone row does' );
Scout_Sync::apply( Scout_Sync::plan()['hash'], 'take', array( $id ) );

$edited         = $v2;
$edited[0]['a'] = 'Yes. Call us.';
update_post_meta( 10, '_scout_aeo_faqs', wp_slash( $edited ) );
ok( 'edited' === state( $id ), 'a WordPress edit reads edited' );
auto();
ok( get_post_meta( 10, '_scout_aeo_faqs', true ) === $edited, 'a safe run leaves it alone' );
$k = Scout_Sync::apply( Scout_Sync::plan()['hash'], 'keep', array( $id ) );
ok( ! is_wp_error( $k ) && 'current' === state( $id ), 'keep my edit clears the flag' );
$v3                         = $v2;
$v3[2]['a']                 = 'Yes, under 12s.';
$data['fields'][0]['value'] = $v3;
manifest( $data );
ok( 'conflict' === state( $id ), 'a kept edit is a conflict when the theme moves on' );
auto();
ok( get_post_meta( 10, '_scout_aeo_faqs', true ) === $edited, 'and is never replaced automatically' );
Scout_Sync::apply( Scout_Sync::plan()['hash'], 'take', array( $id ) );
ok( get_post_meta( 10, '_scout_aeo_faqs', true ) === $v3, 'take writes the theme rows' );
Scout_Sync::undo();
ok( get_post_meta( 10, '_scout_aeo_faqs', true ) === $edited, 'undo after take restores the kept edit' );

update_post_meta( 10, '_scout_aeo_faqs', 'old text' );
ok( 'conflict' === state( $id ), 'a plain saved value against a list is a conflict' );
Scout_Sync::apply( Scout_Sync::plan()['hash'], 'take', array( $id ) );
Scout_Sync::undo();
ok( 'old text' === get_post_meta( 10, '_scout_aeo_faqs', true ), 'undo restores the plain text' );

delete_post_meta( 10, '_scout_aeo_faqs' );
delete_option( Scout_Sync::BASES );
auto();
ok( get_post_meta( 10, '_scout_aeo_faqs', true ) === $v3, 'a fresh field fills' );
Scout_Sync::undo();
ok( ! isset( $GLOBALS['meta'][10]['_scout_aeo_faqs'] ), 'undo removes a field the sync added' );

$data['fields'][0]['value'] = array();
manifest( $data );
ok( 'current' === state( $id ), 'an empty list in the theme matches no field' );

$data['fields'][0]['value'] = $v3;
$data['fields'][]           = array( 'slug' => 'about', 'meta' => '_scout_extra', 'key' => 'tags', 'value' => array( 'steak', 'wine' ) );
manifest( $data );
auto();
ok( array( 'note' => 'A note', 'tags' => array( 'steak', 'wine' ) ) === get_post_meta( 11, '_scout_extra', true ), 'a list under one key is planted, the other key left alone' );
ok( 'current' === state( 'page/about#meta:_scout_extra.tags' ), 'and reads current' );

// Scout AI Search reads the synced rows and adds FAQPage to Scout Core's graph, the node
// Scout Build's Direct answers looks for in the page's HTML.
function is_singular() { return true; }
function get_queried_object_id() { return 10; }
function get_permalink( $id ) { return 'http://example.test/menu/'; }
function get_post_field( $f, $id ) { return $GLOBALS['posts'][ $id ]->$f ?? ''; }
function wp_strip_all_tags( $t ) { return strip_tags( (string) $t ); }
function get_the_date() { return '2026-11-10T00:00:00+00:00'; }
function get_the_modified_date() { return '2026-11-10T00:00:00+00:00'; }
function add_shortcode() {}
function add_filter() {}
require dirname( __DIR__, 2 ) . '/scout-aeo/includes/class-scout-aeo-faq.php';
$graph = Scout_AEO_FAQ::add_to_graph( array() );
$html  = json_encode( array( '@context' => 'https://schema.org', '@graph' => array_values( $graph ) ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
ok( 'FAQPage' === ( $graph['aeo_faq']['@type'] ?? '' ) && 3 === count( $graph['aeo_faq']['mainEntity'] ?? array() ), 'Scout AI Search adds FAQPage with the three synced questions' );
ok( (bool) preg_match( '/"@type"\s*:\s*(\[[^\]]*)?"FAQPage"/', $html ), 'and the graph matches the pattern Direct answers counts' );

array_map( 'unlink', glob( $GLOBALS['dir'] . '/*' ) );
rmdir( $GLOBALS['dir'] );
echo $fail ? "\n$fail FAILED\n" : "\nALL PASSED\n";
exit( $fail ? 1 : 0 );
