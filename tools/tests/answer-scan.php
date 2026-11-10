<?php
// Scout Build's "Direct answers" row: a page's saved FAQ check stops counting once the page
// is saved, its Scout AEO answers change, or the theme is updated, and the next background run
// checks only those pages again. Client staging, Nov 9: FAQs added during the day, report stuck
// at "0 of 11" from the morning checks. Runs without WordPress: php tools/tests/answer-scan.php
define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
$GLOBALS['opts']      = array();
$GLOBALS['trans']     = array();
$GLOBALS['scheduled'] = 0;
$GLOBALS['fetched']   = array();
$GLOBALS['faq_pages'] = array(); // Page IDs whose HTML prints FAQPage right now.
$GLOBALS['theme_ver'] = '1.0.0';
$GLOBALS['modified']  = array();
final class WP_Post {
	public $ID; public $post_parent = 0; public $post_name; public $post_password = '';
	public function __construct( $id ) { $this->ID = $id; $this->post_name = 'page-' . $id; }
}
final class WP_Theme {
	public function get( $k ) { return 'Version' === $k ? $GLOBALS['theme_ver'] : ''; }
	public function get_stylesheet() { return 'scout-media'; }
	public function parent() { return false; }
}
function wp_get_theme() { return new WP_Theme(); }
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function get_transient( $k ) { return $GLOBALS['trans'][ $k ] ?? false; }
function set_transient( $k, $v, $ttl ) { $GLOBALS['trans'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['trans'][ $k ] ); return true; }
function get_pages( $a = array() ) { return array_map( static fn( $id ) => new WP_Post( $id ), range( 1, 11 ) ); }
function get_nav_menu_locations() { return array(); }
function wp_get_nav_menu_items( $m ) { return array(); }
function get_posts( $a ) { return array(); }
function parse_blocks( $c ) { return array(); }
function wp_list_pluck( $l, $f ) { return array_map( static fn( $o ) => $o->$f, $l ); }
function get_post_modified_time( $f, $gmt, $p ) { return $GLOBALS['modified'][ $p->ID ] ?? time() - 2 * DAY_IN_SECONDS; }
function wp_next_scheduled( $h ) { return false; }
function wp_schedule_single_event( $t, $h ) { $GLOBALS['scheduled']++; return true; }
function spawn_cron() {}
function add_action() {}
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function get_permalink( $p ) { return 'https://example.test/page-' . $p->ID . '/'; }
function add_query_arg( $k, $v, $url ) { return $url . '?' . $k . '=' . $v; }
function is_wp_error( $r ) { return false; }
function wp_remote_get( $url, $a ) {
	$id = preg_match( '#/page-(\d+)/#', $url, $m ) ? (int) $m[1] : 0;
	if ( $id ) {
		$GLOBALS['fetched'][] = $id;
	}
	$faq = in_array( $id, $GLOBALS['faq_pages'], true ) ? '{"@type":"FAQPage"}' : '{"@type":"WebPage"}';
	return array( 'code' => 200, 'body' => '<html>' . $faq . '</html>' );
}
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function url_to_postid( $u ) { return 0; }
require dirname( __DIR__, 2 ) . '/scout-build/includes/class-scout-hub-health.php';
$H = 'Scout_Hub_Health';

$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }
function new_theme_version( $v ) { $GLOBALS['theme_ver'] = $v; ( new ReflectionProperty( 'Scout_Hub_Health', 'stamp' ) )->setValue( null, null ); }
function morning_run() { // The run from this morning: no page had FAQs yet.
	$GLOBALS['trans'][ Scout_Hub_Health::SCAN_KEY ] = array( 'time' => time() - 6 * HOUR_IN_SECONDS, 'tried' => 11, 'loaded' => 11 );
	$seen = array();
	foreach ( range( 1, 11 ) as $id ) {
		$seen[ $id ] = array( 'f' => false, 'd' => gmdate( 'Y-m-d' ), 't' => time() - 6 * HOUR_IN_SECONDS, 'v' => Scout_Hub_Health::theme_stamp() );
	}
	$GLOBALS['opts'][ Scout_Hub_Health::SEEN_KEY ] = $seen;
	$GLOBALS['modified']  = array();
	$GLOBALS['faq_pages'] = array();
	$GLOBALS['scheduled'] = 0;
	$GLOBALS['fetched']   = array();
}

// First look, nothing checked yet.
$s = $H::answer_scan();
ok( null === $s && 1 === $GLOBALS['scheduled'], 'no checks yet: Checking, and a background run starts' );
$H::run_scan();
$s = $H::answer_scan();
ok( 11 === $s['checked'] && 0 === $s['found'], 'first run checks all 11 pages' );

