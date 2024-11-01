<?php
/**
 * API schema trait.
 *
 * @package     WPNakama
 * @subpackage  Ingredient
 * @since       0.3.0
 * @version     0.1.0
 * @author      kantbtrue, qdonow, designthingy, savydv
 * @license     GPL-2.0-or-later
 */

// If direct access this file, abort.
if ( ! defined( 'WPINC' ) ) {
	die( 'Direct script access is prohibited!' );
}

if ( ! trait_exists( 'WPNakama_API_Schema' ) ) {
	/**
	 * A trait for better API data understanding using schema.
	 */
	trait WPNakama_API_Schema {



		public static function boards_schema() {
			return array(
				'schema'     => 'http://json-schema.org/draft-04/schema#',
				'title'      => 'wpnakama_boards',
				'type'       => 'object',
				'properties' => array(
					'board_id'       => array(
						'type'     => 'integer',
						'readonly' => true,
					),
					'title'          => array(
						'type' => 'string',
					),
					'description'    => array(
						'type' => 'string',
					),
					'board_date'     => array(
						'type' => 'string',
					),
					'board_date_gmt' => array(
						'type' => 'string',
					),
					'workspace_id'   => array(
						'type' => 'string',
					),
				),
			);
		}
		public static function board_schema() {
			return array(
				'schema'     => 'http://json-schema.org/draft-04/schema#',
				'title'      => 'wpnakama_board',
				'type'       => 'object',
				'properties' => array(
					'board_id'       => array(
						'type'     => 'integer',
						'readonly' => true,
					),
					'title'          => array(
						'type' => 'string',
					),
					'description'    => array(
						'type' => 'string',
					),
					'board_date'     => array(
						'type' => 'string',
					),
					'board_date_gmt' => array(
						'type' => 'string',
					),
					'workspace_id'   => array(
						'type' => 'string',
					),
				),
			);
		}
		public static function boardTable_schema() {
			return array(
				'schema'     => 'http://json-schema.org/draft-04/schema#',
				'title'      => 'wpnakama_boardTable',
				'type'       => 'object',
				'properties' => array(),
			);
		}
	}
}
