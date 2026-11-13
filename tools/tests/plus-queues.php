<?php
// Plus features in To do and their new settings: Members and Events items with
// their capabilities (Members 0.6.0, Events 0.18.0), a calendar link for one
// session type and the Schedule's decline line (Booking 0.4.0), "Send only, keep
// no entry" (Forms Guard 0.4.0) and the site's word for courses (Events 0.18.0).
// Runs without WordPress:
//   php tools/tests/plus-queues.php
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'SCOUT_BOOKING_VERSION', 't' );
define( 'SCOUT_EVENTS_VERSION', 't' );
define( 'SCOUT_EVENTS_URL', '/' );

// ---- A tiny WordPress ----
$GLOBALS['opt'] = array(); $GLOBALS['filters'] = array(); $GLOBALS['uid'] = 0; $GLOBALS['posts'] = array(); $GLOBALS['um'] = array();
$GLOBALS['caps'] = array(
	1 => array( 'read', 'edit_posts', 'list_users', 'manage_options', 'scout_events_manage_people' ), // owner
	2 => array( 'read', 'edit_posts' ),                                                              // staff
);
function current_user_can( $c ) { return in_array( $c, $GLOBALS['caps'][ $GLOBALS['uid'] ] ?? array(), true ); }
function get_current_user_id() { return $GLOBALS['uid']; }
function add_filter( $tag, $cb, $prio = 10 ) { $GLOBALS['filters'][ $tag ][ $prio ][] = $cb; }
function add_action( $tag, $cb = null, $prio = 10 ) {}
function apply_filters( $tag, $v, ...$args ) { $f = $GLOBALS['filters'][ $tag ] ?? array(); ksort( $f ); foreach ( $f as $cbs ) { foreach ( $cbs as $cb ) { $v = call_user_func( $cb, $v, ...$args ); } } return $v; }
function get_option( $k, $d = false ) { return $GLOBALS['opt'][ $k ] ?? $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opt'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $autoload; return true; }
function __( $s ) { return $s; }
function esc_html__( $s ) { return $s; }
function _n( $a, $b, $n ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s ) { return preg_match( '#^https?://#', (string) $s ) ? (string) $s : ''; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $s ) ), '-' ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_email( $s ) { return filter_var( $s, FILTER_VALIDATE_EMAIL ) ? $s : ''; }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function absint( $n ) { return abs( (int) $n ); }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function wp_trim_words( $t ) { return $t; }
function wp_strip_all_tags( $t ) { return strip_tags( (string) $t ); }
function wp_timezone() { return new DateTimeZone( 'America/New_York' ); }
function wp_date( $f, $t = null ) { return ( new DateTime( '@' . ( $t ?? time() ) ) )->setTimezone( wp_timezone() )->format( $f ); }
function admin_url( $p = '' ) { return '/wp-admin/' . $p; }
function home_url( $p = '' ) { return 'https://example.test' . ( '' !== $p ? $p : '/' ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function add_query_arg( $k, $v = null, $u = null ) { return $u . ( false === strpos( (string) $u, '?' ) ? '?' : '&' ) . $k . '=' . $v; }
function wp_generate_password( $n ) { static $i = 0; ++$i; return substr( str_repeat( 'abcdefghij' . $i, 8 ), 0, $n ); }
function get_the_title( $id ) { return $GLOBALS['posts'][ $id ]['title'] ?? ''; }
function get_edit_post_link( $id ) { return '/wp-admin/post.php?post=' . $id . '&action=edit'; }
function get_posts( $q ) {
	$out = array();
	foreach ( $GLOBALS['posts'] as $id => $p ) {
		if ( $p['type'] === $q['post_type'] && in_array( $p['status'], (array) $q['post_status'], true ) && ( empty( $q['meta_key'] ) || ! empty( $p['meta'][ $q['meta_key'] ] ) ) ) {
			$out[] = $id;
		}
	}
	return $out;
}
function wp_count_posts( $type ) { return (object) array( 'pending' => count( get_posts( array( 'post_type' => $type, 'post_status' => 'pending' ) ) ) ); }
function post_type_exists() { return false; }
function get_user_meta( $u, $k ) { return $GLOBALS['um'][ $k ] ?? ''; }
function wp_specialchars_decode( $s ) { return $s; }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d ); }

// ---- Scout Members, with its data stubbed ----
final class Scout_Members {
	public static $applied = array(); public static $due = array(); public static $asked = 0;
	static function ids( $s ) { return 'applied' === $s ? self::$applied : array(); }
	static function renewals_due( int $days = 30 ) { self::$asked = $days; return array_keys( array_filter( self::$due, static fn( $d ) => $d <= $days ) ); }
}
require __DIR__ . '/../../scout-members/includes/class-scout-members-admin.php';
require __DIR__ . '/../../scout-members/includes/class-scout-members-invitations.php';

