<?php
/**
 * Plugin Name:       Scout Forms Guard
 * Description:       In-house spam protection for Gravity Forms: a hidden honeypot, a time trap, and a signed token. Also refuses files sent to File Upload fields set to Hidden and list answers that carry hidden code (two reported Gravity Forms security flaws), and names the upload fields for you to fix. A per-form switch sends a form's notification and keeps no entry. No reCAPTCHA, no Akismet, no third-party calls.
 * Version:           0.4.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Requires Plugins:  gravityforms
 * Author:            Scout Media & Consulting
 * License:           GPL-2.0-or-later
 * Text Domain:       scout-forms-guard
 * Update URI:        https://github.com/Scout-Media-Raleigh/Scout-Plugins/scout-forms-guard
 *
 * @package Scout_Forms_Guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Self-updating from the Scout Plugins releases (see includes/class-scout-plugin-updater.php).
// Loaded on plugins_loaded, after every plugin's own file, so older Scout
// plugins that still declare the shared class at load time always go first.
add_action(
	'plugins_loaded',
	static function () {
		require_once __DIR__ . '/includes/class-scout-plugin-updater.php';
		Scout_Plugin_Updater::boot( __DIR__ . '/scout-forms-guard.php', 'scout-forms-guard' );
	},
	0
);

// Hidden upload guard. Booted at load time so its plugins_loaded check is
// registered before plugins_loaded fires.
require_once __DIR__ . '/includes/class-scout-forms-guard-uploads.php';
Scout_Forms_Guard_Uploads::boot();

// "Send only, keep no entry", a per-form switch (off by default).
require_once __DIR__ . '/includes/class-scout-forms-guard-send-only.php';
Scout_Forms_Guard_Send_Only::boot();

final class Scout_Forms_Guard {

	const HP  = 'scout_hp';   // Honeypot field.
	const TS  = 'scout_ts';   // Render timestamp.
	const SIG = 'scout_sig';  // HMAC of timestamp + form id.
	const LOG = 'scout_forms_guard_blocked'; // Blocked count per month: array( 'Y-m' => n ).

	/** @var bool Did we block the current submission? */
	private static $blocked = false;

	public static function init() {
		add_filter( 'gform_get_form_filter', array( __CLASS__, 'inject' ), 10, 2 );
		add_filter( 'gform_validation', array( __CLASS__, 'validate' ) );
		add_filter( 'gform_validation_message', array( __CLASS__, 'message' ), 10, 2 );
		add_filter( 'gform_entry_is_spam', array( __CLASS__, 'content_spam' ), 10, 3 );
		add_filter( 'gform_pre_validation', array( __CLASS__, 'fix_website_urls' ) );
	}

	/**
	 * Website fields accept "www.example.com" or "example.com". Gravity Forms
	 * rejects an address without http(s)://, which real visitors never type,
	 * so the scheme is added before Gravity Forms checks the field.
	 */
	public static function fix_website_urls( $form ) {
		foreach ( (array) rgar( $form, 'fields' ) as $field ) {
			if ( ! is_object( $field ) || 'website' !== $field->type ) {
				continue;
			}
			$key   = 'input_' . $field->id;
			$value = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? trim( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( '' !== $value && ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $value ) && preg_match( '#^[^\s/]+\.[a-z]{2,}#i', $value ) ) {
				$_POST[ $key ] = 'https://' . $value; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}
		return $form;
	}

	/**
	 * Sign a form id + timestamp with the site's secret salt.
	 */
	private static function sign( $form_id, $ts ) {
		return hash_hmac( 'sha256', $form_id . '|' . $ts, wp_salt( 'auth' ) );
	}

	/**
	 * Add the hidden honeypot + signed-timestamp fields to every form.
	 */
	public static function inject( $form_string, $form ) {
		if ( empty( $form['id'] ) ) {
			return $form_string;
		}
		$form_id = (int) $form['id'];
		$ts      = time();
		$sig     = self::sign( $form_id, $ts );

		$fields  = '<div aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">';
		$fields .= '<label>Leave this field empty<input type="text" name="' . esc_attr( self::HP ) . '" value="" tabindex="-1" autocomplete="off" /></label>';
		$fields .= '</div>';
		$fields .= '<input type="hidden" name="' . esc_attr( self::TS ) . '" value="' . esc_attr( $ts ) . '" />';
		$fields .= '<input type="hidden" name="' . esc_attr( self::SIG ) . '" value="' . esc_attr( $sig ) . '" />';

		$pos = strripos( $form_string, '</form>' );
		if ( false === $pos ) {
			return $form_string . $fields;
		}
		return substr( $form_string, 0, $pos ) . $fields . substr( $form_string, $pos );
	}

	/**
	 * Block the submission if the honeypot is filled, the token is missing or
	 * forged, or the form was submitted impossibly fast or impossibly late.
	 */
	public static function validate( $validation_result ) {
		$form    = isset( $validation_result['form'] ) ? $validation_result['form'] : array();
		$form_id = isset( $form['id'] ) ? (int) $form['id'] : 0;

		// 0. A List field must hold plain answers, never serialized PHP.
		if ( self::list_payload_is_unsafe( $form, $_POST ) ) {
			return self::fail( $validation_result );
		}

		// 1. Honeypot must be empty.
		$honeypot = isset( $_POST[ self::HP ] ) ? trim( (string) wp_unslash( $_POST[ self::HP ] ) ) : '';
		if ( '' !== $honeypot ) {
			return self::fail( $validation_result );
		}

		// 2 + 3. The signed timestamp must verify and sit inside the time window.
		$ts  = isset( $_POST[ self::TS ] ) ? (int) $_POST[ self::TS ] : 0;
		$sig = isset( $_POST[ self::SIG ] ) ? (string) wp_unslash( $_POST[ self::SIG ] ) : '';

		if ( ! $ts || ! $sig || ! hash_equals( self::sign( $form_id, $ts ), $sig ) ) {
			return self::fail( $validation_result );
		}

		$min     = (int) apply_filters( 'scout_forms_guard_min_seconds', 3 );
		$max     = (int) apply_filters( 'scout_forms_guard_max_seconds', DAY_IN_SECONDS );
		$elapsed = time() - $ts;
		if ( $elapsed < $min || $elapsed > $max ) {
			return self::fail( $validation_result );
		}

		return $validation_result;
	}

	/**
	 * Layer 5: List answers that carry serialized PHP. Gravity Forms up to 3.1.2
	 * passes a List answer headed for a Post Custom Field into PHP's unserialize
	 * (CVE-2026-103039, reported 9.8), which lets a stranger run code when another
	 * plugin or theme supplies a way to use it. Nobody types serialized PHP into
	 * a List field, so a submission that does is refused. The fix is to update
	 * Gravity Forms; this keeps a site safe until it has. Off with the
	 * scout_forms_guard_block_serialized filter.
	 *
	 * @param array $form Gravity Forms form array.
	 * @param array $post The submitted values, normally $_POST.
	 */
	public static function list_payload_is_unsafe( $form, $post ): bool {
		if ( ! apply_filters( 'scout_forms_guard_block_serialized', true, $form ) ) {
			return false;
		}
		foreach ( (array) ( isset( $form['fields'] ) ? $form['fields'] : array() ) as $field ) {
			$type  = is_object( $field ) ? ( isset( $field->type ) ? $field->type : '' ) : ( isset( $field['type'] ) ? $field['type'] : '' );
			$input = is_object( $field ) ? ( isset( $field->inputType ) ? $field->inputType : '' ) : ( isset( $field['inputType'] ) ? $field['inputType'] : '' );
			if ( 'list' !== $type && 'list' !== $input ) {
				continue;
			}
			$id  = is_object( $field ) ? $field->id : ( isset( $field['id'] ) ? $field['id'] : '' );
			$key = 'input_' . $id;
			if ( isset( $post[ $key ] ) && self::has_serialized( wp_unslash( $post[ $key ] ) ) ) {
				return true;
			}
		}
		return false;
	}

	/** True when a value, or anything nested in it, looks like serialized PHP. */
	public static function has_serialized( $value ): bool {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( self::has_serialized( $item ) ) {
					return true;
				}
			}
			return false;
		}
		if ( ! is_string( $value ) ) {
			return false;
		}
		// An object or class (O:8:"Name":...), an array (a:2:{...}), or the marker PHP leaves for a missing class.
		return (bool) preg_match( '/(?:^|[^A-Za-z0-9])[OC]:\d+:"|^\s*a:\d+:\{|__PHP_Incomplete_Class/', $value );
	}

	/**
	 * Layer 4: what the message says. Some bots wait, skip the honeypot and
	 * send a valid token, then pitch "add your site to Google's search index"
	 * with a link to their own site. Real customers asking for a quote don't
	 * paste links to other websites. A match marks the entry as spam instead
	 * of refusing it: the visitor sees the normal confirmation, no
	 * notification goes out, and the entry waits in the form's Spam folder in
	 * case a real person was caught. Off with the
	 * scout_forms_guard_content_check filter.
	 */
	public static function content_spam( $is_spam, $form, $entry ) {
		if ( $is_spam || ! apply_filters( 'scout_forms_guard_content_check', true, $form ) ) {
			return $is_spam;
		}
		// A Website field is the visitor's own site, which they are asked for.
		$skip = array();
		foreach ( (array) rgar( $form, 'fields' ) as $field ) {
			if ( is_object( $field ) && 'website' === $field->type ) {
				$skip[] = (string) $field->id;
			}
		}
		$text = '';
		foreach ( (array) $entry as $key => $value ) {
			if ( is_string( $value ) && preg_match( '/^\d+(\.\d+)?$/', (string) $key ) && ! in_array( (string) $key, $skip, true ) ) {
				$text .= ' ' . $value;
			}
		}
		if ( self::looks_like_spam( $text ) ) {
			self::record_block( $form );
			return true;
		}
		return $is_spam;
	}

	/** True when the text links to another website or uses a known spam pitch. */
	public static function looks_like_spam( string $text ): bool {
		$text = strtolower( $text );
		foreach ( (array) apply_filters( 'scout_forms_guard_spam_phrases', array( 'search index', 'search results today', 'backlink', 'domainsubmit', 'searchindex', 'seo services', 'rank your website', 'guest post' ) ) as $phrase ) {
			if ( '' !== $phrase && false !== strpos( $text, strtolower( (string) $phrase ) ) ) {
				return true;
			}
		}
		// Email addresses are fine (a customer may type theirs), so drop them first.
		$text = preg_replace( '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/', ' ', $text );
		if ( ! preg_match_all( '#(?:https?://|www\.)?((?:[a-z0-9-]+\.)+(?:com|net|org|pro|io|co|info|biz|xyz|site|online|top|us|ru|cn|shop|store|link|click|app|dev|ai))\b#', $text, $m ) ) {
			return false;
		}
		$own = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$own = preg_replace( '/^www\./', '', $own );
		foreach ( $m[1] as $host ) {
			$host = preg_replace( '/^www\./', '', $host );
			if ( $host !== $own ) {
				return true;
			}
		}
		return false;
	}

	private static function fail( $validation_result ) {
		$validation_result['is_valid'] = false;
		self::$blocked                 = true;
		self::record_block( isset( $validation_result['form'] ) ? $validation_result['form'] : array() );
		return $validation_result;
	}

	/**
	 * Count one blocked submission or upload and tell anyone listening.
	 * Spam and refused hidden uploads share the monthly count.
	 */
	public static function record_block( $form ) {
		self::count();
		do_action( 'scout_forms_guard_blocked', is_array( $form ) ? $form : array() );
	}

	/**
	 * Hidden upload fields, as a Scout Build health check
	 * (label, value, state, note, link, link_text).
	 */
	public static function upload_check(): array {
		return Scout_Forms_Guard_Uploads::check();
	}

	/**
	 * Every File Upload field set to Hidden or Administrative on an active form.
	 *
	 * @return array<int, array{form_id:int, form_title:string, field_id:int, field_label:string}>
	 */
	public static function hidden_uploads(): array {
		return Scout_Forms_Guard_Uploads::find();
	}

	/** Add one to this month's blocked count. Keeps the last 13 months. */
	private static function count() {
		$log   = get_option( self::LOG, array() );
		$log   = is_array( $log ) ? $log : array();
		$month = wp_date( 'Y-m' );
		$log[ $month ] = ( isset( $log[ $month ] ) ? (int) $log[ $month ] : 0 ) + 1;
		krsort( $log );
		update_option( self::LOG, array_slice( $log, 0, 13, true ), false );
	}

	/**
	 * Spam submissions blocked in a month ('Y-m', default this month in the
	 * site's timezone), or across every month kept when $month is 'all'.
	 * Scout Build shows this on the Dashboard.
	 */
	public static function blocked( $month = '' ) {
		$log = get_option( self::LOG, array() );
		$log = is_array( $log ) ? $log : array();
		if ( 'all' === $month ) {
			return (int) array_sum( array_map( 'intval', $log ) );
		}
		$month = '' === $month ? wp_date( 'Y-m' ) : (string) $month;
		return isset( $log[ $month ] ) ? (int) $log[ $month ] : 0;
	}

	/**
	 * Friendly, generic message when we blocked a submission (so a falsely
	 * caught human knows to retry, without telling a bot why it failed).
	 */
	public static function message( $message, $form ) {
		if ( self::$blocked ) {
			return '<div class="validation_error">Your submission could not be verified. Please reload the page and try again.</div>';
		}
		return $message;
	}
}

add_action( 'init', array( 'Scout_Forms_Guard', 'init' ) );
