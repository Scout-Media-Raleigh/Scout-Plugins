<?php
// The nightly form test, in Site status: scheduling, the made-up answers, the
// two-failure rule, cleanup, and a full run against a stand-in Gravity Forms
// with the real Scout Forms Guard and the real "Form emails sent" counter.
// Runs without WordPress: php tools/tests/form-test.php
$fail = 0;
function ok( $cond, $msg ) { global $fail; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $msg . "\n"; if ( ! $cond ) { $fail++; } }
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['opts']   = array( 'admin_email' => 'owner@example-firm.com', 'blogname' => 'Example Firm' );
$GLOBALS['hooks']  = array();
$GLOBALS['mail']   = array();
$GLOBALS['mailok'] = true;
$GLOBALS['cron']   = array();
$GLOBALS['meta']   = array();
$GLOBALS['live']   = true;
function add_action( $hook, $cb, $prio = 10, $n = 1 ) { $GLOBALS['hooks'][ $hook ][] = array( $cb, $prio ); return true; }
function add_filter( $hook, $cb, $prio = 10, $n = 1 ) { return add_action( $hook, $cb, $prio, $n ); }
function remove_action( $hook, $cb, $prio = 10 ) { $GLOBALS['hooks'][ $hook ] = array_values( array_filter( $GLOBALS['hooks'][ $hook ] ?? array(), static fn( $h ) => ! ( $h[0] === $cb && $h[1] === $prio ) ) ); return true; }
function has_action( $hook, $cb ) { foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $h ) { if ( $h[0] === $cb ) { return $h[1]; } } return false; }
function sorted( $hook ) { $h = $GLOBALS['hooks'][ $hook ] ?? array(); usort( $h, static fn( $a, $b ) => $a[1] <=> $b[1] ); return $h; }
function do_action( $hook, ...$args ) { foreach ( sorted( $hook ) as $h ) { call_user_func_array( $h[0], $args ); } }
function apply_filters( $hook, $value, ...$args ) { foreach ( sorted( $hook ) as $h ) { $value = call_user_func_array( $h[0], array_merge( array( $value ), $args ) ); } return $value; }
function __( $s, $d = '' ) { return $s; }
function esc_html__( $s, $d = '' ) { return $s; }
function _n( $a, $b, $n, $d = '' ) { return 1 === $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function wp_salt( $k = '' ) { return 'site-secret-salt'; }
function rgar( $a, $k ) { return is_array( $a ) ? ( $a[ $k ] ?? '' ) : ''; }
function get_option( $k, $d = false ) { return $GLOBALS['opts'][ $k ] ?? $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function add_option( $k, $v = '', $dep = '', $autoload = null ) { if ( isset( $GLOBALS['opts'][ $k ] ) ) { return false; } $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function wp_date( $f, $t = null ) { return gmdate( $f, $t ?? time() ); }
function wp_timezone() { return new DateTimeZone( 'America/New_York' ); }
function admin_url( $p = '' ) { return '/wp-admin/' . $p; }
function home_url( $p = '' ) { return 'https://example-firm.com' . $p; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function get_bloginfo( $k ) { return 'Example Firm'; }
function wp_specialchars_decode( $s, $q = 0 ) { return $s; }
function current_user_can( $c ) { return true; }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_basename( $f ) { return basename( $f ); }
function register_activation_hook( ...$a ) {}
function is_admin() { return false; }
function wp_next_scheduled( $h ) { return $GLOBALS['cron'][ $h ] ?? false; }
function wp_schedule_single_event( $t, $h ) { $GLOBALS['cron'][ $h ] = $t; return true; }
function wp_clear_scheduled_hook( $h ) { unset( $GLOBALS['cron'][ $h ] ); return 0; }
function gform_update_meta( $id, $k, $v ) { $GLOBALS['meta'][ $id ][ $k ] = $v; }
function gform_get_meta( $id, $k ) { return $GLOBALS['meta'][ $id ][ $k ] ?? false; }
function scout_core_notice_email() { return 'office@example-firm.com'; }
class WP_Error { private $m; function __construct( $c = '', $m = '' ) { $this->m = $m; } function get_error_message() { return $this->m; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function wp_mail( $to, $subject, $body, $headers = '' ) {
	$GLOBALS['mail'][] = array( 'to' => $to, 'subject' => $subject, 'body' => $body );
	if ( ! $GLOBALS['mailok'] ) {
		do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', 'SMTP Error: Could not authenticate as office@example-firm.com.' ) );
	}
	return $GLOBALS['mailok'];
}
final class Scout_Hub_Health { public static function is_live(): bool { return $GLOBALS['live']; } }

// A stand-in for Gravity Forms: the hooks in the order Gravity Forms fires them.
final class GFAPI {
	public static $forms   = array();
	public static $entries = array();
	public static $next    = 100;
	public static $feeds   = 0;
	public static $after   = 0;
	public static function get_forms() { return array_values( self::$forms ); }
	public static function get_form( $id ) { return self::$forms[ $id ] ?? false; }
	public static function get_entry( $id ) { return self::$entries[ $id ] ?? new WP_Error( 'not_found', 'Entry not found' ); }
	public static function delete_entry( $id ) { if ( ! isset( self::$entries[ $id ] ) ) { return new WP_Error( 'x', 'gone' ); } unset( self::$entries[ $id ] ); return true; }
	public static function submit_form( $id, $input ) {
		$form  = self::$forms[ $id ];
		$_POST = array_merge_recursive( $_POST, $input, array( 'gform_submit' => $id ) );
		$v     = apply_filters( 'gform_validation', array( 'is_valid' => true, 'form' => $form ) );
		foreach ( (array) $form['fields'] as $f ) {
			if ( ! empty( $f['isRequired'] ) && 'email' !== $f['type'] && ! array_filter( array_keys( $_POST ), static fn( $k ) => 0 === strpos( $k, 'input_' . $f['id'] ) ) ) {
				$v['is_valid'] = false;
				$v['validation_messages'][ $f['id'] ] = '<span>This field is required.</span>';
			}
		}
		if ( ! $v['is_valid'] ) {
			return array( 'is_valid' => false, 'validation_messages' => $v['validation_messages'] ?? array() );
		}
		$entry = array( 'id' => self::$next++, 'form_id' => $id, 'status' => 'active' );
		foreach ( $_POST as $k => $val ) {
			if ( preg_match( '/^input_(\d+)$/', $k, $m ) && is_string( $val ) ) {
				$entry[ $m[1] ] = $val;
			}
		}
		if ( apply_filters( 'gform_entry_is_spam', false, $form, $entry ) ) {
			$entry['status'] = 'spam';
		}
		self::$entries[ $entry['id'] ] = $entry;
		do_action( 'gform_entry_created', $entry, $form );
		$GLOBALS['later'] = apply_filters( 'gform_is_asynchronous_notifications_enabled', true );
		self::$feeds += count( apply_filters( 'gform_addon_pre_process_feeds', array( array( 'id' => 1, 'addon_slug' => 'gravityformsmailchimp' ) ), $entry, $form ) );
		if ( 'spam' !== $entry['status'] ) {
			foreach ( (array) $form['notifications'] as $n ) {
				$email = apply_filters( 'gform_pre_send_email', array( 'to' => 'office@example-firm.com', 'subject' => 'New request', 'message' => '<p>Hello</p>', 'headers' => array( 'From' => 'From: site' ), 'attachments' => array(), 'abort_email' => false ), 'html', $n, $entry );
				$GLOBALS['last_email'] = $email;
				if ( ! $GLOBALS['mailok'] ) {
					do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', 'SMTP Error: Could not authenticate as office@example-firm.com.' ) );
				}
				do_action( 'gform_after_email', $GLOBALS['mailok'], $email['to'], $email['subject'], $email['message'], '', array(), 'html', '', '', '', '', $entry, '' );
			}
		}
		do_action( 'gform_after_submission', $entry, $form );
		return array( 'is_valid' => true, 'entry_id' => $entry['id'], 'is_spam' => 'spam' === $entry['status'] );
	}
}
// Scout Newsletter's listener, which must stand aside for the test.
final class Scout_Newsletter_Sources { public static function gf_submission( $e, $f ) { GFAPI::$after++; } }
add_action( 'gform_after_submission', array( 'Scout_Newsletter_Sources', 'gf_submission' ), 10, 2 );

require __DIR__ . '/../../scout-forms-guard/scout-forms-guard.php';
Scout_Forms_Guard::init();
require __DIR__ . '/../../scout-build/includes/class-scout-hub-form-mail.php';
require __DIR__ . '/../../scout-build/includes/class-scout-hub-form-test.php';
Scout_Hub_Form_Mail::boot();
$T = 'Scout_Hub_Form_Test';
$T::boot();
$ny = new DateTimeZone( 'America/New_York' );

/* ---- Scheduling ---- */
$m = $T::minute_for( 'https://example-firm.com/' );
ok( $m >= 0 && $m < 120 && $m === $T::minute_for( 'https://example-firm.com/' ), "each site gets its own steady minute after 2 am ($m)" );
$at = static fn( string $local ) => ( new DateTimeImmutable( $local, $ny ) )->getTimestamp();
ok( $at( '2026-11-11 02:15' ) === $T::next_run( $at( '2026-11-11 01:00' ), $ny, 15 ), 'before 2:15 am: tonight at 2:15 local' );
ok( $at( '2026-11-12 02:15' ) === $T::next_run( $at( '2026-11-11 02:15' ), $ny, 15 ), 'at or after the slot: the next night' );
ok( $at( '2026-11-12 03:59' ) === $T::next_run( $at( '2026-11-11 23:00' ), $ny, 119 ), 'the latest minute is 3:59 am' );
ok( $at( '2027-03-14 03:30' ) === $T::next_run( $at( '2027-03-13 12:00' ), $ny, 30 ), 'the night the clocks go forward still lands (2:30 does not exist, so 3:30)' );
ok( '02:10' === ( new DateTimeImmutable( '@' . $T::next_run( $at( '2026-11-01 00:30' ), $ny, 10 ) ) )->setTimezone( $ny )->format( 'H:i' ), 'the night the clocks go back still runs at 2:10 local' );
ok( $T::due( '', $at( '2026-11-11 02:15' ), $ny ), 'never ran: due' );
ok( ! $T::due( '2026-11-11', $at( '2026-11-11 23:59' ), $ny ), 'already ran tonight: not due again' );
ok( $T::due( '2026-11-11', $at( '2026-11-12 02:00' ), $ny ), 'the next local day: due' );

$T::schedule();
ok( empty( $GLOBALS['cron'][ $T::HOOK ] ), 'off by default: nothing is scheduled' );
ok( null === $T::build_row( $T::settings(), $T::state(), time(), '', static fn( $i ) => 'Contact', '' ), 'off by default: no Site status row' );
ok( false === $T::run( true ), 'off by default: Run it now does nothing' );

/* ---- The answers ---- */
$form = array(
	'id'            => 3,
	'title'         => 'Contact',
	'is_active'     => true,
	'notifications' => array( array( 'id' => 'a', 'name' => 'Office notice', 'isActive' => true, 'event' => 'form_submission' ), array( 'id' => 'b', 'name' => 'Auto reply', 'isActive' => true ) ),
	'fields'        => array(
		array( 'id' => 1, 'type' => 'name', 'isRequired' => true, 'inputs' => array( array( 'id' => '1.3' ), array( 'id' => '1.6' ) ) ),
		array( 'id' => 2, 'type' => 'email', 'isRequired' => true, 'emailConfirmEnabled' => true ),
		array( 'id' => 3, 'type' => 'phone', 'isRequired' => true, 'phoneFormat' => 'standard' ),
		array( 'id' => 4, 'type' => 'textarea', 'isRequired' => true ),
		array( 'id' => 5, 'type' => 'select', 'isRequired' => true, 'choices' => array( array( 'text' => 'Pick one', 'value' => '' ), array( 'text' => 'Roof', 'value' => 'roof' ) ) ),
		array( 'id' => 6, 'type' => 'checkbox', 'isRequired' => false, 'choices' => array( array( 'text' => 'Newsletter', 'value' => 'yes' ) ), 'inputs' => array( array( 'id' => '6.1' ) ) ),
		array( 'id' => 7, 'type' => 'consent', 'isRequired' => true, 'checkboxLabel' => 'I agree' ),
		array( 'id' => 8, 'type' => 'text', 'isRequired' => false ),
		array( 'id' => 9, 'type' => 'website', 'isRequired' => true ),
		array( 'id' => 10, 'type' => 'date', 'isRequired' => true, 'dateType' => 'datepicker', 'dateFormat' => 'ymd_dash' ),
		array( 'id' => 11, 'type' => 'html' ),
	),
);
$a = $T::answers( $form, 'office@example-firm.com', 'https://example-firm.com/', strtotime( '2026-11-11 12:00 UTC' ) );
$v = $a['values'];
ok( '' === $a['skip'], 'a plain contact form can be tested' );
ok( 'office@example-firm.com' === $v['input_2'] && 'office@example-firm.com' === $v['input_2_2'], 'email fields get the notice address, confirmation too, so a reply to "the visitor" reaches the business' );
ok( isset( $v['input_1_3'], $v['input_1_6'] ) && '(555) 555-0100' === $v['input_3'] && 'roof' === $v['input_5'] && '1' === $v['input_7_1'], 'name, phone (a 555 number), the first real choice and the consent box are filled' );
ok( ! isset( $v['input_6_1'] ) && ! isset( $v['input_8'] ), 'boxes and fields that are not required stay empty, so no newsletter box is ticked' );
ok( 'https://example-firm.com/' === $v['input_9'] && '2026-11-11' === $v['input_10'], 'the website field is the site itself and the date follows the field\'s format' );
$skip = static fn( array $field, array $extra = array() ) => $T::answers( array( 'fields' => array( $field ) ) + $extra, 'a@b.co', 'https://x.co/', time() )['skip'];
ok( 'it takes payments' === $skip( array( 'id' => 1, 'type' => 'product' ) ), 'a form that takes payments is never sent' );
ok( 'it has a CAPTCHA' === $skip( array( 'id' => 1, 'type' => 'captcha' ) ), 'a CAPTCHA form is skipped with the reason' );
ok( 'it needs a file upload' === $skip( array( 'id' => 1, 'type' => 'fileupload', 'isRequired' => true ) ), 'a required upload is skipped with the reason' );
ok( '' === $skip( array( 'id' => 1, 'type' => 'fileupload' ) ), 'an upload that is not required is left empty' );
ok( 'it makes a post' === $skip( array( 'id' => 1, 'type' => 'post_title' ) ), 'a form that makes a post is skipped' );
ok( 'it needs a login' === $skip( array( 'id' => 1, 'type' => 'text' ), array( 'requireLogin' => true ) ), 'a form behind a login is skipped' );
ok( 'it has a required mystery field' === $skip( array( 'id' => 1, 'type' => 'mystery', 'isRequired' => true ) ), 'an unknown required field is skipped, never guessed' );

/* ---- Marking the email ---- */
$e = $T::marked( array( 'subject' => 'New request', 'message' => '<p>Hi</p>', 'headers' => array() ), 'html' );
ok( '[Test] New request' === $e['subject'] && 0 === strpos( $e['message'], '<p ' ) && false !== strpos( $e['message'], 'Test only.' ) && 'X-Form-Test: nightly' === $e['headers']['X-Form-Test'], 'subject prefix, a first line and a header mark the test' );
ok( '[Test] New request' === $T::marked( $e, 'html' )['subject'], 'the prefix is added once' );
$e = $T::marked( array( 'subject' => 'Hi', 'message' => 'Body', 'headers' => 'From: site' ), 'text' );
ok( 0 === strpos( $e['message'], 'Test only.' ) && "From: site\r\nX-Form-Test: nightly" === $e['headers'], 'plain text emails and string headers are marked too' );
ok( $T::marked( array( 'subject' => 'x' ), 'html' ) !== $T::mark_email( array( 'subject' => 'x' ), 'html' ), 'outside the test a visitor\'s email is left exactly as it was' );

/* ---- The two-failure rule ---- */
$s0 = $T::state();
$s1 = $T::apply( $s0, array( 3 => array( 'r' => 'failed', 'n' => 1, 'ok' => 0, 'e' => 'x' ) ), 1000, $ny );
ok( 1 === $s1['streak'][3] && ! $T::should_notify( 1 ), 'one failure: count 1, no email' );
$s2 = $T::apply( $s1, array( 3 => array( 'r' => 'failed', 'n' => 1, 'ok' => 0, 'e' => 'x' ) ), 2000, $ny );
ok( 2 === $s2['streak'][3] && $T::should_notify( 2 ), 'two in a row: count 2, the notice email goes' );
ok( ! $T::should_notify( 3 ) && ! $T::should_notify( 8 ) && $T::should_notify( 9 ), 'then once a week while it keeps failing' );
$s3 = $T::apply( $s2, array( 3 => array( 'r' => 'refused', 'n' => 0, 'ok' => 0, 'e' => 'x' ) ), 3000, $ny );
ok( 2 === $s3['streak'][3], 'a refused test says nothing about email, so the count stays' );
$s4 = $T::apply( $s3, array( 3 => array( 'r' => 'sent', 'n' => 2, 'ok' => 2, 'e' => '' ) ), 4000, $ny );
ok( empty( $s4['streak'] ), 'a send clears the count' );
$s5 = $T::apply( $s1, array(), 5000, $ny );
ok( empty( $s5['streak'] ), 'a form no longer ticked drops its count' );

/* ---- Rows ---- */
$on    = array( 'on' => true, 'forms' => array( 3 ), 'saved' => true );
$title = static fn( $id ) => array( 3 => 'Contact', 4 => 'Quote' )[ $id ] ?? "Form $id";
$now   = 1000000;
$row   = static fn( array $state, string $here = '' ) => $T::build_row( $on, $state + $T::state(), $now, $here, $title, '/settings' );
$r = $row( array(), 'staging' );
ok( 'Off on staging' === $r['value'] && 'good' === $r['state'] && false !== strpos( $r['note'], 'staging email guard' ), 'staging: the test waits for the live site' );
$r = $T::build_row( array( 'on' => true, 'forms' => array() ), $T::state(), $now, '', $title, '' );
ok( 'warn' === $r['state'] && 'No forms picked' === $r['value'], 'on with no forms picked: a look' );
ok( 'First test tonight' === $row( array() )['value'], 'on, not run yet: first test tonight' );
$r = $row( array( 'last_run' => $now - 3600, 'results' => array( 3 => array( 'r' => 'sent', 'n' => 2, 'ok' => 2, 'e' => '' ) ) ) );
ok( 'Sent last night' === $r['value'] && 'good' === $r['state'] && false !== strpos( $r['note'], 'Contact (form 3) (2 emails)' ) && false !== strpos( $r['note'], 'handed to the mailer' ), 'all sent: green, and it says the emails were handed to the mailer, not delivered' );
$r = $row( array( 'last_run' => $now - 3600, 'results' => array( 3 => array( 'r' => 'failed', 'n' => 1, 'ok' => 0, 'e' => 'SMTP Error' ) ), 'streak' => array( 3 => 1 ) ) );
ok( 'good' === $r['state'] && 'Trying again tonight' === $r['value'] && false !== strpos( $r['note'], 'One miss can be a blip' ), 'one failure: not red, it tries again tonight' );
$r = $row( array( 'last_run' => $now - 3600, 'results' => array( 3 => array( 'r' => 'failed', 'n' => 1, 'ok' => 0, 'e' => 'SMTP Error' ) ), 'streak' => array( 3 => 2 ) ) );
ok( 'bad' === $r['state'] && 'Failed: Contact did not send' === $r['value'] && false !== strpos( $r['note'], '2 nights in a row: SMTP Error' ), 'two failures in a row: red, naming the form and the reason' );
$r = $row( array( 'last_run' => $now - 3600, 'results' => array( 3 => array( 'r' => 'skipped', 'n' => 0, 'ok' => 0, 'e' => 'it needs a file upload' ) ) ) );
ok( 'warn' === $r['state'] && false !== strpos( $r['note'], 'Send it by hand' ), 'a form that cannot be tested: a look, with the reason' );
$r = $row( array( 'last_run' => $now - 40 * 3600, 'results' => array( 3 => array( 'r' => 'sent', 'n' => 1, 'ok' => 1, 'e' => '' ) ) ) );
ok( 'warn' === $r['state'] && 0 === strpos( $r['value'], 'No test since' ), 'no run in 36 hours: the scheduled run did not start' );
$r = $row( array( 'last_run' => $now - 3600, 'results' => array( 3 => array( 'r' => 'sent', 'n' => 1, 'ok' => 1, 'e' => '' ) ), 'pending' => array( 7 ) ) );
ok( 'warn' === $r['state'] && false !== strpos( $r['note'], 'could not be deleted' ), 'a test entry left behind: a look' );

/* ---- Cleanup ---- */
$have = array( 1 => true, 2 => true, 3 => true );
$mark = array( 1 => true, 3 => true );
$gone = array();
$c    = $T::cleanup( array( 1, 2, 3, 4, 3 ), static fn( $id ) => ! empty( $have[ $id ] ), static fn( $id ) => ! empty( $mark[ $id ] ), static function ( $id ) use ( &$gone ) { $gone[] = $id; return 3 !== $id; } );
ok( array( 1, 3 ) === $gone, 'only entries carrying the test mark are deleted, never a real lead' );
ok( array( 1 ) === $c['deleted'] && array( 3 ) === $c['left'], 'an entry that would not delete stays on the list for tomorrow' );

/* ---- Forms Guard: the same token, the guard unchanged ---- */
$guard = (string) file_get_contents( __DIR__ . '/../../scout-forms-guard/scout-forms-guard.php' );
ok( false !== strpos( $guard, "hash_hmac( 'sha256', \$form_id . '|' . \$ts, wp_salt( 'auth' ) )" ) && false !== strpos( $guard, "const TS  = 'scout_ts';" ) && false !== strpos( $guard, "const SIG = 'scout_sig';" ), 'Forms Guard still signs its token the way the test does (update both together)' );
$_POST = array( 'scout_ts' => (string) ( time() - 30 ), 'scout_sig' => $T::guard_token( 3, time() - 30 ) );
ok( true === Scout_Forms_Guard::validate( array( 'is_valid' => true, 'form' => array( 'id' => 3 ) ) )['is_valid'], 'the test\'s token passes the real guard' );
$_POST = array( 'scout_ts' => (string) ( time() - 30 ), 'scout_sig' => $T::guard_token( 3, time() - 30 ), 'scout_form_test' => 'anything' );
ok( false === Scout_Forms_Guard::validate( array( 'is_valid' => true, 'form' => array( 'id' => 4 ) ) )['is_valid'], 'a token for another form still fails' );
$_POST = array( 'scout_form_test' => 'anything' );
ok( false === Scout_Forms_Guard::validate( array( 'is_valid' => true, 'form' => array( 'id' => 3 ) ) )['is_valid'], 'a visitor who sends the test field without a token is still refused' );
$_POST = array( 'scout_form_test' => 'guess' );
ok( ! $T::is_test(), 'outside a run, nothing a visitor sends makes a submission look like the test' );
ok( true === $T::not_later( true ), 'outside a run, background notifications are left as the site set them' );
$_POST = array();

/* ---- A full run ---- */
GFAPI::$forms = array( 3 => $form, 4 => array( 'id' => 4, 'title' => 'Quote', 'is_active' => true, 'notifications' => array( array( 'name' => 'Office', 'isActive' => true ) ), 'fields' => array( array( 'id' => 1, 'type' => 'fileupload', 'isRequired' => true ) ) ) );
update_option( $T::OPTION, array( 'on' => true, 'forms' => array( 3, 4 ), 'saved' => true ) );
$T::schedule();
ok( ! empty( $GLOBALS['cron'][ $T::HOOK ] ), 'switched on: one nightly run is booked' );
$GLOBALS['live'] = false;
ok( false === $T::run( true ) && 'copy' === $T::state()['skipped'] && ! GFAPI::$entries && ! $GLOBALS['mail'], 'not the live site: nothing is sent' );
$GLOBALS['live'] = true;

$T::nightly();
$st = $T::state();
ok( 'sent' === $st['results'][3]['r'] && 2 === $st['results'][3]['n'], 'the contact form was sent and both its emails went out' );
ok( 'skipped' === $st['results'][4]['r'] && 'it needs a file upload' === $st['results'][4]['e'], 'the upload form was skipped with its reason' );
ok( '[Test] New request' === $GLOBALS['last_email']['subject'] && isset( $GLOBALS['last_email']['headers']['X-Form-Test'] ), 'the real notification carried the test marks' );
ok( ! GFAPI::$entries, 'the test entry was deleted' );
ok( 0 === GFAPI::$feeds, 'no add-on feed ran (no Mailchimp, no CRM)' );
ok( false === $GLOBALS['later'], 'the test\'s notifications are sent now, not queued for later' );
ok( 0 === GFAPI::$after, 'Scout Newsletter\'s listener stood aside' );
ok( false !== has_action( 'gform_after_submission', array( 'Scout_Newsletter_Sources', 'gf_submission' ) ), 'and was put back after' );
ok( array() === Scout_Hub_Form_Mail::data()['log'], 'the test never counts in Form emails sent' );
ok( array() === $_POST, 'the request\'s own form data is put back' );
ok( ! $st['pending'] && ! isset( $GLOBALS['opts'][ $T::LOCK ] ), 'nothing left to clean up, and the lock is gone' );
ok( ! empty( $GLOBALS['cron'][ $T::HOOK ] ) && $GLOBALS['cron'][ $T::HOOK ] > time(), 'the next night is booked' );
ok( false === $T::run( false ), 'a second start the same night does nothing' );
$stored = var_export( array( $GLOBALS['opts'][ $T::STATE ], $GLOBALS['opts'][ $T::OPTION ] ), true );
ok( false === strpos( $stored, '@' ) && false === strpos( $stored, 'Nightly' ), 'no address or answer is stored' );

// A visitor's submission between runs is untouched.
$_POST = array( 'scout_ts' => (string) ( time() - 30 ), 'scout_sig' => $T::guard_token( 3, time() - 30 ), 'input_1_3' => 'Pat', 'input_1_6' => 'Lee', 'input_3' => '1', 'input_4' => 'Hi', 'input_5' => 'roof', 'input_7_1' => '1', 'input_9' => 'x', 'input_10' => 'x' );
$res   = GFAPI::submit_form( 3, array() );
ok( 'New request' === $GLOBALS['last_email']['subject'] && 1 === GFAPI::$feeds && 1 === GFAPI::$after && 2 === count( Scout_Hub_Form_Mail::data()['log'] ), 'a visitor\'s entry gets its feeds, its plain subject and its count' );
GFAPI::$entries = array();
GFAPI::$feeds   = 0;
GFAPI::$after   = 0;
$_POST          = array();

// The mailer fails two nights in a row: one notice email, after the second.
$GLOBALS['mailok'] = false;
update_option( $T::OPTION, array( 'on' => true, 'forms' => array( 3 ), 'saved' => true ) );
$GLOBALS['mail'] = array();
$T::run( true );
ok( 1 === $T::state()['streak'][3] && ! $GLOBALS['mail'], 'first failure: no notice email' );
ok( false !== strpos( $T::state()['results'][3]['e'], 'Office notice: SMTP Error: Could not authenticate as [address]' ), 'the reason names the notification, with the address taken out' );
$T::run( true );
ok( 2 === $T::state()['streak'][3] && 1 === count( $GLOBALS['mail'] ) && 'office@example-firm.com' === $GLOBALS['mail'][0]['to'], 'second failure in a row: one notice to the notice address' );
ok( false !== strpos( $GLOBALS['mail'][0]['body'], 'Contact: did not send on 2 nights in a row' ) && false === strpos( $GLOBALS['mail'][0]['body'], 'Scout' ), 'the notice names the form and never names Scout (white-label safe)' );
ok( ! GFAPI::$entries, 'failed tests are cleaned up too' );

// A run that stopped halfway: the entry is cleaned up the next night.
$GLOBALS['mailok'] = true;
GFAPI::$entries    = array( 500 => array( 'id' => 500, 'form_id' => 3 ), 501 => array( 'id' => 501, 'form_id' => 3 ) );
gform_update_meta( 500, $T::META, '1' );
$st            = $T::state();
$st['pending'] = array( 500, 501 );
update_option( $T::STATE, $st );
$T::run( true );
ok( ! isset( GFAPI::$entries[500] ) && isset( GFAPI::$entries[501] ), 'the marked leftover is deleted; an unmarked entry is never touched' );
ok( array() === $T::state()['pending'] && 'sent' === $T::state()['results'][3]['r'], 'then tonight\'s test runs as usual' );

// Spam filters and refusals are a look, not a failure.
$_POST = array();
$r     = $T::read( array( 'is_valid' => false, 'validation_messages' => array( 2 => '<b>This email is already used.</b>' ) ), '', array(), '' );
ok( 'refused' === $r['r'] && false !== strpos( $r['e'], 'This email is already used' ), 'a refused test keeps Gravity Forms\' reason' );
ok( 'refused' === $T::read( array( 'is_valid' => true, 'entry_id' => 9 ), '', array(), 'spam' )['r'], 'a test caught as spam is refused, not failed' );
ok( 'failed' === $T::read( array( 'is_valid' => true, 'entry_id' => 9 ), '', array(), 'active' )['r'], 'no email at all is a failure' );

// Switched off: the booked run is cleared.
update_option( $T::OPTION, array( 'on' => false, 'forms' => array( 3 ), 'saved' => true ) );
$T::schedule();
ok( empty( $GLOBALS['cron'][ $T::HOOK ] ), 'switched off: the nightly run is cleared' );

/* ---- Suggestions ---- */
ok( array( 3, 4 ) === $T::from_setup( array( 'form_test' => array( 'forms' => array( 3, 'quote', 99 ) ) ), array( 3 => 'Contact', 4 => 'Quote' ) ), 'scout-setup.json names forms by id or title' );
ok( array() === $T::from_setup( array(), array( 3 => 'Contact' ) ), 'no form_test section: no suggestion' );
ok( array( 3, 7 ) === $T::form_ids_in( '[gravityform id="3" title="false"] text <!-- wp:gravityforms/form {"formId":"7","title":false} /--> [gravityform id=3]' ), 'forms on a page are found by shortcode and block' );

exit( $fail ? 1 : 0 );