// ---- Scout Events ----
require __DIR__ . '/../../scout-events/includes/class-scout-events.php';
require __DIR__ . '/../../scout-events/includes/class-scout-events-people.php';
require __DIR__ . '/../../scout-events/includes/class-scout-events-admin.php';
require __DIR__ . '/../../scout-events/includes/class-scout-events-presenters.php';
require __DIR__ . '/../../scout-events/includes/class-scout-events-vendors.php';
require __DIR__ . '/../../scout-events/includes/class-scout-events-courses.php';

// ---- Scout Build's To do list ----
class Scout_Hub_Requests { const META_STATUS = 's'; const STATUSES = array( 'received' => 'Received' ); static function all() { return array(); } }
class Scout_Hub_Admin { static function url( $t, $a = array() ) { return '/hub/' . $t; } static function waiting_on_you() { return array(); } }
require __DIR__ . '/../../scout-build/includes/class-scout-hub-inbox.php';

// ================= P2: Members =================
Scout_Members::$applied = array( 11, 12 );
Scout_Members::$due     = array( 21 => 5, 22 => 13, 23 => 25 ); // member => days until renewal
$GLOBALS['posts'] = array(
	31 => array( 'type' => Scout_Members_Invitations::TYPE, 'status' => 'pending', 'title' => 'Tea walk' ),
	41 => array( 'type' => 'scout_speaker', 'status' => 'pending', 'title' => 'Dana Lee' ),
	42 => array( 'type' => 'scout_speaker', 'status' => 'publish', 'title' => 'Sam Hill', 'meta' => array( '_scout_presenter_pending' => 1 ) ),
	51 => array( 'type' => Scout_Events_Vendors::TYPE, 'status' => 'pending', 'title' => 'Tea stall' ),
	52 => array( 'type' => Scout_Events_Vendors::TYPE, 'status' => 'pending', 'title' => 'Book stall' ),
);
$q = Scout_Members_Admin::queue();
ok( array( 'applications', 'invitations', 'renewals' ) === array_keys( $q ), 'Members queues applications, invitations and renewals' );
ok( 14 === Scout_Members::$asked && 2 === $q['renewals']['todo']['count'], 'renewals in To do look 14 days ahead (2 of 3 members)' );
$m = Scout_Members_Admin::to_todo( array(), array_values( $q ), 1000 );
$m = array_column( $m, null, 'id' );
ok( isset( $m['members-applications'], $m['members-invitations'], $m['members-renewals'] ), 'three To do items from Members' );
ok( 'list_users' === $m['members-applications']['cap'] && 'list_users' === $m['members-invitations']['cap'] && 'list_users' === $m['members-renewals']['cap'], 'each Members item needs list_users, the Members screens\' capability' );
ok( 2 === $m['members-applications']['count'] && '/wp-admin/admin.php?page=scout-members#sm-applications' === $m['members-applications']['url'], 'applications item counts and opens the applications on Members Home' );
ok( '/wp-admin/edit.php?post_type=scout_invitation&post_status=pending' === $m['members-invitations']['url'], 'invitations item opens the pending invitations' );
ok( '/wp-admin/admin.php?page=scout-members-renewals' === $m['members-renewals']['url'], 'renewals item opens Renewals due' );
$GLOBALS['uid'] = 2;
ok( array() === Scout_Members_Admin::waiting(), 'staff without list_users still get no Members rows in Needs you' );
$GLOBALS['uid'] = 1;
$w = Scout_Members_Admin::waiting();
ok( isset( $w[0]['todo'] ) && 'members-applications' === $w[0]['todo']['id'] && false !== strpos( end( $w )['text'], '30 days' ), 'Needs you reads the same applications row, and its renewals line still looks 30 days ahead' );

