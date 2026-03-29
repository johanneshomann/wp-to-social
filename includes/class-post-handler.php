<?php
/**
 * Post handler — hooks into WordPress publish flow and triggers social posting.
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Post_Handler {

	/** @var WPTS_Module_Registry */
	private $registry;

	public function __construct( WPTS_Module_Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Register hooks.
	 */
	public function init() {
		// Use save_post at late priority so meta box save_meta (priority 10) runs first.
		add_action( 'save_post', array( $this, 'on_save_post' ), 99, 2 );

		// REST/Gutenberg fallback.
		$eligible = get_option( 'wpts_eligible_post_types', array() );
		$types    = array();
		foreach ( $eligible as $platform_types ) {
			$types = array_merge( $types, (array) $platform_types );
		}
		foreach ( array_unique( $types ) as $pt ) {
			add_action( "rest_after_insert_{$pt}", array( $this, 'on_rest_publish' ), 10, 2 );
		}

		add_action( 'wp_ajax_wpts_retry_post', array( $this, 'ajax_retry' ) );
	}

	/**
	 * Handle Gutenberg REST API publish — fires after meta is saved.
	 *
	 * @param WP_Post         $post    Post object.
	 * @param WP_REST_Request $request Request object.
	 */
	public function on_rest_publish( $post, $request ) {
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// Avoid running if Classic Editor already handled this.
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return;
		}

		// Check the status param — only trigger on initial publish.
		$status_before = $request->get_param( 'original_status' );
		// WordPress doesn't send original_status, so check our own flag.
		if ( get_post_meta( $post->ID, '_wpts_rest_handled', true ) ) {
			return;
		}

		$this->do_publish( $post, 'rest' );
	}

	/**
	 * Trigger social posting when a post transitions to 'publish'.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	/**
	 * Classic Editor: fires on save_post at priority 99 (after meta box saves at 10).
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function on_save_post( $post_id, $post ) {
		// Only on publish.
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// Skip REST requests — on_rest_publish handles those.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		// Skip autosaves and revisions.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$this->do_publish( $post, 'classic' );
	}

	/**
	 * Shared publish logic used by both Classic Editor and REST (Gutenberg) paths.
	 *
	 * @param WP_Post $post   Post object.
	 * @param string  $source 'classic' or 'rest'.
	 */
	private function do_publish( $post, $source ) {
		$debug = array(
			'time'       => current_time( 'mysql' ),
			'post_id'    => $post->ID,
			'post_type'  => $post->post_type,
			'source'     => $source,
		);

		$eligible = get_option( 'wpts_eligible_post_types', array() );
		$active   = $this->registry->get_active();

		$debug['eligible']        = $eligible;
		$debug['active_modules']  = array_keys( $active );

		foreach ( $active as $slug => $module ) {
			$platform_types = $eligible[ $slug ] ?? array();

			if ( ! in_array( $post->post_type, $platform_types, true ) ) {
				$debug['modules'][ $slug ] = 'skipped: post_type not eligible';
				continue;
			}

			$meta_key = '_wpts_post_to_' . $slug;
			if ( ! get_post_meta( $post->ID, $meta_key, true ) ) {
				$debug['modules'][ $slug ] = 'skipped: checkbox not checked (meta value: ' . var_export( get_post_meta( $post->ID, $meta_key, true ), true ) . ')';
				continue;
			}

			$already_posted_key = '_wpts_' . $slug . '_posted';
			if ( get_post_meta( $post->ID, $already_posted_key, true ) ) {
				$debug['modules'][ $slug ] = 'skipped: already posted';
				continue;
			}

			$debug['modules'][ $slug ] = 'posting...';
			update_option( 'wpts_handler_debug', wp_json_encode( $debug ) );

			$this->post_to_platform( $slug, $module, $post );

			$debug['modules'][ $slug ] = 'done';
		}

		update_option( 'wpts_handler_debug', wp_json_encode( $debug ) );
	}

	/**
	 * Post content to a specific platform.
	 *
	 * @param string  $slug   Platform slug.
	 * @param object  $module Module instance.
	 * @param WP_Post $post   Post object.
	 */
	public function post_to_platform( $slug, $module, $post ) {
		$values = WPTS_Field_Mapper::resolve( $post->ID, $slug );

		if ( empty( $values ) ) {
			return;
		}

		// Generate payload hash to prevent duplicate sends.
		$payload_hash = hash( 'sha256', wp_json_encode( $values ) );

		if ( WPTS_Activity_Logger::hash_exists( $post->ID, $slug, $payload_hash ) ) {
			return;
		}

		$result = $module->publish( $values, $post );

		if ( is_wp_error( $result ) ) {
			WPTS_Activity_Logger::log( array(
				'post_id'       => $post->ID,
				'platform'      => $slug,
				'status'        => 'failed',
				'error_message' => $result->get_error_message(),
				'payload_hash'  => $payload_hash,
			) );

			// Store notice for next admin page load.
			set_transient(
				'wpts_admin_notice_' . get_current_user_id(),
				sprintf(
					/* translators: 1: post title, 2: platform name, 3: error message */
					__( 'WP to Social: Failed to post "%1$s" to %2$s — %3$s', 'wp-to-social' ),
					$post->post_title,
					ucfirst( $slug ),
					$result->get_error_message()
				),
				300
			);
		} else {
			WPTS_Activity_Logger::log( array(
				'post_id'          => $post->ID,
				'platform'         => $slug,
				'status'           => 'success',
				'platform_post_id' => is_string( $result ) ? $result : '',
				'payload_hash'     => $payload_hash,
			) );

			update_post_meta( $post->ID, '_wpts_' . $slug . '_posted', 1 );
		}

		// Clear the checkbox so it doesn't re-trigger.
		delete_post_meta( $post->ID, '_wpts_post_to_' . $slug );
	}

	/**
	 * AJAX handler: retry a failed post.
	 */
	public function ajax_retry() {
		check_ajax_referer( 'wpts_retry_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'wp-to-social' ) );
		}

		$activity_id = absint( $_POST['activity_id'] ?? 0 );
		if ( ! $activity_id ) {
			wp_send_json_error( __( 'Invalid activity ID.', 'wp-to-social' ) );
		}

		global $wpdb;
		$table  = $wpdb->prefix . 'wpts_activity';
		$record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $activity_id ) );

		if ( ! $record || 'failed' !== $record->status ) {
			wp_send_json_error( __( 'Activity not found or not failed.', 'wp-to-social' ) );
		}

		$post = get_post( $record->post_id );
		if ( ! $post ) {
			wp_send_json_error( __( 'Post not longer exists.', 'wp-to-social' ) );
		}

		$module = $this->registry->get( $record->platform );
		if ( ! $module ) {
			wp_send_json_error( __( 'Module not available.', 'wp-to-social' ) );
		}

		// Reset the posted flag so retry can proceed.
		delete_post_meta( $post->ID, '_wpts_' . $record->platform . '_posted' );

		$this->post_to_platform( $record->platform, $module, $post );

		wp_send_json_success( __( 'Retry triggered.', 'wp-to-social' ) );
	}
}
