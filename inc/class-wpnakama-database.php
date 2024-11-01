<?php
/**
 * Database class.
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

if ( ! class_exists( 'WPNakama_Database' ) ) {
	/**
	 * Plugin database class is responsible for adding database related
	 * functionalities like table prefixing, check table existence, creating and
	 * deleting. It also has function `delete_all_tables()` for cleaning the database
	 * from all the tables used by the plugin.
	 * The class uses the instantiated `wpdb` class for doing all the functions.
	 */
	class WPNakama_Database {

		/**
		 * Global database object.
		 *
		 * @var object
		 */
		protected $db;

		/**
		 * Database version.
		 *
		 * @var string
		 */
		public $version;

		/**
		 * Run when class instantiated.
		 *
		 * @uses include_once
		 * @uses $wpdb
		 */
		public function __construct() {
			global $wpdb;
			include_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$this->db      = $wpdb;
			$this->version = '0.3.0';
		}

		/**
		 * Add table prefix.
		 *
		 * @param string $name Name of table without WP prefix.
		 *
		 * @return string
		 */
		public function table_prefix( $name ) {
			return $this->db->prefix . $name;
		}

		/**
		 * Check table exists.
		 *
		 * @param string $table_name Name of table with WP prefix.
		 *
		 * @return bool
		 */
		public function table_exists( $table_name ) {
			$quired_table_name = $this->db->get_var(
				$this->db->prepare(
					'SHOW TABLES LIKE %s',
					$table_name
				)
			);
			if ( $table_name !== $quired_table_name ) {
				return false;
			}

			return true;
		}

		/**
		 * Check table column exists.
		 *
		 * @param string $table_name Name of table with WP prefix.
		 * @param string $column_name Name of column.
		 *
		 * @return bool
		 */
		public function column_exists( $table_name, $column_name ) {
			$quired_column_names = $this->db->get_results( "SHOW COLUMNS FROM {$table_name}" );
			foreach ( $quired_column_names as $columns_data ) {
				if ( $column_name === $columns_data->Field ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Create table.
		 *
		 * @param string $table_name Name of table with WP prefix.
		 * @param string $sql_query SQL query.
		 *
		 * @return boolean
		 */
		public function create_table( $table_name, $sql_query ) {
			// Check table exists.
			if ( $this->table_exists( $table_name ) ) {
				return false;
			}
			// SQL query.
			$query = $sql_query . ' ' . $this->db->get_charset_collate();

			dbDelta( $query );
			return true;
		}

		/**
		 * Alter table by dropping and adding columns.
		 *
		 * @param string $table_name Name of table with WP prefix.
		 * @param string $column_name Name of the column.
		 * @param string $column_type Data type of the column.
		 * @param string $operation SQL operation run on alter command - ADD, DROP. Default set to ADD.
		 * @param string $default_value Default value for column.
		 * @param string $not_null Value be null or not.
		 *
		 * @return boolean
		 */
		public function alter_table( $table_name, $column_name = '', $column_type = 'varchar(255)', $operation = 'ADD', $default_value = '', $not_null = true ) {
			// Check table exists.
			if ( ! $this->table_exists( $table_name ) || $this->column_exists( $table_name, $column_name ) ) {
				return false;
			}

			// SQL query.
			switch ( $operation ) {
				case 'DROP':
					$query = "ALTER TABLE {$table_name} {$operation} {$column_name}";
					break;

				default:
					if ( ! empty( $default_value ) ) {
						if ( $not_null ) {
							$query = $this->db->prepare(
								"ALTER TABLE {$table_name} {$operation} {$column_name} {$column_type} NOT NULL DEFAULT %s",
								$default_value
							);
						} else {
							$query = $this->db->prepare(
								"ALTER TABLE {$table_name} {$operation} {$column_name} {$column_type} DEFAULT %s",
								$default_value
							);
						}
					} else {
						if ( $not_null ) {
							$query = "ALTER TABLE {$table_name} {$operation} {$column_name} {$column_type} NOT NULL";
						} else {
							$query = "ALTER TABLE {$table_name} {$operation} {$column_name} {$column_type}";
						}
					}
					break;
			};

			$altered = $this->db->query( $query );
			if ( ! $altered ) {
				return false;
			}
			return true;
		}

		/**
		 * Delete table.
		 *
		 * @param string $name Name of table without WP prefix.
		 *
		 * @return boolean
		 */
		public function delete_table( $name ) {
			$table_name = $this->table_prefix( $name );

			// Check table exists.
			if ( ! $this->table_exists( $table_name ) ) {
				return false;
			}

			$result = $this->db->query( "DROP TABLE IF EXISTS {$table_name}" );
			if ( ! $result ) {
				return false;
			}
			return true;
		}

		/**
		 * Delete all the tables.
		 *
		 * @return boolean
		 */
		public function delete_all_tables() {
			$table_names = array(
				$this->table_prefix( 'wpnakama_workspaces' ),
				$this->table_prefix( 'wpnakama_boards' ),
				$this->table_prefix( 'wpnakama_board_access' ),
				$this->table_prefix( 'wpnakama_phases' ),
				$this->table_prefix( 'wpnakama_tasks' ),
				$this->table_prefix( 'wpnakama_taskslists' ),
				$this->table_prefix( 'wpnakama_cards' ),
			);

			$board_ids_table = $this->table_prefix( 'wpnakama_boards' );
			$board_ids       = $this->db->get_col( "SELECT board_id FROM {$board_ids_table} ORDER BY board_id DESC" );

			foreach ( $board_ids as $key => $board_id ) {
				$table_names[] = $this->table_prefix( 'wpnakama_board_' . $board_id . '_cards' );
			}
			$table_names = array_reverse( $table_names );

			foreach ( $table_names as $table_name ) {
				// Check table exists.
				if ( ! $this->table_exists( $table_name ) ) {
					error . log( 'Table does not exist: ' . $table_name );
					continue;
				}
				$result = $this->db->query( "DROP TABLE IF EXISTS {$table_name}" );
				if ( ! $result ) {
					return false;
				}
			}

			return true;
		}
	}
}
