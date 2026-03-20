<?php
/**
 * Activity logger — log and query social posting attempts.
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Activity_Logger {

	const DB_VERSION = '1.0';

	/**
	 * Create or update the activity table.
	 */
	public static function create_table() {
		global $wpdb;

		$table   = $wpdb->prefix . 'wpts_activity';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			platform VARCHAR(50) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			platform_post_id VARCHAR(255) DEFAULT NULL,
			error_message TEXT DEFAULT NULL,
			payload_hash VARCHAR(64) DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_post_id (post_id),
			KEY idx_platform_status (platform, status),
			KEY idx_created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'wpts_db_version', self::DB_VERSION );
	}

	/**
	 * Log a posting attempt.
	 *
	 * @param array $data {
	 *     @type int    $post_id          WP Post ID.
	 *     @type string $platform         Platform slug.
	 *     @type string $status           'success', 'failed', or 'pending'.
	 *     @type string $platform_post_id Platform's post identifier (optional).
	 *     @type string $error_message    Error message on failure (optional).
	 *     @type string $payload_hash     Hash to detect duplicates (optional).
	 * }
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function log( $data ) {
		global $wpdb;

		$defaults = array(
			'post_id'          => 0,
			'platform'         => '',
			'status'           => 'pending',
			'platform_post_id' => null,
			'error_message'    => null,
			'payload_hash'     => null,
			'created_at'       => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		// Normalise timestamp to valid MySQL datetime.
		$timestamp = strtotime( $data['created_at'] );
		$data['created_at'] = gmdate( 'Y-m-d H:i:s', $timestamp ? $timestamp : time() );

		$result = $wpdb->insert(
			$wpdb->prefix . 'wpts_activity',
			array(
				'post_id'          => absint( $data['post_id'] ),
				'platform'         => sanitize_text_field( $data['platform'] ),
				'status'           => sanitize_text_field( $data['status'] ),
				'platform_post_id' => $data['platform_post_id'] ? sanitize_text_field( $data['platform_post_id'] ) : null,
				'error_message'    => $data['error_message'] ? sanitize_textarea_field( $data['error_message'] ) : null,
				'payload_hash'     => $data['payload_hash'] ? sanitize_text_field( $data['payload_hash'] ) : null,
				'created_at'       => $data['created_at'],
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return ( false !== $result ) ? $wpdb->insert_id : false;
	}

	/**
	 * Query activity entries.
	 *
	 * @param array $args Query arguments.
	 * @return object { items: array, total: int }
	 */
	public static function query( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'platform'  => '',
			'status'    => '',
			'post_type' => '',
			'per_page'  => 20,
			'page'      => 1,
			'orderby'   => 'created_at',
			'order'     => 'DESC',
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = $wpdb->prefix . 'wpts_activity';
		$where = array( '1=1' );
		$vals  = array();

		if ( ! empty( $args['platform'] ) ) {
			$where[] = 'a.platform = %s';
			$vals[]  = $args['platform'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'a.status = %s';
			$vals[]  = $args['status'];
		}

		if ( ! empty( $args['post_type'] ) ) {
			$where[] = 'p.post_type = %s';
			$vals[]  = $args['post_type'];
		}

		$where_sql = implode( ' AND ', $where );

		$allowed_orderby = array( 'created_at', 'id', 'platform', 'status' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = absint( $args['per_page'] );
		$offset   = ( absint( $args['page'] ) - 1 ) * $per_page;

		// Count query.
		$count_sql = "SELECT COUNT(*) FROM {$table} a LEFT JOIN {$wpdb->posts} p ON p.ID = a.post_id WHERE {$where_sql}";
		if ( ! empty( $vals ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $vals ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Items query.
		$items_sql = "SELECT a.*, p.post_title, p.post_type
			FROM {$table} a
			LEFT JOIN {$wpdb->posts} p ON p.ID = a.post_id
			WHERE {$where_sql}
			ORDER BY a.{$orderby} {$order}
			LIMIT %d OFFSET %d";

		$vals[] = $per_page;
		$vals[] = $offset;

		$items = $wpdb->get_results( $wpdb->prepare( $items_sql, $vals ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return (object) array(
			'items' => $items ?: array(),
			'total' => $total,
		);
	}

	/**
	 * Check if a payload hash already exists for a post+platform (prevent duplicates).
	 *
	 * @param int    $post_id      Post ID.
	 * @param string $platform     Platform slug.
	 * @param string $payload_hash Hash to check.
	 * @return bool
	 */
	public static function hash_exists( $post_id, $platform, $payload_hash ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpts_activity';

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE post_id = %d AND platform = %s AND payload_hash = %s AND status = 'success'",
				$post_id,
				$platform,
				$payload_hash
			)
		);
	}
}