// Nothing changed: the once-a-day limit holds.
morning_run();
$s = $H::answer_scan();
ok( 0 === $GLOBALS['scheduled'] && 11 === $s['checked'] && 0 === $s['changed'], 'unchanged pages checked today: no new run' );
$H::run_scan();
ok( array() === $GLOBALS['fetched'], 'a run with nothing changed loads no page' );

// 1. A page is saved with new FAQs.
morning_run();
$GLOBALS['modified'][3]  = time() - MINUTE_IN_SECONDS;
$GLOBALS['faq_pages']    = array( 3 );
$s = $H::answer_scan();
ok( 10 === $s['checked'] && 1 === $s['changed'], 'saved page leaves the count: 0 of 10, 1 changed' );
ok( false === $s['blocked'], 'site loads its own pages: not blocked' );
ok( 1 === $GLOBALS['scheduled'], 'saved page starts a background run' );
$H::run_scan();
ok( array( 3 ) === $GLOBALS['fetched'], 'the run loads only the saved page' );
$s = $H::answer_scan();
ok( 11 === $s['checked'] && 1 === $s['found'] && 0 === $s['changed'], 'reload counts it: 1 of 11' );
ok( $s['oldest'] < $s['time'] - 5 * HOUR_IN_SECONDS, 'oldest page check (morning) is kept for the note' );

// 2. Scout AEO answers saved (post_modified unchanged).
morning_run();
$H::meta_changed( 1, 5, '_scout_aeo_faqs' );
$H::meta_changed( 1, 6, '_some_other_meta' );
$GLOBALS['faq_pages'] = array( 5 );
$s = $H::answer_scan();
ok( 10 === $s['checked'] && 1 === $s['changed'] && 1 === $GLOBALS['scheduled'], 'Scout AEO answers saved: page 5 leaves the count, run starts; other meta ignored' );
$H::run_scan();
ok( array( 5 ) === $GLOBALS['fetched'], 'the run loads only page 5' );
$s = $H::answer_scan();
ok( 1 === $s['found'] && 11 === $s['checked'], 'reload counts page 5' );

// 3. Theme update: every page printed FAQs on fetch.
morning_run();
new_theme_version( '1.1.0' );
$GLOBALS['faq_pages'] = range( 1, 11 );
$s = $H::answer_scan();
ok( null === $s && 1 === $GLOBALS['scheduled'], 'theme updated: no old check counts (Checking), run starts' );
$H::run_scan();
$s = $H::answer_scan();
ok( 11 === $s['found'] && 11 === $s['checked'] && count( $GLOBALS['fetched'] ) === 11, 'reload: 11 of 11 (the Nov 9 case)' );
new_theme_version( '1.0.0' );

// Throttle: a changed page doesn't start a run on every load right after the last one.
morning_run();
$GLOBALS['trans'][ $H::SCAN_KEY ]['time'] = time() - 30;
$GLOBALS['modified'][2] = time() - 10;
$H::answer_scan();
ok( 0 === $GLOBALS['scheduled'], 'run under 2 minutes ago: waits' );
$GLOBALS['trans'][ $H::SCAN_KEY ]['time'] = time() - 3 * MINUTE_IN_SECONDS;
$H::answer_scan();
ok( 1 === $GLOBALS['scheduled'], 'run over 2 minutes ago: starts again' );
$GLOBALS['scheduled'] = 0;
$GLOBALS['trans'][ $H::SCAN_KEY ] = array( 'time' => time() - 5 * MINUTE_IN_SECONDS, 'tried' => 4, 'loaded' => 0 );
$H::answer_scan();
ok( 0 === $GLOBALS['scheduled'], 'site can\'t load its own pages: waits out the 30 minutes' );
$s = $H::answer_scan();
ok( true === $s['blocked'] && 1 === $s['changed'], 'site can\'t load its own pages: the result says so, so the note won\'t promise a re-check' );

// Rows saved before this release have no theme stamp: they are checked again.
morning_run();
foreach ( $GLOBALS['opts'][ $H::SEEN_KEY ] as $id => $row ) {
	unset( $GLOBALS['opts'][ $H::SEEN_KEY ][ $id ]['v'] );
}
ok( null === $H::answer_scan(), 'old rows without a theme stamp no longer count' );

echo $fail ? "$fail failed\n" : "All passed\n";
exit( $fail ? 1 : 0 );
