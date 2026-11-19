<?php
/**
 * Deleting the plugin removes its settings and migration notes. Writing,
 * prompts and each writer's marks are the client's content and stay.
 *
 * @package Scout_Writing
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'scout_writing_settings' );
delete_option( 'scout_writing_migration_checked' );
delete_option( 'scout_writing_migration_log' );
delete_option( 'scout_writing_prompts_upload' );
