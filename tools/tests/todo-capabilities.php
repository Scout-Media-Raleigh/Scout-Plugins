<?php
// To do items carry the capability their own screen needs (Scout Build 0.18.0),
// the Events people capability and Door helper role (Scout Events 0.18.0), and the
// web team's boxes on Booking Settings (Scout Booking 0.4.0). Runs without WordPress:
//   php tools/tests/todo-capabilities.php
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );

// ---- A tiny WordPress: users with roles, filters, options. ----
$GLOBALS['filters'] = array(); $GLOBALS['um'] = array(); $GLOBALS['opt'] = array(); $GLOBALS['mail'] = array(); $GLOBALS['uid'] = 0;
class WP_Role {
	public $name; public $capabilities;
	function __construct( $n, $c ) { $this->name = $n; $this->capabilities = $c; }
	function has_cap( $c ) { return ! empty( $this->capabilities[ $c ] ); }
	function add_cap( $c ) { $this->capabilities[ $c ] = true; $GLOBALS['adds'] = ( $GLOBALS['adds'] ?? 0 ) + 1; }
	function remove_cap( $c ) { unset( $this->capabilities[ $c ] ); }
}
class WP_User { public $ID; public $roles; public $user_email; function __construct( $id, $roles, $email ) { $this->ID = $id; $this->roles = $roles; $this->user_email = $email; } function exists() { return $this->ID > 0; } }
$GLOBALS['roles'] = array(
	'administrator' => new WP_Role( 'administrator', array( 'read' => true, 'edit_posts' => true, 'list_users' => true, 'manage_options' => true, 'activate_plugins' => true ) ),
	'editor'        => new WP_Role( 'editor', array( 'read' => true, 'edit_posts' => true ) ),
);
$GLOBALS['users'] = array(
	1 => new WP_User( 1, array( 'administrator' ), 'owner@example.com' ),
	2 => new WP_User( 2, array( 'editor' ), 'staff@example.com' ),
	3 => new WP_User( 3, array( 'scout_door_helper' ), 'door@example.com' ),
);
function get_role( $r ) { return $GLOBALS['roles'][ $r ] ?? null; }
function add_role( $r, $label, $caps ) { $GLOBALS['roles'][ $r ] = new WP_Role( $r, $caps ); }
function remove_role( $r ) { unset( $GLOBALS['roles'][ $r ] ); }
function user_can( $u, $c ) { foreach ( (array) $u->roles as $r ) { if ( isset( $GLOBALS['roles'][ $r ] ) && $GLOBALS['roles'][ $r ]->has_cap( $c ) ) { return true; } } return false; }
function current_user_can( $c ) { $u = $GLOBALS['users'][ $GLOBALS['uid'] ] ?? null; return $u ? user_can( $u, $c ) : false; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0, array(), '' ); }
function wp_set_current_user( $id ) { $GLOBALS['uid'] = (int) $id; }
function add_filter( $tag, $cb, $prio = 10 ) { $GLOBALS['filters'][ $tag ][ $prio ][] = $cb; }
function add_action() {}
function apply_filters( $tag, $v ) { $f = $GLOBALS['filters'][ $tag ] ?? array(); ksort( $f ); foreach ( $f as $cbs ) { foreach ( $cbs as $cb ) { $v = call_user_func( $cb, $v ); } } return $v; }
function get_option( $k, $d = false ) { return $GLOBALS['opt'][ $k ] ?? $d; }
function update_option( $k, $v ) { $GLOBALS['opt'][ $k ] = $v; return true; }
function get_user_meta( $u, $k ) { return $GLOBALS['um'][ $u ][ $k ] ?? ''; }
function update_user_meta( $u, $k, $v ) { $GLOBALS['um'][ $u ][ $k ] = $v; }
function get_users() { $out = array(); foreach ( $GLOBALS['users'] as $u ) { if ( in_array( get_user_meta( $u->ID, 'scout_hub_inbox_email' ), array( 'daily', 'every' ), true ) ) { $out[] = (object) array( 'ID' => $u->ID, 'user_email' => $u->user_email ); } } return $out; }
function wp_mail( $to, $subject, $body ) { $GLOBALS['mail'][ $to ] = $body; return true; }
function __( $s ) { return $s; }
function _n( $a, $b, $n ) { return 1 === $n ? $a : $b; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $s ) ), '-' ); }
function sanitize_email( $s ) { return filter_var( $s, FILTER_VALIDATE_EMAIL ) ? $s : ''; }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function wp_trim_words( $t ) { return $t; }
function wp_strip_all_tags( $t ) { return strip_tags( $t ); }
function wp_date( $f, $t = null ) { return 'G' === $f ? '9' : date( $f, $t ?? time() ); }
function wp_specialchars_decode( $s ) { return $s; }
function post_type_exists() { return false; }
function admin_url( $p = '' ) { return '/wp-admin/' . $p; }
function is_wp_error() { return false; }

