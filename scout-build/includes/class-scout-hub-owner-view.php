<?php
/**
 * The owner's view of the dashboard: a simpler menu, Home cards and outside
 * links, and Home as the first screen after login.
 *
 * Everything here is off until the theme's scout-setup.json turns it on, so a
 * site without a "dashboard" section looks and works exactly as before
 * (Site Standard rule 20):
 *
 *   "dashboard": {
 *     "simple": {
 *       "hide_groups":  [ "search", "policies", "health" ],
 *       "hide_screens": [ "scout-redirects", "scout-contact", "scout-newsletter" ],
 *       "hide_menus":   [ "edit-comments.php", "tools.php", "options-general.php>options-privacy.php" ]
 *     },
 *     "land_on_home": true,
 *     "shortcuts": [ { "label": "Add an event", "url": "post-new.php?post_type=scout_event", "note": "A new date on the calendar." } ],
 *     "links":     [ { "label": "Your booking calendar", "url": "https://example.com/", "note": "Opens in a new tab." } ]
 *   }
 *
 * 1. simple: hides the listed Scout groups, Scout screens and WordPress menus
 *    from everyone who is not an operator. Having the section with at least one
 *    list turns it on; "on": false turns it off. While it is on, Today's
 *    technical items (failed health checks, a site hidden from Google) go to
 *    operators only. Hiding is never a lock: every screen keeps its address and
 *    its own capability check, and a "Show everything" switch on each person's
 *    profile brings every group, screen and menu back for them.
 * 2. land_on_home: people who are not operators open Scout Home after logging
 *    in, instead of the WordPress Dashboard. Off unless the file says true.
 * 3. shortcuts: extra lines for "Things you can do here" on Home, each a screen
 *    inside wp-admin. The scout_hub_home_shortcuts filter adjusts the list.
 * 4. links: a "Your other tools" card on Home with outside links (https only,
 *    each opens in a new tab). No links, no card.
 *
 * Plugins add their own cards to Home with the scout_hub_home_cards filter.
 *
 * @package Scout_Build
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scout_Hub_Owner_View {

	/** User meta: this person ticked "Show everything" on their profile. */
	const SHOW_ALL = 'scout_hub_show_everything';

	/** Groups that never hide: Home is everyone's first screen, and To do holds what waits on them. */
	const KEEP_GROUPS = array( 'overview', 'inbox' );

	/** Most outside links and shortcuts the setup file can add. */
	const MAX_ROWS = 8;

	/** @var array|null Settings read from the setup file, kept for the request. */
	private static $settings = null;

	public static function boot(): void {
		add_filter( 'scout_admin_hidden_groups', array( __CLASS__, 'hidden_groups' ) );
		// After every plugin and the hub's own ordering (999), before the admin kit shortens the menu (1000).
		add_action( 'admin_menu', array( __CLASS__, 'hide_screens' ), 999 );
		add_action( 'admin_menu', array( __CLASS__, 'hide_menus' ), 9999 );
		add_filter( 'scout_hub_today', array( __CLASS__, 'today' ), 99 );
		add_action( 'show_user_profile', array( __CLASS__, 'profile_field' ), 20 );
		add_action( 'personal_options_update', array( __CLASS__, 'save_profile' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 20, 3 );
	}

	/* ---------------------------------------------------------- settings */

	/**
	 * The "dashboard" section, cleaned. Every part defaults to off and empty.
	 *
	 * @param array|null $data The setup file, or null to read the theme's.
	 * @return array{simple:bool,hide_groups:string[],hide_screens:string[],hide_menus:string[],land_on_home:bool,shortcuts:array,links:array}
	 */
	public static function settings( ?array $data = null ): array {
		if ( null === $data && null !== self::$settings ) {
			return self::$settings;
		}
		$file = null === $data ? ( class_exists( 'Scout_Hub_Setup' ) ? Scout_Hub_Setup::data() : array() ) : $data;
		$d    = is_array( $file['dashboard'] ?? null ) ? $file['dashboard'] : array();
		$s    = is_array( $d['simple'] ?? null ) ? $d['simple'] : array();

		$groups = array_values( array_diff( array_unique( array_filter( array_map( 'sanitize_key', self::strings( $s['hide_groups'] ?? array() ) ) ) ), self::KEEP_GROUPS ) );
		$keys   = class_exists( 'Scout_Admin_UI' ) && defined( 'Scout_Admin_UI::GROUPS' ) ? array_keys( Scout_Admin_UI::GROUPS ) : array();
		if ( $keys ) {
			$groups = array_values( array_intersect( $groups, $keys ) );
		}
		$screens = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'clean_slug' ), self::strings( $s['hide_screens'] ?? array() ) ) ) ) );
		$menus   = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'clean_slug' ), self::strings( $s['hide_menus'] ?? array() ) ) ) ) );
		$simple  = ( $groups || $screens || $menus ) && false !== ( $s['on'] ?? true );

		$out = array(
			'simple'       => $simple,
			'hide_groups'  => $simple ? $groups : array(),
			'hide_screens' => $simple ? $screens : array(),
			'hide_menus'   => $simple ? $menus : array(),
			'land_on_home' => true === ( $d['land_on_home'] ?? false ),
			'shortcuts'    => self::shortcut_rows( $d['shortcuts'] ?? array() ),
			'links'        => self::link_rows( $d['links'] ?? array() ),
		);
		if ( null === $data ) {
			self::$settings = $out;
		}
		return $out;
	}

	/** Forget what was read, so the next call reads the setup file again. */
	public static function reset(): void {
		self::$settings = null;
	}

	/** @return string[] The scalar entries of a list. */
	private static function strings( $list ): array {
		return array_map( 'strval', array_filter( (array) $list, 'is_scalar' ) );
	}

	/** A menu or screen slug as WordPress keeps it ("scout-redirects", "edit.php?post_type=x", "a.php>b.php"). */
	public static function clean_slug( string $slug ): string {
		$slug = trim( $slug );
		return preg_match( '#^[A-Za-z0-9_.\-?=&/>]{1,120}$#', $slug ) && false === strpos( $slug, '..' ) ? $slug : '';
	}

	/**
	 * Outside links for "Your other tools": a label and an https address, with
	 * an optional short note. Anything else is dropped.
	 *
	 * @param mixed $rows Raw rows.
	 * @return array<int,array{label:string,url:string,note:string}>
	 */
	public static function link_rows( $rows ): array {
		$out = array();
		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
			$url   = self::https( (string) ( $row['url'] ?? '' ) );
			if ( '' === $label || '' === $url ) {
				continue;
			}
			$out[] = array(
				'label' => $label,
				'url'   => $url,
				'note'  => sanitize_text_field( (string) ( $row['note'] ?? '' ) ),
			);
			if ( count( $out ) >= self::MAX_ROWS ) {
				break;
			}
		}
		return $out;
	}

	/** A full https address with a real host, or ''. */
	public static function https( string $url ): string {
		$url = trim( $url );
		if ( 0 !== stripos( $url, 'https://' ) || strlen( $url ) > 500 ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}
		return (string) esc_url_raw( $url, array( 'https' ) );
	}

	/**
	 * Shortcuts from the setup file: a label and a screen inside wp-admin
	 * ("post-new.php?post_type=x", "admin.php?page=y"), with an optional note.
	 *
	 * @param mixed $rows Raw rows.
	 * @return array<int,array{url:string,label:string,note:string}>
	 */
	public static function shortcut_rows( $rows ): array {
		$out = array();
		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
			$path  = ltrim( trim( (string) ( $row['url'] ?? '' ) ), '/' );
			if ( 0 === strpos( $path, 'wp-admin/' ) ) {
				$path = substr( $path, 9 );
			}
			if ( '' === $label || ! preg_match( '#^[a-z0-9_\-]+\.php(\?[A-Za-z0-9_.\-=&%\[\]]*)?$#', $path ) ) {
				continue;
			}
			$out[] = array(
				'url'   => admin_url( $path ),
				'label' => $label,
				'note'  => sanitize_text_field( (string) ( $row['note'] ?? '' ) ),
			);
			if ( count( $out ) >= self::MAX_ROWS ) {
				break;
			}
		}
		return $out;
	}

	/* -------------------------------------------------------- who it's for */

	private static function is_operator( ?WP_User $user = null ): bool {
		return class_exists( 'Scout_Hub_Access' ) && Scout_Hub_Access::is_operator( $user );
	}

	/** This person ticked "Show everything" on their profile. */
	public static function shows_everything( int $user_id = 0 ): bool {
		$user_id = $user_id ? $user_id : get_current_user_id();
		return $user_id > 0 && ! empty( get_user_meta( $user_id, self::SHOW_ALL, true ) );
	}

	/** The simple view applies to the person looking: it is on, they are not an operator, and they did not ask to see everything. */
	public static function simple_applies(): bool {
		return self::settings()['simple'] && ! self::is_operator() && ! self::shows_everything();
	}

	/* -------------------------------------------------------------- hiding */

	/**
	 * The admin kit's hidden groups, plus the ones the simple view hides.
	 *
	 * @param mixed $hidden Group keys already hidden.
	 */
	public static function hidden_groups( $hidden ): array {
		$hidden = (array) $hidden;
		if ( ! self::simple_applies() ) {
			return $hidden;
		}
		return array_values( array_unique( array_merge( $hidden, self::settings()['hide_groups'] ) ) );
	}

	/** The screen being viewed ("scout-redirects", or "edit-comments.php"). */
	private static function current_screen_slug(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reading the screen address only.
		if ( isset( $_GET['page'] ) ) {
			return sanitize_text_field( wp_unslash( $_GET['page'] ) );
		}
		global $pagenow;
		$query = isset( $_GET['post_type'] ) ? '?post_type=' . sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		// phpcs:enable
		return (string) $pagenow . $query;
	}

	/**
	 * Does a menu slug match an entry in a hide list? "scout-contact" matches
	 * the screen and its tabs ("scout-contact&tab=x"); a slug with "&" or "?"
	 * matches only itself.
	 *
	 * @param string   $slug The menu item's slug.
	 * @param string[] $list Entries to hide.
	 */
	public static function matches( string $slug, array $list ): bool {
		foreach ( $list as $entry ) {
			if ( $slug === $entry ) {
				return true;
			}
			if ( false === strpbrk( $entry, '&?' ) && strtok( $slug, '&' ) === $entry ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Take the listed screens out of every sidebar section. The screen being
	 * viewed stays, so its own address keeps working (WordPress finds a screen
	 * through the menu that lists it). The Home screen never hides.
	 */
	public static function hide_screens(): void {
		if ( ! self::simple_applies() ) {
			return;
		}
		global $submenu;
		$list = self::settings()['hide_screens'];
		if ( ! $list || ! is_array( $submenu ) ) {
			return;
		}
		$here = self::current_screen_slug();
		foreach ( $submenu as $parent => $items ) {
			foreach ( (array) $items as $i => $item ) {
				$slug = (string) ( $item[2] ?? '' );
				if ( '' === $slug || in_array( $slug, array( 'site-build', 'site-build-features' ), true ) || ! self::matches( $slug, $list ) ) {
					continue;
				}
				if ( strtok( $slug, '&' ) === strtok( $here, '&' ) ) {
					continue;
				}
				unset( $submenu[ $parent ][ $i ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- hide only.
			}
		}
	}

	/** Hide the listed WordPress menus ("tools.php", or "parent.php>child.php" for one submenu item). */
	public static function hide_menus(): void {
		if ( ! self::simple_applies() ) {
			return;
		}
		$here = self::current_screen_slug();
		foreach ( self::settings()['hide_menus'] as $slug ) {
			if ( false !== strpos( $slug, '>' ) ) {
				list( $parent, $child ) = array_map( 'trim', explode( '>', $slug, 2 ) );
				if ( $child !== $here ) {
					remove_submenu_page( $parent, $child );
				}
			} else {
				// A top-level menu's own screens stay reachable: WordPress still finds them through its submenu.
				remove_menu_page( $slug );
			}
		}
	}

	/**
	 * Today's technical items go to operators only while the simple view is on:
	 * failed health checks ("Fix: ..."), a site hidden from Google, and any item
	 * a plugin marks 'technical' => true.
	 *
	 * @param mixed $items Today items.
	 */
	public static function today( $items ): array {
		$items = (array) $items;
		if ( ! self::simple_applies() ) {
			return $items;
		}
		return array_values( array_filter( $items, array( __CLASS__, 'not_technical' ) ) );
	}

	/** @param mixed $item One Today item. */
	public static function not_technical( $item ): bool {
		if ( ! is_array( $item ) ) {
			return true;
		}
		$id = (string) ( $item['id'] ?? '' );
		return empty( $item['technical'] ) && 'hidden-from-google' !== $id && 0 !== strpos( $id, 'health-' );
	}

	/* ------------------------------------------------------------- profile */

	/** Is anything hidden from this person, by this view or the white-label menu list? */
	private static function anything_hidden(): bool {
		if ( self::settings()['simple'] ) {
			return true;
		}
		return class_exists( 'Scout_Hub_Brand' ) && Scout_Hub_Brand::on( 'menu' ) && ! empty( Scout_Hub_Brand::settings()['hide_menus'] );
	}

	/** Users, Profile: "Show everything", for your own account, on sites that hide something. */
	public static function profile_field( $user ): void {
		if ( ! $user instanceof WP_User || get_current_user_id() !== (int) $user->ID || self::is_operator( $user ) || ! self::anything_hidden() ) {
			return;
		}
		$on = self::shows_everything( (int) $user->ID );
		echo '<table class="form-table" role="presentation" id="scout-show-everything"><tr><th scope="row">' . esc_html__( 'Your menu', 'scout-build' ) . '</th><td>';
		echo '<label><input type="checkbox" name="scout_show_everything" value="1" ' . checked( $on, true, false ) . '> ' . esc_html__( 'Show everything in the menu', 'scout-build' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Your menu shows the screens you use most. Tick this to see every screen. Nothing is locked either way.', 'scout-build' ) . '</p>';
		echo '</td></tr></table>';
	}

	/** Save the switch with the profile (WordPress checks the profile form's nonce first). */
	public static function save_profile( $user_id ): void {
		$user_id = (int) $user_id;
		if ( ! current_user_can( 'edit_user', $user_id ) || get_current_user_id() !== $user_id ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by WordPress before personal_options_update runs.
		if ( ! empty( $_POST['scout_show_everything'] ) ) {
			update_user_meta( $user_id, self::SHOW_ALL, 1 );
		} else {
			delete_user_meta( $user_id, self::SHOW_ALL );
		}
	}

	/* ------------------------------------------------------------- landing */

	/** Does this person open Scout Home first? On by the setup file, for people who are not operators. */
	public static function home_first( int $user_id = 0 ): bool {
		if ( ! self::settings()['land_on_home'] ) {
			return false;
		}
		$user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
		return $user instanceof WP_User && $user->exists() && user_can( $user, 'edit_posts' ) && ! self::is_operator( $user );
	}

	/** Scout Home's address. */
	public static function home_url(): string {
		$slug = class_exists( 'Scout_Hub_Admin' ) ? Scout_Hub_Admin::page_slug() : 'site-build-features';
		return admin_url( 'admin.php?page=' . $slug );
	}

	/**
	 * After login, send people who land on the WordPress Dashboard to Scout
	 * Home instead. A login that asked for a particular screen still goes there.
	 *
	 * @param string           $to        Where WordPress will send them.
	 * @param string           $requested The address the login asked for, if any.
	 * @param WP_User|WP_Error $user      Who logged in.
	 */
	public static function login_redirect( $to, $requested = '', $user = null ) {
		if ( ! $user instanceof WP_User || ! self::home_first( (int) $user->ID ) ) {
			return $to;
		}
		$dash = untrailingslashit( admin_url() );
		foreach ( array( (string) $requested, (string) $to ) as $url ) {
			$url = untrailingslashit( preg_replace( '#/index\.php$#', '', strtok( $url, '#' ) ) );
			if ( '' !== $url && $url !== $dash ) {
				return $to; // They asked for somewhere else.
			}
		}
		return self::home_url();
	}

	/* ---------------------------------------------------------------- Home */

	/**
	 * Cards plugins add to Home, sorted, for the person looking. Each card: id,
	 * render (a callable that prints the card), priority (lower first), cap.
	 *
	 * @return array<int,array{id:string,render:callable,priority:int}>
	 */
	public static function cards(): array {
		/**
		 * Filters the cards on Scout Home. Add one array per card: id, render
		 * (prints a .sh-box section), priority (lower first, default 50) and cap
		 * (who sees it, default edit_posts).
		 *
		 * @param array $cards Cards so far.
		 */
		$cards = (array) apply_filters( 'scout_hub_home_cards', array() );
		$out   = array();
		foreach ( $cards as $card ) {
			if ( ! is_array( $card ) || empty( $card['id'] ) || empty( $card['render'] ) || ! is_callable( $card['render'] ) ) {
				continue;
			}
			if ( ! current_user_can( (string) ( $card['cap'] ?? 'edit_posts' ) ) ) {
				continue;
			}
			$out[] = array(
				'id'       => sanitize_key( (string) $card['id'] ),
				'render'   => $card['render'],
				'priority' => (int) ( $card['priority'] ?? 50 ),
			);
		}
		usort( $out, static fn( $a, $b ) => $a['priority'] <=> $b['priority'] );
		return $out;
	}

	/** Print the plugins' Home cards. */
	public static function render_cards(): void {
		foreach ( self::cards() as $card ) {
			call_user_func( $card['render'] );
		}
	}

	/**
	 * "Things you can do here": the hub's own lines, the setup file's
	 * shortcuts, then the scout_hub_home_shortcuts filter.
	 *
	 * @param array $rows Rows of url, label, note.
	 * @return array<int,array{url:string,label:string,note:string}>
	 */
	public static function shortcuts( array $rows ): array {
		$rows = array_merge( $rows, self::settings()['shortcuts'] );
		/**
		 * Filters "Things you can do here" on Scout Home.
		 *
		 * @param array $rows Rows of url, label and note.
		 */
		$rows = (array) apply_filters( 'scout_hub_home_shortcuts', $rows );
		$out  = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['url'] ) || empty( $row['label'] ) ) {
				continue;
			}
			$out[] = array(
				'url'   => (string) $row['url'],
				'label' => (string) $row['label'],
				'note'  => (string) ( $row['note'] ?? '' ),
			);
		}
		return $out;
	}

	/** The "Your other tools" card, when the setup file lists outside links. */
	public static function render_links(): void {
		$links = self::settings()['links'];
		if ( ! $links ) {
			return;
		}
		?>
		<section class="sh-box sh-ov-do sh-ov-links">
			<h2 class="sh-title"><?php esc_html_e( 'Your other tools', 'scout-build' ); ?></h2>
			<ul class="sh-rows">
				<?php foreach ( $links as $link ) : ?>
					<li class="sh-row">
						<div>
							<p class="sh-row-name"><a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $link['label'] ); ?> &rsaquo;<span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'scout-build' ); ?></span></a></p>
							<?php if ( '' !== $link['note'] ) : ?>
								<p class="sh-row-sub"><?php echo esc_html( $link['note'] ); ?></p>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
	}
}