// ================= P2: Events =================
$rows = Scout_Events_Vendors::needs_you( Scout_Events_Presenters::needs_you( array() ) );
$rows[] = array( 'text' => '3 people are on the waitlist for Spring Day, and places are open.', 'url' => '/wp-admin/admin.php?page=scout-events-signups&event=7', 'action' => 'Move people in', 'todo' => array( 'id' => 'events-waitlist-7', 'label' => 'Waitlist, a place is open', 'count' => 3 ) );
$rows[] = array( 'text' => 'A session is a draft.', 'url' => '/x', 'action' => 'Check sessions' ); // no todo part: Needs you only
$e = array_column( Scout_Events_Admin::to_todo( array(), $rows, 1000 ), null, 'id' );
ok( isset( $e['events-presenter-applications'], $e['events-vendor-applications'], $e['events-waitlist-7'] ), 'presenter and vendor applications and a waitlist offer become To do items' );
ok( isset( $e['events-presenter-changes-42'] ), 'a presenter profile change becomes a To do item' );
ok( 4 === count( $e ), 'a Needs you row without a todo part stays out of To do' );
ok( 2 === $e['events-vendor-applications']['count'] && '2 waiting' === $e['events-vendor-applications']['from'], 'vendor applications counted' );
ok( 'scout_events_manage_people' === $e['events-presenter-applications']['cap'] && 'scout_events_manage_people' === $e['events-waitlist-7']['cap'], 'Events items need scout_events_manage_people' );
ok( '/wp-admin/edit.php?post_type=scout_speaker&post_status=pending' === $e['events-presenter-applications']['url'] && '/wp-admin/admin.php?page=scout-events-signups&event=7' === $e['events-waitlist-7']['url'], 'Events items open their exact screens' );
// Members' rows sit in Events Needs you too: added once, whichever plugin runs first.
$both = Scout_Events_Admin::to_todo( Scout_Members_Admin::to_todo( array(), array_values( $q ) ), array_merge( $rows, array( $q['applications'] ) ) );
$ids  = array_column( $both, 'id' );
ok( count( $ids ) === count( array_unique( $ids ) ) && in_array( 'members-applications', $ids, true ), 'a Members row in Events Needs you is added to To do once' );
ok( 'list_users' === array_column( $both, 'cap', 'id' )['members-applications'], 'and keeps the Members capability when Events adds it' );

// Through Scout Build's To do: the owner sees every item, staff see none of them.
add_filter( 'scout_hub_inbox_items', static fn( $i ) => Scout_Members_Admin::todo_items( $i ) );
add_filter( 'scout_hub_inbox_items', static fn( $i ) => Scout_Events_Admin::to_todo( $i, $GLOBALS['rows_for_events'] ), 20 );
$GLOBALS['rows_for_events'] = array_merge( $rows, array( $q['applications'], $q['invitations'] ) );
$GLOBALS['uid'] = 1;
$owner = Scout_Hub_Inbox::items();
$GLOBALS['uid'] = 2;
$staff = Scout_Hub_Inbox::items();
ok( 7 === count( $owner ) && 0 === count( $staff ), 'the owner sees 7 To do items, staff who edit posts see none' );
$GLOBALS['uid'] = 1;
ok( 2 + 1 + 2 + 1 + 2 + 1 + 3 === Scout_Hub_Inbox::total( $owner ), 'the To do count adds each queue\'s own number' );