// ---- Scout Build's To do, with Bookings and one other plugin. ----
class Scout_Hub_Requests { const META_STATUS = 's'; const STATUSES = array( 'received' => 'Received' ); static function all() { return array(); } }
class Scout_Hub_Admin { static function url( $t, $a = array() ) { return '/hub/' . $t; } static function waiting_on_you() { return $GLOBALS['rows']; } }
class Scout_Booking {
	static function with_status( $s ) {
		return array( array( 'id' => 7, 'first_name' => 'Pat', 'last_name' => 'Example', 'email' => 'pat@example.com', 'note' => 'A private note about my session', 'type_name' => 'Session', 'start_at' => '', 'created' => '2026-11-13 09:00:00' ) );
	}
}
$GLOBALS['rows'] = array(
	array( 'label' => 'Applications to read', 'count' => 2, 'url' => '/apps', 'note' => '', 'cap' => 'list_users' ),
	array( 'label' => 'Pages to approve', 'count' => 1, 'url' => '/pages', 'note' => '' ),
);
add_filter(
	'scout_hub_inbox_items',
	static function ( $items ) {
		$items[] = array( 'id' => 'plain-1', 'kind' => 'other', 'label' => 'A plain item', 'from' => 'Someone', 'url' => '/plain', 'when' => time() );
		$items[] = array( 'id' => 'private-1', 'kind' => 'other', 'label' => 'A private item', 'from' => 'A customer', 'url' => '/private', 'when' => time(), 'cap' => 'list_users' );
		return $items;
	}
);
require __DIR__ . '/../../scout-build/includes/class-scout-hub-inbox.php';
require __DIR__ . '/../../scout-booking/includes/class-scout-booking-admin.php';
add_filter( 'scout_hub_inbox_items', array( 'Scout_Booking_Admin', 'inbox_caps' ), 99 );
$I = 'Scout_Hub_Inbox';

wp_set_current_user( 1 );
$admin = array_column( $I::items(), null, 'id' );
wp_set_current_user( 2 );
$staff = array_column( $I::items(), null, 'id' );

ok( isset( $admin['booking-7'] ) && 'list_users' === $admin['booking-7']['cap'], 'an administrator sees the booking request, carrying the Bookings capability' );
ok( ! isset( $staff['booking-7'] ), 'an editor never sees a booking request in To do' );
ok( false === strpos( json_encode( $staff ), 'pat@example.com' ) && false === strpos( json_encode( $staff ), 'private note' ), 'nothing of the booking (email, note) reaches the editor\'s list' );
ok( isset( $staff['plain-1'] ) && 'edit_posts' === $staff['plain-1']['cap'], 'an item without a cap keeps today\'s rule: anyone who can edit posts' );
ok( isset( $admin['private-1'] ) && ! isset( $staff['private-1'] ), 'a plugin item that names its cap is filtered by it' );
ok( isset( $admin['queue-applications-to-read'] ) && ! isset( $staff['queue-applications-to-read'] ), 'a count row that names its cap is filtered by it' );
ok( isset( $staff['queue-pages-to-approve'] ), 'a count row without a cap still shows to editors' );

