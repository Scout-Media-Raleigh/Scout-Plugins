<?php
/**
 * Send only, keep no entry: a per-form switch for Gravity Forms.
 *
 * For a sensitive form, such as a concern or a report that only one person
 * should read: the form's notification still goes out, and the entry is
 * deleted from the site right after, so no copy waits in the dashboard.
 *
 * How it works, using Gravity Forms' own hooks:
 *   - The switch is a Toggle in the form's Settings, "Send only, keep no
 *     entry" (gform_form_settings_fields; Gravity Forms 2.5 and later save
 *     it on the form as scoutSendOnly). Off by default.
 *   - Its notifications are sent in the same request
 *     (gform_is_asynchronous_notifications_enabled), so they go out before
 *     the entry is gone.
 *   - On gform_after_submission, after the notifications and the add-ons at
 *     normal priority, GFAPI::delete_entry() removes the entry, its uploaded
 *     files and its notes. Entries Gravity Forms marks as spam are deleted too.
 *   - The nightly form test never picks the form: it is taken out of the
 *     test's saved list, and if a test reaches it anyway its emails are not
 *     sent, because the test mails the form's real addresses.
 *   - A To do item that names the form ('form_id') is left out.
 *   - Forms Guard's message check (links and spam pitches) is skipped, so a
 *     real report is never sent to Spam, where no email goes out. The
 *     honeypot, time trap and token still run.
 *
 * Assumption: Gravity Forms 2.5 or later saves a field added with
 * gform_form_settings_fields on the form array under its name. Checked on
 * staging before a site turns the switch on.
 *
 * @package Scout_Forms_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scout_Forms_Guard_Send_Only {

	/** The form setting's name, saved on the Gravity Forms form array. */
	const KEY = 'scoutSendOnly';

	/** Scout Build's nightly form test settings (Scout_Hub_Form_Test::OPTION). */
	const TEST_OPTION = 'scout_hub_form_test';

	/** @var int[]|null Ids of the send-only forms, read once a request. */
	private static $ids = null;

	public static function boot(): void {
		add_filter( 'gform_form_settings_fields', array( __CLASS__, 'settings_fields' ), 10, 2 );
		add_filter( 'gform_is_asynchronous_notifications_enabled', array( __CLASS__, 'send_now' ), 10000, 4 );
		add_action( 'gform_after_submission', array( __CLASS__, 'forget' ), 9999, 2 );
		add_filter( 'gform_pre_send_email', array( __CLASS__, 'no_test_mail' ), 0, 4 );
		add_filter( 'option_' . self::TEST_OPTION, array( __CLASS__, 'skip_in_test' ) );
		add_filter( 'pre_update_option_' . self::TEST_OPTION, array( __CLASS__, 'skip_in_test' ) );
		add_filter( 'scout_hub_inbox_items', array( __CLASS__, 'inbox_items' ), 100 );
		// A report that links to a website must still reach its reader: the message check would
		// send it to Spam, where nobody is told, and the entry is deleted.
		add_filter( 'scout_forms_guard_content_check', array( __CLASS__, 'content_check' ), 10, 2 );
		add_action( 'gform_after_save_form', array( __CLASS__, 'reset' ) );
	}

	/** The message check stays off for a send-only form. The honeypot, time trap and token still run. */
	public static function content_check( $check, $form = array() ) {
		return is_array( $form ) && self::on( $form ) ? false : $check;
	}

	/** Is the switch on for this form (a form array or its id)? */
	public static function on( $form ): bool {
		if ( ! is_array( $form ) ) {
			$id   = (int) $form;
			$form = $id && class_exists( 'GFAPI' ) ? GFAPI::get_form( $id ) : array();
		}
		$on = is_array( $form ) && ! empty( $form[ self::KEY ] );
		/**
		 * Filters whether a form keeps no entry (a site can switch it in code).
		 *
		 * @param bool  $on   On for this form.
		 * @param array $form The Gravity Forms form.
		 */
		return (bool) apply_filters( 'scout_forms_guard_send_only', $on, is_array( $form ) ? $form : array() );
	}

	/** Ids of every active form with the switch on. */
	public static function form_ids(): array {
		if ( null !== self::$ids ) {
			return self::$ids;
		}
		self::$ids = array();
		if ( class_exists( 'GFAPI' ) && method_exists( 'GFAPI', 'get_forms' ) ) {
			foreach ( (array) GFAPI::get_forms() as $form ) {
				if ( is_array( $form ) && ! empty( $form['id'] ) && self::on( $form ) ) {
					self::$ids[] = (int) $form['id'];
				}
			}
		}
		return self::$ids;
	}

	/** Read the forms again, after a form is saved in the same request. */
	public static function reset(): void {
		self::$ids = null;
	}

	/**
	 * The Toggle on the form's Settings screen, in its own section.
	 *
	 * @param array $fields Gravity Forms settings sections.
	 * @param array $form   The form.
	 */
	public static function settings_fields( $fields, $form = array() ) {
		$fields   = is_array( $fields ) ? $fields : array();
		$fields[] = array(
			'title'  => __( 'Keep no entry', 'scout-forms-guard' ),
			'fields' => array(
				array(
					'name'          => self::KEY,
					'type'          => 'toggle',
					'label'         => __( 'Send only, keep no entry', 'scout-forms-guard' ),
					'tooltip'       => __( 'For a sensitive form. The notification still goes out, then the entry and its files are deleted from the site, so nobody reads it in the dashboard. The nightly form test skips this form.', 'scout-forms-guard' ),
					'description'   => __( 'Check the notification goes only to the person who should read it.', 'scout-forms-guard' ),
					'default_value' => false,
				),
			),
		);
		return $fields;
	}

	/**
	 * Notifications for a send-only form go out in this request, before its
	 * entry is deleted. Every other form keeps Gravity Forms' own choice.
	 */
	public static function send_now( $later, $event = '', $notifications = array(), $form = array() ) {
		return is_array( $form ) && self::on( $form ) ? false : $later;
	}

	/** After the notifications and add-ons: delete the entry, its files and notes. */
	public static function forget( $entry, $form ): bool {
		if ( ! is_array( $entry ) || empty( $entry['id'] ) || ! is_array( $form ) || ! self::on( $form ) || ! class_exists( 'GFAPI' ) ) {
			return false;
		}
		$done = GFAPI::delete_entry( (int) $entry['id'] );
		return true === $done;
	}

	/**
	 * If the nightly form test ever reaches a send-only form, its emails are
	 * not sent: the test mails the form's real addresses.
	 */
	public static function no_test_mail( $email, $format = '', $notification = array(), $entry = array() ) {
		if ( ! is_array( $email ) || ! function_exists( 'scout_build_form_test_running' ) || ! scout_build_form_test_running() ) {
			return $email;
		}
		$form_id = is_array( $entry ) ? (int) ( $entry['form_id'] ?? 0 ) : 0;
		if ( $form_id && in_array( $form_id, self::form_ids(), true ) ) {
			$email['abort_email'] = true;
		}
		return $email;
	}

	/** The nightly form test's saved list never holds a send-only form. */
	public static function skip_in_test( $value ) {
		if ( ! is_array( $value ) || empty( $value['forms'] ) ) {
			return $value;
		}
		$skip = self::form_ids();
		if ( $skip ) {
			$value['forms'] = array_values( array_diff( array_map( 'intval', (array) $value['forms'] ), $skip ) );
		}
		return $value;
	}

	/** A To do item that names a send-only form never shows. */
	public static function inbox_items( $items ): array {
		$items = (array) $items;
		$skip  = null;
		foreach ( $items as $i => $item ) {
			if ( ! is_array( $item ) || empty( $item['form_id'] ) ) {
				continue;
			}
			$skip = null === $skip ? self::form_ids() : $skip;
			if ( in_array( (int) $item['form_id'], $skip, true ) ) {
				unset( $items[ $i ] );
			}
		}
		return array_values( $items );
	}
}
