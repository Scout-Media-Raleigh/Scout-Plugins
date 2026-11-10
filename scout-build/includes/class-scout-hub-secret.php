<?php
/**
 * Saved secrets that can stop working without anyone touching them.
 *
 * The Email app password, the theme key and the Hub keys are stored encrypted
 * with a key made from wp_salt( 'auth' ). When a host rotates the WordPress
 * security keys (scoutraleigh.com, Nov 2026), the stored value can no longer be
 * read and the screens used to fall back to "not set", silently, while form
 * email went out through the host and landed in junk.
 *
 * So each secret keeps a short salt fingerprint (the first 8 hex characters of
 * sha256( wp_salt( 'auth' ) ), never the salt) from when it was saved. When the
 * fingerprint differs, or the value will not decrypt, the secret reads as
 * "can't be read" instead of "not set", and one Site history line (operators
 * only) says what to do. Nothing here logs or shows a secret or the salt.
 *
 * Plain logic only, so it runs without WordPress (tools/tests/secret-health.php).
 *
 * @package Scout_Build
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scout_Hub_Secret {

	/** Fingerprints by secret: mail, theme, hub. */
	const PRINTS = 'scout_hub_secret_prints';

	/** The fingerprint whose change was already written to Site history. */
	const NOTICED = 'scout_hub_salt_noticed';

	const NONE       = 'none';
	const OK         = 'ok';
	const UNREADABLE = 'unreadable';

	/** @var array<string,array{state:string,plain:string}> One answer per secret and stored value, per request. */
	private static $cache = array();

	/** The first 8 hex characters of sha256 of the auth salt. Never the salt itself. */
	public static function fingerprint(): string {
		return substr( hash( 'sha256', (string) wp_salt( 'auth' ) ), 0, 8 );
	}

	/** Call right after a secret is saved: remembers which security keys it was made with. */
	public static function saved( string $name ): void {
		$prints          = self::prints();
		$prints[ $name ] = self::fingerprint();
		update_option( self::PRINTS, $prints, false );
		self::$cache = array();
	}

	/** Call when a secret is removed. */
	public static function forgot( string $name ): void {
		$prints = self::prints();
		unset( $prints[ $name ] );
		update_option( self::PRINTS, $prints, false );
		self::$cache = array();
	}

	/**
	 * Read one stored secret.
	 *
	 * @param string   $name    mail, theme or hub.
	 * @param string   $stored  The option value: '', 'e:…' (encrypted) or 'p:…' (no openssl).
	 * @param callable $decrypt The owner's own decrypt( string ): string.
	 * @return array{state:string,plain:string} state is none, ok or unreadable.
	 */
	public static function read( string $name, string $stored, callable $decrypt ): array {
		if ( '' === $stored ) {
			return array( 'state' => self::NONE, 'plain' => '' );
		}
		$ck = $name . ':' . md5( $stored );
		if ( isset( self::$cache[ $ck ] ) ) {
			return self::$cache[ $ck ];
		}
		$prints = self::prints();
		$saved  = (string) ( $prints[ $name ] ?? '' );
		$now    = self::fingerprint();
		$sealed = 0 === strpos( $stored, 'e:' );
		if ( $sealed && '' !== $saved && ! hash_equals( $saved, $now ) ) {
			// The security keys changed since it was saved: don't even try.
			self::noticed();
			return self::$cache[ $ck ] = array( 'state' => self::UNREADABLE, 'plain' => '' );
		}
		$plain = (string) $decrypt( $stored );
		// A wrong key can, now and then, decrypt to noise. Every secret here is plain text.
		if ( '' === $plain || ! preg_match( '/^[\x20-\x7E]+$/', $plain ) ) {
			if ( $sealed ) {
				self::noticed();
			}
			return self::$cache[ $ck ] = array( 'state' => self::UNREADABLE, 'plain' => '' );
		}
		if ( $sealed && '' === $saved ) {
			// Saved before fingerprints existed and still readable: note the keys it works with.
			$prints[ $name ] = $now;
			update_option( self::PRINTS, $prints, false );
		}
		return self::$cache[ $ck ] = array( 'state' => self::OK, 'plain' => $plain );
	}

	/** Forget this request's answers (tests, and after a save). */
	public static function reset(): void {
		self::$cache = array();
	}

	/** @return array<string,string> */
	private static function prints(): array {
		$p = get_option( self::PRINTS, array() );
		return is_array( $p ) ? $p : array();
	}

	/**
	 * One Site history line the first time a change of security keys is seen.
	 * Operators only (show_client false). The line names what to do, nothing else.
	 */
	private static function noticed(): void {
		$now = self::fingerprint();
		if ( get_option( self::NOTICED, '' ) === $now ) {
			return;
		}
		update_option( self::NOTICED, $now, false );
		$write = static function (): void {
			if ( class_exists( 'Scout_Hub_Log' ) ) {
				Scout_Hub_Log::add(
					array(
						'title'       => __( 'Security keys changed: reconnect Email and paste the theme key', 'scout-build' ),
						'detail'      => __( 'The site\'s security keys changed, so the saved Email connection, theme key and Hub keys can\'t be read. Connect Email again, paste the theme key on Updates, and make new Hub keys if the site had them.', 'scout-build' ),
						'kind'        => 'setup',
						'topic'       => 'setup',
						'source'      => 'Scout Build',
						'show_client' => false,
					)
				);
			}
		};
		if ( function_exists( 'did_action' ) && ! did_action( 'init' ) ) {
			add_action( 'init', $write, 99 );
		} else {
			$write();
		}
	}

	/* ---------- the Site status rows ---------- */

	/** Site email when the Google connection was set up and can't be read. */
	public static function mail_row( string $url ): array {
		return array(
			'label'     => __( 'Site email', 'scout-build' ),
			'value'     => __( 'Can\'t be read', 'scout-build' ),
			'state'     => 'bad',
			'note'      => __( 'Your Google connection can\'t be read anymore. This happens when the site\'s security keys change. Connect again.', 'scout-build' ),
			'link'      => $url,
			'link_text' => __( 'Connect again', 'scout-build' ),
		);
	}

	/** Theme updates when the saved key can't be read. */
	public static function theme_row( string $url ): array {
		return array(
			'label'     => __( 'Theme updates', 'scout-build' ),
			'value'     => __( 'Stopped', 'scout-build' ),
			'state'     => 'bad',
			'note'      => __( 'Theme updates stopped: the saved key can\'t be read. Paste it again.', 'scout-build' ),
			'link'      => $url,
			'link_text' => __( 'Paste the key', 'scout-build' ),
		);
	}
}
