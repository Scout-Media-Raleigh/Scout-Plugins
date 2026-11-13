<?php
/**
 * Events, Contacts (called Customer list before 2026-11-05): whether the sync is on, and one button that brings
 * in everyone from before it was switched on (sign-ups, volunteers, vendors,
 * presenters, members, paid shop tickets and bookings), tagged the same way.
 * Running it twice changes nothing: each person is matched by email.
 *
 * On a site with Scout Booking and without Scout Events, the same screen sits
 * under Bookings instead (one screen, one place). With Scout Booking it also
 * counts the requests declined in the last 30 days, by reason.
 *
 * @package Scout_CRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scout_CRM_Backfill {

	const PAGE         = 'scout-events-crm';
	const BOOKING_PAGE = 'scout-booking-contacts';

	public static function boot(): void {
		add_action( 'scout_events_menu', array( __CLASS__, 'menu' ) );
		add_action( 'scout_booking_menu', array( __CLASS__, 'booking_menu' ) );
		add_action( 'admin_post_scout_crm_backfill', array( __CLASS__, 'run' ) );
	}

	/** Under Events when Scout Events is on, else under Bookings. */
	public static function page(): string {
		return class_exists( 'Scout_Events_Admin' ) ? self::PAGE : self::BOOKING_PAGE;
	}

	public static function menu( $parent ): void {
		add_submenu_page( (string) $parent, __( 'Contacts', 'scout-crm' ), __( 'Contacts', 'scout-crm' ), 'manage_options', self::PAGE, array( __CLASS__, 'render' ) );
	}

	public static function booking_menu( $parent ): void {
		if ( class_exists( 'Scout_Events_Admin' ) ) {
			return; // Events holds the screen; never a second copy.
		}
		add_submenu_page( (string) $parent, __( 'Contacts', 'scout-crm' ), __( 'Contacts', 'scout-crm' ), 'manage_options', self::BOOKING_PAGE, array( __CLASS__, 'render' ) );
	}

	/** Declined booking requests in the last 30 days, by the owner's reason, when Scout Booking is on. */
	private static function declines_box(): void {
		if ( ! class_exists( 'Scout_Booking' ) || ! method_exists( 'Scout_Booking', 'decline_counts' ) ) {
			return;
		}
		$counts = Scout_Booking::decline_counts( 30 );
		$fit    = __( 'Not a fit', 'scout-crm' );
		echo '<section class="sh-box"><h2 class="sh-title">' . esc_html__( 'Declined requests, last 30 days', 'scout-crm' ) . '</h2>';
		if ( ! $counts ) {
			echo '<p>' . esc_html__( 'No requests declined in the last 30 days.', 'scout-crm' ) . '</p></section>';
			return;
		}
		$n = (int) ( $counts[ $fit ] ?? 0 );
		/* translators: %d: number of requests declined as not a fit. */
		echo '<p><strong>' . esc_html( sprintf( __( 'Declined as not a fit: %d.', 'scout-crm' ), $n ) ) . '</strong></p><p class="sh-row-facts">';
		foreach ( $counts as $reason => $count ) {
			$label = '' !== $reason ? $reason : __( 'No reason given', 'scout-crm' );
			echo '<span class="sh-tag">' . esc_html( $label . ': ' . number_format_i18n( (int) $count ) ) . '</span> ';
		}
		echo '</p><p class="sh-note">' . esc_html__( 'Each person is tagged with the reason in FluentCRM, so you can find them later.', 'scout-crm' ) . '</p></section>';
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$ui = class_exists( 'Scout_Admin_UI' );
		if ( $ui ) {
			Scout_Admin_UI::open( __( 'Contacts', 'scout-crm' ), __( 'Everyone who buys, signs up, volunteers, applies, joins or books goes to your FluentCRM contacts.', 'scout-crm' ), self::BOOKING_PAGE === self::page() ? __( 'Bookings', 'scout-crm' ) : __( 'Events', 'scout-crm' ) );
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'Contacts', 'scout-crm' ) . '</h1>';
		}
		$done = get_transient( 'scout_crm_backfill_' . get_current_user_id() );
		if ( is_array( $done ) ) {
			delete_transient( 'scout_crm_backfill_' . get_current_user_id() );
			$parts = array();
			foreach ( $done as $what => $n ) {
				$parts[] = number_format_i18n( (int) $n ) . ' ' . $what;
			}
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Done. Added or updated', 'scout-crm' ) . ' ' . esc_html( implode( ', ', $parts ) ) . '.</p></div>';
		}
		$status = static function ( string $state, string $headline, string $detail, string $go = '' ): void {
			if ( method_exists( 'Scout_Admin_UI', 'status' ) ) {
				Scout_Admin_UI::status( $state, $headline, $detail, $go );
				return;
			}
			echo '<section class="sh-box"><p class="sh-status is-' . esc_attr( $state ) . '"><span class="sh-dot" aria-hidden="true"></span><strong>' . esc_html( $headline ) . '</strong> <span>' . esc_html( $detail ) . '</span>' . ( '' !== $go ? ' <span class="sh-status-go">' . $go . '</span>' : '' ) . '</p></section>'; // phpcs:ignore WordPress.Security.EscapeOutput -- $go is escaped by the caller.
		};
		if ( ! Scout_CRM::ready() ) {
			$status( 'off', __( 'Off.', 'scout-crm' ), __( 'Install and activate FluentCRM (free) and this page takes it from there.', 'scout-crm' ) );
		} else {
			$status( 'good', __( 'On.', 'scout-crm' ), __( 'New people go to FluentCRM as they happen.', 'scout-crm' ), '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=fluentcrm-admin#/subscribers' ) ) . '">' . esc_html__( 'Open the contacts', 'scout-crm' ) . '</a>' );
			echo '<section class="sh-box"><h2 class="sh-title">' . esc_html__( 'Bring in everyone so far', 'scout-crm' ) . '</h2><p>' . esc_html__( 'Adds the people from before the sync was on. Safe to run again: people are matched by email.', 'scout-crm' ) . '</p>';
			echo '<p class="sh-note">' . esc_html__( 'They get receipts and event notes, and marketing emails only if they subscribe.', 'scout-crm' ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="scout_crm_backfill">';
			wp_nonce_field( 'scout_crm_backfill' );
			echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Bring them in', 'scout-crm' ) . '</button></p></form></section>';
			self::declines_box();
		}
		$labels = array(
			__( 'Attendee', 'scout-crm' ),
			__( 'Waitlist', 'scout-crm' ),
			__( 'Volunteer', 'scout-crm' ),
			__( 'Vendor', 'scout-crm' ),
			__( 'Presenter', 'scout-crm' ),
			__( 'Ticket buyer', 'scout-crm' ),
			__( 'Member applicant', 'scout-crm' ),
			__( 'Member', 'scout-crm' ),
			__( 'Plan: (plan name)', 'scout-crm' ),
			__( 'Event: (event name)', 'scout-crm' ),
			__( 'Region: (region name)', 'scout-crm' ),
			__( 'Ticket: (ticket type)', 'scout-crm' ),
		);
		if ( class_exists( 'Scout_Booking' ) ) {
			$labels[] = __( 'Booking request', 'scout-crm' );
			$labels[] = __( 'Booked a session', 'scout-crm' );
			$labels[] = __( 'Session: (session name)', 'scout-crm' );
			$labels[] = __( 'The reason for a declined request', 'scout-crm' );
		}
		echo '<section class="sh-box"><h2 class="sh-title">' . esc_html__( 'Labels it adds', 'scout-crm' ) . '</h2><p class="sh-row-facts">';
		foreach ( $labels as $label ) {
			echo '<span class="sh-tag">' . esc_html( $label ) . '</span> ';
		}
		echo '</p></section>';
		if ( $ui ) {
			Scout_Admin_UI::close();
		} else {
			echo '</div>';
		}
	}

	public static function run(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'scout-crm' ), 403 );
		}
		check_admin_referer( 'scout_crm_backfill' );
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- a one-off bulk run.
		}
		$count = array();
		$add   = static function ( string $label, bool $ok ) use ( &$count ) {
			$count[ $label ] = ( $count[ $label ] ?? 0 ) + ( $ok ? 1 : 0 );
		};
		global $wpdb;

		if ( class_exists( 'Scout_Events_RSVP' ) ) {
			$rows = $wpdb->get_results( 'SELECT event_id, name, email, status FROM ' . Scout_Events_RSVP::table() . " WHERE status IN ('going','waitlist') LIMIT 5000", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			foreach ( (array) $rows as $r ) {
				$role = 'going' === $r['status'] ? 'attendee' : 'waitlist';
				$add( __( 'sign-ups', 'scout-crm' ), Scout_CRM::save( array( 'email' => $r['email'], 'name' => $r['name'] ), array_merge( array( 'going' === $r['status'] ? __( 'Attendee', 'scout-crm' ) : __( 'Waitlist', 'scout-crm' ) ), Scout_CRM::event_tags( (int) $r['event_id'] ) ), 'events-' . $role ) );
			}
		}
		if ( class_exists( 'Scout_Events_Volunteers' ) ) {
			$rows = $wpdb->get_results( 'SELECT event_id, email, MIN(name) AS name, MIN(phone) AS phone FROM ' . Scout_Events_Volunteers::table() . " WHERE status = 'going' GROUP BY event_id, email LIMIT 5000", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			foreach ( (array) $rows as $r ) {
				$add( __( 'volunteers', 'scout-crm' ), Scout_CRM::save( array( 'email' => $r['email'], 'name' => $r['name'], 'phone' => $r['phone'] ), array_merge( array( __( 'Volunteer', 'scout-crm' ) ), Scout_CRM::event_tags( (int) $r['event_id'] ) ), 'events-volunteer' ) );
			}
		}
		if ( class_exists( 'Scout_Events_Vendors' ) ) {
			$ids = get_posts( array( 'post_type' => Scout_Events_Vendors::TYPE, 'post_status' => array( 'publish', 'pending' ), 'posts_per_page' => 2000, 'fields' => 'ids' ) );
			foreach ( $ids as $id ) {
				$priv = get_post_meta( (int) $id, Scout_Events_Vendors::PRIVATE, true );
				$priv = is_array( $priv ) ? $priv : array();
				$tags = array( __( 'Vendor', 'scout-crm' ) );
				foreach ( Scout_Events_Vendors::vendor( (int) $id )['events'] as $event ) {
					$tags = array_merge( $tags, Scout_CRM::event_tags( (int) $event ) );
				}
				$add( __( 'vendors', 'scout-crm' ), Scout_CRM::save( array( 'email' => (string) ( $priv['email'] ?? '' ), 'name' => (string) ( $priv['contact'] ?? '' ), 'phone' => (string) ( $priv['phone'] ?? '' ), 'company' => get_the_title( (int) $id ) ), $tags, 'events-vendor' ) );
			}
		}
		if ( class_exists( 'Scout_Events_Presenters' ) ) {
			$ids = get_posts( array( 'post_type' => 'scout_speaker', 'post_status' => array( 'publish', 'pending' ), 'posts_per_page' => 2000, 'fields' => 'ids', 'meta_key' => Scout_Events_Presenters::USER ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a one-off run.
			foreach ( $ids as $id ) {
				$user = get_userdata( (int) get_post_meta( (int) $id, Scout_Events_Presenters::USER, true ) );
				if ( ! $user ) {
					continue;
				}
				$tags = array( __( 'Presenter', 'scout-crm' ) );
				foreach ( Scout_Events::sessions_for_speaker( (int) $id ) as $sid ) {
					$tags = array_merge( $tags, Scout_CRM::event_tags( Scout_Events::session( (int) $sid )['event'] ) );
				}
				$add( __( 'presenters', 'scout-crm' ), Scout_CRM::save( array( 'email' => $user->user_email, 'name' => get_the_title( (int) $id ) ), $tags, 'events-presenter' ) );
			}
		}
		if ( class_exists( 'Scout_Members' ) ) {
			foreach ( Scout_Members::ids( array( 'applied', 'active' ) ) as $uid ) {
				Scout_CRM::member( $uid, Scout_Members::member( $uid )['status'], '' );
				$add( __( 'members', 'scout-crm' ), true );
			}
		}
		if ( class_exists( 'Scout_Events_Woo' ) && function_exists( 'wc_get_orders' ) ) {
			$orders = wc_get_orders( array( 'status' => array( 'processing', 'completed' ), 'limit' => 2000 ) );
			foreach ( $orders as $order ) {
				foreach ( $order->get_items() as $item ) {
					$pid   = (int) $item->get_product_id();
					$event = (int) get_post_meta( $pid, Scout_Events_Woo::EVENT, true );
					if ( $event ) {
						Scout_CRM::ticket( $order, $event, $pid, (int) $item->get_quantity() );
						$add( __( 'ticket lines', 'scout-crm' ), true );
					}
				}
			}
		}
		if ( class_exists( 'Scout_Booking' ) ) {
			// Name, email and phone only; the intake answers stay with the booking.
			$rows = $wpdb->get_results( 'SELECT * FROM ' . Scout_Booking::table() . " WHERE status <> 'blocked' AND email <> '' ORDER BY id ASC LIMIT 5000", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			foreach ( (array) $rows as $r ) {
				Scout_CRM::booking( $r );
				if ( 'declined' === $r['status'] && '' !== (string) ( $r['reason'] ?? '' ) ) {
					Scout_CRM::booking_declined( $r, (string) $r['reason'] );
				}
				$add( __( 'bookings', 'scout-crm' ), true );
			}
		}
		set_transient( 'scout_crm_backfill_' . get_current_user_id(), $count, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::page() ) );
		exit;
	}
}
