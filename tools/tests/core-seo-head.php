<?php
// Scout Core: the head's title, description and canonical on lists and error pages. Runs without WordPress:
//   php tools/tests/core-seo-head.php
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }
define( 'ABSPATH', __DIR__ . '/' );
define( 'SCOUT_SEO_META_TITLE', '_scout_seo_title' );
define( 'SCOUT_SEO_META_DESC', '_scout_seo_desc' );
define( 'SCOUT_SEO_META_CANONICAL', '_scout_seo_canonical' );
define( 'SCOUT_SEO_META_NOINDEX', '_scout_seo_noindex' );

$GLOBALS['view'] = array();
$GLOBALS['meta'] = array();
$GLOBALS['opt']  = array( 'page_for_posts' => 7, 'permalink_structure' => '/%postname%/' );
function v( $k ) { return ! empty( $GLOBALS['view'][ $k ] ); }
function add_action() {}
function add_filter() {}
function remove_action() {}
function is_singular() { return v( 'singular' ); }
function is_front_page() { return v( 'front' ); }
function is_home() { return v( 'home' ); }
function is_404() { return v( '404' ); }
function is_search() { return v( 'search' ); }
function is_category() { return v( 'category' ); }
function is_tag() { return false; }
function is_tax() { return false; }
function is_post_type_archive() { return false; }
function get_queried_object_id() { return $GLOBALS['view']['id'] ?? 0; }
function get_queried_object() { return (object) array( 'term_id' => 3 ); }
function get_term_link( $t ) { return 'https://venue.test/blog/category/weddings/'; }
function is_wp_error( $x ) { return false; }
function get_query_var( $k ) { return 'paged' === $k ? ( $GLOBALS['view']['paged'] ?? 0 ) : ''; }
function get_option( $k ) { return $GLOBALS['opt'][ $k ] ?? ''; }
function get_permalink( $id ) { return 7 === $id ? 'https://venue.test/blog/' : 'https://venue.test/p' . $id . '/'; }
function home_url( $p = '' ) { return 'https://venue.test' . $p; }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function user_trailingslashit( $s ) { return trailingslashit( $s ); }
function add_query_arg( $k, $v, $u ) { return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . $k . '=' . $v; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function has_excerpt( $id ) { return false; }
function get_post( $id ) { return (object) array( 'ID' => $id, 'post_content' => '' ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function strip_shortcodes( $s ) { return $s; }
function wp_trim_words( $s ) { return $s; }
function term_description() { return 'Wedding guides.'; }
function get_bloginfo( $k ) { return 'Site tagline'; }
require __DIR__ . '/../../scout-core/includes/seo/class-seo-head.php';

$call = function ( $name, ...$args ) {
	$m = new ReflectionMethod( 'Scout_SEO_Head', $name );
	$m->setAccessible( true );
	return $m->invoke( null, ...$args );
};

// The posts page (Settings, Reading) uses its own page's SEO title and description.
$GLOBALS['meta'][7] = array( SCOUT_SEO_META_TITLE => 'Planning Blog | Venue', SCOUT_SEO_META_DESC => 'Planning guides from the venue.' );
$GLOBALS['view']    = array( 'home' => 1 );
ok( 'Planning guides from the venue.' === $call( 'description' ), 'the blog list uses the posts page description, not the site tagline' );
ok( 'Planning Blog | Venue' === Scout_SEO_Head::document_title( 'Blog - Venue' ), 'the blog list uses the posts page SEO title' );
ok( 'https://venue.test/blog/' === $call( 'canonical' ), 'page one of the blog points at /blog/' );

// Page 2 and on point at themselves and say which page they are.
$GLOBALS['view'] = array( 'home' => 1, 'paged' => 2 );
ok( 'https://venue.test/blog/page/2/' === $call( 'canonical' ), 'page 2 of the blog points at itself' );
ok( 'Planning Blog | Venue, page 2' === Scout_SEO_Head::document_title( 'x' ), 'page 2 of the blog has its own title' );
$GLOBALS['view'] = array( 'category' => 1, 'paged' => 3 );
ok( 'https://venue.test/blog/category/weddings/page/3/' === $call( 'canonical' ), 'page 3 of a topic points at itself' );
$GLOBALS['opt']['permalink_structure'] = '';
ok( 'https://venue.test/blog/category/weddings/?paged=3' === $call( 'canonical' ), 'without pretty links, the page number goes in the address' );
$GLOBALS['opt']['permalink_structure'] = '/%postname%/';

// Error and search pages have no canonical rather than pointing at home.
$GLOBALS['view'] = array( '404' => 1 );
ok( '' === $call( 'canonical' ), 'a not-found page prints no canonical' );
$GLOBALS['view'] = array( 'search' => 1 );
ok( '' === $call( 'canonical' ), 'a search results page prints no canonical' );

// Nothing else moved.
$GLOBALS['view'] = array( 'front' => 1, 'home' => 1 );
ok( 'https://venue.test/' === $call( 'canonical' ), 'the home page still points at /' );
ok( 'Site tagline' === $call( 'description' ), 'a home page that is the blog keeps the site description' );
$GLOBALS['view'] = array( 'singular' => 1, 'id' => 9 );
$GLOBALS['meta'][9] = array( SCOUT_SEO_META_TITLE => 'A post' );
ok( 'https://venue.test/p9/' === $call( 'canonical' ) && 'A post' === Scout_SEO_Head::document_title( 'x' ), 'a post keeps its own canonical and title' );
$GLOBALS['view'] = array( 'singular' => 1, 'id' => 9, 'paged' => 2 );
ok( 'https://venue.test/p9/' === $call( 'canonical' ) && 'A post' === Scout_SEO_Head::document_title( 'x' ), 'a post split into pages keeps one canonical and title' );

echo $fail ? "\n$fail failing\n" : "\nAll passed\n";
exit( $fail ? 1 : 0 );
