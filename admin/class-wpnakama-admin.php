<?php
/**
 * Plugin admin class.
 *
 * @package     WPNakama
 * @subpackage  Core
 * @since       0.1.0
 * @version     0.1.1
 * @author      kantbtrue, qdonow, designthingy, savydv
 * @license     GPL-2.0-or-later
 */

// If direct access this file, abort.
if ( ! defined( 'WPINC' ) ) {
	die( 'Direct script access is prohibited!' );
}

if ( ! class_exists( 'WPNakama_Admin' ) ) {
	/**
	 * Plugin admin class is responsible for
	 */
	class WPNakama_Admin {

		/**
		 * Plugin unique identifier.
		 *
		 * @var string
		 */
		protected $plugin_uid;

		/**
		 * Plugin current version.
		 *
		 * @var string
		 */
		protected $version;

		/**
		 * List of informative links for plugin users.
		 *
		 * @var array
		 */
		protected $plugin_links = array();

		/**
		 * List of plugin pages in WP admin menu area.
		 *
		 * @var array
		 */
		protected $plugin_pages = array();

		/**
		 * Plugin title to be shown on the admin menu area.
		 *
		 * @var string
		 */
		protected $menu_title = '';

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

			// Set plugin menu title.
			$this->menu_title = 'WP Nakama';

			// Set plugin informative links.
			$this->plugin_links = array(
				array(
					'title' => __( 'FAQ', 'wpnakama' ),
					'link'  => 'https://wordpress.org/plugins/wpnakama/#faq',
				),
				array(
					'title' => __( 'Support', 'wpnakama' ),
					'link'  => 'https://wordpress.org/support/plugin/wpnakama/',
				),
			);

			// Initializing plugin pages.
			$this->plugin_pages = array(
				'boards'    => array(
					'page_title'       => __( 'Boards', 'wpnakama' ),
					'admin_menu_title' => __( 'Boards', 'wpnakama' ),
				),
				'resources' => array(
					'page_title'       => __( 'Releases, Updates, Help Center on WPNakama', 'wpnakama' ),
					'admin_menu_title' => __( 'Resources', 'wpnakama' ),
				),
				'license'   => array(
					'page_title'       => __( 'License Upgrade', 'wpnakama' ),
					'admin_menu_title' => __( 'License', 'wpnakama' ),
				),
			);
		}

		/**
		 * Add links in the "Description" column on the plugins page.
		 *
		 * @param array  $links List of links to print in the "Description" column on the Plugins page.
		 * @param string $file  Name of the plugin.
		 *
		 * @return array
		 */
		public function plugin_row_meta( $links, $file ) {
			if ( WPNAKAMA_BASENAME === $file ) {
				foreach ( $this->plugin_links as $plugin_links => $plugin_link ) {
					$links[] = '<a href="' . esc_url( $plugin_link['link'] ) . '">' . esc_html( $plugin_link['title'] ) . '</a>';
				}
			}
			return $links;
		}

		/**
		 * Add menu pages.
		 *
		 * @uses add_menu_page()
		 *
		 * @return bool
		 */
		public function menu_pages() {

			// Add a top-level menu page in admin panel's menu.
			add_menu_page(
				'WPNakama',
				$this->menu_title,
				'edit_theme_options',
				$this->plugin_uid,
				function () {
					return;
				},
				WPNAKAMA_PLUGIN_URL . 'admin/images/wpnakama-logo.svg',
				58
			);

			// Add sub-menu pages in the top-level menu page.
			if ( $this->plugin_pages ) {
				foreach ( $this->plugin_pages as $action => $type ) {
					$slug = $this->plugin_uid;
					if ( 'boards' !== $action ) {
						$slug .= '_' . $action;
					}
					add_submenu_page(
						$this->plugin_uid,
						$type['page_title'],
						$type['admin_menu_title'],
						'edit_theme_options',
						$slug,
						function () {
							$output = '<div class="wrapper" id="wpnakama-app-admin"></div>';
							echo wp_kses_post( $output );
						},
					);
				}
			}

			return true;
		}

		/**
		 * Admin actions that handle user interactions with the page.
		 */
		public function admin_actions() {
			// Modify plugins page.
			add_action( 'load-plugins.php', array( $this, 'modify_plugin_page' ) );
		}

		/**
		 * Add additional links under the plugin on plugins page.
		 *
		 * @uses add_filter()
		 */
		public function modify_plugin_page() {
			add_filter( 'plugin_action_links_' . WPNAKAMA_BASENAME, array( $this, 'plugin_action_links' ) );
			add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
		}

		/**
		 * Add links in the "Plugin" column on the plugins page.
		 *
		 * @uses current_user_can()
		 * @uses menu_page_url()
		 *
		 * @param array $links List of links to print in the "Plugin" column on the Plugins page.
		 *
		 * @return array
		 */
		public function plugin_action_links( array $links ) {

			if ( current_user_can( 'edit_theme_options' ) ) {
				$links[] = '<a href="' . admin_url( 'admin.php?page=' . $this->plugin_uid ) . '">' . __( 'WPNakama plugin page', 'wpnakama' ) . '</a>';
			}
			return $links;
		}

		/**
		 * Enqueue admin styles and scripts.
		 *
		 * @uses wp_enqueue_script()
		 * @uses wp_register_script()
		 * @uses wp_localize_script()
		 *
		 * @param string $hook_suffix The current admin page.
		 *
		 * @return bool
		 */
		public function enqueue_styles_scripts( $hook_suffix ) {
			// Adds a update indicator on admin menu in plugin title.
			wp_register_script(
				$this->plugin_uid . '_common',
				WPNAKAMA_PLUGIN_URL . 'admin/common/indicator.js',
				array( 'wp-api' ),
				// $this->version,
				time(), // To avoid cache.
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);
			wp_enqueue_script( $this->plugin_uid . '_common' );

			// Check for the plugin version.
			$pages = array(
				'toplevel_page_' . $this->plugin_uid,
			);
			foreach ( $this->plugin_pages as $action => $type ) {
				if ( 'boards' !== $action ) {
					$pages[] = str_replace( ' ', '-', strtolower( $this->menu_title ) ) . '_page_' . $this->plugin_uid . '_' . $action;
				}
			}
			if ( ! in_array( $hook_suffix, $pages, true ) ) {
				return false;
			}

			// Automatically load imported dependencies and assets version.
			$admin_app_files = include WPNAKAMA_PLUGIN_PATH . 'admin/app/build/index.asset.php';

			// Enqueue dependencies.
			foreach ( $admin_app_files['dependencies'] as $style ) {
				wp_enqueue_style( $style );
			}

			wp_register_style(
				$this->plugin_uid . '_app',
				WPNAKAMA_PLUGIN_URL . 'admin/app/build/index.css',
				array(),
				// $admin_app_files['version'],
				time(), // To avoid cache.
				'all'
			);
			wp_enqueue_style( $this->plugin_uid . '_app' );

			wp_register_script(
				$this->plugin_uid . '_app',
				WPNAKAMA_PLUGIN_URL . 'admin/app/build/index.js',
				$admin_app_files['dependencies'],
				// $admin_app_files['version'],
				time(), // To avoid cache.
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);
			wp_enqueue_script( $this->plugin_uid . '_app' );

			// Settings the nonce for API middleware.
			wp_localize_script(
				$this->plugin_uid . '_app',
				'wpNakamaApiAuth',
				array(
					'root'    => esc_url_raw( rest_url() ),
					'siteurl' => get_option( 'siteurl' ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
				)
			);
			return true;
		}

		/**
		 * Enqueue block editor styles and scripts.
		 *
		 * @uses wp_enqueue_script()
		 * @uses wp_register_script()
		 * @uses wp_localize_script()
		 *
		 * @return bool
		 */
		public function enqueue_block_editor_styles_scripts() {

			if ( function_exists( 'get_current_screen' ) ) {
				// Check for the current screen is post editing only not page.
				$current_screen = get_current_screen();
				if ( $current_screen->base !== 'post' && $current_screen->post_type !== 'post' ) {
					return;
				}
			}

			// Automatically load imported dependencies and assets version.
			$block_editor_files = include WPNAKAMA_PLUGIN_PATH . 'admin/block-editor/build/index.asset.php';

			// Enqueue dependencies.
			foreach ( $block_editor_files['dependencies'] as $style ) {
				wp_enqueue_style( $style );
			}

			wp_register_style(
				$this->plugin_uid . '_block_editor',
				WPNAKAMA_PLUGIN_URL . 'admin/block-editor/build/index.css',
				array(),
				// $block_editor_files['version'],
				time(), // To avoid cache.
				'all'
			);
			wp_enqueue_style( $this->plugin_uid . '_block_editor' );

			wp_register_script(
				$this->plugin_uid . '_block_editor',
				WPNAKAMA_PLUGIN_URL . 'admin/block-editor/build/index.js',
				$block_editor_files['dependencies'],
				// $block_editor_files['version'],
				time(), // To avoid cache.
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);
			wp_enqueue_script( $this->plugin_uid . '_block_editor' );
			return true;
		}

		/**
		 * Register WPNakama post meta fields.
		 *
		 * @uses register_post_meta()
		 *
		 * @return bool
		 */
		public function register_post_meta_fields() {
			$res = register_post_meta(
				'post',
				'wpnakama_tasks',
				array(
					'single'       => true,
					'type'         => 'array',
					'show_in_rest' => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'number',
							),
						),
					),
				)
			);
			return $res;
		}
	}
}