wp_set_current_user( 1 );
$a_items = $I::items();
wp_set_current_user( 2 );
$s_items = $I::items();
ok( 6 === $I::total( $a_items ) && 2 === $I::total( $s_items ), 'the bubble counts only what each person can open (admin 1 booking + 2 apps + 1 page + 2 items; editor 1 page + 1 item)' );
$srows = array_column( $I::rows( $s_items ), 'count', 'label' );
ok( ! isset( $srows['Booking requests'] ) && ! isset( $srows['Applications to read'] ), 'Today on Home has no booking or private rows for the editor' );
$arows = array_column( $I::rows( $a_items ), 'count', 'label' );
ok( 1 === ( $arows['Booking requests'] ?? 0 ), 'Today on Home keeps the booking row for the administrator' );

// The email digest builds the list for each recipient.
wp_set_current_user( 0 );
update_user_meta( 1, Scout_Hub_Inbox::PREF, 'every' );
update_user_meta( 2, Scout_Hub_Inbox::PREF, 'every' );
$I::digest();
ok( isset( $GLOBALS['mail']['owner@example.com'] ) && false !== strpos( $GLOBALS['mail']['owner@example.com'], 'Pat Example' ), 'the administrator\'s To do email lists the booking request' );
ok( isset( $GLOBALS['mail']['staff@example.com'] ) && false === strpos( $GLOBALS['mail']['staff@example.com'], 'Pat Example' ) && false === strpos( $GLOBALS['mail']['staff@example.com'], 'A private item' ), 'the editor\'s To do email leaves the booking and private items out' );
ok( 0 === $GLOBALS['uid'], 'the digest signs back out when it is done' );

// Bookings stamps its capability on any booking item, whoever built it.
$stamped = Scout_Booking_Admin::inbox_caps( array( array( 'id' => 'booking-9', 'kind' => 'bookings' ), array( 'id' => 'chat-1', 'kind' => 'chat' ) ) );
ok( 'list_users' === $stamped[0]['cap'] && ! isset( $stamped[1]['cap'] ), 'Bookings marks only its own items' );

// ---- Scout Booking: the web team's boxes on Settings. ----
$changes = array( 'notify' => 'a@b.co', 'link_mode' => 'off', 'link_url' => 'https://x.test/', 'link_skip' => array( 'a' ), 'schema' => 1 );
$owner   = Scout_Booking_Admin::allowed_changes( $changes, false );
ok( array( 'notify' => 'a@b.co' ) === $owner, 'an owner who is not an operator saves Settings without touching Book buttons or the Service schema' );
ok( $changes === Scout_Booking_Admin::allowed_changes( $changes, true ), 'an operator saves every setting' );
wp_set_current_user( 1 );
ok( true === Scout_Booking_Admin::operator(), 'without Scout Build, an administrator counts as the web team' );
wp_set_current_user( 2 );
ok( false === Scout_Booking_Admin::operator(), 'without Scout Build, an editor does not' );
// Declared here, at run time, so the checks above ran without Scout Build.
if ( ! class_exists( 'Scout_Hub_Access', false ) ) {
	class Scout_Hub_Access { static function is_operator() { return ! empty( $GLOBALS['is_op'] ); } }
}
wp_set_current_user( 1 );
$GLOBALS['is_op'] = false;
ok( false === Scout_Booking_Admin::operator(), 'with Scout Build, an administrator who is not an operator is an owner' );
$GLOBALS['is_op'] = true;
ok( true === Scout_Booking_Admin::operator(), 'with Scout Build, an operator is the web team' );
$src = file_get_contents( __DIR__ . '/../../scout-booking/includes/class-scout-booking-admin.php' );
ok( 6 === substr_count( $src, '<?php disabled( ! $sbk_op ); ?>' ), 'every input in both boxes is read-only for an owner (three radios, the address, the session ticks, the schema switch)' );
ok( 2 === substr_count( $src, "esc_html_e( 'Your web team sets this.', 'scout-booking' )" ), 'both boxes say "Your web team sets this." to an owner' );

