<?php
// Scout Call Buttons 0.3.0: button sets. A page uses the first set that
// matches (by page, child page or address prefix); every other page keeps the
// default buttons, with the same markup as before. Runs without WordPress:
//   php tools/tests/contact-sets.php
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }
define( 'ABSPATH', __DIR__ . '/' );
define( 'SCOUT_CONTACT_FILE', __FILE__ );
define( 'SCOUT_CONTACT_VERSION', 'test' );
$GLOBALS['opt']     ??= array();
$GLOBALS['single']  ??= 0;
$GLOBALS['parents'] ??= array();
$GLOBALS['filter']  = null;
function apply_filters( $tag, $value, ...$args ) { return ( 'scout_contact_button_set' === $tag && is_callable( $GLOBALS['filter'] ) ) ? ( $GLOBALS['filter'] )( $value, ...$args ) : $value; }
function get_option( $k, $d = false ) { return $GLOBALS['opt']; }
function is_admin() { return false; }
function is_singular() { return (bool) $GLOBALS['single']; }
function get_queried_object_id() { return $GLOBALS['single']; }
function get_post_ancestors( $id ) { return $GLOBALS['parents']; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_email( $s ) { return filter_var( $s, FILTER_VALIDATE_EMAIL ) ? $s : ''; }
function esc_url_raw( $u, $p = null ) { return preg_match( '#^https?://#', (string) $u ) ? (string) $u : ''; }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr_e( $s ) { echo esc_attr( $s ); }
function esc_html_e( $s ) { echo esc_html( $s ); }
function absint( $n ) { return abs( (int) $n ); }
function __( $s ) { return $s; }
function scout_core_business() { return array( 'phone' => '(919) 555-0100', 'email' => 'hello@example.com', 'street' => '1 Main St', 'city' => 'Raleigh', 'region' => 'NC', 'postal' => '27601', 'name' => 'Example' ); }
require __DIR__ . '/../../scout-contact/includes/class-scout-contact.php';
$C = 'Scout_Contact';

/** A fresh request: static caches live in the class, so render in a child PHP. */
function render_in_child( array $opt, int $single, array $parents, string $uri ): string {
	$code = '$GLOBALS["opt"]=' . var_export( $opt, true ) . ';$GLOBALS["single"]=' . $single . ';$GLOBALS["parents"]=' . var_export( $parents, true ) . ';$_SERVER["REQUEST_URI"]=' . var_export( $uri, true ) . ';';
	$cmd  = 'php -r ' . escapeshellarg( 'define("SCC_CHILD",1);' . $code . 'require ' . var_export( __FILE__, true ) . ';' );
	return (string) shell_exec( $cmd );
}
if ( defined( 'SCC_CHILD' ) ) {
	ob_start();
	$C::render();
	echo ob_get_clean();
	return;
}

// Paths and matching.
ok( '/bodywork/' === $C::clean_path( 'Bodywork' ), 'a path gets its slashes and lower case' );
ok( '/bodywork/x/' === $C::clean_path( 'https://site.test/bodywork/x' ), 'a full address keeps only its path' );
$sets = array(
	$C::clean_set( array( 'name' => 'Bodywork', 'paths' => '/bodywork/', 'buttons' => array( array( 'type' => 'book', 'url' => 'https://book.example.com/' ), 'text' ) ) ),
	$C::clean_set( array( 'name' => 'Request', 'pages' => array( 10 ), 'children' => 1, 'buttons' => array( array( 'type' => 'link', 'label' => 'Request', 'url' => 'https://site.test/request/' ) ) ) ),
	$C::clean_set( array( 'name' => 'Catch all for /body', 'paths' => '/body/' ) ),
);
ok( 'Bodywork' === ( $C::match_set( $sets, 0, array(), '/bodywork/deep/' )['name'] ?? '' ), 'a path prefix matches its child addresses' );
ok( null === $C::match_set( $sets, 0, array(), '/bodyworks/' ), 'a prefix matches whole parts of the path only' );
ok( 'Catch all for /body' === ( $C::match_set( $sets, 0, array(), '/body/' )['name'] ?? '' ), 'the next set is tried when the first does not match' );
ok( 'Request' === ( $C::match_set( $sets, 10, array(), '/request/' )['name'] ?? '' ), 'a chosen page matches' );
ok( 'Request' === ( $C::match_set( $sets, 11, array( 10 ), '/request/x/' )['name'] ?? '' ), 'its child page matches when children are on' );
$no_kids = $sets; $no_kids[1]['children'] = 0;
ok( null === $C::match_set( $no_kids, 11, array( 10 ), '/request/x/' ), 'a child page does not match when children are off' );
ok( null === $C::clean_set( array( 'name' => '', 'paths' => '', 'buttons' => array() ) ), 'a blank set is dropped' );
ok( array() === $C::clean_set( array( 'name' => 'x', 'buttons' => array( array( 'type' => 'link', 'label' => 'Go' ) ) ) )['buttons'], 'a link button with no link is dropped' );
ok( array() === $C::clean_set( array( 'name' => 'x', 'buttons' => array( array( 'type' => 'script' ) ) ) )['buttons'], 'an unknown kind of button is dropped' );
ok( '' === $C::clean_link( 'book', 'javascript:alert(1)' ), 'a link override must be a web address' );

$base = array( 'book_url' => 'https://site.test/book/' );

// Default: no sets means exactly the old bar.
$plain = render_in_child( $base, 0, array(), '/bodywork/' );
ok( false !== strpos( $plain, 'href="tel:+19195550100"' ) && false !== strpos( $plain, 'href="sms:+19195550100"' ), 'no sets: Call and Text on the bar as before' );
ok( false !== strpos( $plain, 'data-scc-open' ) && false !== strpos( $plain, 'https://site.test/book/' ), 'no sets: Let\'s talk opens the sheet with the site booking link' );
$with_sets_elsewhere = render_in_child( $base + array( 'sets' => $sets ), 0, array(), '/about/' );
ok( $plain === render_in_child( $base, 0, array(), '/about/' ) && $with_sets_elsewhere === $plain, 'a page no set matches prints the same markup as a site with no sets' );

// The bodywork set: Book (outside) and Text, no Call.
$body = render_in_child( $base + array( 'sets' => $sets ), 0, array(), '/bodywork/deep/' );
ok( false === strpos( $body, 'tel:' ), 'bodywork set: no Call button' );
ok( false !== strpos( $body, 'href="sms:+19195550100"' ), 'bodywork set: Text on the bar' );
ok( false !== strpos( $body, 'https://book.example.com/' ) && false === strpos( $body, 'https://site.test/book/' ), 'bodywork set: Book goes to the outside booking address' );
ok( strpos( $body, 'book.example.com' ) < strpos( $body, 'Send a text' ), 'bodywork set: the sheet keeps the set\'s order' );
ok( false !== strpos( $body, 'class="scc-bar"' ) && false !== strpos( $body, 'class="scc-main"' ), 'bodywork set: same bar classes' );

// The request set: one button, so the main button links straight to it.
$req = render_in_child( $base + array( 'sets' => $sets ), 11, array( 10 ), '/request/x/' );
ok( false !== strpos( $req, '<a class="scc-main" href="https://site.test/request/"' ) && false !== strpos( $req, '>Request</a>' ), 'request set: one Request button, straight to its page' );
ok( false === strpos( $req, '<dialog' ) && false === strpos( $req, 'tel:' ), 'request set: no sheet and no Call or Text' );

// No Text for a set that keeps the usual buttons.
$quiet = render_in_child( $base + array( 'sets' => array( $C::clean_set( array( 'name' => 'Quiet', 'paths' => '/quiet/', 'no_text' => 1 ) ) ) ), 0, array(), '/quiet/' );
ok( false !== strpos( $quiet, 'tel:' ) && false === strpos( $quiet, 'sms:' ) && false !== strpos( $quiet, 'site.test/book/' ), 'no Text button: Call and Book stay, Text goes' );

// Email and Directions read Business info.
$more = render_in_child( $base + array( 'sets' => array( $C::clean_set( array( 'name' => 'More', 'paths' => '/', 'buttons' => array( 'email', 'directions' ) ) ) ) ), 0, array(), '/' );
ok( false !== strpos( $more, 'mailto:hello@example.com' ) && false !== strpos( $more, 'google.com/maps/search' ), 'Email and Directions use Business info' );

echo $fail ? "$fail failed\n" : "All passed\n";
exit( $fail ? 1 : 0 );
