<?php
// Schema names are plain text, never HTML entities (Scout Core 1.10.5, Scout AI Search 0.2.12).
// get_the_title() runs wptexturize, so "New Year's Eve" came out of the JSON-LD as
// "New Year&#8217;s Eve". Runs without WordPress:
//   php tools/tests/core-schema-plain-text.php
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }
define( 'ABSPATH', __DIR__ . '/' );

// What WordPress does to a title or content on display: curly quotes as entities.
function texturize( $s ) {
	return str_replace( array( "'", '"' ), array( '&#8217;', '&#8221;' ), (string) $s );
}

// Posts as stored. Titles are what the editor typed; WordPress stores & as &amp;.
$GLOBALS['posts'] = array(
	10 => array( 'post_title' => "New Year's Eve", 'post_type' => 'page', 'post_name' => 'new-years-eve', 'post_content' => '' ),
	11 => array( 'post_title' => 'Rooms &amp; "Suites"', 'post_type' => 'page', 'post_name' => 'rooms', 'post_content' => '' ),
	12 => array( 'post_title' => 'FAQ', 'post_type' => 'page', 'post_name' => 'faq', 'post_content' => '' ),
	13 => array( 'post_title' => "Chef's <em>picks</em>", 'post_type' => 'post', 'post_name' => 'chefs-picks', 'post_content' => '' ),
	20 => array( 'post_title' => "What's the dress code?", 'post_type' => 'faq', 'post_name' => 'dress', 'post_content' => "It's smart casual." ),
);
$GLOBALS['meta']    = array( 10 => array( '_scout_aeo_faqs' => array( array( 'q' => 'Is there a countdown?', 'a' => 'Yes, at midnight.' ) ) ),
	12 => array( '_scout_aeo_faqs' => array( array( 'q' => "What's the dress code?", 'a' => 'Smart casual.' ), array( 'q' => 'Is parking free?', 'a' => 'Yes.' ) ) ) );
$GLOBALS['current'] = 0;
$GLOBALS['filters'] = array();

