<?php
/**
 * Deleting the plugin removes its settings. Partner listings are the client's
 * content and stay.
 *
 * @package Scout_Partners
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'scout_partners' );
