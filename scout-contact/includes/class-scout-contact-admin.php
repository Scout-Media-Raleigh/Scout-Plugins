<?php
/**
 * Build -> Call buttons: the numbers, the booking link and the wording.
 *
 * @package Scout_Contact
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scout_Contact_Admin {

	/**
	 * One status line at the top of the screen (admin kit 1.10.0). An older kit
	 * copy may be the one loaded mid-update, so fall back to the same markup.
	 *
	 * @param string $go Button markup, escaped by the caller.
	 */
	private static function ui_status( string $state, string $headline, string $detail = '', string $go = '' ): void {
		if ( method_exists( 'Scout_Admin_UI', 'status' ) ) {
			Scout_Admin_UI::status( $state, $headline, $detail, $go );
			return;
		}
		$state = in_array( $state, array( 'good', 'warn', 'bad', 'off' ), true ) ? $state : 'good';
		echo '<section class="sh-box"><p class="sh-status is-' . esc_attr( $state ) . '"><span class="sh-dot" aria-hidden="true"></span><strong>' . esc_html( $headline ) . '</strong>';
		if ( '' !== $detail ) {
			echo ' <span>' . esc_html( $detail ) . '</span>';
		}
		if ( '' !== $go ) {
			echo ' <span class="sh-status-go">' . $go . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by the caller.
		}
		echo '</p></section>';
	}

	/** A closed "More options" box (admin kit 1.10.0), with the same fallback. */
	private static function ui_fold_open( string $title ): void {
		if ( method_exists( 'Scout_Admin_UI', 'fold_open' ) ) {
			Scout_Admin_UI::fold_open( $title );
			return;
		}
		echo '<details class="sh-box sh-fold"><summary class="sh-title">' . esc_html( $title ) . '</summary>';
	}

	private static function ui_fold_close(): void {
		if ( method_exists( 'Scout_Admin_UI', 'fold_close' ) ) {
			Scout_Admin_UI::fold_close();
			return;
		}
		echo '</details>';
	}

	const SLUG = 'scout-contact';

	public static function boot(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SCOUT_CONTACT_FILE ), array( __CLASS__, 'action_link' ) );
	}

	/** Nest under the site's Build section; fall back to Settings. */
	public static function parent(): string {
		global $admin_page_hooks;
		foreach ( array( 'scout-build', 'recon-build', 'site-build', 'scout' ) as $slug ) {
			if ( isset( $admin_page_hooks[ $slug ] ) ) {
				return $slug;
			}
		}
		return 'options-general.php';
	}

	public static function url(): string {
		return 'options-general.php' === self::parent() ? admin_url( 'options-general.php?page=' . self::SLUG ) : admin_url( 'admin.php?page=' . self::SLUG );
	}

	public static function menu(): void {
		add_submenu_page( self::parent(), __( 'Call buttons', 'scout-contact' ), __( 'Call buttons', 'scout-contact' ), 'manage_options', self::SLUG, array( __CLASS__, 'render' ) );
	}

	public static function assets( string $hook ): void {
		if ( false === strpos( $hook, self::SLUG ) ) {
			return;
		}
		Scout_Admin_UI::enqueue( SCOUT_CONTACT_URL . 'assets/scout-admin.css' );
		wp_enqueue_media();
		wp_enqueue_script( 'scout-contact-admin', SCOUT_CONTACT_URL . 'assets/admin.js', array(), SCOUT_CONTACT_VERSION, true );
	}

	public static function action_link( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'scout-contact' ) . '</a>' );
		return $links;
	}

	public static function register(): void {
		register_setting( 'scout_contact_group', Scout_Contact::OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function sanitize( $in ): array {
		$in     = (array) $in;
		$photos = array_slice( array_values( array_filter( array_map( 'absint', explode( ',', (string) ( $in['photos'] ?? '' ) ) ) ) ), 0, 3 );
		$out    = array(
			'enabled'   => ! empty( $in['enabled'] ) ? 1 : 0,
			'phone'     => sanitize_text_field( (string) ( $in['phone'] ?? '' ) ),
			'text'      => sanitize_text_field( (string) ( $in['text'] ?? '' ) ),
			'text_off'  => ! empty( $in['text_off'] ) ? 1 : 0,
			'book_url'  => esc_url_raw( trim( (string) ( $in['book_url'] ?? '' ) ) ),
			'label'     => sanitize_text_field( (string) ( $in['label'] ?? '' ) ),
			'title'     => sanitize_text_field( (string) ( $in['title'] ?? '' ) ),
			'subtitle'  => sanitize_text_field( (string) ( $in['subtitle'] ?? '' ) ),
			'text_note' => sanitize_text_field( (string) ( $in['text_note'] ?? '' ) ),
			'book_note' => sanitize_text_field( (string) ( $in['book_note'] ?? '' ) ),
			'photos'    => $photos,
			'hide_on'   => sanitize_text_field( (string) ( $in['hide_on'] ?? '' ) ),
			'sets'      => self::sanitize_sets( $in['sets'] ?? array() ),
		);
		$d = Scout_Contact::defaults();
		foreach ( array( 'label', 'title' ) as $k ) {
			if ( '' === $out[ $k ] ) {
				$out[ $k ] = $d[ $k ];
			}
		}
		if ( '' !== $out['phone'] && '' === Scout_Contact::dial( $out['phone'] ) ) {
			add_settings_error( Scout_Contact::OPTION, 'phone', __( 'That phone number has no digits in it.', 'scout-contact' ) );
		}
		return $out;
	}

	/** The button rows a set shows on the screen, in this order. */
	const SLOTS = array( 'call', 'text', 'book', 'email', 'directions', 'link1', 'link2' );

	/** Most sets one site can keep. */
	const MAX_SETS = 20;

	/**
	 * Button sets from the form (or already in their stored shape).
	 *
	 * @param mixed $raw Posted sets.
	 */
	public static function sanitize_sets( $raw ): array {
		$sets = array();
		foreach ( (array) $raw as $one ) {
			if ( ! is_array( $one ) || ! empty( $one['remove'] ) ) {
				continue;
			}
			$one['buttons'] = self::form_buttons( $one['buttons'] ?? array() );
			$set            = Scout_Contact::clean_set( $one );
			if ( null === $set ) {
				continue;
			}
			if ( '' === $set['name'] ) {
				/* translators: %d: the set's number on the screen */
				$set['name'] = sprintf( __( 'Button set %d', 'scout-contact' ), count( $sets ) + 1 );
			}
			$sets[] = $set;
		}
		return array_slice( $sets, 0, self::MAX_SETS );
	}

	/**
	 * The screen posts one row per kind of button (ticked, order, wording,
	 * link). Turn the ticked rows into the stored list, sorted by order.
	 *
	 * @param mixed $raw Posted button rows.
	 */
	private static function form_buttons( $raw ): array {
		$raw = (array) $raw;
		if ( isset( $raw[0] ) ) {
			return $raw; // Already a stored list.
		}
		$rows = array();
		foreach ( self::SLOTS as $i => $slot ) {
			$row = (array) ( $raw[ $slot ] ?? array() );
			if ( empty( $row['on'] ) ) {
				continue;
			}
			$type  = 0 === strpos( $slot, 'link' ) ? 'link' : $slot;
			$label = trim( (string) ( $row['label'] ?? '' ) );
			$url   = trim( (string) ( $row['url'] ?? '' ) );
			if ( 'link' === $type && ( '' === $label || '' === $url ) ) {
				add_settings_error( Scout_Contact::OPTION, 'set-link', __( 'A link button needs its own wording and link, so one was left out.', 'scout-contact' ) );
				continue;
			}
			$order  = absint( $row['order'] ?? 0 );
			$rows[] = array( $order ? $order : 99, $i, array( 'type' => $type, 'label' => $label, 'url' => $url ) );
		}
		usort( $rows, static fn( $a, $b ) => array( $a[0], $a[1] ) <=> array( $b[0], $b[1] ) );
		return array_column( $rows, 2 );
	}

	/** The pages to choose from, parents first with their child pages under them. */
	private static function page_choices(): array {
		$out = array();
		foreach ( (array) get_pages( array( 'number' => 300, 'post_status' => 'publish,private,draft' ) ) as $page ) {
			$out[] = array( (int) $page->ID, count( get_post_ancestors( $page ) ), get_the_title( $page ) );
		}
		return $out;
	}

	/** The Button sets box: every saved set, then one blank set to add a new one. */
	private static function render_sets( array $s, string $o ): void {
		$sets   = array_values( array_filter( (array) ( $s['sets'] ?? array() ), 'is_array' ) );
		$pages  = self::page_choices();
		$titles = array(
			'call'       => __( 'Call', 'scout-contact' ),
			'text'       => __( 'Text', 'scout-contact' ),
			'book'       => __( 'Book', 'scout-contact' ),
			'email'      => __( 'Email', 'scout-contact' ),
			'directions' => __( 'Directions', 'scout-contact' ),
			'link1'      => __( 'Link button', 'scout-contact' ),
			'link2'      => __( 'Second link button', 'scout-contact' ),
		);
		$hints  = array(
			'call'       => __( 'Blank uses the phone number above.', 'scout-contact' ),
			'text'       => __( 'Blank uses the texting number above.', 'scout-contact' ),
			'book'       => __( 'Blank uses the booking link above.', 'scout-contact' ),
			'email'      => __( 'Blank uses the email in Business info.', 'scout-contact' ),
			'directions' => __( 'Blank opens a map of the address in Business info.', 'scout-contact' ),
			'link1'      => __( 'Needs its own wording and link.', 'scout-contact' ),
			'link2'      => __( 'Needs its own wording and link.', 'scout-contact' ),
		);
		/* translators: %d: number of button sets */
		$title = $sets ? sprintf( _n( 'Button sets (%d)', 'Button sets (%d)', count( $sets ), 'scout-contact' ), count( $sets ) ) : __( 'Button sets', 'scout-contact' );
		self::ui_fold_open( $title );
		?>
		<p class="description"><?php esc_html_e( 'Different buttons on some pages. A page uses the first set that matches; every other page keeps the buttons above.', 'scout-contact' ); ?></p>
		<?php
		$count = count( $sets );
		for ( $i = 0; $i <= $count && $i < self::MAX_SETS; $i++ ) :
			$set   = $sets[ $i ] ?? array( 'name' => '', 'pages' => array(), 'children' => 1, 'paths' => array(), 'no_text' => 0, 'label' => '', 'buttons' => array() );
			$is_new = $i === $count;
			$n     = $o . '[sets][' . $i . ']';
			$id    = 'scc-set-' . $i;
			// Saved buttons back into one row per kind: the first link fills Link button, the second the next row.
			$rows  = array();
			$links = 0;
			foreach ( (array) $set['buttons'] as $pos => $b ) {
				$slot = (string) ( $b['type'] ?? '' );
				if ( 'link' === $slot ) {
					$links++;
					$slot = 'link' . $links;
				}
				if ( in_array( $slot, self::SLOTS, true ) && ! isset( $rows[ $slot ] ) ) {
					$rows[ $slot ] = array( 'order' => $pos + 1, 'label' => (string) ( $b['label'] ?? '' ), 'url' => (string) ( $b['url'] ?? '' ) );
				}
			}
			?>
		<fieldset class="sh-box" style="margin-top:16px">
			<legend class="sh-title"><?php echo esc_html( $is_new ? __( 'Add a set', 'scout-contact' ) : (string) $set['name'] ); ?></legend>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="<?php echo esc_attr( $id ); ?>-name"><?php esc_html_e( 'Name', 'scout-contact' ); ?></label></th>
					<td><input id="<?php echo esc_attr( $id ); ?>-name" type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[name]" value="<?php echo esc_attr( (string) $set['name'] ); ?>" placeholder="<?php esc_attr_e( 'Bodywork pages', 'scout-contact' ); ?>">
						<?php if ( $is_new ) : ?><p class="description"><?php esc_html_e( 'Fill this in and save to add a set. Save again for another.', 'scout-contact' ); ?></p><?php endif; ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Pages', 'scout-contact' ); ?></th>
					<td>
						<?php if ( $pages ) : ?>
						<div style="max-height:180px;overflow:auto;border:1px solid var(--sh-border-strong,#D2D2D7);border-radius:8px;padding:8px 12px">
							<?php foreach ( $pages as $page ) : ?>
							<label style="display:block;margin:2px 0 2px <?php echo (int) min( $page[1], 4 ) * 18; ?>px"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[pages][]" value="<?php echo (int) $page[0]; ?>" <?php checked( in_array( $page[0], array_map( 'intval', (array) $set['pages'] ), true ) ); ?>> <?php echo esc_html( '' !== $page[2] ? $page[2] : '#' . $page[0] ); ?></label>
							<?php endforeach; ?>
						</div>
						<?php else : ?>
						<p class="description"><?php esc_html_e( 'This site has no pages yet. Use addresses below.', 'scout-contact' ); ?></p>
						<?php endif; ?>
						<p><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[children]" value="1" <?php checked( ! empty( $set['children'] ) ); ?>> <?php esc_html_e( 'Their child pages too', 'scout-contact' ); ?></label></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="<?php echo esc_attr( $id ); ?>-paths"><?php esc_html_e( 'Addresses that start with', 'scout-contact' ); ?></label></th>
					<td><input id="<?php echo esc_attr( $id ); ?>-paths" type="text" class="regular-text code" name="<?php echo esc_attr( $n ); ?>[paths]" value="<?php echo esc_attr( implode( ', ', (array) $set['paths'] ) ); ?>" placeholder="/bodywork/">
						<p class="description"><?php esc_html_e( 'Optional. Separate more than one with commas.', 'scout-contact' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Buttons', 'scout-contact' ); ?></th>
					<td>
						<table class="widefat striped" role="presentation" style="max-width:760px">
							<thead><tr><th scope="col"><?php esc_html_e( 'Show', 'scout-contact' ); ?></th><th scope="col"><?php esc_html_e( 'Order', 'scout-contact' ); ?></th><th scope="col"><?php esc_html_e( 'Wording in the sheet', 'scout-contact' ); ?></th><th scope="col"><?php esc_html_e( 'Link or number', 'scout-contact' ); ?></th></tr></thead>
							<tbody>
							<?php foreach ( self::SLOTS as $k => $slot ) : $row = $rows[ $slot ] ?? null; $rn = $n . '[buttons][' . $slot . ']'; ?>
							<tr>
								<td><label><input type="checkbox" name="<?php echo esc_attr( $rn ); ?>[on]" value="1" <?php checked( null !== $row ); ?>> <?php echo esc_html( $titles[ $slot ] ); ?></label></td>
								<td><input type="number" min="1" max="99" class="small-text" name="<?php echo esc_attr( $rn ); ?>[order]" value="<?php echo esc_attr( (string) ( $row['order'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( (string) ( $k + 1 ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: button name */ __( 'Order for %s', 'scout-contact' ), $titles[ $slot ] ) ); ?>"></td>
								<td><input type="text" class="regular-text" name="<?php echo esc_attr( $rn ); ?>[label]" value="<?php echo esc_attr( (string) ( $row['label'] ?? '' ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: button name */ __( 'Wording for %s', 'scout-contact' ), $titles[ $slot ] ) ); ?>"<?php echo 0 === strpos( $slot, 'link' ) ? ' placeholder="' . esc_attr__( 'Request a date', 'scout-contact' ) . '"' : ''; ?>></td>
								<td><input type="text" class="regular-text code" name="<?php echo esc_attr( $rn ); ?>[url]" value="<?php echo esc_attr( (string) ( $row['url'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $hints[ $slot ] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: button name */ __( 'Link for %s', 'scout-contact' ), $titles[ $slot ] ) ); ?>"></td>
							</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
						<p class="description"><?php esc_html_e( 'Tick none to keep the usual Call, Text and Book. Call and Text also show on the bar itself. With one button, the main button goes straight to it.', 'scout-contact' ); ?></p>
						<p><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[no_text]" value="1" <?php checked( ! empty( $set['no_text'] ) ); ?>> <?php esc_html_e( 'No Text button on these pages', 'scout-contact' ); ?></label></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="<?php echo esc_attr( $id ); ?>-label"><?php esc_html_e( 'Main button', 'scout-contact' ); ?></label></th>
					<td><input id="<?php echo esc_attr( $id ); ?>-label" type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[label]" value="<?php echo esc_attr( (string) $set['label'] ); ?>" placeholder="<?php echo esc_attr( (string) $s['label'] ); ?>">
						<p class="description"><?php esc_html_e( 'Blank keeps the usual wording, or the one button’s own wording.', 'scout-contact' ); ?></p></td>
				</tr>
				<?php if ( ! $is_new ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Remove', 'scout-contact' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[remove]" value="1"> <?php esc_html_e( 'Remove this set when I save', 'scout-contact' ); ?></label></td>
				</tr>
				<?php endif; ?>
			</table>
		</fieldset>
			<?php
		endfor;
		self::ui_fold_close();
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s   = Scout_Contact::saved();
		$o   = Scout_Contact::OPTION;
		$biz = Scout_Contact::business_phone();
		$a = Scout_Contact::actions();
		$n = count( array_filter( $a ) );
		Scout_Admin_UI::open(
			__( 'Call buttons', 'scout-contact' ),
			__( 'A bar at the bottom of the screen on phones, so visitors can call, text or book with one tap.', 'scout-contact' ),
			__( 'Call buttons', 'scout-contact' )
		);
		settings_errors( $o );
		?>
		<?php
		$on = $s['enabled'] && $n;
		if ( $on ) {
			$headline = __( 'On.', 'scout-contact' );
			/* translators: %d: number of options in the call sheet */
			$detail = sprintf( _n( 'The call bar shows on phones, with %d option in the sheet.', 'The call bar shows on phones, with %d options in the sheet.', $n, 'scout-contact' ), $n );
		} else {
			$headline = __( 'Off.', 'scout-contact' );
			$detail   = $n ? __( 'Tick Show the call bar on phones below.', 'scout-contact' ) : __( 'Add a phone number in Business info or below, or a booking link below.', 'scout-contact' );
		}
		// Headline plus one sentence (DESIGN 2a #2). The rest sits in More under the buttons.
		self::ui_status( $on ? 'good' : 'off', $headline, $detail );
		?>

		<form method="post" action="options.php">
			<?php settings_fields( 'scout_contact_group' ); ?>
			<section class="sh-box">
				<h2 class="sh-title"><?php esc_html_e( 'Buttons', 'scout-contact' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Show the bar', 'scout-contact' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $o ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'Show the call bar on phones', 'scout-contact' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="scc-phone"><?php esc_html_e( 'Phone number', 'scout-contact' ); ?></label></th>
						<td><input id="scc-phone" type="text" class="regular-text" name="<?php echo esc_attr( $o ); ?>[phone]" value="<?php echo esc_attr( $s['phone'] ); ?>" placeholder="<?php echo esc_attr( '' !== $biz ? $biz : '(919) 555-0100' ); ?>">
							<p class="description"><?php esc_html_e( 'Blank uses Business info. A number typed here shows as typed.', 'scout-contact' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="scc-text"><?php esc_html_e( 'Texting number', 'scout-contact' ); ?></label></th>
						<td><input id="scc-text" type="text" class="regular-text" name="<?php echo esc_attr( $o ); ?>[text]" value="<?php echo esc_attr( $s['text'] ); ?>" placeholder="<?php esc_attr_e( 'Same as the phone number', 'scout-contact' ); ?>">
							<p><label><input type="checkbox" name="<?php echo esc_attr( $o ); ?>[text_off]" value="1" <?php checked( $s['text_off'] ); ?>> <?php esc_html_e( 'This number cannot receive texts, so leave Text off', 'scout-contact' ); ?></label></p>
							<p class="description"><?php esc_html_e( 'Landlines usually can’t get texts, so turn Text off unless someone reads them.', 'scout-contact' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="scc-book"><?php esc_html_e( 'Booking link', 'scout-contact' ); ?></label></th>
						<td><input id="scc-book" type="url" class="regular-text code" name="<?php echo esc_attr( $o ); ?>[book_url]" value="<?php echo esc_attr( $s['book_url'] ); ?>" placeholder="https://">
							<p class="description"><?php esc_html_e( 'The page or calendar link where people pick a time. Leave blank to hide "Book a time".', 'scout-contact' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="scc-hide"><?php esc_html_e( 'Hide on', 'scout-contact' ); ?></label></th>
						<td><input id="scc-hide" type="text" class="regular-text code" name="<?php echo esc_attr( $o ); ?>[hide_on]" value="<?php echo esc_attr( $s['hide_on'] ); ?>">
							<p class="description"><?php esc_html_e( 'Pages that already have their own buttons, by the first part of their address, separated by commas.', 'scout-contact' ); ?></p></td>
					</tr>
				</table>
				<details class="sh-fold"><summary><?php esc_html_e( 'More', 'scout-contact' ); ?></summary>
					<p class="description"><?php esc_html_e( 'The bar stays hidden on computers, where the header already shows the phone number and booking button.', 'scout-contact' ); ?></p>
					<p class="description"><?php echo esc_html( class_exists( 'Scout_Analytics' ) ? __( 'Taps are counted in Analytics as contact_call, contact_text, contact_book or contact_open.', 'scout-contact' ) : __( 'Taps are not counted until Scout Analytics is on.', 'scout-contact' ) ); ?></p>
				</details>
			</section>

			<?php self::ui_fold_open( __( 'More options: wording & photos', 'scout-contact' ) ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="scc-label"><?php esc_html_e( 'Main button', 'scout-contact' ); ?></label></th>
					<td><input id="scc-label" type="text" class="regular-text" name="<?php echo esc_attr( $o ); ?>[label]" value="<?php echo esc_attr( $s['label'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="scc-title"><?php esc_html_e( 'Sheet title', 'scout-contact' ); ?></label></th>
					<td><input id="scc-title" type="text" class="regular-text" name="<?php echo esc_attr( $o ); ?>[title]" value="<?php echo esc_attr( $s['title'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="scc-sub"><?php esc_html_e( 'Line under the title', 'scout-contact' ); ?></label></th>
					<td><input id="scc-sub" type="text" class="large-text" name="<?php echo esc_attr( $o ); ?>[subtitle]" value="<?php echo esc_attr( $s['subtitle'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="scc-tnote"><?php esc_html_e( 'Under "Send a text"', 'scout-contact' ); ?></label></th>
					<td><input id="scc-tnote" type="text" class="large-text" name="<?php echo esc_attr( $o ); ?>[text_note]" value="<?php echo esc_attr( $s['text_note'] ); ?>" placeholder="<?php esc_attr_e( 'Opens a message on your phone.', 'scout-contact' ); ?>">
						<p class="description"><?php esc_html_e( 'Only promise a reply time you will keep.', 'scout-contact' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="scc-bnote"><?php esc_html_e( 'Under "Book a time"', 'scout-contact' ); ?></label></th>
					<td><input id="scc-bnote" type="text" class="large-text" name="<?php echo esc_attr( $o ); ?>[book_note]" value="<?php echo esc_attr( $s['book_note'] ); ?>" placeholder="<?php esc_attr_e( 'Pick a time that works for you.', 'scout-contact' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Photos in the sheet', 'scout-contact' ); ?></th>
					<td>
						<input type="hidden" id="scc-photos" name="<?php echo esc_attr( $o ); ?>[photos]" value="<?php echo esc_attr( implode( ',', (array) $s['photos'] ) ); ?>">
						<div id="scc-photo-list" class="scc-photo-list" style="display:flex;gap:8px;margin-bottom:8px">
							<?php foreach ( (array) $s['photos'] as $id ) { echo wp_get_attachment_image( (int) $id, array( 48, 48 ), false, array( 'style' => 'width:48px;height:48px;border-radius:50%;object-fit:cover' ) ); } ?>
						</div>
						<button type="button" class="button" id="scc-pick"><?php esc_html_e( 'Choose photos', 'scout-contact' ); ?></button>
						<button type="button" class="button-link" id="scc-clear"><?php esc_html_e( 'Remove photos', 'scout-contact' ); ?></button>
						<p class="description"><?php esc_html_e( 'Up to three head shots of the people who answer. Optional.', 'scout-contact' ); ?></p>
					</td>
				</tr>
			</table>
			<?php self::ui_fold_close(); ?>
			<?php self::render_sets( $s, $o ); ?>
			<?php submit_button( __( 'Save changes', 'scout-contact' ) ); ?>
		</form>
		<?php
		Scout_Admin_UI::close();
	}
}
