<?php
/**
 * Deleting the plugin removes its marker. Sites and park content stay.
 *
 * @package Scout_RVPark
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'scout_rvpark_rewrite' );
