<?php
/**
 * WPNakama Board Template.
 *
 * @package     WPNakama
 * @subpackage  Template
 * @since       0.3.0
 * @version     0.1.0
 * @author      kantbtrue, qdonow, designthingy
 * @license     GPL-2.0-or-later
 */

// Check WPNakama API.
if ( ! class_exists( 'WPNakama_API' ) ) {
	return false;
}
$api = new WPNakama_API( 'WPNakama' );

// Check WPNakama Database.
if ( ! class_exists( 'WPNakama_Database' ) ) {
	return false;
}
$db = new WPNakama_Database();
// Data sets.
if ( get_the_ID() ) {
	$board_id     = get_post_meta( get_the_ID(), 'wpnakama_board_id' )[0];
	$board_status = get_post_status( get_the_ID() );
	$board        = $api->get_record(
		array(
			'board_id' => $board_id,
		),
		$db->table_prefix( 'wpnakama_boards' ),
		'board_id'
	)->data;
	$lists        = $api->get_records(
		array(
			'board_id' => $board_id,
			'per_page' => 15,
			'page'     => 1,
		),
		$db->table_prefix( 'wpnakama_phases' )
	)->data;
	$tasks        = $api->get_records(
		array(
			'board_id' => $board_id,
			'per_page' => 100,
			'page'     => 1,
		),
		$db->table_prefix( 'wpnakama_board_' . $board_id . '_cards' ),
		'',
		'position',
		'asc'
	)->data;
} else {
	$board_id     = 0;
	$board_status = 'private';
	$board        = array();
	$lists        = array();
	$tasks        = array();
}

// Site.
$site_logo_id      = get_theme_mod( 'custom_logo' );
$site_logo_img     = wp_get_attachment_image_src( $site_logo_id, 'full' );
$site_logo_img_url = is_array( $site_logo_img ) ? $site_logo_img[0] : '';

// Set query varaibles.
set_query_var( 'board_id', $board_id );
set_query_var( 'board', $board );
set_query_var( 'lists', $lists );
set_query_var( 'tasks', $tasks );
set_query_var( 'board_status', $board_status );
set_query_var( 'site_logo_img_url', $site_logo_img_url )
?>
<!doctype html>
<html <?php language_attributes(); ?> class="text-base">
<head>
	<title>
		<?php
		if ( 'private' !== $board_status ) {
			echo esc_html( $board['title'] . ' Board' );
		} else {
			echo esc_html( 'Private Board' );
		}
		?>
	</title>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="divport" content="width=device-width, initial-scale=1" />
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'py-4 w-auto h-auto bg-[#f0f0f0] font-base' ); ?>>
	<?php
		wp_body_open();
	if ( 'private' === $board_status ) :
		if ( is_user_logged_in() ) :
			include WPNAKAMA_PLUGIN_PATH . '/frontend/templates/parts/board.php';
			else :
				include WPNAKAMA_PLUGIN_PATH . '/frontend/templates/parts/not-found.php';
			endif;
		else :
			include WPNAKAMA_PLUGIN_PATH . '/frontend/templates/parts/board.php';
		endif;
		wp_footer();
		?>
</body>
</html>
