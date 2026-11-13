<?php
/**
 * Scout Chat: the widget, the questions inbox, and the AI connection.
 *
 * Two modes, no rebuild between them:
 * - Collect (until an AI server is connected): the scripted paths (what it
 *   costs, our work, book a call, existing clients) answer right away, and any
 *   other question is saved to the inbox with the visitor's name and email,
 *   and emailed to the team, who answer personally.
 * - AI: free-text questions go to the AI server (Sean's, outside WordPress,
 *   so no AI key ever sits on a client site). If it fails or is slow, the
 *   chat falls back to collecting the question. Every conversation that
 *   leaves contact details is saved either way.
 *
 * AI server contract (see README): POST {question, history, page, site}
 * with "Authorization: Bearer <connection key>", answering
 * {"answer": "...", "handoff": false}.
 *
 * @package Scout_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scout_Chat {

	const OPTION = 'scout_chat';
	const CPT    = 'scout_chat_q';
	const NS     = 'scout-chat/v1';

	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'register_type' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_head', array( __CLASS__, 'presence_style' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'outside_script' ), 20 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/** @return array<string, mixed> */
	public static function settings(): array {
		$s = (array) get_option( self::OPTION, array() );
		return array(
			'enabled'   => ! empty( $s['enabled'] ),
			'name'      => (string) ( $s['name'] ?? '' ),
			'team'      => (string) ( $s['team'] ?? '' ),
			'launcher'  => (string) ( $s['launcher'] ?? '' ),
			'teaser'    => ! isset( $s['teaser'] ) || ! empty( $s['teaser'] ),
			'teaser_by' => (string) ( $s['teaser_by'] ?? '' ),
			'notify'    => (string) ( $s['notify'] ?? '' ),
			'alert'     => (string) ( $s['alert'] ?? '' ),
			'book_url'  => (string) ( $s['book_url'] ?? '' ),
			'work_url'  => (string) ( $s['work_url'] ?? '' ),
			'photo_1'   => (string) ( $s['photo_1'] ?? '' ),
			'photo_2'   => (string) ( $s['photo_2'] ?? '' ),
			'ai_url'    => (string) ( $s['ai_url'] ?? '' ),
			'ai_key'    => (string) ( $s['ai_key'] ?? '' ),
			// Outside chat widget (0.4.0). Off unless ticked.
			'outside'            => ! empty( $s['outside'] ),
			'outside_src'        => (string) ( $s['outside_src'] ?? '' ),
			'outside_async'      => ! empty( $s['outside_async'] ),
			'outside_defer'      => ! empty( $s['outside_defer'] ),
			'outside_attrs'      => (string) ( $s['outside_attrs'] ?? '' ),
			'outside_config'     => (string) ( $s['outside_config'] ?? '' ),
			'outside_config_var' => (string) ( $s['outside_config_var'] ?? '' ),
			'outside_position'   => 'left' === ( $s['outside_position'] ?? '' ) ? 'left' : 'right',
			'outside_clearance'  => isset( $s['outside_clearance'] ) && '' !== (string) $s['outside_clearance'] ? max( 0, min( 400, (int) $s['outside_clearance'] ) ) : self::CLEARANCE,
			'outside_selector'   => (string) ( $s['outside_selector'] ?? '' ),
		);
	}

	/**
	 * How far the chat launcher sits above the bottom of a phone screen while
	 * Scout Call Buttons' bar shows. Scout Chat's own launcher moves up by this
	 * much (contact.css lifts .sc-root to 86px on body.scc-on); an outside widget
	 * gets the same default.
	 */
	const CLEARANCE = 86;

	/** Attributes an outside script tag may carry besides data-*. */
	const OUTSIDE_ATTRS = array( 'id', 'crossorigin', 'referrerpolicy', 'integrity', 'charset' );

	/** Is the chat on for the page being viewed (either widget)? */
	public static function showing(): bool {
		static $on = null;
		if ( null === $on ) {
			$on = self::settings()['enabled'] && ! is_admin() && (bool) apply_filters( 'scout_chat_show', true );
		}
		return $on;
	}

	/** True when an outside chat widget is on, with a usable script address. */
	public static function outside_on(): bool {
		$s = self::settings();
		return $s['outside'] && '' !== self::outside_url( $s['outside_src'] );
	}

	/**
	 * A safe outside script address, or '' when it is not one: https only, a
	 * real host, no user or password, and inside the allowlist when the site
	 * sets one with the scout_chat_outside_hosts filter.
	 */
	public static function outside_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url || strlen( $url ) > 500 || 0 !== strpos( strtolower( $url ), 'https://' ) ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) ) {
			return '';
		}
		$host = strtolower( (string) $parts['host'] );
		if ( ! preg_match( '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host ) ) {
			return '';
		}
		/**
		 * Filter: the script hosts an outside chat widget may load from, like
		 * array( 'widget.example.com' ). Empty (the default) allows any https host.
		 * A listed host also allows its subdomains.
		 */
		$allow = array_filter( array_map( 'strtolower', array_map( 'strval', (array) apply_filters( 'scout_chat_outside_hosts', array() ) ) ) );
		if ( $allow ) {
			$ok = false;
			foreach ( $allow as $a ) {
				$a = ltrim( trim( $a ), '.' );
				if ( '' !== $a && ( $host === $a || str_ends_with( $host, '.' . $a ) ) ) {
					$ok = true;
					break;
				}
			}
			if ( ! $ok ) {
				return '';
			}
		}
		$clean = esc_url_raw( $url, array( 'https' ) );
		return $clean === $url ? $clean : '';
	}

	/**
	 * The script tag's extra attributes from "name=value" lines. Only data-*
	 * names (a bare name gets data- added) and a few plain ones (OUTSIDE_ATTRS).
	 *
	 * @return array<string, string>
	 */
	public static function outside_attrs( string $raw ): array {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || false === strpos( $line, '=' ) ) {
				continue;
			}
			list( $name, $value ) = array_map( 'trim', explode( '=', $line, 2 ) );
			$name  = strtolower( $name );
			$value = trim( $value, "\"'" );
			if ( ! in_array( $name, self::OUTSIDE_ATTRS, true ) && 0 !== strpos( $name, 'data-' ) ) {
				$name = 'data-' . $name;
			}
			if ( ! preg_match( '/^(data-[a-z0-9][a-z0-9_.-]{0,60}|' . implode( '|', self::OUTSIDE_ATTRS ) . ')$/', $name ) ) {
				continue;
			}
			$out[ $name ] = mb_substr( sanitize_text_field( $value ), 0, 500 );
			if ( count( $out ) >= 20 ) {
				break;
			}
		}
		return $out;
	}

	/** A safe JavaScript global name for the config, or ''. */
	public static function outside_var( string $name ): string {
		$name = trim( $name );
		$deny = array( 'window', 'document', 'location', 'top', 'parent', 'self', 'frames', 'opener', 'name', 'origin', 'history', 'navigator', 'localStorage', 'sessionStorage', 'cookie', 'eval', 'Function', 'Object', 'Array', 'JSON', 'scoutChatConfig', 'scoutChatPresence' );
		return preg_match( '/^[A-Za-z_$][A-Za-z0-9_$]{0,60}$/', $name ) && ! in_array( $name, $deny, true ) ? $name : '';
	}

	/** A safe CSS selector for the outside launcher (ids, classes, tags, attribute tests), or ''. */
	public static function outside_selector( string $sel ): string {
		$sel = trim( $sel );
		return ( strlen( $sel ) <= 200 && preg_match( '/^[A-Za-z0-9_#.\-\[\]="\'\s,>:^$*~()]+$/', $sel ) && false === strpos( $sel, '</' ) && ! preg_match( '/[{};@\\\\]/', $sel ) ) ? $sel : '';
	}

	/**
	 * The signal the rest of the page reads: body classes scout-chat-on,
	 * scout-chat-outside (outside widget) and scout-chat-left or -right.
	 */
	public static function body_class( array $classes ): array {
		if ( self::outside_on() && self::showing() ) {
			$classes[] = 'scout-chat-on';
			$classes[] = 'scout-chat-outside';
			$classes[] = 'scout-chat-' . self::settings()['outside_position'];
		}
		return $classes;
	}

	/**
	 * For an outside widget: the --scout-chat-clearance number, and on phones
	 * with the call bar showing (body.scc-on, the same test contact.css uses for
	 * Scout Chat's own .sc-root), the launcher lifted by that much.
	 */
	public static function presence_style(): void {
		if ( ! self::showing() || ! self::outside_on() ) {
			return;
		}
		$s   = self::settings();
		$px  = (int) $s['outside_clearance'];
		$sel = self::outside_selector( $s['outside_selector'] );
		$css = ':root{--scout-chat-clearance:' . $px . 'px}';
		if ( '' !== $sel ) {
			$lift = array();
			foreach ( explode( ',', $sel ) as $one ) {
				$lift[] = 'body.scc-on ' . trim( $one );
			}
			$css .= '@media (max-width:760px){' . implode( ',', $lift ) . '{bottom:calc(' . $px . 'px + env(safe-area-inset-bottom,0px))!important}}';
		}
		echo '<style id="scout-chat-outside-css">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- built from an int and a checked selector.
	}

	/** The outside widget's script, in the footer, in place of Scout Chat's own. */
	public static function outside_script(): void {
		if ( ! self::showing() || ! self::outside_on() ) {
			return;
		}
		$s   = self::settings();
		$src = self::outside_url( $s['outside_src'] );
		// Who else on the page needs to know: the corner, the clearance, whether the call bar is on.
		$presence = array(
			'source'    => 'outside',
			'position'  => $s['outside_position'],
			'clearance' => (int) $s['outside_clearance'],
		);
		$inline = 'window.scoutChatPresence=' . wp_json_encode( $presence ) . ';';
		$var    = self::outside_var( $s['outside_config_var'] );
		$config = '' !== trim( $s['outside_config'] ) ? json_decode( $s['outside_config'], true ) : null;
		if ( '' !== $var && is_array( $config ) ) {
			$inline .= 'window[' . wp_json_encode( $var ) . ']=' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES ) . ';';
		}
		wp_print_inline_script_tag( $inline, array( 'id' => 'scout-chat-outside-config' ) );
		$attrs = array( 'src' => $src );
		if ( $s['outside_async'] ) {
			$attrs['async'] = true;
		}
		if ( $s['outside_defer'] ) {
			$attrs['defer'] = true;
		}
		foreach ( self::outside_attrs( $s['outside_attrs'] ) as $k => $v ) {
			if ( ! isset( $attrs[ $k ] ) ) {
				$attrs[ $k ] = $v;
			}
		}
		wp_print_script_tag( $attrs );
	}

	/**
	 * Where new chat emails go: this screen's own address when one is set, else
	 * the shared "Send notices to" address in Business info (Scout Core), else
	 * the site's admin email.
	 */
	public static function notify_to(): string {
		$own = sanitize_email( self::settings()['notify'] );
		if ( '' !== $own ) {
			return $own;
		}
		return sanitize_email( function_exists( 'scout_core_notice_email' ) ? (string) scout_core_notice_email() : (string) get_option( 'admin_email' ) );
	}

	/** The connection key: wp-config's SCOUT_CHAT_AI_KEY wins over the setting. */
	public static function ai_key(): string {
		return defined( 'SCOUT_CHAT_AI_KEY' ) ? (string) SCOUT_CHAT_AI_KEY : self::settings()['ai_key'];
	}

	/** The site's booking page from Scout Booking, or Contact when there is none (or it was removed). */
	public static function site_booking_url(): string {
		$url = function_exists( 'scout_booking_url' ) ? (string) scout_booking_url() : '';
		return '' !== $url ? $url : home_url( '/contact/' );
	}

	public static function ai_connected(): bool {
		$url = self::settings()['ai_url'];
		return '' !== self::ai_key() && 0 === strpos( $url, 'https://' );
	}

	/** "Sean and Joy", or "our team" when no names are set. */
	public static function team(): string {
		$t = trim( self::settings()['team'] );
		return '' !== $t ? $t : __( 'our team', 'scout-chat' );
	}

	public static function register_type(): void {
		register_post_type(
			self::CPT,
			array(
				'labels'          => array(
					'name'          => __( 'Chat messages', 'scout-chat' ),
					'singular_name' => __( 'Chat message', 'scout-chat' ),
					'edit_item'     => __( 'Chat message', 'scout-chat' ),
					'search_items'  => __( 'Search messages', 'scout-chat' ),
					'not_found'     => __( 'No messages yet. Every chat where a visitor leaves contact details lands here.', 'scout-chat' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
			)
		);
	}

	/** On every public page while the chat is switched on. */
	public static function assets(): void {
		$s = self::settings();
		if ( ! self::showing() || self::outside_on() ) {
			return; // Off, or an outside chat widget takes its place (outside_script).
		}
		wp_enqueue_style( 'scout-chat', SCOUT_CHAT_URL . 'assets/chat.css', array(), SCOUT_CHAT_VERSION );
		wp_enqueue_script( 'scout-chat', SCOUT_CHAT_URL . 'assets/chat.js', array(), SCOUT_CHAT_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		$book = '' !== $s['book_url'] ? $s['book_url'] : self::site_booking_url();
		$name = '' !== $s['name'] ? $s['name'] : sprintf( /* translators: %s: site name */ __( '%s assistant', 'scout-chat' ), self::short_site_name() );
		wp_localize_script(
			'scout-chat',
			'scoutChatConfig',
			array(
				'rest'     => esc_url_raw( rest_url( self::NS ) ),
				'name'     => $name,
				'team'     => self::team(),
				'hasTeam'  => '' !== trim( $s['team'] ),
				'launcher' => '' !== $s['launcher'] ? $s['launcher'] : ( '' !== trim( $s['team'] ) ? sprintf( /* translators: %s: team names */ __( 'Ask %s', 'scout-chat' ), str_replace( ' and ', ' & ', $s['team'] ) ) : __( 'Ask us', 'scout-chat' ) ),
				'teaser'   => $s['teaser'],
				'teaserBy' => $s['teaser_by'],
				'photos'   => array_values( array_filter( array( $s['photo_1'], $s['photo_2'] ) ) ),
				'bookUrl'  => esc_url_raw( $book ),
				'workUrl'  => esc_url_raw( $s['work_url'] ),
				'ai'       => self::ai_connected(),
			)
		);
	}

	private static function short_site_name(): string {
		$n = (string) get_bloginfo( 'name' );
		$n = preg_split( '/\s*[|\-–]\s*/u', $n )[0];
		return trim( preg_replace( '/\s+(Media|&).*$/i', '', $n ) ) ?: $n;
	}

	public static function routes(): void {
		register_rest_route(
			self::NS,
			'/lead',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'lead' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NS,
			'/ask',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'ask' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** At most $max requests per visitor IP per 10 minutes, per bucket. */
	private static function too_many( string $bucket, int $max ): bool {
		$ip  = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
		$key = 'scout_chat_' . $bucket . '_' . md5( $ip . wp_salt( 'nonce' ) );
		$n   = (int) get_transient( $key );
		if ( $n >= $max ) {
			return true;
		}
		set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );
		return false;
	}

	/** Clean a transcript sent by the widget: [{from, text}], newest last, capped. */
	private static function clean_history( $history ): array {
		$out = array();
		foreach ( array_slice( (array) $history, -30 ) as $m ) {
			if ( ! is_array( $m ) ) {
				continue;
			}
			$text = trim( wp_strip_all_tags( (string) ( $m['text'] ?? '' ) ) );
			if ( '' === $text ) {
				continue;
			}
			$out[] = array(
				'from' => 'user' === ( $m['from'] ?? '' ) ? 'user' : 'bot',
				'text' => mb_substr( $text, 0, 1500 ),
			);
		}
		return $out;
	}

	/** Save a conversation with contact details, and email the team. */
	public static function lead( WP_REST_Request $req ) {
		if ( '' !== (string) $req->get_param( 'website' ) ) { // Honeypot: people never fill it.
			return array( 'ok' => true );
		}
		if ( self::too_many( 'lead', 5 ) ) {
			return new WP_Error( 'scout_chat_slow', __( 'Too many messages in a row. Please try again in a few minutes.', 'scout-chat' ), array( 'status' => 429 ) );
		}
		$name  = sanitize_text_field( mb_substr( (string) $req->get_param( 'name' ), 0, 100 ) );
		$email = sanitize_email( (string) $req->get_param( 'email' ) );
		$phone = self::phone( (string) $req->get_param( 'phone' ) );
		if ( '' === $name ) {
			return new WP_Error( 'scout_chat_name', __( 'Add your name so we know who we are talking to.', 'scout-chat' ), array( 'status' => 400 ) );
		}
		if ( '' === $phone && ! is_email( $email ) ) {
			return new WP_Error( 'scout_chat_email', __( 'Add a phone number or an email so we can reply.', 'scout-chat' ), array( 'status' => 400 ) );
		}
		if ( ! is_email( $email ) ) {
			$email = '';
		}
		$kinds   = array(
			'question' => __( 'Question', 'scout-chat' ),
			'change'   => __( 'Change request', 'scout-chat' ),
			'person'   => __( 'Wants a person', 'scout-chat' ),
		);
		$kind    = sanitize_key( (string) $req->get_param( 'kind' ) );
		$kind    = isset( $kinds[ $kind ] ) ? $kind : 'question';
		$history = self::clean_history( $req->get_param( 'history' ) );
		$page    = esc_url_raw( (string) $req->get_param( 'page' ) );
		$first   = '';
		foreach ( array_reverse( $history ) as $m ) {
			if ( 'user' === $m['from'] ) {
				$first = $m['text'];
				break;
			}
		}
		$title = $kinds[ $kind ] . ': ' . ( '' !== $first ? wp_trim_words( $first, 12 ) : $name );
		$lines = array();
		foreach ( $history as $m ) {
			$lines[] = ( 'user' === $m['from'] ? $name : __( 'Assistant', 'scout-chat' ) ) . ': ' . $m['text'];
		}
		$id = wp_insert_post(
			array(
				'post_type'    => self::CPT,
				'post_status'  => 'private',
				'post_title'   => $title,
				'post_content' => implode( "\n\n", $lines ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return new WP_Error( 'scout_chat_save', __( 'Something went wrong saving that. Please email us instead.', 'scout-chat' ), array( 'status' => 500 ) );
		}
		update_post_meta( $id, '_scout_chat_name', $name );
		update_post_meta( $id, '_scout_chat_email', $email );
		update_post_meta( $id, '_scout_chat_phone', $phone );
		update_post_meta( $id, '_scout_chat_kind', $kind );
		update_post_meta( $id, '_scout_chat_page', $page );
		update_post_meta( $id, '_scout_chat_answered', 0 );

		$contact = trim( ( '' !== $phone ? __( 'Text back:', 'scout-chat' ) . ' ' . self::pretty_phone( $phone ) . ' ' : '' ) . ( '' !== $email ? '<' . $email . '>' : '' ) );
		$to      = self::notify_to();
		if ( is_email( $to ) ) {
			$body = $kinds[ $kind ] . "\n" . $name . ' ' . $contact . "\n" . ( '' !== $phone ? __( 'Tap to text:', 'scout-chat' ) . ' sms:+' . $phone . "\n" : '' ) . ( $page ? __( 'Page:', 'scout-chat' ) . ' ' . $page . "\n" : '' )
				. "\n" . implode( "\n\n", $lines ) . "\n\n" . __( 'Open Chat messages:', 'scout-chat' ) . ' ' . admin_url( 'edit.php?post_type=' . self::CPT );
			wp_mail( $to, sprintf( /* translators: %s: question title */ __( 'New chat: %s', 'scout-chat' ), $title ), $body, '' !== $email ? array( 'Reply-To: ' . $name . ' <' . $email . '>' ) : array() );
		}
		// A short alert for a phone: the carrier's email-to-text address, or any address.
		$alert = sanitize_email( self::settings()['alert'] );
		if ( is_email( $alert ) ) {
			$short = $name . ': ' . wp_trim_words( '' !== $first ? $first : $kinds[ $kind ], 14 ) . ( '' !== $phone ? ' | ' . __( 'Text', 'scout-chat' ) . ' ' . self::pretty_phone( $phone ) : ( '' !== $email ? ' | ' . $email : '' ) );
			wp_mail( $alert, __( 'Site chat', 'scout-chat' ), mb_substr( $short, 0, 150 ) );
		}
		// A change request from the chat also joins the site's request list in
		// Scout Build, so one list holds every request however it came in.
		if ( 'change' === $kind && class_exists( 'Scout_Hub_Requests' ) && method_exists( 'Scout_Hub_Requests', 'create' ) ) {
			$ask = '' !== $first ? $first : $title;
			$who = $name . ( '' !== $email ? ' <' . $email . '>' : '' ) . ( '' !== $phone ? ' ' . self::pretty_phone( $phone ) : '' );
			$rid = Scout_Hub_Requests::create( $ask . "\n\n" . __( 'From the site chat:', 'scout-chat' ) . ' ' . $who, $page, 'whenever', 0, $name );
			if ( $rid ) {
				update_post_meta( $id, '_scout_chat_request', $rid );
				// The request list now tracks it, so the Inbox shows it once, as the request.
				update_post_meta( $id, '_scout_chat_answered', 1 );
			}
		}
		do_action( 'scout_chat_lead', $id, $kind, $name, $email );
		return array( 'ok' => true );
	}

	/** Digits with the US country code, or '' when it is not a phone number. */
	public static function phone( string $raw ): string {
		$d = preg_replace( '/\D/', '', $raw );
		if ( 10 === strlen( $d ) ) {
			$d = '1' . $d;
		}
		return ( 11 === strlen( $d ) && '1' === $d[0] ) ? $d : '';
	}

	/** 19195551234 becomes (919) 555-1234. */
	public static function pretty_phone( string $d ): string {
		return 11 === strlen( $d ) ? sprintf( '(%s) %s-%s', substr( $d, 1, 3 ), substr( $d, 4, 3 ), substr( $d, 7 ) ) : $d;
	}

	/** Pass a free-text question to the AI server. Falls back to collecting. */
	public static function ask( WP_REST_Request $req ) {
		$question = trim( wp_strip_all_tags( mb_substr( (string) $req->get_param( 'question' ), 0, 1000 ) ) );
		if ( '' === $question || ! self::ai_connected() ) {
			return array( 'answer' => '', 'collect' => true );
		}
		if ( self::too_many( 'ask', 20 ) ) {
			return array( 'answer' => '', 'collect' => true );
		}
		$res = wp_remote_post(
			self::settings()['ai_url'],
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . self::ai_key(),
				),
				'body'    => wp_json_encode(
					array(
						'question' => $question,
						'history'  => self::clean_history( $req->get_param( 'history' ) ),
						'page'     => esc_url_raw( (string) $req->get_param( 'page' ) ),
						'site'     => home_url( '/' ),
					)
				),
			)
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return array( 'answer' => '', 'collect' => true );
		}
		$data   = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$answer = is_array( $data ) ? trim( wp_strip_all_tags( (string) ( $data['answer'] ?? '' ) ) ) : '';
		if ( '' === $answer ) {
			return array( 'answer' => '', 'collect' => true );
		}
		return array(
			'answer'  => mb_substr( $answer, 0, 2000 ),
			'handoff' => ! empty( $data['handoff'] ),
			'collect' => false,
		);
	}
}
