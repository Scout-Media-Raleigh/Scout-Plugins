<?php
/**
 * The Scout box on the WordPress Dashboard: what the site has on, what needs a
 * look, and one click into the Scout screens. First box on the page, so the
 * person who logs in sees it before anything else.
 *
 * @package Scout_Build
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scout_Hub_Dashboard {

	const ID = 'scout_hub_dashboard';

	public static function boot(): void {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/** The kit stylesheet on the WordPress Dashboard, so the box uses the kit status line and tokens. */
	public static function assets( string $hook ): void {
		if ( 'index.php' === $hook && current_user_can( 'edit_posts' ) ) {
			Scout_Admin_UI::enqueue( SCOUT_HUB_URL . 'assets/scout-admin.css' );
		}
	}

	public static function register(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$title = Scout_Hub_Admin::branded() ? __( 'Scout', 'scout-build' ) : __( 'Site Build', 'scout-build' );
		wp_add_dashboard_widget( self::ID, $title, array( __CLASS__, 'render' ) );

		// First in the left column.
		global $wp_meta_boxes;
		$core = $wp_meta_boxes['dashboard']['normal']['core'] ?? array();
		if ( isset( $core[ self::ID ] ) ) {
			$wp_meta_boxes['dashboard']['normal']['core'] = array( self::ID => $core[ self::ID ] ) + $core; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reordering only.
		}
	}

	/** Rating, count and new reviews, when Scout Reviews is on. */
	private static function reviews(): void {
		if ( ! class_exists( 'Scout_Reviews_Query' ) || ! class_exists( 'Scout_Reviews_Post_Type' ) ) {
			return;
		}
		$totals = Scout_Reviews_Query::totals();
		$top    = $totals[0] ?? null;
		$new    = class_exists( 'Scout_Reviews_Google_Sync' ) ? count(
			get_posts(
				array(
					'post_type'      => Scout_Reviews_Post_Type::TYPE,
					'post_status'    => 'any',
					'fields'         => 'ids',
					'posts_per_page' => 50,
					'meta_key'       => Scout_Reviews_Google_Sync::META_NEW, // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			)
		) : 0;
		$list   = admin_url( 'edit.php?post_type=' . Scout_Reviews_Post_Type::TYPE );
		?>
		<p class="sd-reviews">
			<span class="sd-stars" aria-hidden="true">&#9733;</span>
			<?php
			if ( $top ) {
				$name = class_exists( 'Scout_Reviews_Sources' ) ? ( Scout_Reviews_Sources::all()[ $top['source'] ]['label'] ?? ucfirst( $top['source'] ) ) : ucfirst( $top['source'] );
				/* translators: 1: rating, 2: review count, 3: platform */
				echo esc_html( sprintf( _n( '%1$s from %2$d review on %3$s', '%1$s from %2$d reviews on %3$s', (int) $top['count'], 'scout-build' ), number_format( (float) $top['rating'], 1 ), (int) $top['count'], $name ) );
			} else {
				esc_html_e( 'No review totals yet. Add them in Reviews, Settings.', 'scout-build' );
			}
			if ( $new ) {
				/* translators: %d: new reviews */
				echo ' &middot; <strong>' . esc_html( sprintf( _n( '%d new review', '%d new reviews', $new, 'scout-build' ), $new ) ) . '</strong>';
			}
			?>
			&middot; <a href="<?php echo esc_url( $list ); ?>"><?php esc_html_e( 'See reviews', 'scout-build' ); ?></a>
		</p>
		<?php
	}

	/** Spam blocked this month, when Scout Forms Guard 0.2.0+ is on. */
	private static function spam(): void {
		if ( ! class_exists( 'Scout_Forms_Guard' ) || ! method_exists( 'Scout_Forms_Guard', 'blocked' ) ) {
			return;
		}
		$month = (int) Scout_Forms_Guard::blocked();
		$all   = (int) Scout_Forms_Guard::blocked( 'all' );
		?>
		<p class="sd-spam">
			<span class="sd-shield" aria-hidden="true"></span>
			<?php
			if ( $month ) {
				/* translators: %d: spam submissions blocked this month */
				echo '<strong>' . esc_html( sprintf( _n( '%d spam submission blocked this month', '%d spam submissions blocked this month', $month, 'scout-build' ), $month ) ) . '</strong>';
			} else {
				esc_html_e( 'Spam protection is on. Nothing blocked yet this month.', 'scout-build' );
			}
			if ( $all > $month ) {
				/* translators: %d: spam submissions blocked in the last year */
				echo ' &middot; ' . esc_html( sprintf( _n( '%d in the last year', '%d in the last year', $all, 'scout-build' ), $all ) );
			}
			?>
			<span class="sd-note"><?php esc_html_e( 'Your forms check every message invisibly, with no puzzles for real visitors.', 'scout-build' ); ?></span>
		</p>
		<?php
	}

	/** How many checks the box names before "And N more". */
	const SHOW = 2;

	public static function render(): void {
		$on = 0;
		foreach ( array_keys( Scout_Hub_Catalog::features() ) as $slug ) {
			$on += 'on' === Scout_Hub_Features::status( $slug )['state'] ? 1 : 0;
		}
		$basics = array_merge( Scout_Hub_Health::checks(), Scout_Hub_Security::visible_rows() );
		$fix    = array_values( array_filter( $basics, static fn( $c ) => 'bad' === $c['state'] ) );
		$look   = array_values( array_filter( $basics, static fn( $c ) => 'warn' === $c['state'] ) );
		$todo   = self::ranked( array_merge( $fix, $look ) );
		$shown  = array_slice( $todo, 0, self::SHOW );
		$more   = count( $todo ) - count( $shown );
		$open   = count( array_filter( Scout_Hub_Requests::all(), static fn( $p ) => 'done' !== get_post_meta( $p->ID, Scout_Hub_Requests::META_STATUS, true ) ) );
		if ( $fix ) {
			$state = 'bad';
			/* translators: %d: failing checks */
			$line = sprintf( _n( '%d thing on the site needs fixing.', '%d things on the site need fixing.', count( $fix ), 'scout-build' ), count( $fix ) );
		} elseif ( $look ) {
			$state = 'warn';
			/* translators: %d: checks that want a look */
			$line = sprintf( _n( 'The site is fine. %d thing wants a look.', 'The site is fine. %d things want a look.', count( $look ), 'scout-build' ), count( $look ) );
		} else {
			$state = 'good';
			$line  = __( 'The site is in good shape.', 'scout-build' );
		}
		// One short row of chips. AI search and Google email are left to Site status, which lists both.
		$done    = 0;
		$waiting = 0;
		if ( class_exists( 'Scout_Hub_Log' ) ) {
			$done    = (int) Scout_Hub_Log::query( array( 'client' => true, 'status' => 'done', 'month' => current_time( 'Y-m' ), 'per_page' => 1 ) )['total'];
			$waiting = (int) Scout_Hub_Log::query( array( 'client' => true, 'status' => 'waiting', 'per_page' => 1 ) )['total'];
		}
		$chips = array();
		if ( class_exists( 'Scout_Hub_Log' ) ) {
			/* translators: %d: updates this month */
			$chips[] = array( sprintf( _n( '%d update this month', '%d updates this month', $done, 'scout-build' ), $done ), Scout_Hub_Admin::url( 'log' ) );
		}
		/* translators: %d: features on */
		$chips[] = array( sprintf( _n( '%d feature on', '%d features on', $on, 'scout-build' ), $on ), Scout_Hub_Admin::url( 'features' ) );
		if ( $open ) {
			/* translators: %d: open change requests */
			$chips[] = array( sprintf( _n( '%d open request', '%d open requests', $open, 'scout-build' ), $open ), Scout_Hub_Admin::url( 'requests' ) );
		}
		?>
		<style>
			#<?php echo esc_attr( self::ID ); ?> .hndle::before{content:""; display:inline-block; width:12px; height:15px; margin-right:8px; background:#0e8fe6; clip-path:polygon(58% 0,0 58%,42% 58%,30% 100%,100% 40%,54% 40%); vertical-align:-2px}
			#<?php echo esc_attr( self::ID ); ?> .inside{margin:0;padding:0 12px 12px}
			.sh.sd{--sh-fs-base:14px; max-width:none; margin:0; font-size:14px; display:grid; gap:var(--sh-space-3)}
			.sd > *{margin:0}
			.sd .sh-box{margin:12px 0 0; padding:12px 14px; border-radius:var(--sh-r-control)}
			.sd .sh-status{font-size:14px}
			.sd-todo{padding:0; list-style:none; display:grid; gap:var(--sh-space-1)}
			.sd-todo li{display:flex; align-items:center; gap:var(--sh-space-2); margin:0; color:var(--sh-text-2)}
			.sd-todo strong{color:var(--sh-text); font-weight:600}
			.sd-todo .sh-dot{width:8px; height:8px}
			.sd-todo .sd-more{padding-left:16px}
			.sd a{color:var(--sh-accent-text); font-weight:500; text-decoration:none}
			.sd-reviews{padding:10px 12px; border-radius:var(--sh-r-control); background:var(--sh-surface-2); color:var(--sh-text-2)}
			.sd-stars{color:var(--sh-text); margin-right:4px}
			.sd-wait{color:var(--sh-text)}
			.sd-chips{display:flex; flex-wrap:wrap; gap:var(--sh-space-2); padding:0; list-style:none}
			.sd-chips li{margin:0}
			.sd-chips a{display:inline-block; padding:4px 10px; border:1px solid var(--sh-border-strong); border-radius:var(--sh-r-pill); font-size:var(--sh-fs-note)}
			.sd-chips a:hover{border-color:var(--sh-accent-line); background:var(--sh-glow)}
			.sd-ask{display:grid; gap:var(--sh-space-2); padding-top:var(--sh-space-3); border-top:1px solid var(--sh-border)}
			.sd-ask textarea{width:100%}
			.sd-send{margin:0; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:var(--sh-space-2)}
		</style>
		<div class="sh sd">
		<?php Scout_Hub_Admin::status_line( $state, $line ); ?>
		<?php if ( $shown ) : ?>
			<ul class="sd-todo">
				<?php foreach ( $shown as $c ) : ?>
					<li class="sh-check is-<?php echo 'bad' === $c['state'] ? 'bad' : 'warn'; ?>"><span class="sh-dot" aria-hidden="true"></span><span><strong><?php echo esc_html( $c['label'] ); ?>:</strong> <?php echo esc_html( $c['value'] ); ?></span></li>
				<?php endforeach; ?>
				<li class="sd-more"><a href="<?php echo esc_url( Scout_Hub_Admin::url( 'health' ) ); ?>">
					<?php
					echo esc_html(
						$more
							/* translators: %d: checks not named in the box */
							? sprintf( _n( 'And %d more in Site status', 'And %d more in Site status', $more, 'scout-build' ), $more )
							: __( 'Details in Site status', 'scout-build' )
					);
					?>
					&rsaquo;</a></li>
			</ul>
		<?php endif; ?>
		<?php self::reviews(); ?>
		<?php if ( $waiting ) : ?>
			<p class="sd-wait"><strong>
				<?php
				/* translators: %d: items waiting on the client */
				echo esc_html( sprintf( _n( '%d thing is waiting on your OK.', '%d things are waiting on your OK.', $waiting, 'scout-build' ), $waiting ) );
				?>
			</strong> <a href="<?php echo esc_url( Scout_Hub_Admin::url( 'log' ) ); ?>"><?php esc_html_e( 'See them', 'scout-build' ); ?> &rsaquo;</a></p>
		<?php endif; ?>
		<ul class="sd-chips">
			<?php foreach ( $chips as $chip ) : ?>
				<li><a href="<?php echo esc_url( $chip[1] ); ?>"><?php echo esc_html( $chip[0] ); ?> &rsaquo;</a></li>
			<?php endforeach; ?>
		</ul>
		<form class="sd-ask" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( Scout_Hub_Requests::ACTION ); ?>">
			<input type="hidden" name="do" value="new">
			<?php wp_nonce_field( Scout_Hub_Requests::ACTION ); ?>
			<label for="sd-what"><strong><?php esc_html_e( 'Need something changed?', 'scout-build' ); ?></strong></label>
			<textarea id="sd-what" name="what" rows="2" required placeholder="<?php esc_attr_e( 'Tell us what and where. We\'ll take it from here.', 'scout-build' ); ?>"></textarea>
			<p class="sd-send">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Send request', 'scout-build' ); ?></button>
				<a class="button sh-quiet" href="<?php echo esc_url( Scout_Hub_Admin::url( 'overview' ) ); ?>"><?php esc_html_e( 'Open Scout', 'scout-build' ); ?> &rsaquo;</a>
			</p>
		</form>
		</div>
		<?php
	}

	/**
	 * Fixes first, then looks. A row that is only there because this is a
	 * staging copy (search engines hidden) goes last, so it never pushes a real
	 * look out of the top two.
	 */
	public static function ranked( array $rows ): array {
		$rank = static fn( array $c ): int => ( 'bad' === $c['state'] ? 0 : 1 ) + ( empty( $c['staging'] ) ? 0 : 2 );
		usort( $rows, static fn( $a, $b ) => $rank( $a ) <=> $rank( $b ) );
		return $rows;
	}
}
