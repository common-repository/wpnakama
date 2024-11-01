<?php
/**
 * On plugin activation, it'll check for plugin required WordPress version,
 * internal dependencies for internal app to run and add required
 * tables for plugin to work in the database.
 *
 * @package     WPNakama
 * @subpackage  Core
 * @since       0.1.0
 * @version     0.3.3
 * @author      kantbtrue, qdonow, designthingy, savydv
 * @license     GPL-2.0-or-later
 */

// If direct access this file, abort.
if ( ! defined( 'WPINC' ) ) {
	die( 'Direct script access is prohibited!' );
}

if ( ! function_exists( 'wpnakama_activate' ) ) {
	/**
	 * Function `wpnakama_activate()` will run on plugin activation.
	 */
	function wpnakama_activate() {
		/**
		 * Check for the WordPress version required
		 * for the plugin to run.
		 */
		if ( ! is_wp_version_compatible( '6.2' ) ) {
			wp_die(
				esc_html__( 'To use this plugin, upgrade your WordPress version', 'wpnakama' ),
				esc_html__( '500 Error: WordPress compatibility issue', 'wpnakama' ),
				500
			);
		}

		/**
		 * Check the required asset file whether exists or not, which is
		 * used by the plugin app.
		 */
		if ( ! file_exists( WPNAKAMA_PLUGIN_PATH . 'admin/app/build/index.asset.php' ) ) {
			wp_die(
				esc_html__( 'Plugin not able to activate. It has some admin dashboard dependencies related issue. Please contact to plugin developer.', 'wpnakama' ),
				esc_html__( '500 Error: Plugin dependencies issue', 'wpnakama' ),
				500
			);
		}

		/**
		 * Clear plugin related cache upon activation.
		 */

		// Clear the plugin cache.
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache( true );
		}

		// Clear the entire WordPress cache.
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		/**
		 * Create required tables on activation of plugin.
		 */
		require_once WPNAKAMA_PLUGIN_PATH . 'inc/class-wpnakama-database.php';
		$database            = new WPNakama_Database();
		$database_tables_arr = array(
			array(
				'name'  => $database->table_prefix( 'wpnakama_workspaces' ),
				'query' => 'CREATE TABLE ' . $database->table_prefix( 'wpnakama_workspaces' ) . ' (
					workspace_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,
					title TEXT,
					description VARCHAR(250),
					PRIMARY KEY (workspace_id)
				)',
			),
			array(
				'name'  => $database->table_prefix( 'wpnakama_boards' ),
				'query' => 'CREATE TABLE ' . $database->table_prefix( 'wpnakama_boards' ) . ' (
					board_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,
					title TEXT,
					description VARCHAR(250),
					board_date datetime NOT NULL DEFAULT "0000-00-00 00:00:00",
					board_date_gmt datetime NOT NULL DEFAULT "0000-00-00 00:00:00",
					start_date datetime NOT NULL DEFAULT "0000-00-00 00:00:00",
					end_date datetime NOT NULL DEFAULT "0000-00-00 00:00:00",
					workspace_id BIGINT(20) NOT NULL DEFAULT 0,
					PRIMARY KEY (board_id),
					KEY workspace_id (workspace_id),
					KEY board_date_gmt (board_date_gmt)
				)',
			),
			array(
				'name'  => $database->table_prefix( 'wpnakama_phases' ),
				'query' => 'CREATE TABLE ' . $database->table_prefix( 'wpnakama_phases' ) . ' (
					phase_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,
					board_id BIGINT(20) NOT NULL DEFAULT 0,
					title TEXT,
					position BIGINT(20) NOT NULL DEFAULT 0,
					PRIMARY KEY (phase_id),
					KEY board_id (board_id),
					KEY position (position)
				)',
			),
			array(
				'name'  => $database->table_prefix( 'wpnakama_tasks' ),
				'query' => 'CREATE TABLE ' . $database->table_prefix( 'wpnakama_tasks' ) . ' (
					task_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,
					content VARCHAR(250),
					board_id BIGINT(20) NOT NULL DEFAULT 0,
					card_id BIGINT(20) NOT NULL DEFAULT 0,
					user_id BIGINT(20) NOT NULL DEFAULT 0,
					position BIGINT(20) NOT NULL DEFAULT 0,
					is_completed SMALLINT NOT NULL DEFAULT "0",
					PRIMARY KEY (task_id),
					KEY board_id (board_id),
					KEY card_id (card_id),
					KEY user_id (user_id),
					KEY position (position),
					KEY is_completed (is_completed)
				)',
			),
			array(
				'name'  => $database->table_prefix( 'wpnakama_taskslists' ),
				'query' => 'CREATE TABLE ' . $database->table_prefix( 'wpnakama_taskslists' ) . ' (
					taskslist_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,
					title TEXT,
					tasks LONGTEXT,
					PRIMARY KEY (taskslist_id)
				)',
			),
			array(
				'name'  => $database->table_prefix( 'wpnakama_cards' ),
				'query' => 'CREATE TABLE ' . $database->table_prefix( 'wpnakama_cards' ) . ' (
					id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,
					board_id BIGINT(20) NOT NULL DEFAULT 0,
					card_id BIGINT(20) NOT NULL DEFAULT 0,
					PRIMARY KEY (id),
					KEY board_id (board_id),
					KEY card_id (card_id)
				)',
			),
			array(
				'name'  => $database->table_prefix( 'wpnakama_boards_access' ),
				'query' => 'CREATE TABLE ' . $database->table_prefix( 'wpnakama_boards_access' ) . ' (
					id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,
					board_id BIGINT(20) NOT NULL UNIQUE DEFAULT 0,
					access_value LONGTEXT,
					PRIMARY KEY (id),
					KEY board_id (board_id)
				)',
			),
		);
		foreach ( $database_tables_arr as $table ) {
			$database->create_table( $table['name'], $table['query'] );
		}

		/**
		 * Update indicator tells what type if update is this.
		 *
		 * 0 - No update or updates are checked
		 * 1 - Major or Security update
		 * 2 - New feature update
		 * 3 - Bug fix update
		 */
		update_option( 'wpnakama_update_indicator', 3 );

		/**
		 * Rating the plugin.
		 *
		 * status: not_rated, rated
		 */
		update_option(
			'wpnakama_rating',
			array(
				'status'           => 'not_rated',
				'last_asked'       => time() + (1 * DAY_IN_SECONDS),
				'times_asked'      => 0,
				'rate_btn_clicked' => false,
			)
		);

		/**
		 * Reset the feature info notice.
		 * status: 0 - Not dismissed, 1 - Dismissed
		 */
		update_user_meta( get_current_user_id(), 'wpnakama_feature_info_notice_dissmiss', '0' );
	}
}
