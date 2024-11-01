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

// Get the query variables data.
$board_id          = get_query_var( 'board_id' );
$board             = get_query_var( 'board' );
$lists             = get_query_var( 'lists' );
$tasks             = get_query_var( 'tasks' );
$board_status      = get_query_var( 'board_status' );
$site_logo_img_url = get_query_var( 'site_logo_img_url' );
?>

<a class="skip-link screen-reader-text" href="#content">
		<?php
		/**
		 * Translators: Hidden accessibility text.
		 */

		esc_html_e( 'Skip to content', 'wpnakama' );
		?>
	</a>
	<header class="min-w-[1000px] header flex justify-center bg-white shadow-wg-lg container min-h-16 mx-auto rounded-full px-5 md:px-9 py-2">
		<div class="flex justify-between w-full items-center">
		<?php
		if ( $site_logo_img_url ) :
			?>
			<img src="<?php echo esc_url( $site_logo_img_url ); ?>" class="w-auto h-10" />
			<?php
		else :
			?>
			<div class=" text-lg">
				<?php echo esc_html( get_bloginfo( 'name' ) ); ?>
			</div>
			<?php
		endif;
		?>
		</div>
	</header>
	<main id="content" class="min-w-[1000px] container main my-0 mx-auto px-5 md:px-9 py-8 md:py-10 xxl:py-16">
		<?php
		if ( have_posts() ) {
			while ( have_posts() ) {
				the_post();
				?>
					<div class="flex gap-3 justify-between item-center">
						<div class="flex-shrink w-full max-w-80 flex flex-col gap-1" data-wp-interactive="wpnakama">
							<div class="font-semibold text-base leading-5 font-base m-0">
								<?php echo esc_html( $board['title'] ); ?>
							</div>
							<div class="text-sm text-surface-500 m-0">
								<?php echo esc_html( $board['description'] ); ?>
							</div>
						</div>
						<?php
						if ( ! post_password_required() && 'publish' === $board_status && is_user_logged_in() ) :
							?>
							<div class="wg-antialiased flex text-sm leading-6 items-start rounded-lg px-2 py-3 sm:items-center border-wg-yellow bg-wg-yellow-50 text-wg-yellow-800" role="alert">
								<svg fill="currentColor" height="24" viewBox="0 0 24 24" width="24" class="size-6 shrink-0 text-wg-yellow pl-1">
									<path clip-rule="evenodd" d="M12 20C16.4183 20 20 16.4183 20 12C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12C4 16.4183 7.58172 20 12 20ZM11 9C11 8.44772 11.4477 8 12 8C12.5523 8 13 8.44772 13 9C13 9.55228 12.5523 10 12 10C11.4477 10 11 9.55228 11 9ZM12 12C12.5523 12 13 12.4477 13 13V15C13 15.5523 12.5523 16 12 16C11.4477 16 11 15.5523 11 15V13C11 12.4477 11.4477 12 12 12Z" fill-rule="evenodd"></path>
								</svg>
								<div class="flex flex-col items-start px-2 sm:flex-row sm:items-center sm:gap-2">
									<div class="flex-grow flex-col items-start sm:flex-row sm:items-center sm:gap-2">
										<p class="text-start font-medium text-wg-yellow-800">
											<?php esc_html_e( 'Secure this board with password.', 'wpnakama' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpnakama_license' ) ); ?>" class="underline text-wg-yellow-800"><?php esc_html_e( 'Set Password', 'wpnakama' ); ?></a>
										</p>
									</div>
								</div>
							</div>
							<?php
						endif;
						?>
					</div>
					<div class="flex gap-12 mt-10 overflow-x-auto -mx-4">
					<?php
					$board_lists = array_filter( $lists, fn( $list) => $list['board_id'] === $board['board_id'] );
					usort( $board_lists, fn( $a, $b) => $a['position'] <=> $b['position'] );

					foreach ( $board_lists as $list ) {

						?>
							<div class="p-4 w-72 min-w-72 h-full flex flex-col gap-8 justify-center">
								<div class="bg-wg-white px-3 py-4 rounded-xl">
									<div class="text-base leading-5 text-primary font-medium">
									<?php echo esc_html( ucfirst( $list['title'] ) ); ?>
									</div>
								</div>
								<div class="flex gap-4 flex-wrap w-full h-full">
							<?php
							foreach ( array_filter( $tasks, fn( $task) => $task['phase_id'] == $list['phase_id'] ) as $task ) :
								$todos           = $api->get_records(
									array(
										'board_id' => $board_id,
										'card_id'  => $task['card_id'],
										'per_page' => 100,
										'page'     => 1,
									),
									$db->table_prefix( 'wpnakama_tasks' ),
									array(
										'board_id',
										'card_id',
									),
									'position',
									'asc'
								)->data;
								$todos_completed = 0 < count( $todos ) ? array_filter( $todos, fn( $todo) => $todo['is_completed'] ) : 0;
								?>
									<div class="transition px-5 py-3 shadow-nakama rounded-2xl w-64 h-full flex flex-col gap-4 bg-white">
										<div class="flex flex-col gap-3 h-full">
											<div class="flex justify-between items-center">
												<div class="text-sm leading-5 text-primary font-medium flex flex-col items-start gap-1">
												<?php echo esc_html( $task['title'] ); ?>
												<?php
												if ( ! $task['is_completed'] ) :
													?>
													<?php
													$crr_date           = gmdate( 'Y-m-d h:i:s' );
													$task_deadline_date = $task['card_deadline_date'];
													// Attempt to parse the date using the expected format.
													$date_obj    = DateTime::createFromFormat( 'Y-m-d h:i:s', $task_deadline_date );
													$date_errors = DateTime::getLastErrors();

													if ( $date_errors['warning_count'] > 0 ) {
														$task_deadline_date = $crr_date;
													}

													$is_deadline_passed = $crr_date > $task_deadline_date;

													?>
													<?php
													if ( $is_deadline_passed ) :
														?>
													<div class="text-xs font-normal flex items-start gap-1 text-red-400">
														<?php
													else :
														?>
													<div class="text-xs font-normal flex items-start gap-1 text-gray-400">
															<?php
													endif;
													?>
														<svg width="24" height="24" class="w-4 h-auto text-inherit fill-none" fill="none" viewBox="0 0 24 24">
															<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.75 8.75C4.75 7.64543 5.64543 6.75 6.75 6.75H17.25C18.3546 6.75 19.25 7.64543 19.25 8.75V17.25C19.25 18.3546 18.3546 19.25 17.25 19.25H6.75C5.64543 19.25 4.75 18.3546 4.75 17.25V8.75Z"></path>
															<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 4.75V8.25"></path>
															<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 4.75V8.25"></path>
															<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7.75 10.75H16.25"></path>
														</svg>
														<?php echo esc_html( date_format( date_create( $task_deadline_date ), 'j F Y' ) ); ?>
													</div>
													<?php
													else :
														?>
													<span class="inline-flex items-center px-2 py-1 wg-antialiased text-xs leading-4 text-wg-green-800 outline-wg-green-200 wg-bg-wg-green-50 dark:text-wg-green dark:wg-bg-surface/5 dark:outline-surface-50 rounded-full outline outline-1 -outline-offset-1">
														<svg width="24" height="24" fill="none" viewBox="0 0 24 24" class="size-4 text-wg-green-700 dark:text-current fill-none">
															<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.75 12.8665L8.33995 16.4138C9.15171 17.5256 10.8179 17.504 11.6006 16.3715L18.25 6.75"></path>
														</svg>
														<span class="px-0.5"><?php esc_html_e( 'Completed', 'wpnakama' ); ?></span>
													</span>
													<?php endif; ?>
												</div>
											</div>
											<?php
											if ( 0 < count( $todos ) ) :
												?>
											<div>
												<?php
												if ( count( $todos ) === count( $todos_completed ) ) :
													?>
												<div class="inline-flex items-center px-2 py-1 wg-antialiased text-xs leading-4 rounded-full text-green-900 outline-wg-green-200 outline outline-1 -outline-offset-1 bg-wg-green-50">
													<?php
											else :
												?>
												<div class="inline-flex items-center px-2 py-1 wg-antialiased text-xs leading-4 rounded-full text-surface-900 outline-surface-200 outline outline-1 -outline-offset-1 wg-bg-surface">
											<?php endif; ?>
													<svg width="24" height="24" fill="none" viewBox="0 0 24 24" class="size-4 text-surface-400 fill-none">
														<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.75 12.8665L8.33995 16.4138C9.15171 17.5256 10.8179 17.504 11.6006 16.3715L18.25 6.75"></path>
													</svg>
													<div class="px-0.5"><?php echo esc_html( count( $todos_completed ) . '/' . count( $todos ) ); ?></div>
												</div>
											</div>
												<?php
											endif;
											?>
										</div>
									</div>
									<?php
							endforeach;
							?>
								</div>
							</div>
							<?php
					}
					?>
					</div>
					<?php
			}
		} else {
			include WPNAKAMA_PLUGIN_PATH . '/frontend/templates/parts/not-found.php';
		}
		if ( 'private' === $board_status ) :
			?>
		<div class="wg-antialiased fixed bottom-7 left-6 min-w-1/2 inline-flex text-sm leading-6 dark:bg-surface dark:text-surface-500 items-start rounded-lg px-2 py-3 sm:items-center border-wg-yellow bg-wg-yellow-50 text-wg-yellow-800" role="alert">
			<svg fill="currentColor" height="24" viewBox="0 0 24 24" width="24" class="size-6 shrink-0 text-wg-yellow pl-1">
				<path clip-rule="evenodd" d="M12 20C16.4183 20 20 16.4183 20 12C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12C4 16.4183 7.58172 20 12 20ZM11 9C11 8.44772 11.4477 8 12 8C12.5523 8 13 8.44772 13 9C13 9.55228 12.5523 10 12 10C11.4477 10 11 9.55228 11 9ZM12 12C12.5523 12 13 12.4477 13 13V15C13 15.5523 12.5523 16 12 16C11.4477 16 11 15.5523 11 15V13C11 12.4477 11.4477 12 12 12Z" fill-rule="evenodd"></path>
			</svg>
			<div class="flex flex-col items-start px-2 sm:flex-row sm:items-center sm:gap-2">
				<div class="flex grow flex-col items-start sm:flex-row sm:items-center sm:gap-2">
					<p class="text-start font-medium text-wg-yellow-800 dark:text-wg-yellow">
						<?php esc_html_e( 'Visible to all logged in members.', 'wpnakama' ); ?>
					</p>
				</div>
			</div>
		</div>
			<?php
		endif;
		wp_reset_postdata();
		?>
		<div class="footer flex justify-center items-center mt-10 text-sm leading-6">
			<div class="inline-flex gap-1 justify-center items-center text-surface-500">
				Powered by <a
				href="https://wordpress.org/plugins/wpnakama/"
				class="!text-surface-500 underline !visited:text-surface-700 !hover:text-surface-700 inline-block"
			>
				WPNakama
			</a>
			</div>
		</div>
	</main>
