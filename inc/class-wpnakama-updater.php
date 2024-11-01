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

if ( ! class_exists( 'WPNakama_Updater' ) ) {
	/**
	 * Updater class.
	 * The updater class is responsible for checking if there
	 * is an update available for the plugin.
	 *
	 * It is the offical class provided by lemon-squeezy.
	 */
	class WPNakama_Updater {

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
		 * Plugin Slug.
		 *
		 * @var string Plugin slug.
		 */
		public $plugin_slug;

		/**
		 * Remote server API URL.
		 *
		 * @var string $api_url The API URL to the remove update server.
		 */
		public $api_url;

		/**
		 * Run when class instantiated.
		 *
		 * @param string $plugin_uid  The ID of the plugin.
		 * @param string $version     The current version of the plugin.
		 * @param string $api_url     The API URL to the update server.
		 */
		public function __construct( $plugin_uid, $version, $api_url ) {
			$this->plugin_uid  = $plugin_uid;
			$this->plugin_slug = dirname( WPNAKAMA_BASENAME );
			$this->version     = $version;
			$this->api_url     = $api_url;

			$this->cache_key     = str_replace( '-', '_', $this->plugin_slug ) . '_updater';
			$this->cache_allowed = true; // Only disable this for debugging
		}

		/**
		 * Get the license key. Normally, your plugin would have a settings page where
		 * you ask for and store a license key. Fetch it here.
		 *
		 * @return string
		 */
		protected function get_license_key() {
			$wpnakama_license = get_option( 'wpnakama_license' );
			return isset( $wpnakama_license['api_key_0'] ) ? $wpnakama_license['api_key_0'] : null;
		}

		/**
		 * Fetch the update info from the remote server running the Lemon Squeezy plugin.
		 *
		 * @return object|bool
		 */
		public function request() {
			$lsq_license_key = $this->get_license_key();
			if ( ! $lsq_license_key ) {
				return false;
			}

			$remote = get_transient( $this->cache_key );

			if ( false !== $remote && $this->cache_allowed ) {
				if ( 'error' === $remote ) {
					return false;
				}

				return json_decode( $remote );
			}
			$remote = wp_remote_get(
				$this->api_url . "/update?license_key={$lsq_license_key}",
				array(
					'timeout' => 10,
				)
			);

			if (
				is_wp_error( $remote )
				|| 200 !== wp_remote_retrieve_response_code( $remote )
				|| empty( wp_remote_retrieve_body( $remote ) )
			) {
				set_transient( $this->cache_key, 'error', MINUTE_IN_SECONDS * 10 );

				return false;
			}

			$payload = wp_remote_retrieve_body( $remote );

			set_transient( $this->cache_key, $payload, DAY_IN_SECONDS );

			return json_decode( $payload );
		}

		/**
		 * Override the WordPress request to return the correct plugin info.
		 *
		 * @see https://developer.wordpress.org/reference/hooks/plugins_api/
		 *
		 * @param false|object|array $result
		 * @param string             $action
		 * @param object             $args
		 *
		 * @return object|bool
		 */
		public function info( $result, $action, $args ) {
			if ( 'plugin_information' !== $action ) {
				return false;
			}
			if ( $this->plugin_slug !== $args->slug ) {
				return false;
			}

			$remote = $this->request();
			if ( ! $remote || ! $remote->success || empty( $remote->update ) ) {
				return false;
			}

			$plugin_data = get_plugin_data( __FILE__ );

			$result           = $remote->update;
			$result->name     = $plugin_data['Name'];
			$result->slug     = $this->plugin_slug;
			$result->sections = (array) $result->sections;

			return $result;
		}

		/**
		 * Override the WordPress request to check if an update is available.
		 *
		 * @see https://make.wordpress.org/core/2020/07/30/recommended-usage-of-the-updates-api-to-support-the-auto-updates-ui-for-plugins-and-themes-in-wordpress-5-5/
		 *
		 * @param object $transient
		 *
		 * @return object
		 */
		public function update( $transient ) {
			if ( empty( $transient->checked ) ) {
				return $transient;
			}

			$res = (object) array(
				'id'            => $this->plugin_uid,
				'slug'          => $this->plugin_slug,
				'plugin'        => WPNAKAMA_BASENAME,
				'new_version'   => $this->version,
				'url'           => '',
				'package'       => '',
				'icons'         => array(),
				'banners'       => array(),
				'banners_rtl'   => array(),
				'tested'        => '',
				'requires_php'  => '',
				'compatibility' => new stdClass(),
			);

			$remote = $this->request();

			if (
				$remote && $remote->success && ! empty( $remote->update )
				&& version_compare( $this->version, $remote->update->version, '<' )
			) {
				$res->new_version  = $remote->update->version;
				$res->package      = $remote->update->download_link;
				$res->tested       = '6.6';
				$res->requires_php = '7.4';

				$transient->response[ $res->plugin ] = $res;

			} else {
				$transient->no_update[ $res->plugin ] = $res;
			}

			return $transient;
		}

		/**
		 * When the update is complete, purge the cache.
		 *
		 * @see https://developer.wordpress.org/reference/hooks/upgrader_process_complete/
		 *
		 * @param WP_Upgrader $upgrader
		 * @param array       $options
		 *
		 * @return void
		 */
		public function purge( $upgrader, $options ) {
			if (
				$this->cache_allowed
				&& 'update' === $options['action']
				&& 'plugin' === $options['type']
				&& ! empty( $options['plugins'] )
			) {
				foreach ( $options['plugins'] as $plugin ) {
					if ( $plugin === $this->plugin_uid ) {
						delete_transient( $this->cache_key );
					}
				}
			}
		}
	}
}
