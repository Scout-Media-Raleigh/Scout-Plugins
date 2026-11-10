<?php
// Scout Build: the Scout box on the WordPress Dashboard. Runs without WordPress:
//   php tools/tests/dashboard-box.php
// It loads the real box and the real WordPress checks row, with the Scout
// classes the box reads stubbed out, and checks the calm layout (0.14.5).
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['opts']     = array();
$GLOBALS['checks']   = array();
$GLOBALS['security'] = array();
$GLOBALS['open']     = array();
function __( $s ) { return $s; }
function _n( $a, $b, $n ) { return 1 === $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return esc_html( $s ); }
function esc_html_e( $s ) { echo esc_html( $s ); }
function esc_attr_e( $s ) { echo esc_attr( $s ); }
function admin_url( $p = '' ) { return '/wp-admin/' . $p; }
function add_action() {}
function current_user_can() { return true; }
function get_post_meta( $id ) { return $GLOBALS['open'][ $id ] ?? 'done'; }
function wp_nonce_field() {}
function current_time( $f ) { return '2026-11'; }
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function wp_next_scheduled() { return true; }
function wp_schedule_single_event() {}
function human_time_diff() { return '3 hours'; }
function wp_list_pluck( $l, $k ) { return array_column( $l, $k ); }
final class Scout_Hub_Catalog { public static function features() { return array( 'a' => 1, 'b' => 1, 'c' => 1 ); } }
final class Scout_Hub_Features { public static function status( $s ) { return array( 'state' => 'c' === $s ? 'off' : 'on' ); } }
final class Scout_Hub_Requests { const ACTION = 'scout_hub_request'; const META_STATUS = '_status'; public static function all() { return array_map( static fn( $id ) => (object) array( 'ID' => $id ), array_keys( $GLOBALS['open'] ) ); } }
final class Scout_Hub_Log { public static function query( $a ) { return array( 'total' => 'done' === $a['status'] ? 22 : 0 ); } }
final class Scout_Hub_Admin {
	public static function url( $tab = 'overview' ) { return '/hub/' . $tab; }
	public static function status_line( $state, $line ) { echo '<p class="sh-status is-' . esc_attr( $state ) . '">' . esc_html( $line ) . '</p>'; }
}
final class Scout_Hub_Health { public static function checks() { return array_merge( $GLOBALS['checks'], array( Scout_Hub_WP_Health::row() ) ); } }
final class Scout_Hub_Security { public static function visible_rows() { return $GLOBALS['security']; } }
require __DIR__ . '/../../scout-build/includes/class-scout-hub-wp-health.php';
require __DIR__ . '/../../scout-build/includes/class-scout-hub-dashboard.php';
function box() { ob_start(); Scout_Hub_Dashboard::render(); return ob_get_clean(); }

// The staging copy from the report: search engines hidden, and WordPress's own
// check saying the same thing, plus three real looks.
$GLOBALS['opts']['scout_hub_wp_health'] = array( 'at' => time(), 'items' => array(
	array( 'test' => 'search_engine_visibility', 'label' => 'Your site is set to discourage search engines from indexing its content', 'status' => 'recommended', 'host' => false ),
) );
$GLOBALS['checks']   = array(
	array( 'label' => 'Secure connection (HTTPS)', 'value' => 'On', 'state' => 'good', 'note' => '' ),
	array( 'label' => 'Search engines', 'value' => 'Hidden', 'state' => 'warn', 'note' => 'Right for staging.', 'staging' => true ),
);
$GLOBALS['security'] = array(
	array( 'label' => 'Two-step login', 'value' => 'Off for 2 admins', 'state' => 'warn', 'note' => '' ),
	array( 'label' => 'Restore test', 'value' => 'Not done yet', 'state' => 'warn', 'note' => '' ),
	array( 'label' => 'Admins', 'value' => '4 admins', 'state' => 'warn', 'note' => '' ),
);
$html = box();
ok( 'All clear' === Scout_Hub_WP_Health::row()['value'], 'WordPress checks leave out search engine visibility, which Scout\'s own row shows' );
ok( false !== strpos( $html, '4 things want a look.' ), 'the status line counts one row for the one setting' );
ok( 2 === substr_count( $html, 'class="sh-check' ), 'the box names two checks' );
ok( false !== strpos( $html, 'Two-step login:' ) && false !== strpos( $html, 'Restore test:' ), 'the two named are the first real looks' );
ok( false === strpos( $html, 'Search engines:' ) && false === strpos( $html, 'WordPress checks:' ), 'the staging-only row is never named over a real look, and never twice' );
ok( false !== strpos( $html, 'And 2 more in Site status' ) && false !== strpos( $html, 'href="/hub/health"' ), '"And 2 more" links to Site status' );
ok( 1 === substr_count( $html, 'button-primary' ), 'one primary button' );
ok( false !== strpos( $html, 'Send request' ) && false === strpos( $html, 'Request a change' ), 'one way to ask for a change' );
ok( false === strpos( $html, 'AI search' ) && false === strpos( $html, 'Google email' ), 'AI search and Google email are left to Site status' );
ok( false !== strpos( $html, '22 updates this month' ) && false !== strpos( $html, '2 features on' ), 'updates and features share one row of chips' );
ok( false === strpos( $html, 'open request' ), 'no open requests chip when there are none' );
ok( false === strpos( $html, "\u{2014}" ), 'no em dashes' );

$GLOBALS['open'] = array( 7 => 'received' );
ok( false !== strpos( box(), '1 open request' ), 'an open request gets a chip' );

// A fix comes before every look; a staging row stays last.
$r = Scout_Hub_Dashboard::ranked( array(
	array( 'label' => 'S', 'state' => 'warn', 'staging' => true ),
	array( 'label' => 'L', 'state' => 'warn' ),
	array( 'label' => 'F', 'state' => 'bad' ),
) );
ok( 'F,L,S' === implode( ',', array_column( $r, 'label' ) ), 'fixes, then looks, then staging-only rows: ' . implode( ',', array_column( $r, 'label' ) ) );

// Two looks or fewer: no "And N more", one link to the details.
$GLOBALS['opts']['scout_hub_wp_health']['items'] = array();
$GLOBALS['security'] = array( array( 'label' => 'Restore test', 'value' => 'Not done yet', 'state' => 'warn', 'note' => '' ) );
$html = box();
ok( false === strpos( $html, 'more in Site status' ) && false !== strpos( $html, 'Details in Site status' ), 'with two or fewer, the link says Details' );

// Nothing to look at: the status line alone says so.
$GLOBALS['checks']   = array();
$GLOBALS['security'] = array();
$html = box();
ok( false !== strpos( $html, 'The site is in good shape.' ) && false === strpos( $html, '<ul class="sd-todo"' ), 'all clear shows the status line and no list' );

echo $fail ? "\n$fail FAILED\n" : "\nAll Dashboard box checks passed\n";
exit( $fail ? 1 : 0 );