// ---- Scout Events: the people capability and the Door helper role. ----
class Scout_Events {
	static function settings() { return array( 'door_role' => $GLOBALS['door_on'] ?? 0 ); }
	static function event( $id ) { return $GLOBALS['events'][ $id ]; }
	static function start( $e ) { return $e[0]; }
	static function end( $e ) { return $e[1]; }
	static function now() { return $GLOBALS['now']; }
}
require __DIR__ . '/../../scout-events/includes/class-scout-events-people.php';
$P = 'Scout_Events_People';
ok( ! $GLOBALS['roles']['administrator']->has_cap( $P::CAP ), 'before the update, no role has the people capability' );
$P::maybe_grant();
ok( $GLOBALS['roles']['administrator']->has_cap( $P::CAP ), 'on update, administrators get the people capability, so they see no change' );
ok( ! $GLOBALS['roles']['editor']->has_cap( $P::CAP ), 'editors do not get it, so they lose sign-ups, volunteers and Needs you' );
ok( $P::GRANT === get_option( $P::OPTION ), 'the grant is recorded' );
$adds = $GLOBALS['adds'];
$P::maybe_grant();
$P::grant();
ok( $adds === $GLOBALS['adds'] && 1 === count( array_filter( array_keys( $GLOBALS['roles']['administrator']->capabilities ), static fn( $c ) => $c === $P::CAP ) ), 'running the grant again changes nothing' );
$GLOBALS['roles']['administrator']->remove_cap( $P::CAP );
$GLOBALS['opt'][ $P::OPTION ] = '0';
$P::maybe_grant();
ok( $GLOBALS['roles']['administrator']->has_cap( $P::CAP ), 'a site that missed the grant gets it on the next load' );
wp_set_current_user( 1 );
ok( $P::can_manage(), 'an administrator can manage people' );
wp_set_current_user( 2 );
ok( ! $P::can_manage(), 'an editor cannot' );

// The Door helper role: off by default, created only when turned on.
ok( null === get_role( $P::ROLE ), 'no Door helper role until an owner turns it on' );
$P::sync_role( true );
ok( get_role( $P::ROLE ) && get_role( $P::ROLE )->has_cap( $P::DOOR_CAP ) && ! get_role( $P::ROLE )->has_cap( 'edit_posts' ), 'turned on, the role can read and open the door list, nothing else' );
$P::sync_role( true );
ok( 2 === count( get_role( $P::ROLE )->capabilities ), 'turning it on twice is safe' );

$tz  = new DateTimeZone( 'America/New_York' );
$day = static fn( $s ) => new DateTimeImmutable( $s, $tz );
$GLOBALS['now']    = $day( '2026-11-13 10:00' );
$GLOBALS['events'] = array(
	10 => array( $day( '2026-11-13 19:00' ), $day( '2026-11-13 22:00' ) ),
	11 => array( $day( '2026-11-14 19:00' ), $day( '2026-11-14 22:00' ) ),
	12 => array( $day( '2026-11-12 18:00' ), $day( '2026-11-15 12:00' ) ),
	13 => array( $day( '2026-11-12 18:00' ), $day( '2026-11-12 23:00' ) ),
);
ok( $P::is_today( 10 ) && ! $P::is_today( 11 ) && $P::is_today( 12 ) && ! $P::is_today( 13 ), 'today means an event that runs on today, including a weekend that started yesterday' );
$GLOBALS['door_on'] = 1;
wp_set_current_user( 3 );
ok( $P::is_door_helper(), 'a door helper is recognised while the role is on' );
ok( $P::can_check_in( 10 ) && ! $P::can_check_in( 11 ), 'a door helper checks people in for today\'s event only' );
ok( ! $P::can_manage(), 'a door helper never manages people (no emails, downloads or waitlist)' );
$GLOBALS['door_on'] = 0;
ok( ! $P::can_check_in( 10 ), 'with the setting off, the door helper can check no one in' );
$P::sync_role( false );
ok( null === get_role( $P::ROLE ), 'turning it off removes the role' );
wp_set_current_user( 2 );
ok( ! $P::can_check_in( 10 ), 'an editor cannot check people in' );

echo $fail ? "\n$fail FAILED\n" : "\nAll To do capability checks passed\n";
exit( $fail ? 1 : 0 );
