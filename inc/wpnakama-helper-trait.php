<?php
/**
 * Plugin helper trait.
 *
 * @package     WPNakama
 * @subpackage  Core
 * @since       0.1.0
 * @version     0.3.3
 * @author      kantbtrue, qdonow, designthingy
 * @license     GPL-2.0-or-later
 */

// If direct access this file, abort.
if ( ! defined( 'WPINC' ) ) {
	die( 'Direct script access is prohibited!' );
}

if ( ! trait_exists( 'WPNakama_Helper' ) ) {
	/**
	 * The trait class of main commonly used functions
	 * like sanitization, escaping etc.
	 */
	trait WPNakama_Helper {

		/**
		 * Sanitize value as per the form's element types.
		 * If value doesn't match the element type then take the
		 * input value as text.
		 * Form element types are: text, textarea, checkbox, radio,
		 * url, file, number and select.
		 *
		 * @param mixed  $val      Settings value.
		 * @param string $form_ele Form element type.
		 *
		 * @return mixed
		 */
		public static function sanitize_value( $val, $form_ele = 'text' ) {

			// Delcaring the variable.
			$sanitized_val;

			switch ( $form_ele ) {
				case 'title':
					$sanitized_val = sanitize_text_field( $val );
					break;
				case 'text':
					$sanitized_val = sanitize_text_field( $val );
					break;
				case 'textarea':
					$sanitized_val = wp_kses_post( $val );
					break;
				case 'checkbox':
				case 'radio':
					$sanitized_val = rest_sanitize_boolean( $val );
					break;
				case 'url':
					$sanitized_val = esc_url_raw( $val );
					break;
				case 'array':
					if ( ! is_scalar( $val ) ) {
						$sanitized_val = maybe_serialize( array_map( 'maybe_serialize', $val ) );
					} else {
						$sanitized_val = null;
					}
					break;
				case 'file':
				case 'number':
					$sanitized_val = absint( $val );
					break;
				case 'select':
					$sanitized_val = sanitize_text_field( $val );
					break;
				default:
					$sanitized_val = sanitize_text_field( $val );
					break;
			};

			return $sanitized_val;
		}

		/**
		 * Escape value as per the value types.
		 * If value doesn't match the element type then take the
		 * value as text.
		 * Form element types are: text, textarea, content, checkbox,
		 * radio, url, file, number and select.
		 *
		 * @param mixed  $val  Value.
		 * @param string $type Data Type.
		 *
		 * @return mixed
		 */
		public static function escape_value( $val, $type = 'text' ) {

			// Delcaring the variable.
			$escaped_val;

			switch ( $type ) {
				case 'text':
					$escaped_val = esc_html( $val );
					break;
				case 'textarea':
					$escaped_val = wp_kses_post( $val );
					break;
				case 'content':
					$escaped_val = wp_kses_post( $val );
					break;
				case 'array':
					$unserialized_value = maybe_unserialize( $val );
					$escaped_val        = array_map( 'maybe_unserialize', $unserialized_value );
					break;
				case 'checkbox':
				case 'radio':
				case 'select':
					$escaped_val = esc_attr( $val );
					break;
				case 'url':
					$escaped_val = esc_url( $val );
					break;
				case 'file':
				case 'number':
					$escaped_val = absint( $val );
					break;
				default:
					$escaped_val = sanitize_text_field( $val );
					break;
			};

			return $escaped_val;
		}

		/**
		 * Check the license key.
		 *
		 * @param string $license_key License key.
		 * @param string $api_url License server API URL.
		 */
		public static function check_license( $license_key, $api_url ) {
			$activation_url = add_query_arg(
				array(
					'license_key'   => $license_key,
					'instance_name' => home_url(),
				),
				$api_url . '/update'
			);

			$response = wp_remote_get(
				$activation_url,
				array(
					'sslverify' => false,
					'timeout'   => 10,
				)
			);

			return json_decode( wp_remote_retrieve_body( $response ) )->success;
		}

		/**
		 * Activate the license key.
		 *
		 * @param string $license_key License key.
		 * @param string $api_url License server API URL.
		 *
		 * @return void
		 */
		public static function activate_license( $license_key, $api_url ) {
			$activation_url = add_query_arg(
				array(
					'license_key'   => $license_key,
					'instance_name' => home_url(),
				),
				$api_url . '/activate'
			);

			$response = wp_remote_get(
				$activation_url,
				array(
					'sslverify' => false,
					'timeout'   => 10,
				)
			);

			if (
				is_wp_error( $response )
				|| ( 200 !== wp_remote_retrieve_response_code( $response ) && 400 !== wp_remote_retrieve_response_code( $response ) )
				|| empty( wp_remote_retrieve_body( $response ) || ! json_decode( wp_remote_retrieve_body( $response ) )->activated )
			) {
				return false;
			}

			update_option( 'wpnakama_license_message', wp_remote_retrieve_body( $response ) );
			return wp_remote_retrieve_body( $response );
		}

		/**
		 * Deactivate the license key.
		 *
		 * @param string $license_key License key.
		 * @param string $instance_id Instance ID.
		 * @param string $api_url License server API URL.
		 *
		 * @return void
		 */
		public function deactivate_license( $license_key, $instance_id, $api_url ) {
			$deactivation_url = add_query_arg(
				array(
					'license_key' => $license_key,
					'instance_id' => $instance_id,
				),
				$api_url . '/deactivate'
			);

			$response = wp_remote_get(
				$deactivation_url,
				array(
					'sslverify' => false,
					'timeout'   => 10,
				)
			);

			if ( is_wp_error( $response ) ) {
				return false;
			}

			if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
				delete_option( 'wpnakama_license_message' );
			}
			return true;
		}
		
		/**
		 * Check pretty permalinks are enabled or not.
		 * 
		 * @return bool
		 */
		public static function is_pretty_permalinks() {
			$rewrite              = new WP_Rewrite();
			$is_permalink_enabled = $rewrite->using_permalinks();
			return $is_permalink_enabled;
		}
	}
}
