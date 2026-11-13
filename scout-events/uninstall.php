<?php
/**
 * Deleting the plugin removes its settings only. Events, sessions and
 * speakers are the client's content and stay in the database, so putting
 * the plugin back brings every page back as it was.
 *
 * @package Scout_Events
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'scout_events' );
delete_option( 'scout_events_rewrite' );
delete_option( 'scout_events_caps' );
// The people capability and the optional Door helper role go with the plugin.
if ( function_exists( 'wp_roles' ) ) {
	foreach ( array_keys( wp_roles()->roles ) as $scout_events_role ) {
		$scout_events_r = get_role( $scout_events_role );
		if ( $scout_events_r ) {
			$scout_events_r->remove_cap( 'scout_events_manage_people' );
			$scout_events_r->remove_cap( 'scout_events_door' );
		}
	}
}
remove_role( 'scout_door_helper' );
