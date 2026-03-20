<?php
/**
 * Activity page — display social posting log with filters.
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Activity_Page {

	/**
	 * Render the activity page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-to-social' ) );
		}
		include WPTS_PLUGIN_DIR . 'templates/admin/activity.php';
	}

	/**
	 * Get filtered activity results for the current request.
	 *
	 * @return object { items, total }
	 */
	public function get_results() {
		$args = array(
			'platform'  => sanitize_text_field( $_GET['platform'] ?? '' ),
			'status'    => sanitize_text_field( $_GET['status'] ?? '' ),
			'post_type' => sanitize_text_field( $_GET['post_type'] ?? '' ),
			'page'      => absint( $_GET['paged'] ?? 1 ),
			'per_page'  => 20,
		);

		return WPTS_Activity_Logger::query( $args );
	}
}