class WP_Post {
	public $ID;
	public $post_content;
	public function __construct( $id ) { $this->ID = $id; $this->post_content = $GLOBALS['posts'][ $id ]['post_content']; }
}
class WP_Query {
	public $posts = array();
	public function __construct( $args ) {
		foreach ( $GLOBALS['posts'] as $id => $p ) {
			if ( $p['post_type'] === $args['post_type'] ) {
				$this->posts[] = new WP_Post( $id );
			}
		}
	}
}
function post_id( $post ) { return $post instanceof WP_Post ? $post->ID : (int) $post; }
function add_filter( $tag, $cb, $prio = 10 ) { $GLOBALS['filters'][ $tag ][ $prio ][] = $cb; }
function add_action() {}
function add_shortcode() {}
function apply_filters( $tag, $value ) {
	if ( 'the_content' === $tag ) {
		return '<p>' . texturize( $value ) . '</p>';
	}
	$hooks = $GLOBALS['filters'][ $tag ] ?? array();
	ksort( $hooks );
	foreach ( $hooks as $cbs ) {
		foreach ( $cbs as $cb ) {
			$value = $cb( $value );
		}
	}
	return $value;
}
function get_the_title( $post ) { return texturize( $GLOBALS['posts'][ post_id( $post ) ]['post_title'] ); }
function get_post_field( $field, $post ) { return $GLOBALS['posts'][ post_id( $post ) ][ $field ] ?? ''; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function get_queried_object_id() { return $GLOBALS['current']; }
function get_the_ID() { return $GLOBALS['current']; }
function is_singular( $type = '' ) { $p = $GLOBALS['posts'][ $GLOBALS['current'] ] ?? null; return $p && ( '' === $type || $type === $p['post_type'] ); }
function is_page() { return is_singular( 'page' ); }
function is_post_type_archive() { return false; }
function is_admin() { return false; }
function is_feed() { return false; }
function get_permalink( $post ) { return 'https://venue.test/' . get_post_field( 'post_name', $post ) . '/'; }
function home_url( $p = '' ) { return 'https://venue.test' . $p; }
function get_post_type_archive_link() { return false; }
function get_the_date() { return '2026-11-01T00:00:00+00:00'; }
function get_the_modified_date() { return '2026-11-09T00:00:00+00:00'; }
function has_excerpt() { return false; }
function get_the_excerpt() { return ''; }
function has_post_thumbnail() { return false; }
function get_the_author_meta() { return 'Joy'; }
// WordPress stores the site title escaped: an apostrophe comes back as &#039;.
function get_bloginfo() { return 'Joe&#039;s Example Venue'; }
function get_site_icon_url() { return ''; }
function wp_reset_postdata() {}
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function __( $s ) { return $s; }
function scout_core_business() {
	return array_fill_keys( array( 'business_type', 'name', 'legal_name', 'phone', 'email', 'price_range', 'street', 'city', 'region', 'postal', 'country', 'latitude', 'longitude', 'same_as' ), '' );
}

require __DIR__ . '/../../scout-core/includes/schema/class-scout-schema.php';
require __DIR__ . '/../../scout-core/includes/seo/class-seo-breadcrumbs.php';
require __DIR__ . '/../../scout-aeo/includes/class-scout-aeo-faq.php';
Scout_AEO_FAQ::boot();

/** Render the head graph for a post, as the JSON text and the decoded nodes. */
function graph_for( $id ) {
	$GLOBALS['current'] = $id;
	ob_start();
	Scout_Schema::render();
	$out  = ob_get_clean();
	$json = trim( preg_replace( '#^\s*<script type="application/ld\+json">|</script>\s*$#', '', $out ) );
	$data = json_decode( $json, true );
	$by   = array();
	foreach ( (array) ( $data['@graph'] ?? array() ) as $node ) {
		$by[ (string) $node['@type'] ][] = $node;
	}
	return array( $json, $by );
}
function no_entities( $json ) { return 0 === preg_match( '/&#?[a-z0-9]+;/i', $json ); }

// The reported case: a page with an apostrophe and its own questions (FAQPage stands for the page).
list( $json, $by ) = graph_for( 10 );
ok( isset( $by['FAQPage'][0] ) && "New Year's Eve" === $by['FAQPage'][0]['name'], 'the FAQPage for "New Year\'s Eve" is named with a plain apostrophe' );
ok( ! isset( $by['WebPage'] ), 'that page carries one page node, not a WebPage as well' );
ok( no_entities( $json ), 'no HTML entities anywhere in that graph' );

// A page with no questions: Core's WebPage node.
list( $json, $by ) = graph_for( 11 );
ok( isset( $by['WebPage'][0] ) && 'Rooms & "Suites"' === $by['WebPage'][0]['name'], 'the WebPage name decodes a stored &amp; and keeps the typed quotes' );
ok( no_entities( $json ), 'no HTML entities in the WebPage graph' );

// Core's FAQ hub: the FAQ post titles are the questions, and AEO's questions join them.
list( $json, $by ) = graph_for( 12 );
$faq   = $by['FAQPage'][0] ?? array( 'mainEntity' => array() );
$names = array_column( $faq['mainEntity'], 'name' );
ok( in_array( "What's the dress code?", $names, true ), 'a FAQ post title is a plain-text question name' );
ok( 1 === count( array_keys( $names, "What's the dress code?", true ) ) && in_array( 'Is parking free?', $names, true ), 'the same question from Scout AI Search is not added twice' );
// Content and breadcrumbs go through display filters, so they decode to a curly
// apostrophe: plain text either way, just not the one typed.
ok( "It\u{2019}s smart casual." === ( $faq['mainEntity'][0]['acceptedAnswer']['text'] ?? '' ), 'the answer text is plain too' );
ok( 1 === count( $by['FAQPage'] ?? array() ), 'one FAQPage on the hub' );
ok( no_entities( $json ), 'no HTML entities in the FAQ hub graph' );

// A blog post: the headline.
list( $json, $by ) = graph_for( 13 );
ok( "Chef's picks" === ( $by['BlogPosting'][0]['headline'] ?? '' ), 'the BlogPosting headline is plain text, tags dropped' );
ok( no_entities( $json ), 'no HTML entities in the post graph' );

// Breadcrumbs share the trail with the visible links, so the schema side decodes.
$node = Scout_SEO_Breadcrumbs::node( array( array( 'name' => 'Home', 'url' => 'https://venue.test/' ), array( 'name' => get_the_title( 10 ), 'url' => 'https://venue.test/new-years-eve/' ) ) );
ok( "New Year\u{2019}s Eve" === $node['itemListElement'][1]['name'], 'a breadcrumb name is plain text' );

// The site title (Scout Core 1.14.0): WordPress stores it escaped, so the WebSite name
// came out as "Joe&#039;s ...".
$m = new ReflectionMethod( 'Scout_Schema', 'website_node' );
$m->setAccessible( true );
$site = $m->invoke( null );
ok( "Joe's Example Venue" === ( $site['name'] ?? '' ), 'the WebSite name is the plain site title' );

// An encoded tag must not come back as markup.
ok( 'x' === Scout_Schema::plain( '&lt;b&gt;x&lt;/b&gt;' ), 'decoding never turns an encoded tag into markup' );
ok( false === strpos( Scout_Schema::plain( '&lt;/script&gt;Hi' ), '</script' ), 'an encoded </script> cannot close the JSON-LD block' );

echo $fail ? "\n$fail failed\n" : "\nAll passed\n";
exit( $fail ? 1 : 0 );
