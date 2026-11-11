<?php
// Scout Core: no single word alone on a line. Runs without WordPress:
//   php tools/tests/core-typography.php
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['support'] = false;
$GLOBALS['filters'] = array();
function add_action() {}
function apply_filters( $tag, $value ) { return array_key_exists( $tag, $GLOBALS['filters'] ) ? $GLOBALS['filters'][ $tag ] : $value; }
function current_theme_supports( $f ) { return false !== $GLOBALS['support']; }
function get_theme_support( $f ) { return false === $GLOBALS['support'] ? false : ( true === $GLOBALS['support'] ? true : array( $GLOBALS['support'] ) ); }
require __DIR__ . '/../../scout-core/includes/typography.php';

$t = function ( $html, $args = array() ) { return Scout_Core_Typography::tie( $html, $args ); };

// Off until the theme asks.
ok( false === Scout_Core_Typography::enabled(), 'off without theme support' );
$GLOBALS['support'] = true;
ok( true === Scout_Core_Typography::enabled(), 'on with add_theme_support( scout-no-orphans )' );
$a = Scout_Core_Typography::args();
ok( 22 === $a['max'] && in_array( 'h2', $a['elements'], true ) && in_array( 'summary', $a['elements'], true ), 'defaults: headings and summary, pairs up to 22' );
$GLOBALS['support'] = array( 'elements' => array( 'H2', 'bad tag' ), 'max' => 18, 'skip' => array( 'footer-head' ) );
$a = Scout_Core_Typography::args();
ok( array( 'h2' ) === $a['elements'] && 18 === $a['max'] && array( 'footer-head' ) === $a['skip'], 'theme args read, tag names cleaned' );

// Headings.
ok( '<h2>Websites that bring in the right&nbsp;clients.</h2>' === $t( '<h2>Websites that bring in the right clients.</h2>' ), 'last two words tied' );
ok( '<h1>Built for your&nbsp;<span class="x">business.</span></h1>' === $t( '<h1>Built for your <span class="x">business.</span></h1>' ), 'last word inside a span still tied' );
ok( '<h3 class="t">Planning and&nbsp;troubleshooting</h3>' === $t( '<h3 class="t">Planning and troubleshooting</h3>' ), 'pair of 19 characters tied' );
ok( '<h3>Notes on a very-long-hyphenated-word</h3>' === $t( '<h3>Notes on a very-long-hyphenated-word</h3>' ), 'pair over the limit left alone' );
ok( '<h2>Two words</h2>' === $t( '<h2>Two words</h2>' ), 'two-word heading left alone' );
ok( '<summary>Can you work on my&nbsp;site?</summary>' === $t( '<summary>Can you work on my site?</summary>' ), 'FAQ question tied' );
ok( '<h2>Already tied&nbsp;here</h2>' === $t( '<h2>Already tied&nbsp;here</h2>' ), 'already tied, left alone (no three-word tie)' );

// What it never touches.
ok( '<p>A paragraph with words in it</p>' === $t( '<p>A paragraph with words in it</p>' ), 'paragraphs left alone by default' );
ok( '<li>Raleigh and the Triangle</li>' === $t( '<li>Raleigh and the Triangle</li>' ), 'list items left alone (narrow footer columns)' );
ok( '<h2 class="footer-head x">Service area for you</h2>' === $t( '<h2 class="footer-head x">Service area for you</h2>', array( 'skip' => array( 'footer-head' ) ) ), 'skipped class left alone' );
$js = '<script>var s = "<h2>a b c</h2>";</script>';
ok( $js === $t( $js ), 'scripts left alone' );
ok( '<h2><a href="/a b">Read the full story</a></h2>' === str_replace( '&nbsp;', ' ', $t( '<h2><a href="/a b">Read the full story</a></h2>' ) ) && false !== strpos( $t( '<h2><a href="/a b">Read the full story</a></h2>' ), 'full&nbsp;story' ), 'link text tied, attribute spaces kept' );
ok( '<h2>Hi</h2>' === $t( '<h2>Hi</h2>', array( 'max' => 0 ) ), 'max 0 turns it off' );

// A whole page.
$page = "<html><body><h1>We keep the site fast and up to date.</h1>\n<p>Body copy stays as it is.</p></body></html>";
ok( false !== strpos( $t( $page ), 'to&nbsp;date.</h1>' ) && false !== strpos( $t( $page ), '<p>Body copy stays as it is.</p>' ), 'full page: heading tied, body untouched' );

echo $fail ? "\n$fail failed\n" : "\nall passed\n";
exit( $fail ? 1 : 0 );
