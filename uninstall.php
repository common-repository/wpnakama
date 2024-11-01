<?php
/**
 * On plugin uninstall, it'll delete all the data that was generated
 * during the plugin.
 *
 * @package     WPNakama
 * @subpackage  Core
 * @since       0.1.0
 * @version     0.1.0
 * @author      kantbtrue, qdonow, designthingy
 * @license     GPL-2.0-or-later
 */

// If this file not called by WordPress, abort.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die( 'Direct script access is prohibited!' );
}

// Deletes all the tables related to the plugin.
require_once plugin_dir_path( __FILE__ ) . 'inc/class-wpnakama-database.php';
$database = new WPNakama_Database();
$database->delete_all_tables();
delete_option( 'wpnakama_rating' );
delete_site_option( 'wpnakama_rating' );
delete_option( 'wpnakama_update_indicator' );
delete_site_option( 'wpnakama_update_indicator' );
delete_option( 'wpnakama_update_indicator' );
delete_site_option( 'wpnakama_update_indicator' );
delete_option( 'wpnakama_license' );
delete_site_option( 'wpnakama_license' );
delete_option( 'wpnakama_license_message' );
delete_site_option( 'wpnakama_license_message' );
delete_post_meta_by_key( 'wpnakama_board_id' );
delete_post_meta_by_key( 'wpnakama_tasks' );
