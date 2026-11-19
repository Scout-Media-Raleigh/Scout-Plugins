<?php
/**
 * Deleting the plugin removes its settings and markers. The cards themselves
 * and the people table are the client's content and stay, so putting the
 * plugin back brings every card back. 'scout_cards' belongs to the Scout
 * Media theme and is left alone.
 *
 * @package Scout_Cards
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'scout_cards_settings' );
delete_option( 'scout_cards_theme_import' );
delete_option( 'scout_cards_people_note' );
delete_option( 'scout_cards_routes' );
delete_transient( 'scout_cards_people_moving' );