// ================= P6a: a calendar link for one session type =================
require __DIR__ . '/../../scout-booking/includes/class-scout-booking.php';
require __DIR__ . '/../../scout-booking/includes/class-scout-booking-emails.php';
require __DIR__ . '/../../scout-booking/includes/class-scout-booking-front.php';
require __DIR__ . '/../../scout-booking/includes/class-scout-booking-admin.php';
ok( array() === Scout_Booking::type_feeds() && '' === Scout_Booking_Front::type_feed_url( 'tantra1' ), 'no session type has a calendar link until one is made' );
ok( false === Scout_Booking::settings()['type_feed_names'], 'initials, not names, by default' );
$tok = Scout_Booking::new_token();
Scout_Booking::set_type_feed( 'sat', $tok );
ok( $tok === Scout_Booking::type_feeds()['sat'] && false === $GLOBALS['autoload'][ Scout_Booking::TYPE_FEEDS ], 'a link is made and kept out of the settings loaded on every page' );
ok( 'sat' === Scout_Booking::type_for_feed( $tok ) && '' === Scout_Booking::type_for_feed( 'wrong' . substr( $tok, 5 ) ) && '' === Scout_Booking::type_for_feed( '' ), 'only the exact token opens the link' );
ok( false !== strpos( Scout_Booking_Front::type_feed_url( 'sat' ), 'scout_booking_type_feed=' . $tok ), 'the link carries its token' );
$new = Scout_Booking::new_token();
Scout_Booking::set_type_feed( 'sat', $new );
ok( '' === Scout_Booking::type_for_feed( $tok ) && 'sat' === Scout_Booking::type_for_feed( $new ), 'a new link retires the old one' );
$mk = static fn( $id, $type, $status, $first, $last ) => array( 'id' => $id, 'token' => 'tok' . $id, 'type_id' => $type, 'type_name' => 'sat' === $type ? 'Saturday session' : 'Massage', 'status' => $status, 'first_name' => $first, 'last_name' => $last, 'email' => 'client' . $id . '@example.com', 'phone' => '919-555-01' . $id, 'note' => 'private note ' . $id, 'answers' => array( array( 'q' => 'Anything else?', 'a' => 'secret answer ' . $id ) ), 'start_at' => '2026-11-14 10:00:00', 'end_at' => '2026-11-14 11:00:00' );
$all  = array( $mk( 1, 'sat', 'confirmed', 'Jordan', 'Smith' ), $mk( 2, 'sat', 'requested', 'Riley', 'Moss' ), $mk( 3, 'mas', 'confirmed', 'Pat', 'Quinn' ), $mk( 4, 'sat', 'blocked', '', '' ), $mk( 5, 'sat', 'cancelled', 'Ana', 'Ruiz' ) );
$mine = Scout_Booking::type_feed_rows( $all, 'sat' );
ok( array( 1 ) === array_column( $mine, 'id' ), 'the link carries only that type\'s confirmed bookings' );
$ics = Scout_Booking_Emails::type_ics( $mine, 'Saturday session' );
ok( false !== strpos( $ics, 'SUMMARY:Saturday session · J. S.' ), 'each session reads its name and the client\'s initials' );
ok( false === strpos( $ics, 'Jordan' ) && false === strpos( $ics, '@example.com' ) && false === strpos( $ics, '919-555' ) && false === strpos( $ics, 'secret answer' ) && false === strpos( $ics, 'private note' ) && false === strpos( $ics, 'DESCRIPTION' ), 'no name, email, phone, answers or notes in the link' );
ok( false !== strpos( $ics, 'DTSTART:20261114T150000Z' ) && false !== strpos( $ics, 'REFRESH-INTERVAL' ), 'the time is there and calendars refresh it hourly' );
ok( false === strpos( Scout_Booking_Emails::type_ics( $all, 'x' ), 'Riley' ) && false === strpos( Scout_Booking_Emails::type_ics( $all, 'x' ), 'Blocked' ), 'requests and blocked time never reach the link, even if passed in' );
ok( false !== strpos( Scout_Booking_Emails::type_ics( $mine, 'Saturday session', true ), 'SUMMARY:Saturday session · Jordan Smith' ), 'with the names switch on, the full name shows' );
Scout_Booking::set_type_feed( 'sat', '' );
ok( '' === Scout_Booking::type_for_feed( $new ) && array() === Scout_Booking::type_feeds(), 'turning a link off removes it' );

// ================= P10: the decline line on Schedule =================
ok( '' === Scout_Booking_Admin::declines_line( array() ), 'no decline line when nothing was declined' );
ok( 'Declined in the last 30 days as not a fit: 3, as other: 1.' === Scout_Booking_Admin::declines_line( array( 'Not a fit' => 3, 'Other' => 1 ) ), 'one line counts declines by reason' );
ok( false !== strpos( Scout_Booking_Admin::declines_line( array( '' => 2 ) ), 'as no reason given: 2' ), 'a decline without a reason is counted too' );
ok( '' === Scout_Booking_Admin::declines_link(), 'no link without Scout CRM' );
final class Scout_CRM_Backfill { static function page() { return 'scout-booking-contacts'; } }
ok( '/wp-admin/admin.php?page=scout-booking-contacts' === Scout_Booking_Admin::declines_link(), 'with Scout CRM, the line links to Contacts' );
$GLOBALS['uid'] = 2;
ok( '' === Scout_Booking_Admin::declines_link(), 'no Contacts link for someone who cannot open it' );
$GLOBALS['uid'] = 1;

