<?php
/**
 * Cache class.
 *
 * @package     WPNakama
 * @subpackage  Ingredient
 * @since       0.1.0
 * @version     0.2.0
 * @author      kantbtrue, qdonow, designthingy, savydv
 * @license     GPL-2.0-or-later
 */

// If direct access this file, abort.
if ( ! defined( 'WPINC' ) ) {
	die( 'Direct script access is prohibited!' );
}

if ( ! class_exists( 'WPNakama_Cache' ) ) {
	/**
	 * Cache class prevent's the creation of stale data becuase of cache plugins on WPNakama admin pages.
	 * It required to prevent the inconsistencies and incorrect information being
	 * displayed to the user.
	 */
	class WPNakama_Cache {

		/**
		 * Plugin unique identifier.
		 *
		 * @var string $plugin_uid The ID of this plugin.
		 */
		protected $plugin_uid;

		/**
		 * Plugin version.
		 *
		 * @var string $version Current version of this plugin.
		 */
		protected $version;

		/**
		 * Run when class instantiated.
		 *
		 * @param string $plugin_uid Plugin unique identifier.
		 * @param string $version Plugin version.
		 */
		public function __construct( $plugin_uid, $version ) {
			// Set plugin unique identifier.
			$this->plugin_uid = $plugin_uid;

			// Set plugin version.
			$this->version = $version;
		}

		/**
		 * Clear cache, on WPNakama admin pages.
		 */
		public function clear_cache() {
			if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
				$screen = get_current_screen();
				if ( $screen ) {
					// List of admin page IDs where cache should be cleared.
					$admin_pages_to_clear_cache = array(
						'toplevel_page_wpnakama',
						'wp-nakama_page_wpnakama_license',
						'wp-nakama_page_wpnakama_resources',
					);

					if ( in_array( $screen->id, $admin_pages_to_clear_cache ) ) {
						$this->clear_object_cache();
					}
				}
			}
		}

		/**
		 * Clear the object cache.
		 */
		public function clear_object_cache() {
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}
		}
	}
}
