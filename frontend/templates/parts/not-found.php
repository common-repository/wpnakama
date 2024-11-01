<?php
/**
 * WPNakama Board Template Parts.
 *
 * @package     WPNakama
 * @subpackage  Template/Parts
 * @since       0.3.0
 * @version     0.1.0
 * @author      kantbtrue, qdonow, designthingy
 * @license     GPL-2.0-or-later
 */

?>

<div style="font-family: Inter, sans-serif; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); padding: 2rem; width: 300px; font-size: 17px; line-height: 26px;">
	<div>
		<div style="margin: 0;font-family: Koulen, sans-serif;font-size: 52px; line-height: 60px;">
			<?php esc_html_e( '404' ); ?>
		</div>
		<div style="font-family: Inter, sans-serif;margin: 0;font-size:40px;line-height: 48px;">
			<?php esc_html_e( 'Not Found' ); ?>
		</div>
		<div style="margin: 0;">
			<?php esc_html_e( 'The board is not available.' ); ?>
		</div>
	</div>
	<div>
		<div>
			<?php esc_html_e( 'powered by' ); ?> <a
				href="https://wordpress.org/plugins/wpnakama/"
			>
				WPNakama
			</a>
		</div>
	</div>
</div>
<style>
	.header,
	.footer {
		display: none;
	}
</style>