// ================= P8: Send only, keep no entry =================
final class GFAPI {
	public static $forms = array(); public static $deleted = array();
	static function get_forms() { return array_values( self::$forms ); }
	static function get_form( $id ) { return self::$forms[ $id ] ?? false; }
	static function delete_entry( $id ) { self::$deleted[] = $id; return true; }
}
function scout_build_form_test_running() { return ! empty( $GLOBALS['testing'] ); }
require __DIR__ . '/../../scout-forms-guard/includes/class-scout-forms-guard-send-only.php';
$S = 'Scout_Forms_Guard_Send_Only';
GFAPI::$forms = array( 3 => array( 'id' => 3, 'title' => 'Contact' ), 9 => array( 'id' => 9, 'title' => 'Concern', $S::KEY => true ) );
$sections = $S::settings_fields( array(), GFAPI::$forms[3] );
$field    = end( $sections )['fields'][0];
ok( $S::KEY === $field['name'] && 'toggle' === $field['type'] && false === $field['default_value'], 'the switch shows on the form settings, off by default' );
ok( ! $S::on( GFAPI::$forms[3] ) && $S::on( GFAPI::$forms[9] ) && $S::on( 9 ), 'only the form with the switch on keeps no entry' );
ok( true === $S::send_now( true, 'form_submission', array(), GFAPI::$forms[3] ) && false === $S::send_now( true, 'form_submission', array(), GFAPI::$forms[9] ), 'a send-only form sends its notification now, other forms keep their choice' );
ok( false === $S::forget( array( 'id' => 70, 'form_id' => 3 ), GFAPI::$forms[3] ) && array() === GFAPI::$deleted, 'an ordinary form keeps its entry' );
ok( true === $S::forget( array( 'id' => 71, 'form_id' => 9 ), GFAPI::$forms[9] ) && array( 71 ) === GFAPI::$deleted, 'a send-only form\'s entry is deleted after it sends' );
ok( array( 9 ) === $S::form_ids(), 'the send-only forms are found' );
ok( array( 'on' => true, 'forms' => array( 3 ) ) === $S::skip_in_test( array( 'on' => true, 'forms' => array( 3, 9 ) ) ), 'the nightly form test never picks a send-only form' );
ok( 'x' === $S::skip_in_test( 'x' ) && array() === $S::skip_in_test( array() ), 'an empty or odd test setting passes through' );
$mail = array( 'to' => 'reader@example.com' );
ok( empty( $S::no_test_mail( $mail, 'html', array(), array( 'form_id' => 9 ) )['abort_email'] ), 'a real submission\'s email goes out' );
$GLOBALS['testing'] = true;
ok( ! empty( $S::no_test_mail( $mail, 'html', array(), array( 'form_id' => 9 ) )['abort_email'] ) && empty( $S::no_test_mail( $mail, 'html', array(), array( 'form_id' => 3 ) )['abort_email'] ), 'during a form test a send-only form sends nothing; other forms still test' );
$GLOBALS['testing'] = false;
$kept = $S::inbox_items( array( array( 'id' => 'a', 'form_id' => 9 ), array( 'id' => 'b', 'form_id' => 3 ), array( 'id' => 'c' ) ) );
ok( array( 'b', 'c' ) === array_column( $kept, 'id' ), 'a To do item naming a send-only form is left out' );
ok( false === $S::content_check( true, GFAPI::$forms[9] ) && true === $S::content_check( true, GFAPI::$forms[3] ), 'the message check stays off for a send-only form only' );

// ================= P12: the site's word for courses =================
ok( 'Courses' === Scout_Events_Courses::label() && 'Course' === Scout_Events_Courses::label( true ), 'Courses by default' );
ok( 'Add course' === Scout_Events_Courses::labels()['add_new'] && 'Courses' === Scout_Events_Courses::labels()['name'], 'default screen words are unchanged' );
$GLOBALS['opt']['scout_events'] = array( 'course_label' => 'Trainings' );
ok( 'Trainings' === Scout_Events_Courses::label() && 'Training' === Scout_Events_Courses::label( true ), 'the site\'s word, and one of them without the last s' );
$l = Scout_Events_Courses::labels();
ok( 'Trainings' === $l['name'] && 'Trainings' === $l['menu_name'] && 'Add training' === $l['add_new'] && 'Edit training' === $l['edit_item'] && 'No trainings yet.' === $l['not_found'], 'menu and screen titles use the word' );
$GLOBALS['posts'][81] = array( 'type' => 'scout_course', 'status' => 'publish', 'title' => 'Level 1' );
ok( '<h2 class="se-course-title">Training: Level 1</h2>' === Scout_Events_Courses::heading( 81 ), 'the shortcode heading uses the word' );
$GLOBALS['opt']['scout_events'] = array( 'course_label' => 'Classes' );
ok( 'Class' === Scout_Events_Courses::label( true ), 'Classes gives Class' );
$GLOBALS['opt']['scout_events'] = array( 'course_label' => 'CPR programs', 'course_label_one' => 'CPR program' );
ok( 'CPR program' === Scout_Events_Courses::label( true ) && 'Add CPR program' === Scout_Events_Courses::labels()['add_new'], 'a word for one can be set, and initials keep their capitals' );
$GLOBALS['opt']['scout_events'] = array( 'course_label' => '<b>Trainings</b> and a very long label that goes on far too long' );
ok( 40 >= strlen( Scout_Events::settings()['course_label'] ) && false === strpos( Scout_Events::settings()['course_label'], '<' ), 'the word is plain text, 40 characters at most' );

echo $fail ? "\n$fail FAILED\n" : "\nAll Plus queue checks passed\n";
exit( $fail ? 1 : 0 );
