<?php
/**
 * WPNakama
 *
 * @package    WPNakama
 * @author     kantbtrue, qdonow, designthingy, savydv
 * @copyright  2023 WPNAKAMA.com
 * @license    GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       WPNakama - Team and client collaboration Starts Here
 * Description:       Do you have Clients, a Team, & deadlines? OR Struggling with Productivity and Transparency? Then WPNakama is just meant for you.
 * Version:           0.3.3
 * Requires at least: 6.2.0
 * Requires PHP:      7.4
 * Author:            kantbtrue, qdonow, designthingy, savydv
 * Author URI:        https://wpnakama.com
 * Domain Path:       languages
 * License:           GPLv2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpnakama
 * Update URI:        https://wordpress.org/plugins/wpnakama
 */

/*
WP Nakama is free software, (C) 2023 WPNAKAMA.com: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

WP Nakama is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with WPNakama. If not, see http://www.gnu.org/licenses/gpl-2.0.html.
*/

// If direct access this file, abort.
if ( ! defined( 'WPINC' ) ) {
	die( 'Direct script access is prohibited!' );
}

// Define plugin constants for internal use.
define( 'WPNAKAMA_PLUGIN_FILE', __FILE__ );
define( 'WPNAKAMA_BASENAME', plugin_basename( WPNAKAMA_PLUGIN_FILE ) );
define( 'WPNAKAMA_PLUGIN_URL', plugin_dir_url( WPNAKAMA_PLUGIN_FILE ) );
define( 'WPNAKAMA_PLUGIN_PATH', plugin_dir_path( WPNAKAMA_PLUGIN_FILE ) );
define( 'WPNAKAMA_PLUGIN_VERSION', '0.3.3' );

/**
 * On plugin activation, it'll check for required WordPress version,
 * internal dependencies and add required tables for plugin to
 * work in the database.
 */
require WPNAKAMA_PLUGIN_PATH . 'inc/activate.php';
register_activation_hook( __FILE__, 'wpnakama_activate' );

if ( ! function_exists( 'wpnakama_run' ) ) {
	/**
	 * Function `wpnakama_run()` load, instantiate and run
	 * the plugin.
	 */
	function wpnakama_run() {
		// Load core plugin files.
		require_once WPNAKAMA_PLUGIN_PATH . 'inc/wpnakama-helper-trait.php';
		require_once WPNAKAMA_PLUGIN_PATH . 'inc/wpnakama-api-schema-trait.php';
		require_once WPNAKAMA_PLUGIN_PATH . 'inc/class-wpnakama.php';

		// Instantiate core plugin class.
		$wpnakama = new WPNakama();
		$wpnakama->run();
	}
}

wpnakama_run();

// Temporarily feature info notice.
function wpnakama_feature_info_notice() {
	if (get_user_meta( get_current_user_id(), 'wpnakama_feature_info_notice_dissmiss', true ) != '1'):
	?>
	<div class="notice notice-info is-dismissible">
		<div style="display: flex; align-items: center; gap: 10px; padding: 5px">
			<img src="<?php echo WPNAKAMA_PLUGIN_URL . 'admin/images/wpnakama-logo.svg'; ?>" alt="WPNakama Logo" style="width: 50px; height: 50px;">
			<p>
				<strong>WPNakama Pro</strong> - We're listening to your feedback. Now <strong>Protect your Public Boards with Passwords.</strong> <br>
				<a href="https://qdonow.lemonsqueezy.com/buy/9acae5d8-e273-448c-b5ab-54847edc42a1" target="_blank" style="color: #3c20ac; text-decoration: underline">Go Pro Now</a>
			</p>
			<a href="<?php echo add_query_arg( 'wpnakama_feature_info_notice_dissmiss', '1' ); ?>" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></a>
		</div>
	</div>
	<?php
	endif;
}

function wpnakama_feature_info_notice_dissmiss() {
	if ( isset( $_GET['wpnakama_feature_info_notice_dissmiss'] ) && $_GET['wpnakama_feature_info_notice_dissmiss'] == '1' ) {
		update_user_meta( get_current_user_id(), 'wpnakama_feature_info_notice_dissmiss', '1' );
	}
}
add_action( 'admin_notices', 'wpnakama_feature_info_notice' );
add_action( 'admin_init', 'wpnakama_feature_info_notice_dissmiss' );