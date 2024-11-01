<?php
/**
 * API class.
 *
 * @package     WPNakama
 * @subpackage  Ingredient
 * @since       0.1.0
 * @version     0.3.0
 * @author      kantbtrue, qdonow, designthingy, savydv
 * @license     GPL-2.0-or-later
 */

// If direct access this file, abort.
if ( ! defined( 'WPINC' ) ) {
	die( 'Direct script access is prohibited!' );
}

if ( ! class_exists( 'WPNakama_API' ) ) {
	/**
	 * Plugin API class
	 */
	class WPNakama_API extends WP_REST_Controller {

		/**
		 * Global database object.
		 *
		 * @var object
		 */
		protected $db;

		/**
		 * API version.
		 *
		 * @var string
		 */
		protected $api_version;

		/**
		 * Run when class instantiated.
		 *
		 * @param string $namespace Namespace for API identification.
		 */
		public function __construct( $namespace ) {
			// WP database object.
			global $wpdb;
			include_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$this->db = $wpdb;

			// Namespace.
			$this->namespace = $namespace;

			// API Version.
			$this->api_version = 'v1';
		}

		/**
		 * Check the request coming from a valid source.
		 * Uses the core rest-api.php file code.
		 *
		 * @param string $req_header_nonce X-WP-Nonce value in request header.
		 * @param string $rest_nonce Nonce value used for rest api.
		 *
		 * @return mixed
		 */
		public function checkRestNonce( $req_header_nonce, $rest_nonce ) {
			if ( ! wp_verify_nonce( $req_header_nonce, $rest_nonce ) ) {
				add_filter( 'rest_send_nocache_headers', '__return_true', 20 );
				return new WP_Error( 'rest_cookie_invalid_nonce', __( 'Cookie check failed' ), array( 'status' => 403 ) );
			}
			return true;
		}

		/**
		 * Create API route without path variable.
		 *
		 * @param string $api_route     WPNakama API route.
		 * @param string $method        Request methods.
		 * @param mixed  $cb            API route callback function.
		 * @param mixed  $permission_cb API route permission callback function.
		 * @param array  $args          Arguments.
		 * @param array  $schema        Schema.
		 *
		 * @return bool
		 */
		public function create_rest_route( $api_route, $method, $cb, $permission_cb, $args = array(), $schema = array(), $override = false ) {
			// Assing the schema.
			$this->schema = $schema;

			// API rest base.
			$this->rest_base = $api_route;

			register_rest_route(
				$this->namespace . '/' . $this->api_version,
				'/' . $api_route,
				array(
					'methods'             => $method,
					'callback'            => $cb,
					'args'                => $this->get_record_params( $args ),
					'permission_callback' => $permission_cb,
					'schema'              => $schema,
				),
				$override
			);

			return true;
		}

		/**
		 * Create API route with path variable.
		 *
		 * @param string $api_route     WPNakama API route.
		 * @param string $method        Request methods.
		 * @param mixed  $cb            API route callback function.
		 * @param mixed  $permission_cb API route permission callback function.
		 * @param string $path_var_name Path vairable name.
		 * @param array  $args          Arguments.
		 * @param array  $schema        Schema.
		 *
		 * @return bool
		 */
		public function create_rest_route_pathVar( $api_route, $method, $cb, $permission_cb, $path_var_name = 'id', $args = array(), $schema = array() ) {
			// Assing the schema.
			$this->schema = $schema;

			register_rest_route(
				$this->namespace . '/' . $this->api_version,
				'/' . $api_route . '/(?P<' . $path_var_name . '>[\d]+)',
				array(
					'methods'             => $method,
					'callback'            => $cb,
					'args'                => $args,
					'permission_callback' => $permission_cb,
					'schema'              => $schema,
				)
			);

			return true;
		}

		/**
		 * Retrieves the query params for the record collection.
		 *
		 * @param array $args Custom arguments.
		 *
		 * @return array Collection parameters.
		 */
		public function get_record_params( $args ) {
			$query_params = parent::get_collection_params();
			foreach ( $args as $key => $value ) {
				$query_params[ $key ] = $value;
			}

			return $query_params;
		}

		/**
		 * Get all records.
		 *
		 * @param WP_REST_Request  $req                 Requested information to API.
		 * @param string           $table_name          Table name.
		 * @param string|array     $field_name          Field name used to restrict the records.
		 * @param string           $orderby             Order the records on the basis of specific field values.
		 * @param string           $order               Arrange the order of records, ASC|DESC.
		 * @param ARRAY_A|OBJECT_K $formate_constant    Formate the api result.
		 *
		 * @return object
		 */
		public function get_records( $req, $table_name, $field_name = '', $orderby = '', $order = 'desc', $formate_constant = ARRAY_A ) {
			$name = str_replace( $this->db->prefix, '', $table_name );

			// Add number of results.
			$records_per_page = $req['per_page'];
			if ( $records_per_page ) {
				if ( $req['page'] ) {
					$offset_rows = $records_per_page * ( $req['page'] - 1 );
				} else {
					$offset_rows = 0;
				}
			} else {
				$offset_rows = 0;
			}

			if ( isset( $field_name ) && ! empty( $field_name ) ) {
				if ( ! empty( $orderby ) ) {
					if ( is_array( $field_name ) ) {
						if ( 2 === count( $field_name ) ) {
							$query = $this->db->prepare(
								"SELECT * FROM {$table_name} WHERE {$field_name[0]} = %d AND {$field_name[1]} = %d ORDER BY {$orderby} {$order} LIMIT %d,%d",
								$req[ $field_name[0] ],
								$req[ $field_name[1] ],
								$offset_rows,
								$records_per_page
							);
						} elseif ( 3 === count( $field_name ) ) {
							$query = $this->db->prepare(
								"SELECT * FROM {$table_name} WHERE {$field_name[0]} = %d AND {$field_name[1]} = %d AND {$field_name[2]} = %d ORDER BY {$orderby} {$order} LIMIT %d,%d",
								$req[ $field_name[0] ],
								$req[ $field_name[1] ],
								$req[ $field_name[2] ],
								$offset_rows,
								$records_per_page
							);
						}
					} else {
						$query = $this->db->prepare(
							"SELECT * FROM {$table_name} WHERE {$field_name} = %d ORDER BY {$orderby} {$order} LIMIT %d,%d",
							$req[ $field_name ],
							$offset_rows,
							$records_per_page
						);
					}
				} else {
					if ( is_array( $field_name ) ) {
						if ( 2 === count( $field_name ) ) {
							$query = $this->db->prepare(
								"SELECT * FROM {$table_name} WHERE {$field_name[0]} = %d AND {$field_name[1]} = %d LIMIT %d,%d",
								$req[ $field_name[0] ],
								$req[ $field_name[1] ],
								$offset_rows,
								$records_per_page
							);
						} elseif ( 3 === count( $field_name ) ) {
							$query = $this->db->prepare(
								"SELECT * FROM {$table_name} WHERE {$field_name[0]} = %d AND {$field_name[1]} = %d AND {$field_name[2]} = %d LIMIT %d,%d",
								$req[ $field_name[0] ],
								$req[ $field_name[1] ],
								$req[ $field_name[2] ],
								$offset_rows,
								$records_per_page
							);
						}
					} else {
						$query = $this->db->prepare(
							"SELECT * FROM {$table_name} WHERE {$field_name} = %d LIMIT %d,%d",
							$req[ $field_name ],
							$offset_rows,
							$records_per_page
						);
					}
				}
				if ( is_array( $field_name ) ) {
					if ( 2 === count( $field_name ) ) {
						$tot_records_query = $this->db->prepare(
							"SELECT COUNT({$field_name[0]}) FROM {$table_name} WHERE {$field_name[0]} = %d AND {$field_name[1]} = %d",
							$req[ $field_name[0] ],
							$req[ $field_name[1] ]
						);
					} elseif ( 3 === count( $field_name ) ) {
						$tot_records_query = $this->db->prepare(
							"SELECT COUNT({$field_name[0]}) FROM {$table_name} WHERE {$field_name[0]} = %d AND {$field_name[1]} = %d AND {$field_name[2]} = %d",
							$req[ $field_name[0] ],
							$req[ $field_name[1] ],
							$req[ $field_name[2] ]
						);
					}
				} else {
					$tot_records_query = $this->db->prepare(
						"SELECT COUNT({$field_name}) FROM {$table_name} WHERE {$field_name} = %d",
						$req[ $field_name ]
					);
				}
			} else {
				if ( ! empty( $orderby ) ) {
					$query = $this->db->prepare(
						"SELECT * FROM {$table_name} ORDER BY {$orderby} {$order} LIMIT %d,%d",
						$offset_rows,
						$records_per_page
					);
				} else {
					$query = $this->db->prepare(
						"SELECT * FROM {$table_name} LIMIT %d,%d",
						$offset_rows,
						$records_per_page
					);
				}
				$tot_records_query = "SELECT COUNT(*) FROM {$table_name}";
			}
			// It'll add the pagination feature to the query.
			$tot_records = $this->db->get_var( $tot_records_query );
			$max_pages   = $records_per_page ? ceil( $tot_records / $records_per_page ) : 0;

			$result = $this->db->get_results(
				$query,
				$formate_constant
			);

			if ( false === $result ) {
				$this->db->query( 'ROLLBACK' );
				$res = new WP_Error(
					'not_found',
					__( 'Not Found', 'wpnakama' ),
					array(
						'status' => 404,
					)
				);
			} else {
				$res = new WP_REST_Response( $result, 200 );
				$res->set_headers(
					array(
						'X-WP-Total'      => (int) $tot_records,
						'X-WP-TotalPages' => (int) $max_pages,
					)
				);
			}

			return rest_filter_response_fields( $res, new WP_REST_Server(), $req );
		}

		/**
		 * Get record/s from a table using field.
		 *
		 * @param WP_REST_Request $req        Requested information to API.
		 * @param string          $table_name Table name.
		 * @param string          $field_name Field name used to identify the records.
		 */
		public function get_record( $req, $table_name, $field_name = 'id' ) {
			if ( is_array( $field_name ) ) {
				$query = 'SELECT * FROM ' . $table_name . ' WHERE id = ' . $field_name['id'];

			} else {
				$query = 'SELECT * FROM ' . $table_name . ' WHERE ' . $field_name . ' = ' . $req[ $field_name ];
			}
			$result = $this->db->get_row(
				$query,
				ARRAY_A
			);
			if ( 'NULL' === gettype( $result ) ) {
				$res = new WP_Error(
					'not_found',
					__( 'Not Found', 'wpnakama' ),
					array(
						'status' => 404,
					)
				);
			} else {
				$res = new WP_REST_Response( $result, 200 );
			}
			return rest_filter_response_fields( $res, new WP_REST_Server(), $req );
		}

		/**
		 * Add record in a table.
		 *
		 * @param array  $data       Requested data array.
		 * @param string $table_name Table name.
		 * @param string $field_name Field name used to identify the records.
		 *
		 * @return mixed
		 */
		public function add_record( $data, $table_name, $field_name = 'id' ) {
			$date_field        = '';
			$date_gmt_field    = '';
			$date_modify_field = '';
			if ( preg_match( '/_board_/', $table_name ) ) {
				$date_field        = 'card_date';
				$date_gmt_field    = 'card_date_gmt';
				$date_modify_field = 'card_modify_date';
			}
			if ( preg_match( '/_boards$/', $table_name ) ) {
				$date_field     = 'board_date';
				$date_gmt_field = 'board_date_gmt';
			}
			if ( '' !== $date_field && '' !== $date_gmt_field ) {
				$data[ $date_field ]     = current_time( 'mysql', 0 );
				$data[ $date_gmt_field ] = current_time( 'mysql', 1 );
			}
			if ( '' !== $date_modify_field ) {
				$data[ $date_modify_field ] = current_time( 'mysql', 0 );
			}
			$result = $this->db->insert(
				$table_name,
				$data,
			);

			// If something went wrong, rollback the changes.
			if ( false === $result ) {
				$this->db->query( 'ROLLBACK' );
				return new WP_Error(
					'not_found',
					__( 'Not Found', 'wpnakama' ),
					array(
						'status' => 404,
					)
				);
			}

			return new WP_REST_Response(
				array(
					'status'  => 200,
					'message' => 'Successfully, added the record.',
					'data'    => array(
						$field_name => $this->db->insert_id,
					),
				)
			);
		}

		/**
		 * Delete record/s from a table.
		 *
		 * @param WP_REST_Request $req        Requested information to API.
		 * @param string          $table_name Table name.
		 * @param string          $field_name Field name used to identify the records.
		 *
		 * @return mixed
		 */
		public function delete_record( $req, $table_name, $field_name = 'id' ) {
			if ( is_array( $field_name ) ) {
				$where = array();
				foreach ( $field_name as $key => $value ) {
					$where[ $key ] = $value;
				}
				$result = $this->db->delete(
					$table_name,
					$where
				);
			} else {
				$result = $this->db->delete(
					$table_name,
					array(
						$field_name => $req[ $field_name ],
					),
					array(
						'%d',
					)
				);
			}
			if ( false === $result ) {
				$this->db->query( 'ROLLBACK' );
				$res = new WP_Error(
					'not_found',
					__( 'Not Found', 'wpnakama' ),
					array(
						'status' => 404,
					)
				);
			}

			return new WP_REST_Response(
				array(
					'status'  => 200,
					'message' => 'Successfully, deleted the record.',
				)
			);
		}

		/**
		 * Update record in a table.
		 *
		 * @param array  $data       Requested data array.
		 * @param string $table_name Table name.
		 * @param string $field_name Field name used to identify the record.
		 *
		 * @return mixed
		 */
		public function update_record( $data, $table_name, $field_name = 'id' ) {
			$date_modify_field = '';
			if ( preg_match( '/_board_/', $table_name ) ) {
				$date_modify_field = 'card_modify_date';
			}
			if ( '' !== $date_modify_field ) {
				$data[ $date_modify_field ] = current_time( 'mysql', 0 );
			}
			$result = $this->db->update(
				$table_name,
				$data,
				array(
					$field_name => $data[ $field_name ],
				),
			);
			if ( false === $result ) {
				$this->db->query( 'ROLLBACK' );
				$res = new WP_Error(
					'not_found',
					__( 'Not Found', 'wpnakama' ),
					array(
						'status' => 404,
					)
				);
			}

			return new WP_REST_Response(
				array(
					'status'  => 200,
					'message' => 'Successfully, updated the record.',
				)
			);
		}

		/**
		 * Checks if a given request has access to get records.
		 *
		 * @return true
		 */
		public function get_records_permissions_check() {
			return '__return_true';
		}

		/**
		 * Checks if a given request has access to get record.
		 *
		 * @return true
		 */
		public function get_record_permissions_check() {
			return '__return_true';
		}

		/**
		 * Checks if a given request has access to create record.
		 *
		 * @return true|WP_Error True if the request has access to create items, WP_Error object otherwise.
		 */
		public function create_record_permissions_check() {
			if ( current_user_can( 'edit_posts' ) ) {
				return true;
			}
			return new WP_Error(
				'invalid-method',
				/* translators: %s: Method name. */
				sprintf( __( 'Do not have permission to create entry in the database.' ), __METHOD__ ),
				array( 'status' => 405 )
			);
		}

		/**
		 * Checks if a given request has access to update a specific record.
		 *
		 * @return true|WP_Error True if the request has access to update the item, WP_Error object otherwise.
		 */
		public function update_record_permissions_check() {
			if ( current_user_can( 'edit_posts' ) ) {
				return true;
			}
			return new WP_Error(
				'invalid-method',
				/* translators: %s: Method name. */
				sprintf( __( 'Do not have permission to update entry in the database.' ), __METHOD__ ),
				array( 'status' => 405 )
			);
		}

		/**
		 * Checks if a given request has access to delete a specific record.
		 *
		 * @return true|WP_Error True if the request has access to delete the item, WP_Error object otherwise.
		 */
		public function delete_record_permissions_check() {
			if ( current_user_can( 'delete_posts' ) ) {
				return true;
			}
			return new WP_Error(
				'invalid-method',
				/* translators: %s: Method name. */
				sprintf( __( 'Do not have permission to delete entry in the database.' ), __METHOD__ ),
				array( 'status' => 405 )
			);
		}

		/**
		 * Retrieves the item's schema, conforming to JSON Schema.
		 *
		 * @since 0.3.0
		 *
		 * @return array Item schema data.
		 */
		public function get_item_schema() {
			return $this->add_additional_fields_schema( $this->schema );
		}
	}
}
