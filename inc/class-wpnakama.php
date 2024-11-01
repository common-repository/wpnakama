<?php
/**
 * Core plugin class.
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

if ( ! class_exists( 'WPNakama' ) ) {
	/**
	 * A plugin's core class responsible for assigning unique identifier,
	 * plugin's version and handle dependencies for API and databases.
	 */
	class WPNakama {

		/**
		 * Plugin helper functions for sanitization, escaping etc.
		 */
		use WPNakama_Helper;

		/**
		 * API schema helper functions.
		 */
		use WPNakama_API_Schema;

		/**
		 * Plugin unique identifier.
		 *
		 * @var string
		 */
		protected $plugin_uid;

		/**
		 * Plugin version.
		 *
		 * @var string
		 */
		protected $version;

		/**
		 * Plugin API.
		 *
		 * @var object
		 */
		protected $api;

		/**
		 * Plugin database.
		 *
		 * @var WPDB
		 */
		protected $db;

		/**
		 * License server API URL.
		 *
		 * @var string
		 */
		protected $license_server_api_url;

		/**
		 * Run when class instantiated.
		 *
		 * @uses defined()
		 */
		public function __construct() {

			// Set plugin uniquie identifier.
			$this->plugin_uid = 'wpnakama';

			// Set plugin version.
			if ( defined( 'WPNAKAMA_VERSION' ) ) {
				// Plugin version defined in plugin header.
				$this->version = WPNAKAMA_VERSION;
			} else {
				$this->version = '1.0.0';
			}

			// Set license server API URL.
			$this->license_server_api_url = 'https://updater.qdonow.com/wp-json/lsq/v1';

			// Load plugin dependencies.
			$this->load_dependencies();
		}

		/**
		 * Load plugin dependencies.
		 */
		public function load_dependencies() {
			require_once WPNAKAMA_PLUGIN_PATH . 'inc/class-wpnakama-cache.php';
			require_once WPNAKAMA_PLUGIN_PATH . 'inc/class-wpnakama-updater.php';
			require_once WPNAKAMA_PLUGIN_PATH . 'inc/class-wpnakama-api.php';
			require_once WPNAKAMA_PLUGIN_PATH . 'inc/class-wpnakama-database.php';
			require_once WPNAKAMA_PLUGIN_PATH . 'inc/class-wpnakama-cpt.php';
			require_once WPNAKAMA_PLUGIN_PATH . 'admin/class-wpnakama-admin.php';
			require_once WPNAKAMA_PLUGIN_PATH . 'frontend/class-wpnakama-frontend.php';
		}

		/**
		 * Define API.
		 *
		 * It'll create and assign different routes and
		 * endpoints in the API.
		 *
		 * @uses add_action()
		 * @uses WPNakama_API
		 * @uses WPNakama_API::create_rest_route
		 * @uses WPNakama_API::create_rest_route_pathVar
		 * @uses WPNakama_API::get_records
		 * @uses WPNakama_API::get_record
		 * @uses WPNakama_API::add_record
		 * @uses WPNakama_API::delete_record
		 *
		 * @return bool
		 */
		public function define_api() {
			if ( ! class_exists( 'WPNakama_API' ) ) {
				return false;
			}

			// Instantiate the api class with namespace.
			$this->api = new WPNakama_API( 'WPNakama' );

			// Create route.
			add_action(
				'rest_api_init',
				function () {
					// Endpoint - Get all the boards.
					$this->api->create_rest_route(
						'boards',
						WP_REST_Server::READABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_boards' );

							return $this->api->get_records( $req, $table_name );
						},
						$this->api->get_records_permissions_check()
					);
					// Endpoint - Get a board by board_id.
					$this->api->create_rest_route_pathVar(
						'boards',
						WP_REST_Server::READABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name = 'board_id';

							// Check the record identifier is an integer.
							$id = (int) $req[ $field_name ];
							if ( 0 === $id ) {
								return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_boards' );

							$res = $this->api->get_record( $req, $table_name, $field_name );

							// Get board access.
							if ( ! is_wp_error( $res ) ) {
								$table_name2 = $this->db->table_prefix( 'wpnakama_boards_access' );

								$res2 = $this->api->get_record( $req, $table_name2, 'board_id' );

								if ( array_key_exists( 'access_value', $res2->data ) ) {
									$access_data = $this->escape_value( $res2->data['access_value'], 'array' );

									$res->data['is_global']  = $access_data['global'];
									$res->data['global_id']  = $access_data['global_id'];
									$res->data['public_url'] = $this->is_pretty_permalinks() ? home_url( "/wpn/boards/" . get_post_field( "post_name", $access_data['global_id'] ) ) : get_permalink( $access_data['global_id'] );
								}

								return rest_filter_response_fields( $res, new WP_REST_Server(), $req );
							} else {
								return $res;
							}
						},
						$this->api->get_record_permissions_check(),
						'board_id'
					);
					// Endpoint - Add a new board.
					$this->api->create_rest_route(
						'boards',
						WP_REST_Server::CREATABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							// Sanitize data.
							$data = array(
								'title'        => $this->sanitize_value( $req['title'] ?? '', 'title' ),
								'description'  => $this->sanitize_value( $req['description'] ?? '', 'textarea' ),
								'start_date'   => $this->sanitize_value( $req['start_date'] ?? '0000-00-00 00:00:00', 'date' ),
								'end_date'     => $this->sanitize_value( $req['end_date'] ?? '0000-00-00 00:00:00', 'date' ),
								'workspace_id' => 0,
							);

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_boards' );

							// Get the response from the boards table.
							$res = $this->api->add_record( $data, $table_name, 'board_id' );

							// Add a default boards access in access table.
							if ( ! is_wp_error( $res ) ) {
								$global_id             = wp_insert_post(
									array(
										'post_title'  => $data['title'],
										'post_status' => 'private',
										'post_type'   => 'wpn_boards',
										'post_date'   => date( 'Y-m-d h:i:s', strtotime( '+100 years' ) ),
										'post_name'   => substr( base64_encode( $data['title'] . random_bytes( 2 ) ), 0, 8 ),
									)
								);
								$table_name2           = $this->db->table_prefix( 'wpnakama_boards_access' );
								$data2                 = array();
								$data2['board_id']     = $this->sanitize_value( $res->data['data']['board_id'], 'number' );
								$data2['access_value'] = $this->sanitize_value(
									array(
										'global'    => false,
										'global_id' => $global_id,
									),
									'array'
								);

								add_post_meta( $global_id, 'wpnakama_board_id', $data2['board_id'], true );

								$this->api->add_record( $data2, $table_name2 );
							}

							return $res;
						},
						function () {
							return $this->api->create_record_permissions_check();
						},
						array(),
						$this->boards_schema()
					);
					// Enpoint - Edit a board by board_id.
					$this->api->create_rest_route_pathVar(
						'boards',
						WP_REST_Server::EDITABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name = 'board_id';

							// Check the record identifier is an integer.
							$id = (int) $req[ $field_name ];
							if ( 0 === $id ) {
								return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
							}

							// Data that need to added in database.
							$data = array(
								$field_name => $req[ $field_name ],
							);

							// Sanitize data.
							if ( isset( $req['title'] ) ) {
								$data['title'] = $this->sanitize_value( $req['title'], 'text' );
							}
							if ( isset( $req['description'] ) ) {
								$data['description'] = $this->sanitize_value( $req['description'], 'textarea' );
							}
							if ( isset( $req['start_date'] ) ) {
								$data['start_date'] = $this->sanitize_value( $req['start_date'], 'text' );
							}
							if ( isset( $req['end_date'] ) ) {
								$data['end_date'] = $this->sanitize_value( $req['end_date'], 'text' );
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_boards' );

							$res = $this->api->update_record( $data, $table_name, $field_name );

							// update board access.
							if ( ! is_wp_error( $res ) && isset( $req['is_global'] ) ) {
								// Get access table.
								$table_name2      = $this->db->table_prefix( 'wpnakama_boards_access' );
								$get_access_table = $this->api->get_record( $req, $table_name2, $field_name );
								$access_data      = $this->escape_value( $get_access_table->data['access_value'], 'array' );

								// Update the CPT status.
								if ( $req['is_global'] ) {
									wp_publish_post( $access_data['global_id'] );
								} else {
									wp_update_post(
										array(
											'ID'          => $access_data['global_id'],
											'post_status' => 'private',
											'post_date'   => date( 'Y-m-d h:i:s' ),
										)
									);
								}

								$data2                 = array();
								$data2['access_value'] = $this->sanitize_value(
									array(
										'global'    => $req['is_global'],
										'global_id' => $access_data['global_id'],
									),
									'array'
								);
								$data2['board_id']     = $req['board_id'];

								$res2 = $this->api->update_record( $data2, $table_name2, $field_name );

								return rest_filter_response_fields( $res, new WP_REST_Server(), $req );
							} else {
								return $res;
							}
						},
						function () {
							return $this->api->update_record_permissions_check();
						},
						'board_id'
					);
					// Enpoint - Delete a board by board_id.
					$this->api->create_rest_route_pathVar(
						'boards',
						WP_REST_Server::DELETABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name = 'board_id';

							// Check the record identifier is an integer.
							$id = (int) $req[ $field_name ];
							if ( 0 === $id ) {
								return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_boards' );

							// Get the response from the boards table.
							$res = $this->api->delete_record( $req, $table_name, $field_name );

							// Delete the board access from the access table and CPT.
							if ( ! is_wp_error( $res ) ) {
								$table_name2      = $this->db->table_prefix( 'wpnakama_boards_access' );
								$get_access_table = $this->api->get_record( $req, $table_name2, $field_name );
								$access_data      = $this->escape_value( $get_access_table->data['access_value'], 'array' );
								$delete_cpt       = wp_delete_post( $access_data['global_id'], true );
								delete_post_meta( $access_data['global_id'], 'wpnakama_board_id' );
								return $this->api->delete_record( $req, $table_name2, $field_name );
							} else {
								return;
							}
						},
						function () {
							return $this->api->delete_record_permissions_check();
						},
						'board_id'
					);
					// Endpoint - Create a board table by board_id.
					$this->api->create_rest_route_pathVar(
						'boardTable',
						WP_REST_Server::CREATABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name = 'board_id';

							// Check table exists in database.
							if ( $this->db->table_exists( $this->db->table_prefix( 'wpnakama_board_' . $req[ $field_name ] . '_cards' ) ) ) {
								return new WP_REST_Response(
									array(
										'status'  => 403,
										'message' => 'Table already exists. It\'s not possible to recreate the table.',
									)
								);
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_board_' . $req[ $field_name ] . '_cards' );

							// From version 0.3.0, the description field is replaced with notes from the table
							$result = $this->db->create_table(
								$table_name,
								'CREATE TABLE ' . $table_name . ' (
								card_id BIGINT(20) NOT NULL AUTO_INCREMENT,
								title TEXT,
								notes LONGTEXT,
								card_date datetime NOT NULL DEFAULT "0000-00-00 00:00:00",
								card_date_gmt datetime NOT NULL DEFAULT "0000-00-00 00:00:00",
								card_modify_date datetime NOT NULL DEFAULT "0000-00-00 00:00:00",
								card_deadline_date datetime NOT NULL DEFAULT "0000-00-00 00:00:00",
								phase_id BIGINT(20) NOT NULL DEFAULT 0,
								post_ids LONGTEXT,
								position BIGINT(20) NOT NULL DEFAULT 0,
								is_completed SMALLINT NOT NULL DEFAULT "0",
								PRIMARY KEY (card_id),
								KEY phase_id (phase_id),
								KEY card_date_gmt (card_date_gmt),
								KEY position (position),
								KEY is_completed (is_completed)
                                )'
							);

							if ( ! $result ) {
								return new WP_Error( 500, $this->db->last_error, $result );
							}

							return new WP_REST_Response(
								array(
									'status'  => 200,
									'message' => 'Successfully, created the board.',
								)
							);
						},
						function () {
							return $this->api->create_record_permissions_check();
						},
						'board_id'
					);
					// Enpoint - Delete a board table by board_id.
					$this->api->create_rest_route_pathVar(
						'boardTable',
						WP_REST_Server::DELETABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name = 'board_id';

							// Check the record identifier is an integer.
							$id = (int) $req[ $field_name ];
							if ( 0 === $id ) {
								return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
							}

							return $this->db->delete_table( 'wpnakama_board_' . $req[ $field_name ] . '_cards' );
						},
						function () {
							return $this->api->delete_record_permissions_check();
						},
						'board_id'
					);
					// Endpoint - Get all the phases by general mean or field.
					$this->api->create_rest_route(
						'phases',
						WP_REST_Server::READABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name = 'board_id';

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_phases' );

							if ( isset( $req[ $field_name ] ) ) {
								// Check the record identifier is an integer.
								$id = (int) $req[ $field_name ];
								if ( 0 === $id ) {
									return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
								}

								if ( isset( $req['order_by'] ) ) {
									$order_by = (string) $req['order_by'];
									if ( empty( $order_by ) ) {
										return new WP_Error( 403, __( 'Invaild order field passed.', 'wpnakama' ) );
									}
								} else {
									$order_by = false;
								}

								if ( isset( $req['order'] ) ) {
									$order = (string) $req['order'];
									if ( empty( $order ) ) {
										return new WP_Error( 403, __( 'Invaild order passed.', 'wpnakama' ) );
									}
								} else {
									$order = 'asc';
								}

								return $this->api->get_records( $req, $table_name, $field_name, $order_by, $order );
							} else {
								return $this->api->get_records( $req, $table_name );
							}
						},
						$this->api->get_records_permissions_check(),
						array(
							'board_id' => array(
								'require'           => false,
								'description'       => __( 'Argument use to filter the records by board id.', 'wpnakama' ),
								'type'              => 'number',
								'validate_callback' => function ( $value, $req, $param ) {
									// Check the record identifier is an integer.
									$id = (int) $value;
									if ( 0 === $id ) {
										return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
									}
								},
								'sanitize_callback' => function ( $value, $req, $param ) {
									return $this->sanitize_value( $value, 'number' );
								},
							),
						)
					);
					// Endpoint - Get a phase by phase_id.
					$this->api->create_rest_route_pathVar(
						'phases',
						WP_REST_Server::READABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name = 'phase_id';

							// Check the record identifier is an integer.
							$id = (int) $req[ $field_name ];
							if ( 0 === $id ) {
								return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_phases' );

							return $this->api->get_record( $req, $table_name, $field_name );
						},
						$this->api->get_record_permissions_check(),
						'phase_id'
					);
					// Endpoint - Add a new phase.
					$this->api->create_rest_route(
						'phases',
						WP_REST_Server::CREATABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							// Sanitize data.
							$data = array(
								'title'    => $this->sanitize_value( $req['title'], 'title' ),
								'board_id' => $this->sanitize_value( $req['board_id'], 'number' ),
								'position' => $this->sanitize_value( $req['position'], 'number' ),
							);

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_phases' );

							return $this->api->add_record( $data, $table_name, 'phase_id' );
						},
						function () {
							return $this->api->create_record_permissions_check();
						},
					);
					// Enpoint - Delete phases by board_id.
					$this->api->create_rest_route(
						'phases',
						WP_REST_Server::DELETABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name = 'board_id';

							// Check the record identifier is an integer.
							$id = (int) $req[ $field_name ];
							if ( 0 === $id ) {
								return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_phases' );

							return $this->api->delete_record( $req, $table_name, $field_name );
						},
						function () {
							return $this->api->delete_record_permissions_check();
						},
						array(
							'board_id' => array(
								'require'           => true,
								'description'       => __( 'Argument use to filter the records by board id.', 'wpnakama' ),
								'type'              => 'number',
								'validate_callback' => function ( $value, $req, $param ) {
									// Check the record identifier is an integer.
									$id = (int) $value;
									if ( 0 === $id ) {
										return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
									}
								},
								'sanitize_callback' => function ( $value, $req, $param ) {
									return $this->sanitize_value( $value, 'number' );
								},
							),
						)
					);
					// Enpoint - Delete phase by phase_id and restrict by board_id.
					$this->api->create_rest_route_pathVar(
						'phases',
						WP_REST_Server::DELETABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name = 'phase_id';

							// Check the record identifier is an integer.
							$id = (int) $req[ $field_name ];
							if ( 0 === $id ) {
								return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_phases' );

							return $this->api->delete_record( $req, $table_name, $field_name );
						},
						function () {
							return $this->api->delete_record_permissions_check();
						},
						'phase_id',
						array(
							'board_id' => array(
								'require'           => false,
								'description'       => __( 'Argument use to filter the records by board id.', 'wpnakama' ),
								'type'              => 'number',
								'validate_callback' => function ( $value, $req, $param ) {
									// Check the record identifier is an integer.
									$id = (int) $value;
									if ( 0 === $id ) {
										return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
									}
								},
								'sanitize_callback' => function ( $value, $req, $param ) {
									return $this->sanitize_value( $value, 'number' );
								},
							),
						)
					);
					// Enpoint - Edit a phase by phase_id.
					$this->api->create_rest_route_pathVar(
						'phases',
						WP_REST_Server::EDITABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name = 'phase_id';

							// Check the record identifier is an integer.
							$id = (int) $req[ $field_name ];
							if ( 0 === $id ) {
								return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
							}

							$data = array(
								$field_name => $req[ $field_name ],
							);

							// Sanitize data.
							if ( isset( $req['title'] ) ) {
								$data['title'] = $this->sanitize_value( $req['title'], 'title' );
							}
							if ( isset( $req['position'] ) ) {
								$data['position'] = $this->sanitize_value( $req['position'], 'number' );
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_phases' );

							return $this->api->update_record( $data, $table_name, $field_name );
						},
						function () {
							return $this->api->update_record_permissions_check();
						},
						'phase_id'
					);
					// Endpoint - Get all cards with a specific board_id.
					if ( '0.2.0' < $this->db->version ) {
						$this->api->create_rest_route(
							'cards',
							WP_REST_Server::READABLE,
							function ( $req ) {

								// Check valid source using nonce validation.
								$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

								if ( isset( $req['order_by'] ) ) {
									$order_by = (string) $req['order_by'];
									if ( empty( $order_by ) ) {
										return new WP_Error( 403, __( 'Invaild order field passed.', 'wpnakama' ) );
									}
								} else {
									$order_by = false;
								}

								if ( isset( $req['order'] ) ) {
									$order = (string) $req['order'];
									if ( empty( $order ) ) {
										return new WP_Error( 403, __( 'Invaild order passed.', 'wpnakama' ) );
									}
								} else {
									$order = 'asc';
								}

								if ( $req['board_id'] ) {
									$field_name = 'board_id';

									// Check the record identifier is an integer.
									$id = (int) $req[ $field_name ];
									if ( 0 === $id ) {
										return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
									}

									// Prefix table name.
									$table_name = $this->db->table_prefix( 'wpnakama_board_' . $req[ $field_name ] . '_cards' );
								} else {

									// Prefix table name.
									$table_name = $this->db->table_prefix( 'wpnakama_cards' );
								}

								if ( $this->db->table_exists( $table_name ) ) {
									return $this->api->get_records( $req, $table_name, '', $order_by, $order );
								} else {
									return new WP_Error( 500, __( 'Table doesn\'t exists.', 'wpnakama' ) );
								}

							},
							$this->api->get_records_permissions_check(),
							array(
								'board_id' => array(
									'require'           => false,
									'description'       => __( 'Argument required to add the record by board id.', 'wpnakama' ),
									'type'              => 'number',
									'validate_callback' => function ( $value, $req, $param ) {
										// Check the record identifier is an integer.
										$id = (int) $value;
										if ( 0 === $id ) {
											return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
										}
									},
									'sanitize_callback' => function ( $value, $req, $param ) {
										return $this->sanitize_value( $value, 'number' );
									},
								),
							)
						);
					} else {
						$this->api->create_rest_route(
							'cards',
							WP_REST_Server::READABLE,
							function ( $req ) {

								// Check valid source using nonce validation.
								$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

								$field_name = 'board_id';

								// Check the record identifier is an integer.
								$id = (int) $req[ $field_name ];
								if ( 0 === $id ) {
									return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
								}

								if ( isset( $req['order_by'] ) ) {
									$order_by = (string) $req['order_by'];
									if ( empty( $order_by ) ) {
										return new WP_Error( 403, __( 'Invaild order field passed.', 'wpnakama' ) );
									}
								} else {
									$order_by = false;
								}

								if ( isset( $req['order'] ) ) {
									$order = (string) $req['order'];
									if ( empty( $order ) ) {
										return new WP_Error( 403, __( 'Invaild order passed.', 'wpnakama' ) );
									}
								} else {
									$order = 'asc';
								}

								// Prefix table name.
								$table_name = $this->db->table_prefix( 'wpnakama_board_' . $req[ $field_name ] . '_cards' );

								return $this->api->get_records( $req, $table_name, '', $order_by, $order );
							},
							$this->api->get_records_permissions_check()
						);
					}
					// Endpoint - Get all cards with a specific board_id in object form.
					$this->api->create_rest_route(
						'kanbancards',
						WP_REST_Server::READABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name = 'board_id';

							// Check the record identifier is an integer.
							$id = (int) $req[ $field_name ];
							if ( 0 === $id ) {
								return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
							}

							if ( isset( $req['order_by'] ) ) {
								$order_by = (string) $req['order_by'];
								if ( empty( $order_by ) ) {
									return new WP_Error( 403, __( 'Invaild order field passed.', 'wpnakama' ) );
								}
							} else {
								$order_by = false;
							}

							if ( isset( $req['order'] ) ) {
								$order = (string) $req['order'];
								if ( empty( $order ) ) {
									return new WP_Error( 403, __( 'Invaild order passed.', 'wpnakama' ) );
								}
							} else {
								$order = 'asc';
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_board_' . $req[ $field_name ] . '_cards' );

							return $this->api->get_records( $req, $table_name, '', $order_by, $order, OBJECT_K );
						},
						$this->api->get_records_permissions_check()
					);
					// Endpoint - Delete cards with a specific phase_id.
					$this->api->create_rest_route(
						'cards',
						WP_REST_Server::DELETABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name        = 'board_id';
							$filter_field_name = 'phase_id';

							// Check the record identifier is an integer.
							$id        = (int) $req[ $field_name ];
							$filter_id = (int) $req[ $filter_field_name ];
							if ( 0 === $id && 0 === $filter_id ) {
								return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_board_' . $req[ $field_name ] . '_cards' );

							return $this->api->delete_record( $req, $table_name, $filter_field_name );
						},
						function () {
							return $this->api->delete_record_permissions_check();
						},
						array(
							'phase_id' => array(
								'require'           => true,
								'description'       => __( 'Argument use to filter the records by board id.', 'wpnakama' ),
								'type'              => 'number',
								'validate_callback' => function ( $value, $req, $param ) {
									// Check the record identifier is an integer.
									$id = (int) $value;
									if ( 0 === $id ) {
										return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
									}
								},
								'sanitize_callback' => function ( $value, $req, $param ) {
									return $this->sanitize_value( $value, 'number' );
								},
							),
						)
					);
					// Endpoint - Get a card by card_id.
					$this->api->create_rest_route_pathVar(
						'cards',
						WP_REST_Server::READABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name = 'card_id';

							// Check the record identifier is an integer.
							$id = (int) $req[ $field_name ];
							if ( 0 === $id ) {
								return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
							}

							if ( '0.2.0' < $this->db->version && ( ! isset( $req['board_id'] ) || empty( $req['board_id'] ) ) ) {
								// Prefix table name.
								$table_name2 = $this->db->table_prefix( 'wpnakama_cards' );
								return $this->api->get_record(
									$req,
									$table_name2,
									array(
										'id' => $req['card_id'],
									)
								);
							} else {
								// Prefix table name.
								$table_name = $this->db->table_prefix( 'wpnakama_board_' . $req['board_id'] . '_cards' );
								return $this->api->get_record( $req, $table_name, $field_name );

							}
						},
						$this->api->get_record_permissions_check(),
						'card_id'
					);
					// Endpoint - Add a card with a specific board_id.
					$this->api->create_rest_route(
						'cards',
						WP_REST_Server::CREATABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							// Sanitize data.
							$data = array();

							if ( isset( $req['title'] ) && ! empty( $req['title'] ) ) {
								$data['title'] = $this->sanitize_value( $req['title'], 'title' );
							}
							if ( isset( $req['notes'] ) && ! empty( $req['notes'] ) ) {
								$data['notes'] = $this->sanitize_value( $req['notes'], 'textarea' );
							}
							if ( isset( $req['phase_id'] ) && ! empty( $req['phase_id'] ) ) {
								$data['phase_id'] = $this->sanitize_value( $req['phase_id'], 'number' );
							}
							if ( isset( $req['post_ids'] ) && ! empty( $req['post_ids'] ) ) {
								$data['post_ids'] = $this->sanitize_value( $req['post_ids'], 'text' );
							}
							if ( isset( $req['position'] ) && ! empty( $req['position'] ) ) {
								$data['position'] = $this->sanitize_value( $req['position'], 'number' );
							}
							if ( isset( $req['card_deadline_date'] ) && ! empty( $req['card_deadline_date'] ) ) {
								$data['card_deadline_date'] = $this->sanitize_value( $req['card_deadline_date'] ?? '0000-00-00 00:00:00', 'date' );
							}
							if ( isset( $req['is_completed'] ) && ! empty( $req['is_completed'] ) ) {
								$data['is_completed'] = $this->sanitize_value( $req['is_completed'], 'number' );
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_board_' . $req['board_id'] . '_cards' );

							$res = $this->api->add_record( $data, $table_name, 'card_id' );

							if ( '0.2.0' < $this->db->version ) {
								$table_name2                   = $this->db->table_prefix( 'wpnakama_cards' );
								$data2                         = array();
								$data2['card_id']              = $this->sanitize_value( $res->data['data']['card_id'], 'number' );
								$data2['board_id']             = $this->sanitize_value( $req['board_id'], 'number' );
								$res2                          = $this->api->add_record( $data2, $table_name2 );
								$res2->data['data']['card_id'] = $this->sanitize_value( $res->data['data']['card_id'], 'number' );
								return $res2;
							} else {
								return $res;
							}

							return $res;
						},
						function () {
							return $this->api->create_record_permissions_check();
						},
						array(
							'board_id' => array(
								'require'           => true,
								'description'       => __( 'Argument required to add the record by board id.', 'wpnakama' ),
								'type'              => 'number',
								'validate_callback' => function ( $value, $req, $param ) {
									// Check the record identifier is an integer.
									$id = (int) $value;
									if ( 0 === $id ) {
										return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
									}
								},
								'sanitize_callback' => function ( $value, $req, $param ) {
									return $this->sanitize_value( $value, 'number' );
								},
							),
						)
					);
					// Endpoint - Delete card by card_id with a specific board_id.
					$this->api->create_rest_route_pathVar(
						'cards',
						WP_REST_Server::DELETABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name = 'card_id';

							// Check the record identifier is an integer.
							$id = (int) $req[ $field_name ];
							if ( 0 === $id ) {
								return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_board_' . $req['board_id'] . '_cards' );

							$res = $this->api->delete_record( $req, $table_name, $field_name );

							if ( '0.2.0' < $this->db->version ) {
								$table_name2 = $this->db->table_prefix( 'wpnakama_cards' );
								return $this->api->delete_record(
									$req,
									$table_name2,
									array(
										'card_id'  => $req[ $field_name ],
										'board_id' => $req['board_id'],
									)
								);
							} else {
								return $res;
							}
						},
						function () {
							return $this->api->delete_record_permissions_check();
						},
						'card_id',
						array(
							'board_id' => array(
								'require'           => true,
								'description'       => __( 'Argument use to delete the record by board id.', 'wpnakama' ),
								'type'              => 'number',
								'validate_callback' => function ( $value, $req, $param ) {
									// Check the record identifier is an integer.
									$id = (int) $value;
									if ( 0 === $id ) {
										return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
									}
								},
								'sanitize_callback' => function ( $value, $req, $param ) {
									return $this->sanitize_value( $value, 'number' );
								},
							),
						)
					);
					// Endpoint - Delete card by card_id with a specific board_id.
					$this->api->create_rest_route(
						'uniquecards',
						WP_REST_Server::DELETABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name = 'board_id';

							// Check the record identifier is an integer.
							$id = (int) $req[ $field_name ];

							if ( 0 === $id ) {
								return new WP_Error( 403, __( 'Invaild value passed. A valid board_id required.', 'wpnakama' ) );
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_cards' );

							return $this->api->delete_record(
								$req,
								$table_name,
								array(
									'board_id' => $req[ $field_name ],
								)
							);
						},
						function () {
							return $this->api->delete_record_permissions_check();
						},
						array(
							'board_id' => array(
								'require'           => true,
								'description'       => __( 'Argument use to delete the record by board id.', 'wpnakama' ),
								'type'              => 'number',
								'validate_callback' => function ( $value, $req, $param ) {
									// Check the record identifier is an integer.
									$id = (int) $value;
									if ( 0 === $id ) {
										return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
									}
								},
								'sanitize_callback' => function ( $value, $req, $param ) {
									return $this->sanitize_value( $value, 'number' );
								},
							),
						)
					);
					// Enpoint - Edit a card by card_id with a specific board_id..
					$this->api->create_rest_route_pathVar(
						'cards',
						WP_REST_Server::EDITABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name = 'card_id';

							// Check the record identifier is an integer.
							$id = (int) $req[ $field_name ];
							if ( 0 === $id ) {
								return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
							}

							$data = array(
								$field_name => $req[ $field_name ],
							);

							// Sanitize data.
							if ( isset( $req['title'] ) ) {
								$data['title'] = $this->sanitize_value( $req['title'], 'title' );
							}
							if ( isset( $req['notes'] ) ) {
								$data['notes'] = $this->sanitize_value( $req['notes'], 'textarea' );
							}
							if ( isset( $req['position'] ) ) {
								$data['position'] = $this->sanitize_value( $req['position'], 'number' );
							}
							if ( isset( $req['phase_id'] ) ) {
								$data['phase_id'] = $this->sanitize_value( $req['phase_id'], 'number' );
							}
							if ( isset( $req['post_ids'] ) ) {
								$data['post_ids'] = $this->sanitize_value( $req['post_ids'], 'text' );
							}
							if ( isset( $req['card_deadline_date'] ) ) {
								$data['card_deadline_date'] = $this->sanitize_value( $req['card_deadline_date'] ?? '0000-00-00 00:00:00', 'date' );
							}
							if ( isset( $req['is_completed'] ) ) {
								$data['is_completed'] = $this->sanitize_value( $req['is_completed'], 'number' );
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_board_' . $req['board_id'] . '_cards' );

							return $this->api->update_record( $data, $table_name, $field_name );
						},
						function () {
							return $this->api->update_record_permissions_check();
						},
						'card_id',
						array(
							'board_id' => array(
								'require'           => true,
								'description'       => __( 'Argument use to delete the record by board id.', 'wpnakama' ),
								'type'              => 'number',
								'validate_callback' => function ( $value, $req, $param ) {
									// Check the record identifier is an integer.
									$id = (int) $value;
									if ( 0 === $id ) {
										return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
									}
								},
								'sanitize_callback' => function ( $value, $req, $param ) {
									return $this->sanitize_value( $value, 'number' );
								},
							),
						)
					);
					// Endpoint - Add task with specific board_id and card_id.
					$this->api->create_rest_route(
						'tasks',
						WP_REST_Server::CREATABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							// Sanitize data.
							$data = array();

							if ( isset( $req['content'] ) && ! empty( $req['content'] ) ) {
								$data['content'] = $this->sanitize_value( $req['content'], 'textarea' );
							}
							if ( isset( $req['board_id'] ) && ! empty( $req['board_id'] ) ) {
								$data['board_id'] = $this->sanitize_value( $req['board_id'], 'number' );
							}
							if ( isset( $req['card_id'] ) && ! empty( $req['card_id'] ) ) {
								$data['card_id'] = $this->sanitize_value( $req['card_id'], 'number' );
							}
							if ( isset( $req['user_id'] ) && ! empty( $req['user_id'] ) ) {
								$data['user_id'] = $this->sanitize_value( $req['user_id'], 'number' );
							}
							if ( isset( $req['position'] ) && ! empty( $req['position'] ) ) {
								$data['position'] = $this->sanitize_value( $req['position'], 'number' );
							}
							if ( isset( $req['is_completed'] ) && ! empty( $req['is_completed'] ) ) {
								$data['is_completed'] = $this->sanitize_value( $req['is_completed'], 'number' );
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_tasks' );

							return $this->api->add_record( $data, $table_name, 'task_id' );
						},
						function () {
							return $this->api->create_record_permissions_check();
						},
						array(
							'board_id' => array(
								'require'           => true,
								'description'       => __( 'Argument use to filter the records by board id.', 'wpnakama' ),
								'type'              => 'number',
								'validate_callback' => function ( $value, $req, $param ) {
									// Check the record identifier is an integer.
									$id = (int) $value;
									if ( 0 === $id ) {
										return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
									}
								},
								'sanitize_callback' => function ( $value, $req, $param ) {
									return $this->sanitize_value( $value, 'number' );
								},
							),
							'card_id'  => array(
								'require'           => true,
								'description'       => __( 'Argument use to filter the records by card id.', 'wpnakama' ),
								'type'              => 'number',
								'validate_callback' => function ( $value, $req, $param ) {
									// Check the record identifier is an integer.
									$id = (int) $value;
									if ( 0 === $id ) {
										return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
									}
								},
								'sanitize_callback' => function ( $value, $req, $param ) {
									return $this->sanitize_value( $value, 'number' );
								},
							),
							'user_id'  => array(
								'require'           => true,
								'description'       => __( 'Argument use to filter the records by user id.', 'wpnakama' ),
								'type'              => 'number',
								'validate_callback' => function ( $value, $req, $param ) {
									// Check the record identifier is an integer.
									$id = (int) $value;
									if ( 0 === $id ) {
										return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
									}
								},
								'sanitize_callback' => function ( $value, $req, $param ) {
									return $this->sanitize_value( $value, 'number' );
								},
							),
						)
					);
					// Endpoint - Edit task by task_id.
					$this->api->create_rest_route_pathVar(
						'tasks',
						WP_REST_Server::EDITABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name = 'task_id';

							// Check the record identifier is an integer.
							$id = (int) $req[ $field_name ];
							if ( 0 === $id ) {
								return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
							}

							$data = array(
								$field_name => $req[ $field_name ],
							);

							// Sanitize data.
							if ( isset( $req['content'] ) ) {
								$data['content'] = $this->sanitize_value( $req['content'], 'textarea' );
							}
							if ( isset( $req['board_id'] ) ) {
								$data['board_id'] = $this->sanitize_value( $req['board_id'], 'number' );
							}
							if ( isset( $req['card_id'] ) ) {
								$data['card_id'] = $this->sanitize_value( $req['card_id'], 'number' );
							}
							if ( isset( $req['user_id'] ) ) {
								$data['user_id'] = $this->sanitize_value( $req['user_id'], 'number' );
							}
							if ( isset( $req['position'] ) ) {
								$data['position'] = $this->sanitize_value( $req['position'], 'number' );
							}
							if ( isset( $req['is_completed'] ) ) {
								$data['is_completed'] = $this->sanitize_value( $req['is_completed'], 'number' );
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_tasks' );

							return $this->api->update_record( $data, $table_name, $field_name );
						},
						function () {
							return $this->api->update_record_permissions_check();
						},
						'task_id'
					);
					// Endpoint - Delete task by task_id.
					$this->api->create_rest_route_pathVar(
						'tasks',
						WP_REST_Server::DELETABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name = 'task_id';

							// Check the record identifier is an integer.
							$id = (int) $req[ $field_name ];
							if ( 0 === $id ) {
								return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_tasks' );

							return $this->api->delete_record( $req, $table_name, $field_name );
						},
						function () {
							return $this->api->delete_record_permissions_check();
						},
						'task_id'
					);
					// Endpoint - Get a task with specific board_id and card_id.
					$this->api->create_rest_route_pathVar(
						'tasks',
						WP_REST_Server::READABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name = 'task_id';

							// Check the record identifier is an integer.
							$id = (int) $req[ $field_name ];
							if ( 0 === $id ) {
								return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_tasks' );

							return $this->api->get_record( $req, $table_name, $field_name );
						},
						$this->api->get_record_permissions_check(),
						'task_id'
					);
					// Endpoint - Get all tasks by required board_id and card_id.
					$this->api->create_rest_route(
						'tasks',
						WP_REST_Server::READABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							if ( isset( $req['board_id'] ) && isset( $req['card_id'] ) && isset( $req['user_id'] ) ) {
								$field_name = array( 'board_id', 'card_id', 'user_id' );
							} elseif ( isset( $req['board_id'] ) && isset( $req['card_id'] ) ) {
								$field_name = array( 'board_id', 'card_id' );
							} elseif ( isset( $req['board_id'] ) ) {
								$field_name = 'board_id';
							} elseif ( isset( $req['card_id'] ) ) {
								$field_name = 'card_id';
							} elseif ( isset( $req['user_id'] ) ) {
								$field_name = 'user_id';
							} else {
								$field_name = '';
							}

							if ( isset( $req['order_by'] ) ) {
								$order_by = (string) $req['order_by'];
								if ( empty( $order_by ) ) {
									return new WP_Error( 403, __( 'Invaild order field passed.', 'wpnakama' ) );
								}
							} else {
								$order_by = false;
							}

							if ( isset( $req['order'] ) ) {
								$order = (string) $req['order'];
								if ( empty( $order ) ) {
									return new WP_Error( 403, __( 'Invaild order passed.', 'wpnakama' ) );
								}
							} else {
								$order = 'asc';
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_tasks' );

							return $this->api->get_records( $req, $table_name, $field_name, $order_by, $order );
						},
						$this->api->get_records_permissions_check(),
						array(
							'board_id' => array(
								'require'           => false,
								'description'       => __( 'Argument use to filter the records by board id.', 'wpnakama' ),
								'type'              => 'number',
								'validate_callback' => function ( $value, $req, $param ) {
									// Check the record identifier is an integer.
									$id = (int) $value;
									if ( 0 === $id ) {
										return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
									}
								},
								'sanitize_callback' => function ( $value, $req, $param ) {
									return $this->sanitize_value( $value, 'number' );
								},
							),
							'card_id'  => array(
								'require'           => false,
								'description'       => __( 'Argument use to filter the records by card id.', 'wpnakama' ),
								'type'              => 'number',
								'validate_callback' => function ( $value, $req, $param ) {
									// Check the record identifier is an integer.
									$id = (int) $value;
									if ( 0 === $id ) {
										return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
									}
								},
								'sanitize_callback' => function ( $value, $req, $param ) {
									return $this->sanitize_value( $value, 'number' );
								},
							),
							'user_id'  => array(
								'require'           => false,
								'description'       => __( 'Argument use to filter the records by card id.', 'wpnakama' ),
								'type'              => 'number',
								'validate_callback' => function ( $value, $req, $param ) {
									// Check the record identifier is an integer.
									$id = (int) $value;
									if ( 0 === $id ) {
										return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
									}
								},
								'sanitize_callback' => function ( $value, $req, $param ) {
									return $this->sanitize_value( $value, 'number' );
								},
							),
						)
					);
					// Endpoint - Add taskslist by taskslist_id.
					$this->api->create_rest_route(
						'taskslists',
						WP_REST_Server::CREATABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							// Sanitize data.
							$data = array();

							if ( isset( $req['title'] ) && ! empty( $req['title'] ) ) {
								$data['title'] = $this->sanitize_value( $req['title'], 'title' );
							}
							if ( isset( $req['tasks'] ) && ! empty( $req['tasks'] ) ) {
								$data['tasks'] = $this->sanitize_value( $req['tasks'], 'array' );
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_taskslists' );

							return $this->api->add_record( $data, $table_name, 'taskslist_id' );
						},
						function () {
							return $this->api->create_record_permissions_check();
						},
					);
					// Endpoint - Edit taskslist by taskslist_id.
					$this->api->create_rest_route_pathVar(
						'taskslists',
						WP_REST_Server::EDITABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name = 'taskslist_id';

							// Check the record identifier is an integer.
							$id = (int) $req[ $field_name ];
							if ( 0 === $id ) {
								return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
							}

							$data = array(
								$field_name => $req[ $field_name ],
							);

							// Sanitize data.
							if ( isset( $req['title'] ) ) {
								$data['title'] = $this->sanitize_value( $req['title'], 'title' );
							}
							if ( isset( $req['tasks'] ) ) {
								$data['tasks'] = $this->sanitize_value( $req['tasks'], 'array' );
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_taskslists' );

							return $this->api->update_record( $data, $table_name, $field_name );
						},
						function () {
							return $this->api->update_record_permissions_check();
						},
						'taskslist_id'
					);
					// Endpoint - Delete taskslist by taskslist_id.
					$this->api->create_rest_route_pathVar(
						'taskslists',
						WP_REST_Server::DELETABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							$field_name = 'taskslist_id';

							// Check the record identifier is an integer.
							$id = (int) $req[ $field_name ];
							if ( 0 === $id ) {
								return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
							}

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_taskslists' );

							return $this->api->delete_record( $req, $table_name, $field_name );
						},
						function () {
							return $this->api->delete_record_permissions_check();
						},
						'taskslist_id'
					);
					// Endpoint - Get all taskslists.
					$this->api->create_rest_route(
						'taskslists',
						WP_REST_Server::READABLE,
						function ( $req ) {

							// Check valid source using nonce validation.
							$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

							// Prefix table name.
							$table_name = $this->db->table_prefix( 'wpnakama_taskslists' );

							return $this->api->get_records( $req, $table_name );
						},
						$this->api->get_records_permissions_check()
					);
					if ( '0.2.0' < $this->db->version ) {
						// Endpoint - Get the option as per the option name.
						$this->api->create_rest_route(
							'options',
							WP_REST_Server::READABLE,
							function ( $req ) {

								// Check valid source using nonce validation.
								$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

								$option_name = (string) $req['option_name'];
								if ( $option_name ) {
									// Check for the option name passed a string.
									if ( ! isset( $option_name ) || empty( $option_name ) ) {
										return new WP_Error( 403, __( 'Must pass an option\'s name.', 'wpnakama' ) );
									}
									// Valid option name related to the plugin.
									$valid_options_arr = array(
										'wpnakama_update_indicator',
										'blogname',
										'wpnakama_rating',
									);
									if ( ! in_array( $option_name, $valid_options_arr ) ) {
										return new WP_Error( 403, __( 'Invaild options name passed.', 'wpnakama' ) );
									}

									$option_value = get_option( $req['option_name'] );
									if ( $option_name === 'wpnakama_rating' ) {
										$option_value = $this->escape_value( $option_value, 'array' );
									} else {
										$option_value = $this->escape_value( $option_value, 'text' );
									}
									$res = new WP_REST_Response( $option_value, 200 );
								} else {
									$res     = new WP_REST_Response(
										array(
											'wpnakama_update_indicator' => get_option( 'wpnakama_update_indicator' ),
											'using_permalinks' => $this->is_pretty_permalinks(),
											'wpnakama_rating' => $this->escape_value( get_option( 'wpnakama_rating' ), 'array' ),
										),
										200
									);
								}

								// return valid option value.
								return rest_filter_response_fields( $res, new WP_REST_Server(), $req );
							},
							$this->api->get_records_permissions_check()
						);
						// Endpoint - Update the option as per the option name.
						$this->api->create_rest_route(
							'options',
							WP_REST_Server::EDITABLE,
							function ( $req ) {

								// Check valid source using nonce validation.
								$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

								// update the valid plugin option value.
								$data = array(
									'wpnakama_update_indicator' => $this->sanitize_value( $req['wpnakama_update_indicator'], 'text' ),
									'wpnakama_rating' => $this->sanitize_value( $req['wpnakama_rating'], 'array' ),
								);

								$successfully_updated = false;
								foreach ( $data as $option_name => $option_value ) {
									$successfully_updated = update_option( $option_name, $option_value );
								}
								if ( ! $successfully_updated ) {
									return new WP_Error( 403, __( 'Something, went wrong, while update the option.', 'wpnakama' ) );
								}

								$res = new WP_REST_Response( 'Successfully updated!', 200 );
								return rest_filter_response_fields( $res, new WP_REST_Server(), $req );
							},
							function () {
								return $this->api->update_record_permissions_check();
							},
						);
						// Endpoint - Edit board access by board_id.
						$this->api->create_rest_route_pathVar(
							'boardaccess',
							WP_REST_Server::EDITABLE,
							function ( $req ) {

								// Check valid source using nonce validation.
								$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

								$field_name = 'board_id';

								// Check the record identifier is an integer.
								$id = (int) $req[ $field_name ];
								if ( 0 === $id ) {
									return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
								}

								// Sanitize data.
								$data = array();

								if ( isset( $req['access_value'] ) && ! empty( $req['access_value'] ) ) {
									$data['access_value'] = $this->sanitize_value( $req['access_value'], 'array' );
								}

								// Prefix table name.
								$table_name = $this->db->table_prefix( 'wpnakama_board_access' );

								return $this->api->update_record( $data, $table_name, $field_name );
							},
							function () {
								return $this->api->update_record_permissions_check();
							},
							'board_id'
						);
						// Endpoint - Get the license information.
						$this->api->create_rest_route(
							'license',
							WP_REST_Server::READABLE,
							function ( $req ) {

								// Check valid source using nonce validation.
								$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

								$license = get_option( 'wpnakama_license' );
								// Check the license key.
								if ( isset( $license['key'] ) ) {
									$license_message = json_decode( get_option( 'wpnakama_license_message' ) );
								} else {
									$res = new WP_REST_Response(
										array(
											'status' => 'not found',
										),
										200
									);
								}

								/**
								 * License status:
								 * inactive: The license key is valid but has no activations.
								 * active: The license key has one or more activations.
								 * expired: The license key's expiry date has passed.
								 * disabled: The license key has been manually disabled (now not supported in the app)
								 */
								if ( isset( $license_message->data->activated ) || ! empty( $license_message->data->activated ) ) {
									$res = new WP_REST_Response(
										array(
											'status' => $license_message->data->license_key->status,
											'usage'  => $license_message->data->license_key->activation_usage,
											'limit'  => $license_message->data->license_key->activation_limit,
										),
										200
									);
								} else {
									if ( isset( $license['key'] ) && isset( $this->license_server_api_url ) ) {
										$res = new WP_REST_Response(
											array(
												'status' => 'inactive',
												'usage'  => 0,
												'limit'  => 0,
											),
											200
										);
									} else {
										$res = new WP_REST_Response(
											array(
												'status' => 'not found',
												'usage'  => 0,
												'limit'  => 0,
											),
											200
										);
									}
								}

								// return valid option value.
								return rest_filter_response_fields( $res, new WP_REST_Server(), $req );
							},
							$this->api->get_records_permissions_check()
						);
						// Endpoint - Delete the license.
						$this->api->create_rest_route(
							'license',
							WP_REST_Server::DELETABLE,
							function ( $req ) {

								// Check valid source using nonce validation.
								$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

								$license = get_option( 'wpnakama_license' );
								if ( isset( $license['key'] ) ) {
									delete_option( 'wpnakama_license' );
									$res = array(
										'code'    => 200,
										'message' => 'Successfully deleted the license key!',
									);
								} else {
									return new WP_Error( 403, __( 'License key is missing.', 'wpnakama' ) );
								}

								// return valid option value.
								return rest_filter_response_fields( $res, new WP_REST_Server(), $req );
							},
							function () {
								return $this->api->delete_record_permissions_check();
							}
						);
						// Endpoint - Update/Add the license key.
						$this->api->create_rest_route(
							'license',
							WP_REST_Server::EDITABLE,
							function ( $req ) {

								// Check valid source using nonce validation.
								$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

								if ( ! isset( $req['license_key'] ) || empty( $req['license_key'] ) ) {
									return new WP_Error( 403, __( 'License key is required.', 'wpnakama' ) );
								}
								// Check the license key.
								$validate_license = $this->check_license( $req['license_key'], $this->license_server_api_url );
								if ( ! $validate_license ) {
									return new WP_Error( 403, __( 'Invalid License Key.', 'wpnakama' ) );
								}

								// Check the license key already exists.
								$license = get_option( 'wpnakama_license' );
								if ( isset( $license['key'] ) ) {
									if ( $license['key'] === $req['license_key'] ) {
										return new WP_Error( 403, __( 'License key already exists.', 'wpnakama' ) );
									}
								}

								$successfully_updated = update_option(
									'wpnakama_license',
									array(
										'key' => $this->sanitize_value( $req['license_key'], 'text' ),
									)
								);
								if ( ! $successfully_updated ) {
									return new WP_Error( 403, __( 'Something, went wrong, while update the option.', 'wpnakama' ) );
								}

								$res = array(
									'code'    => 200,
									'message' => 'Successfully added the license key!',
								);
								return rest_filter_response_fields( $res, new WP_REST_Server(), $req );
							},
							function () {
								return $this->api->update_record_permissions_check();
							}
						);
						// Endpoint - Activate license.
						$this->api->create_rest_route(
							'license/activate',
							WP_REST_Server::EDITABLE,
							function ( $req ) {

								// Check valid source using nonce validation.
								$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

								$license         = get_option( 'wpnakama_license' );
								$license_message = json_decode( get_option( 'wpnakama_license_message' ) );

								if ( isset( $license['key'] ) && isset( $this->license_server_api_url ) ) {
									if ( isset( $license_message ) ) {
										if ( $license_message->data->activated ) {
											return new WP_Error( 403, __( 'License key already activated.', 'wpnakama' ) );
										}
									}

									$activated = json_decode( $this->activate_license( $license['key'], $this->license_server_api_url ) );
									$res       = array(
										'code'    => 200,
										'message' => 'Successfully activated the license key!',
									);
								} else {
									return new WP_Error( 403, __( 'Key is missing.', 'wpnakama' ) );
								}

								// return valid option value.
								return rest_filter_response_fields( $res, new WP_REST_Server(), $req );
							},
							function () {
								return $this->api->update_record_permissions_check();
							},
						);
						// Endpoint - Deactivate license.
						$this->api->create_rest_route(
							'license/deactivate',
							WP_REST_Server::EDITABLE,
							function ( $req ) {

								// Check valid source using nonce validation.
								$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

								$license         = get_option( 'wpnakama_license' );
								$license_message = json_decode( get_option( 'wpnakama_license_message' ) );

								if ( ! $license_message ) {
									return new WP_Error( 403, __( 'License key already deactivated.', 'wpnakama' ) );
								}
								if ( isset( $license['key'] ) && isset( $license_message->data->instance->id ) && isset( $this->license_server_api_url ) ) {
									$this->deactivate_license( $license['key'], $license_message->data->instance->id, $this->license_server_api_url );
									$res = array(
										'code'    => 200,
										'message' => 'Successfully deactivated the license key!',
									);
								} else {
									if ( ! isset( $license_message->data->instance->id ) ) {
										return new WP_Error( 403, __( 'Instance id is missing.', 'wpnakama' ) );
									} else {
										return new WP_Error( 403, __( 'Key or instance id is missing.', 'wpnakama' ) );
									}
								}

								// return valid option value.
								return rest_filter_response_fields( $res, new WP_REST_Server(), $req );
							},
							function () {
								return $this->api->update_record_permissions_check();
							},
						);
						// Endpoint - Edit board access by board_id.
						$this->api->create_rest_route_pathVar(
							'boardaccess',
							WP_REST_Server::EDITABLE,
							function ( $req ) {

								// Check valid source using nonce validation.
								$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

								$field_name = 'board_id';

								// Check the record identifier is an integer.
								$id = (int) $req[ $field_name ];
								if ( 0 === $id ) {
									return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
								}

								// Sanitize data.
								$data = array();

								if ( isset( $req['access_value'] ) && ! empty( $req['access_value'] ) ) {
									$data['access_value'] = $this->sanitize_value( $req['access_value'], 'array' );
								}

								// Prefix table name.
								$table_name = $this->db->table_prefix( 'wpnakama_board_access' );

								return $this->api->update_record( $data, $table_name, $field_name );
							},
							function () {
								return $this->api->update_record_permissions_check();
							},
							'board_id'
						);
						// Endpoint - Delete board access by board_id.
						$this->api->create_rest_route_pathVar(
							'boardaccess',
							WP_REST_Server::DELETABLE,
							function ( $req ) {

								// Check valid source using nonce validation.
								$this->api->checkRestNonce( $req->get_header( 'X-WP-Nonce' ), 'wp_rest' );

								$field_name = 'board_id';

								// Check the record identifier is an integer.
								$id = (int) $req[ $field_name ];
								if ( 0 === $id ) {
									return new WP_Error( 403, __( 'Invaild value passed.', 'wpnakama' ) );
								}

								// Prefix table name.
								$table_name = $this->db->table_prefix( 'wpnakama_board_access' );

								return $this->api->delete_record( $req, $table_name, $field_name );
							},
							function () {
								return $this->api->delete_record_permissions_check();
							},
							'board_id'
						);
					}
				}
			);

			return true;
		}

		/**
		 * Define database.
		 *
		 * It'll provide custom database functions for
		 * table prefixing, creating and deleting
		 * custom tables etc.
		 *
		 * @uses WPNakama_Database
		 * @uses WPNakama_Database::table_prefix
		 * @uses WPNakama_Database::create_table
		 *
		 * @return bool
		 */
		public function define_database() {
			if ( ! class_exists( 'WPNakama_Database' ) ) {
				return false;
			}

			// Instantiate the database class.
			$this->db = new WPNakama_Database();

			// Updating database for older versions.
			add_action(
				'plugins_loaded',
				function () {
					$database_tables_arr = array();

					// Create tables as per versions.
					if ( '0.2.0' <= $this->db->version ) {
						$database_tables_arr[] = array(
							'name'  => $this->db->table_prefix( 'wpnakama_tasks' ),
							'query' => 'CREATE TABLE ' . $this->db->table_prefix( 'wpnakama_tasks' ) . ' (
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
						);
						$database_tables_arr[] = array(
							'name'  => $this->db->table_prefix( 'wpnakama_taskslists' ),
							'query' => 'CREATE TABLE ' . $this->db->table_prefix( 'wpnakama_taskslists' ) . ' (
								taskslist_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,
								title TEXT,
								tasks LONGTEXT,
								PRIMARY KEY (taskslist_id)
							)',
						);
						$database_tables_arr[] = array(
							'name'  => $this->db->table_prefix( 'wpnakama_cards' ),
							'query' => 'CREATE TABLE ' . $this->db->table_prefix( 'wpnakama_cards' ) . ' (
								id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,
								board_id BIGINT(20) NOT NULL DEFAULT 0,
								card_id BIGINT(20) NOT NULL DEFAULT 0,
								PRIMARY KEY (id),
								KEY board_id (board_id),
								KEY card_id (card_id)
							)',
						);
					}
					if ( '0.3.0' <= $this->db->version ) {
						$database_tables_arr[] = array(
							'name'  => $this->db->table_prefix( 'wpnakama_boards_access' ),
							'query' => 'CREATE TABLE ' . $this->db->table_prefix( 'wpnakama_boards_access' ) . ' (
								id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,
								board_id BIGINT(20) NOT NULL DEFAULT 0,
								access_value LONGTEXT,
								PRIMARY KEY (id),
								KEY board_id (board_id)
							)',
						);
					}

					// If multisite.
					if ( is_multisite() ) {
						$database_tables_arr[] = array(
							'name'  => $this->db->table_prefix( 'wpnakama_workspaces' ),
							'query' => 'CREATE TABLE ' . $this->db->table_prefix( 'wpnakama_workspaces' ) . ' (
								workspace_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,
								title TEXT,
								description VARCHAR(250),
								PRIMARY KEY (workspace_id)
							)',
						);
						$database_tables_arr[] = array(
							'name'  => $this->db->table_prefix( 'wpnakama_boards' ),
							'query' => 'CREATE TABLE ' . $this->db->table_prefix( 'wpnakama_boards' ) . ' (
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
						);
						$database_tables_arr[] = array(
							'name'  => $this->db->table_prefix( 'wpnakama_phases' ),
							'query' => 'CREATE TABLE ' . $this->db->table_prefix( 'wpnakama_phases' ) . ' (
								phase_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE,
								board_id BIGINT(20) NOT NULL DEFAULT 0,
								title TEXT,
								position BIGINT(20) NOT NULL DEFAULT 0,
								PRIMARY KEY (phase_id),
								KEY board_id (board_id),
								KEY position (position)
							)',
						);
					}

					foreach ( $database_tables_arr as $table ) {
						$this->db->create_table( $table['name'], $table['query'] );
					}

					// Alter table by adding or droping columns from tables.
					if ( '0.3.0' <= $this->db->version ) {
						$this->db->alter_table(
							$this->db->table_prefix( 'wpnakama_boards' ),
							'start_date',
							'datetime',
							'ADD',
							'0000-00-00 00:00:00'
						);
						$this->db->alter_table(
							$this->db->table_prefix( 'wpnakama_boards' ),
							'end_date',
							'datetime',
							'ADD',
							'0000-00-00 00:00:00'
						);
					}

					return true;
				}
			);

			return true;
		}

		/**
		 * Define custom post type.
		 *
		 * @uses WPNakama_CPT
		 *
		 * @return bool
		 */
		public function define_cpt() {
			if ( ! class_exists( 'WPNakama_CPT' ) ) {
				return false;
			}

			// CPT object.
			$cpt_cards = new WPNakama_CPT(
				array(
					'slug'          => 'wpn_boards', // Custom post type slug i.e cpt_slug.
					'plural_name'   => 'WPNakama Boards',  // Custom post type plural name i.e cpt plural name
					'singular_name' => 'WPNakama Board',  // Custom post type singular name i.e cpt singular name
					'description'   => 'Boards those are public in view.', // Custom post type description.
				)
			);

			/** This filter is documented in inc/class-wpnakama-cpt.php */
			add_filter(
				'wpn_boards_cpt_args',
				function ( $args ) {
					$custom_args             = $args;
					$custom_args['supports'] = array(
						'editor',
					);
					// Remove from admin area.
					$custom_args['show_ui']           = false;
					$custom_args['show_in_menu']      = false;
					$custom_args['show_in_nav_menus'] = false;
					$custom_args['show_in_admin_bar'] = false;
					$custom_args['has_archive']       = false;

					// Controlling the REST route i.e. /WPNakama/v1/public_boards
					$custom_args['rest_base']      = 'public_boards';
					$custom_args['rest_namespace'] = 'WPNakama/v1';
					$custom_args['pages']          = true;

					// Scrifice the default front view for custom view.
					$custom_args['public'] = false;

					// Controlling the slug.
					$custom_args['publicly_queryable'] = true;
					$custom_args['rewrite']            = array(
						'slug'       => 'wpn/boards',
						'with_front' => false,
						'ep_mask'    => EP_ROOT,
					);

					// CPT security and listing.
					$custom_args['delete_with_user']    = false;
					$custom_args['exclude_from_search'] = false;

					// Flush the previous rewrite rules.
					flush_rewrite_rules();

					// Templating for
					return $custom_args;
				}
			);

			return true;
		}

		/**
		 * Define admin functionalities.
		 *
		 * It'll add menu and submenu options in WordPress admin
		 * menu area for the plugin. And add JS scripts and CSS
		 * styles on different plugin pages on admin area.
		 *
		 * @uses add_action()
		 * @uses WPNakama_Admin
		 * @uses WPNakama_Admin::menu_pages()
		 * @uses WPNakama_Admin::enqueue_styles_scripts()
		 *
		 * @return bool
		 */
		public function define_admin() {
			if ( ! class_exists( 'WPNakama_Admin' ) ) {
				return false;
			}

			// Instantiate the admin class.
			$admin = new WPNakama_Admin( $this->plugin_uid, $this->version );

			// Add extra submenus and menu options to the admin panel's menu structure.
			add_action( 'admin_menu', array( $admin, 'menu_pages' ) );

			// Add admin actions, to process user interaction.
			add_action( 'admin_init', array( $admin, 'admin_actions' ) );

			// Enqueuing scripts and styles that are meant to be used in the administration panel.
			add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_styles_scripts' ) );

			// Enqueuing scripts and styles that are meant to be used in the block sidebar or toolbar.
			add_action( 'enqueue_block_editor_assets', array( $admin, 'enqueue_block_editor_styles_scripts' ) );

			// Register WPNakama post meta fields.
			add_action( 'init', array( $admin, 'register_post_meta_fields' ) );

			return true;
		}

		/**
		 * Custom endpoint or query variables as per the permalinks
		 * availability.
		 *
		 * A detailed tutorial on endpoints.
		 *
		 * @link https://make.wordpress.org/plugins/2012/06/07/rewrite-endpoints-api/
		 *
		 * @uses add_action()
		 * @uses add_filter()
		 * @uses add_rewrite_endpoint
		 * @uses status_header()
		 * @uses $wp_query
		 */
		public function define_endpoints() {
			// List of endpoints.
			$endpoints = array( 'wpn/boards' );

			// Check pretty permalinks are enabled or not.
			$is_permalink_enabled = $this->is_pretty_permalinks();

			/**
			 * If permalinks enabled, add the custom endpoints otherwise
			 * create custom query variables from same name of endpoints with
			 * an underscore `_` in place of `/`
			 */
			if ( $is_permalink_enabled ) {
				add_action(
					'init',
					function () use ( $endpoints ) {
						foreach ( $endpoints as $endpoint ) {
							add_rewrite_endpoint( $endpoint, EP_ROOT );
						}
					}
				);
			} else {
				$fallback_query_vars = array();
				foreach ( $endpoints as $endpoint ) {
					$fallback_query_vars[] = str_replace( '/', '_', $endpoint );
				}
				if ( 0 === count( $fallback_query_vars ) ) {
					add_filter(
						'query_vars',
						function ( $arr ) {
							array_merge( $arr, $fallback_query_vars );
							return $arr;
						}
					);
				}
			}

			/**
			 * Show custom template, as per the endpoint.
			 *
			 * The reason behind using `template_redirect` is, we know
			 * the content we are going to render for specific endpoints.
			 * alternativly we can use `template_include` filter.
			 */
			add_action(
				'template_redirect',
				function () {
					global $wp_query;

					status_header( 200 );
					// if this is not a request for specific endpoint then do nothing.
					if ( isset( $wp_query->query_vars['wpn/boards'] ) || isset( $wp_query->query_vars['wpn_boards'] ) || $wp_query->query_vars['post_type'] === 'wpn_boards' ) {
						include WPNAKAMA_PLUGIN_PATH . '/frontend/templates/wpn-boards.php';
						exit;
					} else {
						return;
					}
				}
			);
		}

		/**
		 * Define front end functionalities.
		 *
		 * @uses add_action()
		 * @uses WPNakama_Frontend
		 * @uses WPNakama_Frontend::enqueue_styles_scripts()
		 *
		 * @return bool
		 */
		public function define_frontend() {
			if ( ! class_exists( 'WPNakama_Frontend' ) ) {
				return false;
			}

			// Instantiate the front class.
			$frontend = new WPNakama_Frontend( $this->plugin_uid, $this->version );

			// Enqueuing scripts and styles that are meant to appear on the front end.
			add_action( 'wp_enqueue_scripts', array( $frontend, 'enqueue_styles_scripts' ), 99 );

			// Register WPNakama post meta fields.
			add_action( 'init', array( $frontend, 'register_post_meta_fields' ) );

			return true;
		}

		/**
		 * Define cache.
		 * The core purpose of this function is to clear the cache
		 * and prevent to show the stale data to the user.
		 *
		 * @uses WPNakama_Cache
		 * @uses WPNakama_Cache::clear_cache()
		 * @uses add_action()
		 *
		 * @return bool
		 */
		public function define_cache() {
			if ( ! class_exists( 'WPNakama_Cache' ) ) {
				return false;
			}

			// Instantiate the cache class.
			$cache = new WPNakama_Cache( $this->plugin_uid, $this->version );

			// On plugin load, clear the cache on slected Nakama admin pages.
			add_action( 'plugins_loaded', array( $cache, 'clear_cache' ) );

			return true;
		}

		/**
		 * Define updater.
		 * The core purpose of this function is to update the plugin
		 * from the remote server.
		 * It'll check the plugin version and update the plugin if
		 * the new version is available.
		 *
		 * @uses WPNakama_Updater
		 * @uses WPNakama_Updater::info()
		 * @uses WPNakama_Updater::update()
		 * @uses WPNakama_Updater::purge()
		 * @uses add_filter()
		 * @uses add_action()
		 *
		 * @return bool
		 */
		public function define_updater() {
			if ( ! class_exists( 'WPNakama_Updater' ) ) {
				return false;
			}

			// Instantiate the updater class.
			$updater = new WPNakama_Updater( $this->plugin_uid, $this->version, $this->license_server_api_url );

			// Extend the plugin information.
			add_filter( 'plugins_api', array( $updater, 'info' ), 20, 3 );

			// Override the transient data of plugin.
			add_filter( 'site_transient_update_plugins', array( $updater, 'update' ) );
			add_filter( 'transient_update_plugins', array( $updater, 'update' ) );

			// Clear the transient data after plugin update.
			add_action( 'upgrader_process_complete', array( $updater, 'purge' ), 10, 2 );

			return true;
		}

		/**
		 * Run the plugin.
		 */
		public function run() {

			// Adding updater.
			$this->define_updater();

			// Adding database.
			$this->define_database();

			// Adding API.
			$this->define_api();

			// Calling custom post type.
			$this->define_cpt();

			// Calling admin functionalities.
			$this->define_admin();

			// Adding cache.
			$this->define_cache();

			// Calling front end functionalities.
			$this->define_frontend();

			// Custom endpoints for frontend.
			$this->define_endpoints();

		}
	}
}
