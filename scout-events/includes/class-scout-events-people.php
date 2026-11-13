<?php
/**
 * Who sees the people who come: sign-ups, the door list, volunteers and the
 * Needs you list.
 *
 * Anyone who can edit posts still edits events, sessions and speakers. The
 * screens that show customers' names, emails, answers and agreements need
 * their own capability, scout_events_manage_people, which administrators get
 * on activation and on update (once, safely re-run). An Editor, Author or
 * Contributor no longer sees them.
 *
 * An owner can also turn on a Door helper role in Events, Settings (off by
 * default). A door helper signs in and sees one screen: today's door list,
 * with names, guests, agreements and photo choices, and a Check in button.
 * No emails, no downloads, no other events and no other screens.
 *
 * @package Scout_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scout_Events_People {

	/** Sign-ups, the door list, volunteers and Needs you. */
	const CAP = 'scout_events_manage_people';

	/** Today's door list only. Only the Door helper role has it. */
	const DOOR_CAP = 'scout_events_door';

	/** The optional role, created only when an owner turns it on. */
	const ROLE = 'scout_door_helper';

	/** Option: which grant has run on this site. */
	const OPTION = 'scout_events_caps';

	/** Bump when the grant changes, so it runs again once on update. */
	const GRANT = '1';

	/** The door helper's screen. */
	const DOOR_PAGE = 'scout-events-door';

	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'maybe_grant' ), 1 );
		add_action( 'admin_menu', array( __CLASS__, 'door_menu' ), 31 );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
	}

	/* ---------- the capability ---------- */

	/** On update: give administrators the capability once. Re-running changes nothing. */
	public static function maybe_grant(): void {
		if ( self::GRANT !== (string) get_option( self::OPTION, '' ) ) {
			self::grant();
		}
	}

	/** Administrators manage people, so they see exactly what they saw before. */
	public static function grant(): void {
		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( self::CAP ) ) {
			$role->add_cap( self::CAP );
		}
		update_option( self::OPTION, self::GRANT, true );
	}

	/** Can this person see sign-ups, volunteers and Needs you? */
	public static function can_manage(): bool {
		return current_user_can( self::CAP );
	}

	/* ---------- the door helper ---------- */

	/** Is the Door helper role turned on in Events, Settings? */
	public static function door_on(): bool {
		return ! empty( Scout_Events::settings()['door_role'] );
	}

	/** A door helper who has nothing more than today's door list. */
	public static function is_door_helper(): bool {
		return self::door_on() && current_user_can( self::DOOR_CAP ) && ! self::can_manage();
	}

	/** Create or remove the role to match the setting. */
	public static function sync_role( bool $on ): void {
		if ( ! $on ) {
			remove_role( self::ROLE );
			return;
		}
		$role = get_role( self::ROLE );
		if ( ! $role ) {
			add_role(
				self::ROLE,
				__( 'Door helper', 'scout-events' ),
				array(
					'read'         => true,
					self::DOOR_CAP => true,
				)
			);
		} elseif ( ! $role->has_cap( self::DOOR_CAP ) ) {
			$role->add_cap( self::DOOR_CAP );
		}
	}

	/**
	 * Does a moment range touch today? True when the event starts before
	 * today ends and ends after today starts, in the site's timezone.
	 */
	public static function touches_day( ?DateTimeImmutable $start, ?DateTimeImmutable $end, DateTimeImmutable $now ): bool {
		if ( ! $start ) {
			return false;
		}
		$end      = $end ?? $start;
		$day_from = $now->setTime( 0, 0, 0 );
		$day_to   = $now->setTime( 23, 59, 59 );
		return $start <= $day_to && $end >= $day_from;
	}

	/** Is this event on today? */
	public static function is_today( int $event_id ): bool {
		$e = Scout_Events::event( $event_id );
		return self::touches_day( Scout_Events::start( $e ), Scout_Events::end( $e ), Scout_Events::now() );
	}

	/**
	 * May this person check people in for this event? People managers on any
	 * event they can edit; a door helper only on today's events.
	 */
	public static function can_check_in( int $event_id ): bool {
		if ( self::can_manage() && current_user_can( 'edit_post', $event_id ) ) {
			return true;
		}
		return self::door_on() && current_user_can( self::DOOR_CAP ) && self::is_today( $event_id );
	}

	/** The door list for one event, on the screen this person can open. */
	public static function door_url( int $event_id = 0 ): string {
		if ( self::can_manage() ) {
			return Scout_Events_Admin::signups_url( $event_id );
		}
		$base = admin_url( 'admin.php?page=' . self::DOOR_PAGE );
		return $event_id ? add_query_arg( 'event', $event_id, $base ) : $base;
	}

	/** The door helper's one screen. People managers use Events, Sign-ups instead. */
	public static function door_menu(): void {
		if ( ! self::door_on() || self::can_manage() ) {
			return;
		}
		add_menu_page( __( 'Door list', 'scout-events' ), __( 'Door list', 'scout-events' ), self::DOOR_CAP, self::DOOR_PAGE, array( __CLASS__, 'render_door' ), 'dashicons-tickets-alt', '3.1' );
	}

	public static function render_door(): void {
		if ( ! self::door_on() || ! current_user_can( self::DOOR_CAP ) ) {
			return;
		}
		Scout_Admin_UI::open( __( 'Door list', 'scout-events' ), __( 'Today’s events: who’s going and who’s checked in.', 'scout-events' ), __( 'Events', 'scout-events' ) );
		echo '<div class="se-admin">';
		Scout_Events_RSVP_Admin::view( 'door' );
		echo '</div>';
		Scout_Admin_UI::close();
	}

	/** A door helper lands on the door list after signing in. */
	public static function login_redirect( $to, $requested, $user ) {
		if ( $user instanceof WP_User && in_array( self::ROLE, (array) $user->roles, true ) && ! user_can( $user, 'edit_posts' ) && self::door_on() ) {
			return admin_url( 'admin.php?page=' . self::DOOR_PAGE );
		}
		return $to;
	}
}
