<?php
/**
 * Activity log page template.
 *
 * @var WPTS_Activity_Page $this Activity page instance.
 */

defined( 'ABSPATH' ) || exit;

$results    = $this->get_results();
$items      = $results->items;
$total      = $results->total;
$per_page   = 20;
$paged      = absint( $_GET['paged'] ?? 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$total_pages = ceil( $total / $per_page );

$current_platform  = sanitize_text_field( $_GET['platform'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$current_status    = sanitize_text_field( $_GET['status'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$current_post_type = sanitize_text_field( $_GET['post_type'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$retry_nonce = wp_create_nonce( 'wpts_retry_nonce' );
?>

<div class="wrap wpts-wrap">
	<h1><?php esc_html_e( 'WP to Social — Activity', 'wp-to-social' ); ?></h1>

	<div class="wpts-activity-filters">
		<form method="get">
			<input type="hidden" name="page" value="wpts-activity" />

			<select name="platform">
				<option value=""><?php esc_html_e( 'All Platforms', 'wp-to-social' ); ?></option>
				<option value="linkedin" <?php selected( $current_platform, 'linkedin' ); ?>><?php esc_html_e( 'LinkedIn', 'wp-to-social' ); ?></option>
			</select>

			<select name="status">
				<option value=""><?php esc_html_e( 'All Statuses', 'wp-to-social' ); ?></option>
				<option value="success" <?php selected( $current_status, 'success' ); ?>><?php esc_html_e( 'Success', 'wp-to-social' ); ?></option>
				<option value="failed" <?php selected( $current_status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'wp-to-social' ); ?></option>
			</select>

			<?php
			$post_types = get_post_types( array( 'public' => true ), 'objects' );
			unset( $post_types['attachment'] );
			?>
			<select name="post_type">
				<option value=""><?php esc_html_e( 'All Post Types', 'wp-to-social' ); ?></option>
				<?php foreach ( $post_types as $pt ) : ?>
					<option value="<?php echo esc_attr( $pt->name ); ?>" <?php selected( $current_post_type, $pt->name ); ?>>
						<?php echo esc_html( $pt->labels->singular_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'wp-to-social' ); ?></button>
		</form>
	</div>

	<?php if ( empty( $items ) ) : ?>
		<div class="wpts-empty-state">
			<span class="dashicons dashicons-clipboard"></span>
			<p><?php esc_html_e( 'No activity yet. Posts will appear here after you publish content to social platforms.', 'wp-to-social' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped wpts-activity-table">
			<thead>
				<tr>
					<th class="column-title"><?php esc_html_e( 'Post', 'wp-to-social' ); ?></th>
					<th class="column-platform"><?php esc_html_e( 'Platform', 'wp-to-social' ); ?></th>
					<th class="column-status"><?php esc_html_e( 'Status', 'wp-to-social' ); ?></th>
					<th class="column-date"><?php esc_html_e( 'Date', 'wp-to-social' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Actions', 'wp-to-social' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $item ) : ?>
					<tr>
						<td class="column-title">
							<strong>
								<?php if ( get_post( $item->post_id ) ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $item->post_id ) ); ?>">
										<?php echo esc_html( $item->post_title ?: __( '(no title)', 'wp-to-social' ) ); ?>
									</a>
								<?php else : ?>
									<?php echo esc_html( $item->post_title ?: __( '(deleted)', 'wp-to-social' ) ); ?>
								<?php endif; ?>
							</strong>
							<?php if ( ! empty( $item->post_type ) ) : ?>
								<span class="wpts-post-type-badge"><?php echo esc_html( $item->post_type ); ?></span>
							<?php endif; ?>
						</td>
						<td class="column-platform">
							<?php echo esc_html( ucfirst( $item->platform ) ); ?>
						</td>
						<td class="column-status">
							<?php if ( 'success' === $item->status ) : ?>
								<span class="wpts-status-dot wpts-status-dot--success"></span>
								<?php esc_html_e( 'Success', 'wp-to-social' ); ?>
							<?php elseif ( 'failed' === $item->status ) : ?>
								<span class="wpts-status-dot wpts-status-dot--failed"></span>
								<?php esc_html_e( 'Failed', 'wp-to-social' ); ?>
								<?php if ( ! empty( $item->error_message ) ) : ?>
									<p class="wpts-error-message"><?php echo esc_html( $item->error_message ); ?></p>
								<?php endif; ?>
							<?php else : ?>
								<span class="wpts-status-dot wpts-status-dot--pending"></span>
								<?php esc_html_e( 'Pending', 'wp-to-social' ); ?>
							<?php endif; ?>
						</td>
						<td class="column-date">
							<?php
							echo esc_html(
								date_i18n( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), strtotime( $item->created_at ) )
							);
							?>
						</td>
						<td class="column-actions">
							<?php if ( get_post( $item->post_id ) ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $item->post_id ) ); ?>" class="button button-small">
									<?php esc_html_e( 'View', 'wp-to-social' ); ?>
								</a>
							<?php endif; ?>

							<?php if ( 'failed' === $item->status ) : ?>
								<button type="button"
								        class="button button-small wpts-retry-btn"
								        data-activity-id="<?php echo esc_attr( $item->id ); ?>"
								        data-nonce="<?php echo esc_attr( $retry_nonce ); ?>">
									<?php esc_html_e( 'Retry', 'wp-to-social' ); ?>
								</button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post( paginate_links( array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => $total_pages,
						'type'    => 'plain',
					) ) );
					?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
