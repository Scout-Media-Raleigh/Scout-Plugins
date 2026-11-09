<?php
/**
 * Google totals, checked every day with a Google API key (Places API, New).
 *
 * A lighter path than the Business Profile sign-in in class-scout-reviews-google.php:
 * no sign-in and no review import, only the Google rating and review count.
 * With an API key and the business's place ID set on Review settings, the
 * site asks Google once a day (WP-Cron, plus "Check Google now"):
 *
 *   GET https://places.googleapis.com/v1/places/{PLACE_ID}
 *   X-Goog-Api-Key: the key
 *   X-Goog-FieldMask: rating,userRatingCount
 *
 * A good answer replaces the Google rating and count on Review settings and
 * stamps the totals as checked now. A failed one changes nothing and keeps a
 * plain-words error (never the key) for Review settings and Site status.
 *
 * The key is a secret. It is stored encrypted with a key made from the site's
 * own security keys (the same way Scout Build stores the theme key), with a
 * short fingerprint of those keys so a change reads as "can't be read" instead
 * of silently going blank. It can also be set as SCOUT_REVIEWS_PLACES_KEY in
 * wp-config.php. It is never printed back, never logged, and never part of
 * scout-setup.json, the update report, REST output or the Hub API. The place
 * ID is not a secret; the theme can fill it with `reviews.google_place_id`.
 *
 * While the Business Profile sign-in is connected, its sync owns the Google
 * numbers and this check stands down.
 *
 * Plain logic, so it runs without WordPress (tools/tests/reviews-places.php).
 *
 * @package Scout_Reviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scout_Reviews_Places {

	const KEY_OPTION = 'scout_reviews_places_key'; // The encrypted key ('e:…', or 'p:…' without openssl).
	const OPTION     = 'scout_reviews_places';     // place_id, key_print, last_check, last_ok, last_error, failing_since, rating, count.
	const CRON       = 'scout_reviews_places_check';
	const ACTION     = 'scout_reviews_places';
	const URL        = 'https://places.googleapis.com/v1/places/';

	/** Days of failed checks before Site status asks someone to look. */
	const FAIL_DAYS = 3;

	public static function boot(): void {
		add_action( self::CRON, array( __CLASS__, 'run' ) );
		add_action( 'admin_init', array( __CLASS__, 'keep_scheduled' ) );
		add_action( 'scout_reviews_settings_top', array( __CLASS__, 'box' ), 20 );
		add_action( 'admin_post_' . self::ACTION . '_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION . '_check', array( __CLASS__, 'handle_check' ) );
	}

	/* ---------- stored state ---------- */

	public static function get(): array {
		$saved = get_option( self::OPTION, array() );
		return array_merge(
			array(
				'place_id'      => '',
				'key_print'     => '',
				'last_check'    => 0,
				'last_ok'       => 0,
				'last_error'    => '',
				'failing_since' => 0,
				'rating'        => 0,
				'count'         => 0,
			),
			is_array( $saved ) ? $saved : array()
		);
	}

	private static function update( array $changes ): void {
		update_option( self::OPTION, array_merge( self::get(), $changes ), false );
	}

	public static function place_id(): string {
		return (string) self::get()['place_id'];
	}

	/** A place ID as Google writes it (ChIJ…), with a "places/" prefix taken off. '' when it isn't one. */
	public static function clean_place_id( string $id ): string {
		$id = trim( preg_replace( '#^places/#', '', trim( $id ) ) );
		return preg_match( '/^[A-Za-z0-9_-]{10,400}$/', $id ) ? $id : '';
	}

	public static function set_place_id( string $id ): void {
		self::update( array( 'place_id' => self::clean_place_id( $id ) ) );
		self::keep_scheduled();
	}

	/** The theme's `reviews.google_place_id`: fills a blank place ID only. */
	public static function fill_place_id( string $id ): bool {
		$id = self::clean_place_id( $id );
		if ( '' === $id || '' !== self::place_id() ) {
			return false;
		}
		self::set_place_id( $id );
		return true;
	}

	/* ---------- the key ---------- */

	/**
	 * Where the key stands: config (wp-config.php), saved, unreadable (saved,
	 * but the site's security keys changed since) or none. Never the key.
	 */
	public static function key_state(): string {
		if ( defined( 'SCOUT_REVIEWS_PLACES_KEY' ) && '' !== (string) SCOUT_REVIEWS_PLACES_KEY ) {
			return 'config';
		}
		$stored = (string) get_option( self::KEY_OPTION, '' );
		if ( '' === $stored ) {
			return 'none';
		}
		return '' !== self::read_key( $stored ) ? 'saved' : 'unreadable';
	}

	/** The key itself, for the request header only. '' when none or unreadable. */
	private static function key(): string {
		if ( defined( 'SCOUT_REVIEWS_PLACES_KEY' ) && '' !== (string) SCOUT_REVIEWS_PLACES_KEY ) {
			return (string) SCOUT_REVIEWS_PLACES_KEY;
		}
		return self::read_key( (string) get_option( self::KEY_OPTION, '' ) );
	}

	public static function save_key( string $key ): void {
		update_option( self::KEY_OPTION, self::encrypt( $key ), false );
		self::update( array( 'key_print' => self::fingerprint(), 'last_error' => '', 'failing_since' => 0 ) );
		self::keep_scheduled();
	}

	public static function forget_key(): void {
		delete_option( self::KEY_OPTION );
		self::update( array( 'key_print' => '', 'last_error' => '', 'failing_since' => 0 ) );
		self::keep_scheduled();
	}

	/** A Google API key's shape: letters, digits, - and _, 20 to 100 long. */
	public static function looks_like_key( string $key ): bool {
		return (bool) preg_match( '/^[A-Za-z0-9_-]{20,100}$/', $key );
	}

	private static function read_key( string $stored ): string {
		if ( '' === $stored ) {
			return '';
		}
		$print = (string) self::get()['key_print'];
		if ( 0 === strpos( $stored, 'e:' ) && '' !== $print && ! hash_equals( $print, self::fingerprint() ) ) {
			return ''; // The security keys changed since it was saved.
		}
		$plain = self::decrypt( $stored );
		return self::looks_like_key( $plain ) ? $plain : '';
	}

	/** The first 8 hex characters of sha256 of the auth salt. Never the salt itself. */
	private static function fingerprint(): string {
		return substr( hash( 'sha256', (string) wp_salt( 'auth' ) ), 0, 8 );
	}

	private static function cipher_key(): string {
		return hash( 'sha256', wp_salt( 'auth' ) . 'scout-reviews-places', true );
	}

	private static function encrypt( string $plain ): string {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return 'p:' . base64_encode( $plain ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}
		$iv = random_bytes( 16 );
		return 'e:' . base64_encode( $iv . openssl_encrypt( $plain, 'aes-256-cbc', self::cipher_key(), OPENSSL_RAW_DATA, $iv ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	private static function decrypt( string $stored ): string {
		$raw = base64_decode( substr( $stored, 2 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw ) {
			return '';
		}
		if ( 0 === strpos( $stored, 'p:' ) ) {
			return $raw;
		}
		if ( ! function_exists( 'openssl_decrypt' ) || strlen( $raw ) < 17 ) {
			return '';
		}
		$out = openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', self::cipher_key(), OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );
		return false === $out ? '' : $out;
	}

	/* ---------- the daily check ---------- */

	/** A key (in any state) and a place ID are set, and the Business Profile sign-in is not doing the job. */
	public static function is_on(): bool {
		return 'none' !== self::key_state() && '' !== self::place_id() && ! Scout_Reviews_Google::is_ready();
	}

	public static function keep_scheduled(): void {
		$next = wp_next_scheduled( self::CRON );
		if ( self::is_on() && ! $next ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		} elseif ( ! self::is_on() && $next ) {
			wp_clear_scheduled_hook( self::CRON );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON );
	}

	/**
	 * Ask Google for the rating and review count, once.
	 *
	 * @return array|WP_Error { rating, count } on success.
	 */
	public static function run() {
		if ( ! self::is_on() ) {
			return new WP_Error( 'scout_reviews_places_off', __( 'Add a Google API key and a place ID first.', 'scout-reviews' ) );
		}
		$result = self::ask();
		$now    = time();
		if ( is_wp_error( $result ) ) {
			$state = self::get();
			self::update(
				array(
					'last_check'    => $now,
					'last_error'    => $result->get_error_message(),
					'failing_since' => $state['failing_since'] ? (int) $state['failing_since'] : $now,
				)
			);
			return $result;
		}
		Scout_Reviews_Settings::set_profile( 'google', $result ); // Stamps the totals as checked now.
		self::update(
			array(
				'last_check'    => $now,
				'last_ok'       => $now,
				'last_error'    => '',
				'failing_since' => 0,
				'rating'        => $result['rating'],
				'count'         => $result['count'],
			)
		);
		return $result;
	}

	/**
	 * @return array|WP_Error
	 */
	private static function ask() {
		$key = self::key();
		if ( '' === $key ) {
			return new WP_Error( 'scout_reviews_places_key', __( 'The saved Google API key can\'t be read anymore. This happens when the site\'s security keys change. Paste the key again.', 'scout-reviews' ) );
		}
		$response = wp_remote_get(
			self::URL . rawurlencode( self::place_id() ),
			array(
				'timeout' => 10,
				'headers' => array(
					'X-Goog-Api-Key'   => $key,
					'X-Goog-FieldMask' => 'rating,userRatingCount',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'scout_reviews_places_offline', __( 'Google could not be reached. The site tries again tomorrow.', 'scout-reviews' ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'scout_reviews_places_' . $code, self::error_words( $code ) );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'scout_reviews_places_body', __( 'Google\'s answer could not be read. The site tries again tomorrow.', 'scout-reviews' ) );
		}
		// A place with no reviews yet has neither field.
		$rating = round( (float) ( $data['rating'] ?? 0 ), 1 );
		return array(
			'rating' => ( $rating >= 1 && $rating <= 5 ) ? $rating : 0,
			'count'  => max( 0, (int) ( $data['userRatingCount'] ?? 0 ) ),
		);
	}

	/** Google's error codes in plain words. Google's own text is never shown: it can echo the request. */
	public static function error_words( int $code ): string {
		switch ( $code ) {
			case 400:
				return __( 'Google did not accept the request. Check the place ID and the API key.', 'scout-reviews' );
			case 401:
			case 403:
				return __( 'Google turned the API key down. Check that Places API (New) is turned on for the key\'s project and that the key\'s limits allow it.', 'scout-reviews' );
			case 404:
				return __( 'Google has no place with that place ID. Check it on Review settings.', 'scout-reviews' );
			case 429:
				return __( 'The API key reached Google\'s limit. The site tries again tomorrow.', 'scout-reviews' );
		}
		/* translators: %d: the error number Google sent. */
		return sprintf( __( 'Google answered with an error (%d). The site tries again tomorrow.', 'scout-reviews' ), $code );
	}

	/**
	 * Where the daily check stands, for Site status. Never the key.
	 *
	 * @return array{on:bool, working:bool, error:string, failing_days:int, last_ok:int}
	 */
	public static function status(): array {
		$s       = self::get();
		$on      = self::is_on();
		$failing = $on && '' !== $s['last_error'] && $s['failing_since'] ? (int) floor( ( time() - (int) $s['failing_since'] ) / DAY_IN_SECONDS ) : 0;
		return array(
			'on'           => $on,
			'working'      => $on && '' === $s['last_error'] && $s['last_ok'] > 0,
			'error'        => $on ? (string) $s['last_error'] : '',
			'failing_days' => $failing,
			'last_ok'      => (int) $s['last_ok'],
		);
	}

	/* ---------- Review settings ---------- */

	public static function box(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s     = self::get();
		$state = self::key_state();
		$post  = admin_url( 'admin-post.php' );
		?>
		<section class="sh-box srk-places" id="srk-places">
			<h2 class="sh-title"><?php esc_html_e( 'Check Google every day', 'scout-reviews' ); ?></h2>
			<?php if ( Scout_Reviews_Google::is_ready() ) : ?>
				<p class="sh-note"><?php esc_html_e( 'Google is connected above, so its sync keeps the Google rating and count. This check is not needed.', 'scout-reviews' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'With a Google API key and your place ID, the site looks up your Google rating and review count once a day and puts them in the Google row below.', 'scout-reviews' ); ?></p>
				<?php if ( self::is_on() ) : ?>
					<p class="sh-note">
						<?php
						if ( '' !== $s['last_error'] ) {
							/* translators: %s: what went wrong, in plain words. */
							echo esc_html( sprintf( __( 'The last check did not work: %s', 'scout-reviews' ), $s['last_error'] ) );
						} elseif ( $s['last_ok'] ) {
							echo esc_html(
								sprintf(
									/* translators: 1: time since the check, 2: rating, 3: review count. */
									__( 'Checked %1$s ago: %2$s stars from %3$s reviews.', 'scout-reviews' ),
									human_time_diff( (int) $s['last_ok'] ),
									number_format_i18n( (float) $s['rating'], 1 ),
									number_format_i18n( (int) $s['count'] )
								)
							);
						} else {
							esc_html_e( 'Not checked yet. The first check runs within the hour, or click Check Google now.', 'scout-reviews' );
						}
						?>
					</p>
				<?php endif; ?>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( $post ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION . '_save' ); ?>" />
				<?php wp_nonce_field( self::ACTION . '_save' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="srk-place-id"><?php esc_html_e( 'Google place ID', 'scout-reviews' ); ?></label></th>
						<td>
							<input type="text" id="srk-place-id" class="regular-text" name="place_id" value="<?php echo esc_attr( (string) $s['place_id'] ); ?>" autocomplete="off" placeholder="ChIJ..." />
							<p class="description"><?php esc_html_e( 'Find it with Google\'s Place ID Finder: search for the business and copy the ID that starts with ChIJ.', 'scout-reviews' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="srk-places-key"><?php esc_html_e( 'Google API key', 'scout-reviews' ); ?></label></th>
						<td>
							<?php if ( 'config' === $state ) : ?>
								<span class="sh-tag is-current"><?php esc_html_e( 'Set in wp-config.php', 'scout-reviews' ); ?></span>
							<?php elseif ( 'saved' === $state ) : ?>
								<span class="sh-tag is-current"><?php esc_html_e( 'Saved', 'scout-reviews' ); ?></span>
								<details class="srk-replace">
									<summary class="button"><?php esc_html_e( 'Replace', 'scout-reviews' ); ?></summary>
									<p><input type="password" id="srk-places-key" class="regular-text" name="api_key" value="" autocomplete="new-password" /></p>
									<p><label><input type="checkbox" name="forget_key" value="1" /> <?php esc_html_e( 'Remove the saved key', 'scout-reviews' ); ?></label></p>
								</details>
							<?php else : ?>
								<?php if ( 'unreadable' === $state ) : ?>
									<p class="sh-note"><?php esc_html_e( 'The saved key can\'t be read anymore. This happens when the site\'s security keys change. Paste it again.', 'scout-reviews' ); ?></p>
								<?php endif; ?>
								<input type="password" id="srk-places-key" class="regular-text" name="api_key" value="" autocomplete="new-password" />
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'In Google Cloud, turn on Places API (New), then make an API key under APIs & Services, Credentials, and limit it to Places API (New). The key is stored encrypted and never shown again. You can also set SCOUT_REVIEWS_PLACES_KEY in wp-config.php.', 'scout-reviews' ); ?></p>
						</td>
					</tr>
				</table>
				<div class="sh-links">
					<button type="submit" class="button"><?php esc_html_e( 'Save', 'scout-reviews' ); ?></button>
				</div>
			</form>
			<?php if ( self::is_on() ) : ?>
				<form method="post" action="<?php echo esc_url( $post ); ?>" class="sh-inline">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION . '_check' ); ?>" />
					<?php wp_nonce_field( self::ACTION . '_check' ); ?>
					<button type="submit" class="button sh-quiet"><?php esc_html_e( 'Check Google now', 'scout-reviews' ); ?></button>
				</form>
			<?php endif; ?>
		</section>
		<?php
	}

	public static function handle_save(): void {
		self::guard( 'save' );
		$place = trim( sanitize_text_field( wp_unslash( $_POST['place_id'] ?? '' ) ) );
		$key   = trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ) );
		if ( '' !== $place && '' === self::clean_place_id( $place ) ) {
			self::back( __( 'That place ID doesn\'t look right. It starts with ChIJ and has only letters, numbers, - and _.', 'scout-reviews' ), 'error' );
		}
		if ( '' !== $key && ! self::looks_like_key( $key ) ) {
			self::back( __( 'That doesn\'t look like a Google API key. Copy it again from Google Cloud.', 'scout-reviews' ), 'error' );
		}
		self::set_place_id( $place );
		if ( ! empty( $_POST['forget_key'] ) ) {
			self::forget_key();
			self::back( __( 'The API key is removed. Google is no longer checked every day.', 'scout-reviews' ) );
		}
		if ( '' !== $key ) {
			self::save_key( $key );
		}
		if ( self::is_on() ) {
			self::report( self::run() );
		}
		self::back( __( 'Saved.', 'scout-reviews' ) );
	}

	public static function handle_check(): void {
		self::guard( 'check' );
		self::report( self::run() );
	}

	/**
	 * @param array|WP_Error $result
	 */
	private static function report( $result ): void {
		if ( is_wp_error( $result ) ) {
			self::back( $result->get_error_message(), 'error' );
		}
		self::back(
			sprintf(
				/* translators: 1: rating, 2: review count. */
				__( 'Checked Google: %1$s stars from %2$s reviews. The Google row below is updated.', 'scout-reviews' ),
				number_format_i18n( (float) $result['rating'], 1 ),
				number_format_i18n( (int) $result['count'] )
			)
		);
	}

	private static function guard( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'scout-reviews' ), 403 );
		}
		check_admin_referer( self::ACTION . '_' . $action );
	}

	/** Back to Review settings, with the same notice the Google box uses. */
	private static function back( string $message, string $type = 'success' ): void {
		set_transient( Scout_Reviews_Google_Admin::NOTICE . get_current_user_id(), array( 'message' => $message, 'type' => $type ), MINUTE_IN_SECONDS );
		wp_safe_redirect( Scout_Reviews_Menu::url( Scout_Reviews_Settings::PAGE ) . '#srk-places' );
		exit;
	}
}
