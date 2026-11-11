<?php
/**
 * The nightly form test (Site Standard rule 31: a form with no received test
 * submission is a blocker).
 *
 * Off on every site until an operator switches it on in Scout tools, Features,
 * and ticks the forms to test. Nothing else turns it on: the theme's
 * scout-setup.json can only suggest which forms ("form_test": { "forms": [ 3,
 * "Contact" ] }). Updating the plugin changes nothing for visitors or clients.
 *
 * Once a night, around 2 am in the site's own time zone (each site picks its
 * own minute so they don't all run at once), each ticked Gravity Forms form
 * gets one test submission through GFAPI::submit_form. The form's own
 * notifications go to their real addresses, with "[Test]" at the start of the
 * subject, a first line that says it is a test, and an X-Form-Test header. Any
 * email field gets the site's notice address (scout_core_notice_email()), so
 * an auto-reply to "the visitor" goes to the business, never a stranger.
 *
 * How sending is confirmed: Gravity Forms reports each notification's result
 * (gform_after_email), which is wp_mail's answer: the mailer, or the SMTP
 * server it signs in to, took the email. Nothing on the site can see the
 * inbox, so the row says "handed to the mailer", never "delivered".
 *
 * What the test never does:
 * - leave an entry: it is deleted right after the check. Its id is saved
 *   before the emails go out, so a run that stops halfway is cleaned up the
 *   next night. Only entries this test marked are ever deleted.
 * - reach a CRM, Mailchimp or any add-on feed: feeds are emptied for the test
 *   (gform_addon_pre_process_feeds), and Scout Newsletter's form listener is
 *   paused. Other plugins can ask scout_build_form_test_running().
 * - count in analytics: it runs on the server, so no page loads and no
 *   browser event fires, and the deleted entry leaves Gravity Forms' counts.
 * - run on staging or a copy on someone's computer: the staging email guard
 *   would drop the client's emails anyway, so the test waits for the live site.
 * - get past Scout Forms Guard by a back door: it carries the same signed
 *   token a real page gives a visitor, made here with the site's own secret
 *   (the auth salt), and only inside this run. The guard is unchanged.
 *
 * One failure turns nothing red, since one blip should not alarm anyone. Two
 * failures in a row for the same form turn the Site status row red and email
 * the notice address, then once a week while it keeps failing.
 *
 * Kept, in options: the switch and the ticked form ids (scout_hub_form_test),
 * and each form's last result (sent, failed, refused or skipped, how many
 * emails went, the error with addresses taken out), the failure count and
 * the ids of test entries waiting to be deleted (scout_hub_form_test_state).
 * Nothing about visitors.
 *
 * @package Scout_Build
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'scout_build_form_test_running' ) ) {
	/** True while the nightly form test is submitting a form, so other plugins can stand aside. */
	function scout_build_form_test_running(): bool {
		return Scout_Hub_Form_Test::is_test();
	}
}

final class Scout_Hub_Form_Test {

	const OPTION = 'scout_hub_form_test';       // { on, forms: int[], saved: bool, by, at }.
	const STATE  = 'scout_hub_form_test_state'; // { last_run, last_day, results: { id: {r, n, ok, e, t} }, streak: { id: n }, pending: int[], skipped }.
	const HOOK   = 'scout_hub_form_test_run';
	const ACTION = 'scout_hub_form_test';
	const LOCK   = 'scout_hub_form_test_lock';
	const META   = 'scout_form_test';           // Entry meta on a test entry, so cleanup deletes only those.
	const FIELD  = 'scout_form_test';           // The hidden field carried in the test submission.
	const HOUR   = 2;                           // Local hour the run starts, plus each site's own minute (0 to 119).
	const STALE  = 36 * 3600;                   // No run for this long reads as "the nightly run didn't start".
	const ALARM  = 2;                           // Failures in a row before the row turns red.

	/** @var array|null The submission under way: { form, secret, emails: [ {n, ok, e} ], entry }. */
	private static $run = null;

