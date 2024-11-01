<?php
/**
 * Plugin front functionality class.
 *
 * @package     WPNakama
 * @subpackage  Core
 * @since       0.3.0
 * @version     0.1.0
 * @author      kantbtrue, qdonow, designthingy, savydv
 * @license     GPL-2.0-or-later
 */

// If direct access this file, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'WPNakama_Frontend' ) ) {
	/**
	 * Plugin front functionalities.
	 */
	class WPNakama_Frontend {

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
		 * Enqueue front end styles and scripts.
		 *
		 * @return bool
		 */
		public function enqueue_styles_scripts() {
			if ( is_singular( 'wpn_boards' ) ) {
				// Automatically load imported dependencies and assets version.
				$front_app_files = include WPNAKAMA_PLUGIN_PATH . 'frontend/build/index.asset.php';

				// Enqueue dependencies.
				foreach ( $front_app_files['dependencies'] as $style ) {
					wp_enqueue_style( $style );
				}

				wp_register_style(
					'google_fonts_frontend',
					'https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Koulen&display=swap',
					array(),
					null
				);
				wp_enqueue_style( 'google_fonts_frontend' );

				wp_register_style(
					$this->plugin_uid . '_frontend',
					WPNAKAMA_PLUGIN_URL . 'frontend/build/index.css',
					array(),
					$front_app_files['version'],
					'all'
				);
				wp_enqueue_style( $this->plugin_uid . '_frontend' );

				wp_register_script(
					$this->plugin_uid . '_frontend',
					WPNAKAMA_PLUGIN_URL . 'frontend/build/index.js',
					$front_app_files['dependencies'],
					$front_app_files['version'],
					array(
						'strategy'  => 'defer',
						'in_footer' => true,
					)
				);
				wp_enqueue_script( $this->plugin_uid . '_frontend' );

				// Settings the nonce for API middleware.
				wp_localize_script(
					$this->plugin_uid . '_frontend',
					'wpNakamaApiAuth',
					array(
						'root'    => esc_url_raw( rest_url() ),
						'siteurl' => get_option( 'siteurl' ),
						'nonce'   => wp_create_nonce( 'wp_rest' ),
					)
				);
			}
			return true;
		}

		/**
		 * Register WPNakama post meta fields.
		 *
		 * @return bool
		 */
		public function register_post_meta_fields() {
			return register_post_meta(
				'wpn_boards',
				'wpnakama_board_id',
				array(
					'single'       => true,
					'type'         => 'number',
					'show_in_rest' => array(
						'schema' => array(
							'type' => 'number',
						),
					),
				)
			);
		}
	}
}