	public static function boot(): void {
		add_action( self::HOOK, array( __CLASS__, 'nightly' ) );
		add_action( 'admin_init', array( __CLASS__, 'schedule' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		// Each checks is_test() first, so a visitor's submission passes straight through.
		add_filter( 'gform_pre_send_email', array( __CLASS__, 'mark_email' ), 1, 4 );
		add_filter( 'gform_addon_pre_process_feeds', array( __CLASS__, 'no_feeds' ), 9999, 3 );
		add_action( 'gform_entry_created', array( __CLASS__, 'entry_created' ), 1, 2 );
		// Assumes Gravity Forms' filter for sending notifications in the background
		// (newer versions). The test's emails must go out in this request to be read.
		add_filter( 'gform_is_asynchronous_notifications_enabled', array( __CLASS__, 'not_later' ), 9999 );
	}

	/** The test's notifications are sent now, never queued for later. */
	public static function not_later( $later ) {
		return self::is_test() ? false : $later;
	}

	/* ---- Settings ------------------------------------------------------ */

	/** @return array{on:bool, forms:int[], saved:bool, by:string, at:int} */
	public static function settings(): array {
		$s = get_option( self::OPTION, array() );
		$s = is_array( $s ) ? $s : array();
		return array(
			'on'    => ! empty( $s['on'] ),
			'forms' => array_values( array_unique( array_filter( array_map( 'intval', (array) ( $s['forms'] ?? array() ) ) ) ) ),
			'saved' => ! empty( $s['saved'] ),
			'by'    => (string) ( $s['by'] ?? '' ),
			'at'    => (int) ( $s['at'] ?? 0 ),
		);
	}

	/** @return array{last_run:int, last_day:string, results:array, streak:array, pending:int[], skipped:string} */
	public static function state(): array {
		$s = get_option( self::STATE, array() );
		$s = is_array( $s ) ? $s : array();
		return array(
			'last_run' => (int) ( $s['last_run'] ?? 0 ),
			'last_day' => (string) ( $s['last_day'] ?? '' ),
			'results'  => is_array( $s['results'] ?? null ) ? $s['results'] : array(),
			'streak'   => is_array( $s['streak'] ?? null ) ? $s['streak'] : array(),
			'pending'  => array_values( array_filter( array_map( 'intval', (array) ( $s['pending'] ?? array() ) ) ) ),
			'skipped'  => (string) ( $s['skipped'] ?? '' ),
		);
	}

	private static function save_state( array $state ): void {
		update_option( self::STATE, $state, false );
	}

	/**
	 * The forms the theme's scout-setup.json suggests: "form_test": { "forms":
	 * [ 3, "Contact" ] }, by id or exact title. Only a suggestion for the
	 * Features box; it never switches the test on.
	 *
	 * @param array $setup The parsed setup file.
	 * @param array $forms id => title of the active forms.
	 * @return int[]
	 */
	public static function from_setup( array $setup, array $forms ): array {
		$out = array();
		foreach ( (array) ( $setup['form_test']['forms'] ?? array() ) as $want ) {
			if ( is_int( $want ) || ( is_string( $want ) && ctype_digit( $want ) ) ) {
				$id = (int) $want;
				if ( isset( $forms[ $id ] ) ) {
					$out[] = $id;
				}
				continue;
			}
			$want = strtolower( trim( (string) $want ) );
			foreach ( $forms as $id => $title ) {
				if ( '' !== $want && strtolower( trim( (string) $title ) ) === $want ) {
					$out[] = (int) $id;
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Form ids placed in a page's content, by shortcode or block.
	 *
	 * @return int[]
	 */
	public static function form_ids_in( string $content ): array {
		$ids = array();
		if ( preg_match_all( '/\[gravityforms?\b[^\]]*?\bid\s*=\s*["\']?(\d+)/i', $content, $m ) ) {
			$ids = array_merge( $ids, $m[1] );
		}
		if ( preg_match_all( '/<!--\s*wp:gravityforms\/form\s+\{[^}]*?"formId"\s*:\s*"?(\d+)/i', $content, $m ) ) {
			$ids = array_merge( $ids, $m[1] );
		}
		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/** Form ids on published pages and posts. Read only for the Features box. */
	private static function on_pages(): array {
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- read once when an operator opens the box.
		$rows = (array) $wpdb->get_col( "SELECT post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND ( post_content LIKE '%[gravityform%' OR post_content LIKE '%wp:gravityforms/form%' ) LIMIT 200" );
		$ids  = array();
		foreach ( $rows as $content ) {
			$ids = array_merge( $ids, self::form_ids_in( (string) $content ) );
		}
		return array_values( array_unique( $ids ) );
	}

	/* ---- Scheduling ---------------------------------------------------- */

	/** Each site's own minute after 2 am (0 to 119), so sites don't all run at once. */
	public static function minute_for( string $home ): int {
		return (int) ( sprintf( '%u', crc32( $home ) ) % 120 );
	}

	/**
	 * The next start after $now: today at 2 am plus the site's minute in the
	 * site's time zone, or tomorrow when that has passed. DST safe.
	 */
	public static function next_run( int $now, DateTimeZone $tz, int $minute ): int {
		$at = ( new DateTimeImmutable( '@' . $now ) )->setTimezone( $tz )->setTime( self::HOUR, 0 )->modify( '+' . max( 0, $minute ) . ' minutes' );
		if ( $at->getTimestamp() <= $now ) {
			$at = ( new DateTimeImmutable( '@' . $now ) )->setTimezone( $tz )->modify( '+1 day' )->setTime( self::HOUR, 0 )->modify( '+' . max( 0, $minute ) . ' minutes' );
		}
		return $at->getTimestamp();
	}

	/** The site's local date for a time, used so the test runs once a night at most. */
	public static function local_day( int $t, DateTimeZone $tz ): string {
		return ( new DateTimeImmutable( '@' . $t ) )->setTimezone( $tz )->format( 'Y-m-d' );
	}

	/** Whether tonight's test is still to run: nothing ran on this local date yet. */
	public static function due( string $last_day, int $now, DateTimeZone $tz ): bool {
		return self::local_day( $now, $tz ) !== $last_day;
	}

	private static function tz(): DateTimeZone {
		return function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
	}

	/** One single event at the next start while the test is on; none while it is off. */
	public static function schedule(): void {
		$on   = self::settings()['on'];
		$next = wp_next_scheduled( self::HOOK );
		if ( $on && ! $next ) {
			wp_schedule_single_event( self::next_run( time(), self::tz(), self::minute_for( home_url( '/' ) ) ), self::HOOK );
		} elseif ( ! $on && $next ) {
			self::unschedule();
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/** Why the test can't run on this copy, or ''. */
	public static function blocked_here(): string {
		if ( class_exists( 'Scout_Core_Staging' ) && Scout_Core_Staging::is_staging() ) {
			return 'staging';
		}
		if ( class_exists( 'Scout_Hub_Health' ) && ! Scout_Hub_Health::is_live() ) {
			return 'copy';
		}
		return '';
	}

	/* ---- The run ------------------------------------------------------- */

	/** The scheduled run. The next one is booked first, so a run that stops halfway still has tomorrow. */
	public static function nightly(): void {
		self::unschedule();
		if ( ! self::settings()['on'] ) {
			return;
		}
		wp_schedule_single_event( self::next_run( time() + 60, self::tz(), self::minute_for( home_url( '/' ) ) ), self::HOOK );
		self::run( false );
	}

	/**
	 * Test every ticked form. $now_too skips the once-a-night check (the Run it
	 * now button).
	 *
	 * @return bool Whether the forms were tested.
	 */
	public static function run( bool $now_too ): bool {
		$settings = self::settings();
		$state    = self::state();
		$now      = time();
		$tz       = self::tz();
		if ( ! $settings['on'] || ! class_exists( 'GFAPI' ) || ! method_exists( 'GFAPI', 'submit_form' ) ) {
			return false;
		}
		$here = self::blocked_here();
		if ( '' !== $here ) {
			$state['skipped'] = $here;
			self::save_state( $state );
			return false;
		}
		if ( ! $now_too && ! self::due( $state['last_day'], $now, $tz ) ) {
			return false;
		}
		// One run at a time: WP-Cron can start the same event twice.
		if ( ! add_option( self::LOCK, $now, '', false ) ) {
			if ( $now - (int) get_option( self::LOCK, 0 ) < 15 * 60 ) {
				return false;
			}
			update_option( self::LOCK, $now, false );
		}
		try {
			$state['skipped'] = '';
			$state            = self::sweep( $state );
			$results          = array();
			foreach ( $settings['forms'] as $id ) {
				$results[ $id ] = self::test_form( (int) $id );
			}
			$state = self::apply( $state, $results, $now, $tz );
			// test_form() adds and removes pending ids as it goes; keep its list.
			$state['pending'] = self::state()['pending'];
			self::save_state( $state );
			self::maybe_notify( $state, $results );
		} finally {
			delete_option( self::LOCK );
		}
		return true;
	}

	/**
	 * Fold one night's results into the state: each form's result, and its
	 * failures in a row. Sent clears the count; refused and skipped leave it,
	 * since they say nothing about the emails. Pure.
	 *
	 * @param array $results id => { r: sent|failed|refused|skipped, n, ok, e }.
	 */
	public static function apply( array $state, array $results, int $now, DateTimeZone $tz ): array {
		$state['last_run'] = $now;
		$state['last_day'] = self::local_day( $now, $tz );
		$state['results']  = array();
		$streak            = array();
		foreach ( $results as $id => $r ) {
			$id                        = (int) $id;
			$r['t']                    = $now;
			$state['results'][ $id ]   = $r;
			$was                       = (int) ( $state['streak'][ $id ] ?? 0 );
			$streak[ $id ]             = 'failed' === $r['r'] ? $was + 1 : ( 'sent' === $r['r'] ? 0 : $was );
		}
		// Forms no longer ticked drop their count.
		$state['streak'] = array_filter( $streak );
		return $state;
	}

	/** Whether a failure count calls for the notice email: at two, then once a week. Pure. */
	public static function should_notify( int $streak ): bool {
		return $streak >= self::ALARM && 0 === ( $streak - self::ALARM ) % 7;
	}

	/**
	 * Clean up test entries an earlier run left behind. Only an entry that
	 * still carries this test's mark is deleted. Pure apart from the callbacks.
	 *
	 * @param int[]    $pending Entry ids saved before their emails went out.
	 * @param callable $exists  id => bool, the entry is still there.
	 * @param callable $marked  id => bool, the entry carries the test mark.
	 * @param callable $delete  id => bool, deleted.
	 * @return array{left:int[], deleted:int[]}
	 */
	public static function cleanup( array $pending, callable $exists, callable $marked, callable $delete ): array {
		$left    = array();
		$deleted = array();
		foreach ( array_unique( array_map( 'intval', $pending ) ) as $id ) {
			if ( $id <= 0 || ! $exists( $id ) ) {
				continue; // Already gone.
			}
			if ( ! $marked( $id ) ) {
				continue; // Not a test entry: never touch it.
			}
			if ( $delete( $id ) ) {
				$deleted[] = $id;
			} else {
				$left[] = $id;
			}
		}
		return array( 'left' => $left, 'deleted' => $deleted );
	}

	/** Delete leftover test entries from an earlier run. */
	private static function sweep( array $state ): array {
		if ( ! $state['pending'] ) {
			return $state;
		}
		$done             = self::cleanup( $state['pending'], array( __CLASS__, 'entry_exists' ), array( __CLASS__, 'entry_marked' ), array( __CLASS__, 'delete_entry' ) );
		$state['pending'] = $done['left'];
		self::save_state( $state );
		return $state;
	}

	public static function entry_exists( int $id ): bool {
		$e = GFAPI::get_entry( $id );
		return is_array( $e ) && ! empty( $e['id'] );
	}

	public static function entry_marked( int $id ): bool {
		return function_exists( 'gform_get_meta' ) && '1' === (string) gform_get_meta( $id, self::META );
	}

	public static function delete_entry( int $id ): bool {
		$r = GFAPI::delete_entry( $id );
		return true === $r || ! self::entry_exists( $id );
	}

	private static function pending( int $id, bool $add ): void {
		$state = self::state();
		$list  = $state['pending'];
		$list  = $add ? array_merge( $list, array( $id ) ) : array_diff( $list, array( $id ) );
		$state['pending'] = array_values( array_unique( array_map( 'intval', $list ) ) );
		self::save_state( $state );
	}

	/* ---- One form ------------------------------------------------------ */

	/** Whether a form sends any email when it is sent. */
	public static function has_notifications( array $form ): bool {
		foreach ( (array) ( $form['notifications'] ?? array() ) as $n ) {
			if ( is_array( $n ) && ( ! isset( $n['isActive'] ) || $n['isActive'] ) && 'form_submission' === (string) ( $n['event'] ?? 'form_submission' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Submit one form and read what its emails did.
	 *
	 * @return array{r:string, n:int, ok:int, e:string}
	 */
	public static function test_form( int $id ): array {
		$form = GFAPI::get_form( $id );
		if ( ! is_array( $form ) || empty( $form['is_active'] ) || ! empty( $form['is_trash'] ) ) {
			return self::result( 'skipped', 0, 0, __( 'the form is off or deleted', 'scout-build' ) );
		}
		if ( ! self::has_notifications( $form ) ) {
			return self::result( 'failed', 0, 0, __( 'the form has no email turned on', 'scout-build' ) );
		}
		$fill = self::answers( $form, Scout_Hub_Form_Mail::notice_to(), home_url( '/' ), time() );
		if ( '' !== $fill['skip'] ) {
			return self::result( 'skipped', 0, 0, $fill['skip'] );
		}
		$secret     = bin2hex( random_bytes( 16 ) );
		$ts         = time() - 30; // Forms Guard's time trap wants at least 3 seconds.
		$values     = $fill['values'] + array(
			self::FIELD => $secret,
			'scout_ts'  => (string) $ts,
			'scout_sig' => self::guard_token( $id, $ts ),
		);
		self::$run  = array( 'form' => $id, 'secret' => $secret, 'emails' => array(), 'entry' => 0 );
		$post       = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- kept to put back after the test.
		$newsletter = array( 'Scout_Newsletter_Sources', 'gf_submission' );
		$paused     = function_exists( 'has_action' ) && false !== has_action( 'gform_after_submission', $newsletter );
		if ( $paused ) {
			remove_action( 'gform_after_submission', $newsletter, 10 );
		}
		$result = null;
		$error  = '';
		$_POST  = array(); // Only the test answers, never what the request that started it carried.
		try {
			$result = GFAPI::submit_form( $id, $values );
		} catch ( Throwable $e ) {
			$error = $e->getMessage();
		} finally {
			$_POST = $post;
			if ( $paused ) {
				add_action( 'gform_after_submission', $newsletter, 10, 2 );
			}
		}
		$run       = self::$run;
		self::$run = null;
		$entry     = (int) ( is_array( $result ) ? ( $result['entry_id'] ?? 0 ) : 0 );
		$entry     = $entry ? $entry : (int) $run['entry'];
		$out       = self::read( $result, $error, (array) $run['emails'], $entry ? self::entry_status( $entry ) : '' );
		if ( $entry ) {
			if ( self::delete_entry( $entry ) ) {
				self::pending( $entry, false );
			} else {
				$out['e'] = trim( $out['e'] . ' ' . __( 'The test entry could not be deleted; it is tried again tomorrow.', 'scout-build' ) );
			}
		}
		return $out;
	}

	private static function entry_status( int $id ): string {
		$e = GFAPI::get_entry( $id );
		return is_array( $e ) ? (string) ( $e['status'] ?? 'active' ) : '';
	}

	/**
	 * What one test submission says. Pure.
	 *
	 * @param mixed  $result  GFAPI::submit_form()'s answer: an array, a WP_Error or null.
	 * @param string $error   A thrown error's message, or ''.
	 * @param array  $emails  Each notification Gravity Forms tried: { n, ok, e }.
	 * @param string $status  The entry's status (active, spam), or ''.
	 * @return array{r:string, n:int, ok:int, e:string}
	 */
	public static function read( $result, string $error, array $emails, string $status ): array {
		if ( '' !== $error ) {
			return self::result( 'refused', 0, 0, $error );
		}
		if ( is_object( $result ) && method_exists( $result, 'get_error_message' ) ) {
			return self::result( 'refused', 0, 0, (string) $result->get_error_message() );
		}
		if ( ! is_array( $result ) ) {
			return self::result( 'refused', 0, 0, __( 'Gravity Forms gave no answer', 'scout-build' ) );
		}
		if ( empty( $result['is_valid'] ) ) {
			$msgs = array_filter( array_map( static fn( $m ) => trim( wp_strip_all_tags( (string) $m ) ), (array) ( $result['validation_messages'] ?? array() ) ) );
			/* translators: %s: Gravity Forms' message */
			return self::result( 'refused', 0, 0, $msgs ? sprintf( __( 'Gravity Forms refused the test answers: %s', 'scout-build' ), (string) reset( $msgs ) ) : __( 'Gravity Forms refused the test answers', 'scout-build' ) );
		}
		if ( 'spam' === $status || ! empty( $result['is_spam'] ) ) {
			return self::result( 'refused', 0, 0, __( 'a spam filter caught the test', 'scout-build' ) );
		}
		$n  = count( $emails );
		$ok = count( array_filter( $emails, static fn( $m ) => ! empty( $m['ok'] ) ) );
		if ( ! $n ) {
			return self::result( 'failed', 0, 0, __( 'no email went out (check the notifications and their conditions)', 'scout-build' ) );
		}
		if ( $ok < $n ) {
			foreach ( $emails as $m ) {
				if ( empty( $m['ok'] ) ) {
					$why = '' !== (string) ( $m['e'] ?? '' ) ? (string) $m['e'] : __( 'no reason given', 'scout-build' );
					$who = '' !== (string) ( $m['n'] ?? '' ) ? (string) $m['n'] . ': ' : '';
					return self::result( 'failed', $n, $ok, $who . $why );
				}
			}
		}
		return self::result( 'sent', $n, $ok, '' );
	}

	private static function result( string $r, int $n, int $ok, string $e ): array {
		$e = class_exists( 'Scout_Hub_Form_Mail' ) ? Scout_Hub_Form_Mail::clean_error( $e ) : $e;
		return array( 'r' => $r, 'n' => $n, 'ok' => $ok, 'e' => rtrim( $e, '.' ) );
	}

	/**
	 * Scout Forms Guard's signed token for a form and time, made with the
	 * site's own secret exactly as the guard makes it for a page a visitor
	 * loads. tools/tests/form-test.php checks it still matches the guard.
	 */
	public static function guard_token( int $form_id, int $ts ): string {
		return hash_hmac( 'sha256', $form_id . '|' . $ts, wp_salt( 'auth' ) );
	}

	/* ---- During the submission ----------------------------------------- */

	/** True only inside test_form(), for the submission it made. */
	public static function is_test(): bool {
		if ( null === self::$run ) {
			return false;
		}
		$sent = isset( $_POST[ self::FIELD ] ) && is_string( $_POST[ self::FIELD ] ) ? (string) $_POST[ self::FIELD ] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- compared to a secret made in this request.
		return '' !== $sent && hash_equals( (string) self::$run['secret'], $sent );
	}

	/** Mark the test entry and save its id before any email goes out. */
	public static function entry_created( $entry, $form = array() ): void {
		if ( ! self::is_test() || ! is_array( $entry ) || empty( $entry['id'] ) ) {
			return;
		}
		$id                 = (int) $entry['id'];
		self::$run['entry'] = $id;
		self::pending( $id, true );
		if ( function_exists( 'gform_update_meta' ) ) {
			gform_update_meta( $id, self::META, '1' );
		}
	}

	/** No add-on feed (Mailchimp, a CRM, Zapier, webhooks) runs for the test. */
	public static function no_feeds( $feeds, $entry = array(), $form = array() ) {
		return self::is_test() ? array() : $feeds;
	}

	/** "[Test]" in the subject, a first line that says so, and an X-Form-Test header. */
	public static function mark_email( $email, $format = 'html', $notification = array(), $entry = array() ) {
		if ( ! self::is_test() || ! is_array( $email ) ) {
			return $email;
		}
		return self::marked( $email, (string) $format );
	}

	/** Pure: the email with the test marks. */
	public static function marked( array $email, string $format ): array {
		$subject = (string) ( $email['subject'] ?? '' );
		if ( 0 !== strpos( $subject, '[Test]' ) ) {
			$email['subject'] = '[Test] ' . $subject;
		}
		$line = __( 'Test only. The website sent this to check that this form\'s emails go out. No one filled in the form, and the test entry is deleted right after.', 'scout-build' );
		$body = (string) ( $email['message'] ?? '' );
		$email['message'] = 'text' === $format
			? $line . "\n\n" . $body
			: '<p style="margin:0 0 16px;padding:10px 12px;border:1px solid #c9c9c9;font-weight:600">' . esc_html( $line ) . '</p>' . $body;
		$headers = $email['headers'] ?? array();
		if ( is_array( $headers ) ) {
			$headers['X-Form-Test'] = 'X-Form-Test: nightly';
		} else {
			$headers = rtrim( (string) $headers ) . ( '' !== trim( (string) $headers ) ? "\r\n" : '' ) . 'X-Form-Test: nightly';
		}
		$email['headers'] = $headers;
		return $email;
	}

	/**
	 * Scout_Hub_Form_Mail hands each notice it sees here while the test runs,
	 * so the test never counts in "Form emails sent".
	 *
	 * @return bool Whether the test took it.
	 */
	public static function note_email( string $name, bool $ok, string $error ): bool {
		if ( ! self::is_test() ) {
			return false;
		}
		self::$run['emails'][] = array( 'n' => $name, 'ok' => $ok, 'e' => $error );
		return true;
	}

	/* ---- The answers --------------------------------------------------- */

	/** A field as an array, whether Gravity Forms gave an object or an array. */
	private static function field( $f ): array {
		if ( is_object( $f ) ) {
			$a = get_object_vars( $f );
			if ( method_exists( $f, 'get_input_type' ) ) {
				$a['inputType'] = (string) $f->get_input_type();
			}
			return $a;
		}
		return is_array( $f ) ? $f : array();
	}

	/**
	 * Made-up answers that pass the form's own checks: every required field,
	 * plus every email field (the notice address), so a "send to the visitor"
	 * email reaches the business. Or why this form can't be tested by itself.
	 * Pure apart from the words.
	 *
	 * @return array{values: array, skip: string}
	 */
	public static function answers( array $form, string $email, string $site, int $now ): array {
		$skip = static fn( string $why ) => array( 'values' => array(), 'skip' => $why );
		if ( ! empty( $form['requireLogin'] ) ) {
			return $skip( __( 'it needs a login', 'scout-build' ) );
		}
		$text   = __( 'Nightly form test', 'scout-build' );
		$long   = __( 'This is the website\'s nightly form test. No one filled in this form.', 'scout-build' );
		$values = array();
		$pay    = array( 'product', 'option', 'shipping', 'total', 'creditcard', 'stripe_creditcard', 'square_creditcard', 'paypal_checkout', 'donation' );
		foreach ( (array) ( $form['fields'] ?? array() ) as $raw ) {
			$f     = self::field( $raw );
			$id    = (int) ( $f['id'] ?? 0 );
			$type  = (string) ( $f['type'] ?? '' );
			$input = '' !== (string) ( $f['inputType'] ?? '' ) ? (string) $f['inputType'] : $type;
			$req   = ! empty( $f['isRequired'] );
			$key   = 'input_' . $id;
			if ( in_array( $type, $pay, true ) || in_array( $input, $pay, true ) ) {
				return $skip( __( 'it takes payments', 'scout-build' ) );
			}
			if ( 'captcha' === $type ) {
				return $skip( __( 'it has a CAPTCHA', 'scout-build' ) );
			}
			if ( 0 === strpos( $type, 'post_' ) ) {
				return $skip( __( 'it makes a post', 'scout-build' ) );
			}
			if ( 'password' === $type ) {
				return $skip( __( 'it has a password field', 'scout-build' ) );
			}
			if ( 'email' === $input ) {
				$values[ $key ] = $email;
				if ( ! empty( $f['emailConfirmEnabled'] ) ) {
					$values[ $key . '_2' ] = $email;
				}
				continue;
			}
			if ( ! $req || in_array( $input, array( 'html', 'section', 'page' ), true ) ) {
				continue;
			}
			$choices = array_values( array_filter( (array) ( $f['choices'] ?? array() ), static fn( $c ) => '' !== (string) ( $c['value'] ?? $c['text'] ?? '' ) ) );
			$first   = $choices ? (string) ( $choices[0]['value'] ?? $choices[0]['text'] ?? '' ) : '';
			switch ( $input ) {
				case 'text':
				case 'textarea':
				case 'hidden':
					if ( ! empty( $f['inputMask'] ) ) {
						return $skip( __( 'a required field has an input mask', 'scout-build' ) );
					}
					$v   = 'textarea' === $input ? $long : $text;
					$max = (int) ( $f['maxLength'] ?? 0 );
					$values[ $key ] = $max > 0 ? substr( $v, 0, $max ) : $v;
					break;
				case 'phone':
					$values[ $key ] = '(555) 555-0100';
					break;
				case 'website':
					$values[ $key ] = $site;
					break;
				case 'number':
					$min = $f['rangeMin'] ?? '';
					$max = $f['rangeMax'] ?? '';
					$values[ $key ] = (string) ( '' !== (string) $min ? $min : ( '' !== (string) $max ? min( 1, (float) $max ) : 1 ) );
					break;
				case 'name':
					if ( 'simple' === (string) ( $f['nameFormat'] ?? '' ) || empty( $f['inputs'] ) ) {
						$values[ $key ] = $text;
					} else {
						$values[ $key . '_3' ] = __( 'Nightly', 'scout-build' );
						$values[ $key . '_6' ] = __( 'Test', 'scout-build' );
					}
					break;
				case 'address':
					$type_a               = (string) ( $f['addressType'] ?? 'international' );
					$values[ $key . '_1' ] = __( '1 Test Street', 'scout-build' );
					$values[ $key . '_3' ] = __( 'Test City', 'scout-build' );
					$values[ $key . '_4' ] = 'us' === $type_a ? 'North Carolina' : ( 'canadian' === $type_a ? 'Ontario' : __( 'Test', 'scout-build' ) );
					$values[ $key . '_5' ] = 'canadian' === $type_a ? 'K1A 0B1' : '00000';
					$values[ $key . '_6' ] = 'canadian' === $type_a ? 'Canada' : 'United States';
					break;
				case 'date':
					if ( 'datepicker' !== (string) ( $f['dateType'] ?? 'datepicker' ) ) {
						return $skip( __( 'a required date is not a date picker', 'scout-build' ) );
					}
					$formats = array( 'mdy' => 'm/d/Y', 'dmy' => 'd/m/Y', 'dmy_dash' => 'd-m-Y', 'dmy_dot' => 'd.m.Y', 'ymd_slash' => 'Y/m/d', 'ymd_dash' => 'Y-m-d', 'ymd_dot' => 'Y.m.d' );
					$values[ $key ] = gmdate( $formats[ (string) ( $f['dateFormat'] ?? 'mdy' ) ] ?? 'm/d/Y', $now );
					break;
				case 'time':
					$parts          = '24' === (string) ( $f['timeFormat'] ?? '12' ) ? array( '09', '00' ) : array( '09', '00', 'am' );
					$values[ $key ] = $parts;
					break;
				case 'select':
				case 'radio':
					if ( '' === $first ) {
						return $skip( __( 'a required choice has no options', 'scout-build' ) );
					}
					$values[ $key ] = $first;
					break;
				case 'multiselect':
					if ( '' === $first ) {
						return $skip( __( 'a required choice has no options', 'scout-build' ) );
					}
					$values[ $key ] = array( $first );
					break;
				case 'checkbox':
					$inputs = (array) ( $f['inputs'] ?? array() );
					if ( '' === $first || empty( $inputs[0]['id'] ) ) {
						return $skip( __( 'a required choice has no options', 'scout-build' ) );
					}
					$values[ 'input_' . str_replace( '.', '_', (string) $inputs[0]['id'] ) ] = $first;
					break;
				case 'consent':
					$values[ $key . '_1' ] = '1';
					$values[ $key . '_2' ] = (string) ( $f['checkboxLabel'] ?? '' );
					$values[ $key . '_3' ] = '';
					break;
				case 'list':
					$values[ $key ] = array( $text );
					break;
				case 'fileupload':
					return $skip( __( 'it needs a file upload', 'scout-build' ) );
				case 'signature':
					return $skip( __( 'it needs a signature', 'scout-build' ) );
				default:
					/* translators: %s: field type */
					return $skip( sprintf( __( 'it has a required %s field', 'scout-build' ), $input ) );
			}
		}
		return array( 'values' => $values, 'skip' => '' );
	}

	/* ---- The notice ---------------------------------------------------- */

	/** Email the notice address when a form reaches two failures in a row, then weekly. */
	private static function maybe_notify( array $state, array $results ): void {
		$bad = array();
		foreach ( $results as $id => $r ) {
			if ( 'failed' === $r['r'] && self::should_notify( (int) ( $state['streak'][ $id ] ?? 0 ) ) ) {
				$bad[ (int) $id ] = $r;
			}
		}
		if ( ! $bad ) {
			return;
		}
		$to = Scout_Hub_Form_Mail::notice_to();
		if ( ! is_email( $to ) ) {
			return;
		}
		$site  = wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
		$site  = '' !== $site ? $site : (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$lines = array();
		foreach ( $bad as $id => $r ) {
			/* translators: 1: form title, 2: failures in a row, 3: the reason */
			$lines[] = sprintf( __( '%1$s: did not send on %2$d nights in a row. %3$s.', 'scout-build' ), self::title( $id ), (int) $state['streak'][ $id ], '' !== $r['e'] ? $r['e'] : __( 'No reason given', 'scout-build' ) );
		}
		/* translators: %s: site name */
		$subject = sprintf( __( '%s: a form did not send its emails', 'scout-build' ), $site );
		$body    = implode(
			"\n\n",
			array(
				__( 'The nightly form test sends each picked form once a night and checks that its emails go out. These did not:', 'scout-build' ),
				implode( "\n", $lines ),
				__( 'New requests from this form may not be reaching anyone. Check the Site email row and the form\'s notifications in Site status:', 'scout-build' ) . "\n" . ( class_exists( 'Scout_Hub_Admin' ) ? Scout_Hub_Admin::url( 'health' ) : admin_url() ),
				__( 'The test runs again tonight. This email comes when a form fails two nights in a row, then once a week while it keeps failing.', 'scout-build' ),
			)
		);
		wp_mail( $to, $subject, $body );
	}

	private static function title( int $id ): string {
		$form = method_exists( 'GFAPI', 'get_form' ) ? GFAPI::get_form( $id ) : false;
		/* translators: %d: form id */
		return is_array( $form ) && '' !== (string) ( $form['title'] ?? '' ) ? (string) $form['title'] : sprintf( __( 'Form %d', 'scout-build' ), $id );
	}

	/* ---- The Site status row ------------------------------------------- */

	/** The "Form test" row, or null while the test is off or without Gravity Forms. */
	public static function row(): ?array {
		if ( ! class_exists( 'GFAPI' ) ) {
			return null;
		}
		$link = class_exists( 'Scout_Hub_Access' ) && Scout_Hub_Access::is_operator() && class_exists( 'Scout_Hub_Admin' ) ? Scout_Hub_Admin::url( 'features' ) . '#sh-form-test' : '';
		return self::build_row( self::settings(), self::state(), time(), self::blocked_here(), array( __CLASS__, 'title' ), $link );
	}

	/**
	 * The row from what is kept. Pure apart from the words.
	 *
	 * @param string   $here  '' on the live site, 'staging' or 'copy' where the test waits.
	 * @param callable $title Form id to title.
	 * @param string   $link  Where the switch is (operators only), or ''.
	 */
	public static function build_row( array $settings, array $state, int $now, string $here, callable $title, string $link ): ?array {
		if ( empty( $settings['on'] ) ) {
			return null;
		}
		$row  = array( 'label' => __( 'Form test', 'scout-build' ) );
		$go   = '' !== $link ? array( 'link' => $link, 'link_text' => __( 'Settings', 'scout-build' ) ) : array();
		/* translators: 1: form title, 2: form id */
		$name = static fn( $id ) => sprintf( __( '%1$s (form %2$d)', 'scout-build' ), $title( (int) $id ), (int) $id );
		if ( '' !== $here ) {
			return $row + array(
				'value' => 'staging' === $here ? __( 'Off on staging', 'scout-build' ) : __( 'Off on this copy', 'scout-build' ),
				'state' => 'good',
				'note'  => 'staging' === $here
					? __( 'The staging email guard is on, so the nightly form test waits for the live site.', 'scout-build' )
					: __( 'This is not the live site, so the nightly form test waits for it.', 'scout-build' ),
			);
		}
		if ( empty( $settings['forms'] ) ) {
			return $row + array(
				'value' => __( 'No forms picked', 'scout-build' ),
				'state' => 'warn',
				'note'  => __( 'The nightly form test is on, but no form is ticked. Pick the forms people use to reach the business.', 'scout-build' ),
			) + $go;
		}
		$last = (int) ( $state['last_run'] ?? 0 );
		if ( ! $last ) {
			return $row + array(
				'value' => __( 'First test tonight', 'scout-build' ),
				'state' => 'good',
				'note'  => __( 'Each picked form is sent once a night, around 2 am, and its emails are checked.', 'scout-build' ),
			) + $go;
		}
		$results = (array) ( $state['results'] ?? array() );
		$streak  = (array) ( $state['streak'] ?? array() );
		$failing = array();
		$missed  = array();
		$other   = array();
		$sent    = array();
		foreach ( $results as $id => $r ) {
			$why = '' !== (string) ( $r['e'] ?? '' ) ? (string) $r['e'] : __( 'no reason given', 'scout-build' );
			if ( 'failed' === ( $r['r'] ?? '' ) ) {
				if ( (int) ( $streak[ $id ] ?? 0 ) >= self::ALARM ) {
					$failing[ $id ] = $why;
				} else {
					$missed[ $id ] = $why;
				}
			} elseif ( 'sent' === ( $r['r'] ?? '' ) ) {
				$sent[ $id ] = (int) ( $r['n'] ?? 0 );
			} else {
				$other[ $id ] = $why;
			}
		}
		$pending = ! empty( $state['pending'] ) ? ' ' . __( 'A test entry could not be deleted; it is tried again tonight.', 'scout-build' ) : '';
		if ( $failing ) {
			$id    = (int) array_key_first( $failing );
			$lines = array();
			foreach ( $failing as $fid => $why ) {
				/* translators: 1: form, 2: nights, 3: the reason */
				$lines[] = sprintf( __( '%1$s did not send on %2$d nights in a row: %3$s.', 'scout-build' ), $name( $fid ), (int) $streak[ $fid ], $why );
			}
			return $row + array(
				/* translators: %s: form title */
				'value'     => sprintf( __( 'Failed: %s did not send', 'scout-build' ), $title( $id ) ),
				'state'     => 'bad',
				'note'      => implode( ' ', $lines ) . ' ' . __( 'Check the Site email row, then the form\'s notifications.', 'scout-build' ) . $pending,
				'link'      => admin_url( 'admin.php?page=gf_edit_forms&view=settings&subview=notification&id=' . $id ),
				'link_text' => __( 'Notifications', 'scout-build' ),
			);
		}
		if ( $now - $last > self::STALE ) {
			return $row + array(
				/* translators: %s: date */
				'value' => sprintf( __( 'No test since %s', 'scout-build' ), function_exists( 'wp_date' ) ? (string) wp_date( 'M j', $last ) : gmdate( 'M j', $last ) ),
				'state' => 'warn',
				'note'  => __( 'The nightly run didn\'t start. WordPress\'s scheduled tasks may be off on this host.', 'scout-build' ),
			) + $go;
		}
		$notes = array();
		foreach ( $missed as $fid => $why ) {
			/* translators: 1: form, 2: the reason */
			$notes[] = sprintf( __( '%1$s did not send last night: %2$s. One miss can be a blip, so this turns red only if tonight\'s test fails too.', 'scout-build' ), $name( $fid ), $why );
		}
		foreach ( $other as $fid => $why ) {
			/* translators: 1: form, 2: the reason */
			$notes[] = sprintf( __( 'Could not test %1$s: %2$s. Send it by hand once to check its emails.', 'scout-build' ), $name( $fid ), $why );
		}
		if ( $sent ) {
			$bits = array();
			foreach ( $sent as $fid => $n ) {
				/* translators: 1: form, 2: emails */
				$bits[] = sprintf( _n( '%1$s (%2$d email)', '%1$s (%2$d emails)', $n, 'scout-build' ), $name( $fid ), $n );
			}
			/* translators: %s: forms and their email counts */
			$notes[] = sprintf( __( 'Sent last night: %s. Each email was handed to the mailer; the site can\'t see the inbox, so check a [Test] email arrives now and then.', 'scout-build' ), implode( ', ', $bits ) );
		}
		$notes = implode( ' ', $notes ) . $pending;
		if ( $missed ) {
			return $row + array( 'value' => __( 'Trying again tonight', 'scout-build' ), 'state' => 'good', 'note' => $notes ) + $go;
		}
		if ( $other ) {
			/* translators: %s: form title */
			return $row + array( 'value' => sprintf( __( 'Could not test %s', 'scout-build' ), $title( (int) array_key_first( $other ) ) ), 'state' => 'warn', 'note' => $notes ) + $go;
		}
		return $row + array( 'value' => __( 'Sent last night', 'scout-build' ), 'state' => '' !== $pending ? 'warn' : 'good', 'note' => $notes ) + $go;
	}

	/* ---- The box in Scout tools, Features ------------------------------ */

	/** Operators only. */
	private static function allowed(): bool {
		return current_user_can( 'manage_options' ) && class_exists( 'Scout_Hub_Access' ) && Scout_Hub_Access::is_operator();
	}

	/** The switch and the forms, folded under Features. */
	public static function render_settings(): void {
		if ( ! self::allowed() || ! class_exists( 'GFAPI' ) ) {
			return;
		}
		$s     = self::settings();
		$forms = array();
		foreach ( (array) GFAPI::get_forms() as $f ) {
			if ( is_array( $f ) && ! empty( $f['id'] ) ) {
				$forms[ (int) $f['id'] ] = $f;
			}
		}
		$titles = array_map( static fn( $f ) => (string) ( $f['title'] ?? '' ), $forms );
		$theme  = class_exists( 'Scout_Hub_Setup' ) ? self::from_setup( Scout_Hub_Setup::data(), $titles ) : array();
		$pages  = self::on_pages();
		if ( $s['saved'] ) {
			$ticked = $s['forms'];
		} elseif ( $theme ) {
			$ticked = $theme;
		} else {
			$ticked = array_values( array_filter( $pages, static fn( $id ) => isset( $forms[ $id ] ) && self::has_notifications( $forms[ $id ] ) ) );
		}
		$here = self::blocked_here();
		/* translators: %s: On or Off */
		Scout_Hub_Admin::fold_open( sprintf( __( 'More options: nightly form test (%s)', 'scout-build' ), $s['on'] ? __( 'On', 'scout-build' ) : __( 'Off', 'scout-build' ) ), false, 'sh-form-test' );
		?>
		<div class="sh-ops">
			<p class="sh-note"><?php esc_html_e( 'Once a night, around 2 am, each ticked form is sent with made-up answers. Its own emails go to their usual addresses with [Test] at the start of the subject, and any email field gets the notice address. The test entry is deleted, add-ons such as Mailchimp or a CRM are skipped, and nothing counts in analytics. Site status turns red when a form fails two nights in a row.', 'scout-build' ); ?></p>
			<?php if ( '' !== $here ) : ?>
				<p class="sh-note"><strong><?php echo esc_html( 'staging' === $here ? __( 'This is a staging copy, so the test waits for the live site.', 'scout-build' ) : __( 'This is not the live site, so the test waits for it.', 'scout-build' ) ); ?></strong></p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION ); ?>
				<p><label><input type="checkbox" name="on" value="1" <?php checked( $s['on'] ); ?>> <?php esc_html_e( 'Run the test every night', 'scout-build' ); ?></label></p>
				<?php if ( ! $forms ) : ?>
					<p class="sh-note"><?php esc_html_e( 'Gravity Forms has no forms yet.', 'scout-build' ); ?></p>
				<?php endif; ?>
				<fieldset>
					<legend class="sh-label"><?php esc_html_e( 'Forms to test', 'scout-build' ); ?></legend>
					<?php foreach ( $forms as $id => $f ) : ?>
						<?php
						$hints = array();
						if ( empty( $f['is_active'] ) ) {
							$hints[] = __( 'inactive', 'scout-build' );
						}
						if ( in_array( $id, $pages, true ) ) {
							$hints[] = __( 'on a published page', 'scout-build' );
						}
						if ( in_array( $id, $theme, true ) ) {
							$hints[] = __( 'named in the theme\'s setup file', 'scout-build' );
						}
						if ( ! self::has_notifications( $f ) ) {
							$hints[] = __( 'sends no email', 'scout-build' );
						}
						$full = method_exists( 'GFAPI', 'get_form' ) ? GFAPI::get_form( $id ) : $f;
						$why  = is_array( $full ) ? self::answers( $full, 'test@example.com', home_url( '/' ), time() )['skip'] : '';
						if ( '' !== $why ) {
							/* translators: %s: the reason */
							$hints[] = sprintf( __( 'can\'t be tested: %s', 'scout-build' ), $why );
						}
						?>
						<p><label><input type="checkbox" name="forms[]" value="<?php echo esc_attr( (string) $id ); ?>" <?php checked( in_array( $id, $ticked, true ) ); ?>> <?php echo esc_html( sprintf( '%s (form %d)', (string) ( $f['title'] ?? '' ), $id ) ); ?></label><?php if ( $hints ) : ?> <span class="sh-note"><?php echo esc_html( implode( ', ', $hints ) ); ?></span><?php endif; ?></p>
					<?php endforeach; ?>
				</fieldset>
				<p>
					<button type="submit" class="button button-primary" name="do" value="save"><?php esc_html_e( 'Save', 'scout-build' ); ?></button>
					<?php if ( $s['on'] && '' === $here ) : ?>
						<button type="submit" class="button" name="do" value="now"><?php esc_html_e( 'Save and run it now', 'scout-build' ); ?></button>
					<?php endif; ?>
				</p>
				<?php if ( ! $s['saved'] && $ticked ) : ?>
					<p class="sh-note"><?php echo esc_html( $theme ? __( 'Ticked from the theme\'s setup file. Nothing runs until you save with the switch on.', 'scout-build' ) : __( 'Ticked: forms on published pages that send email. Nothing runs until you save with the switch on.', 'scout-build' ) ); ?></p>
				<?php endif; ?>
			</form>
		</div>
		<script>( function () { var d = document.getElementById( 'sh-form-test' ); if ( d && '#sh-form-test' === location.hash ) { d.open = true; } } )();</script>
		<?php
		Scout_Hub_Admin::fold_close();
	}

	/** Save the box, and run it at once when asked. */
	public static function handle(): void {
		check_admin_referer( self::ACTION );
		if ( ! self::allowed() ) {
			wp_die( esc_html__( 'Only the people who look after this site can change the form test.', 'scout-build' ), 403 );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked above.
		$on    = ! empty( $_POST['on'] );
		$forms = array_values( array_unique( array_filter( array_map( 'intval', (array) wp_unslash( $_POST['forms'] ?? array() ) ) ) ) );
		$now   = 'now' === sanitize_key( (string) wp_unslash( $_POST['do'] ?? '' ) );
		// phpcs:enable
		$user = wp_get_current_user();
		update_option(
			self::OPTION,
			array(
				'on'    => $on,
				'forms' => $forms,
				'saved' => true,
				'by'    => $user ? (string) $user->display_name : '',
				'at'    => time(),
			),
			false
		);
		self::unschedule();
		self::schedule();
		$msg = $on ? __( 'Saved. The form test runs tonight.', 'scout-build' ) : __( 'Saved. The form test is off.', 'scout-build' );
		if ( $on && $now ) {
			$msg = self::run( true ) ? __( 'Saved, and the forms were sent. Site status shows the result.', 'scout-build' ) : __( 'Saved. The test could not run here; Site status says why.', 'scout-build' );
		}
		set_transient( 'scout_hub_notice_' . get_current_user_id(), array( 'message' => $msg, 'type' => 'success' ), MINUTE_IN_SECONDS );
		wp_safe_redirect( Scout_Hub_Admin::url( $on && $now ? 'health' : 'features' ) );
		exit;
	}
}
