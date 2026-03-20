<?php
/**
 * Instagram module — Facebook OAuth, Instagram Graph API publishing.
 *
 * Requires an Instagram Business or Creator account linked to a Facebook Page.
 * Uses the two-step container publish flow: create container -> publish.
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Instagram_Module {

	const API_BASE  = 'https://graph.facebook.com/v19.0';
	const AUTH_URL  = 'https://www.facebook.com/v19.0/dialog/oauth';
	const TOKEN_URL = 'https://graph.facebook.com/v19.0/oauth/access_token';

	/**
	 * Module metadata.
	 *
	 * @return array
	 */
	public function get_info() {
		return array(
			'name'        => __( 'Instagram', 'wp-to-social' ),
			'slug'        => 'instagram',
			'icon'        => 'dashicons-camera',
			'description' => __( 'Share image posts to your Instagram Business account.', 'wp-to-social' ),
		);
	}

	/**
	 * Credential field labels (differs from LinkedIn's "Client ID / Client Secret").
	 *
	 * @return array
	 */
	public function get_credential_labels() {
		return array(
			'id'     => __( 'App ID', 'wp-to-social' ),
			'secret' => __( 'App Secret', 'wp-to-social' ),
		);
	}

	/**
	 * Check if API credentials have been saved.
	 *
	 * @return bool
	 */
	public function has_credentials() {
		return ! empty( get_option( 'wpts_instagram_app_id', '' ) );
	}

	/**
	 * Get platform fields that can be mapped.
	 *
	 * @return array
	 */
	public function get_platform_fields() {
		return array(
			'caption'   => __( 'Caption', 'wp-to-social' ),
			'image_url' => __( 'Image URL (required)', 'wp-to-social' ),
			'alt_text'  => __( 'Alt Text', 'wp-to-social' ),
		);
	}

	/**
	 * Get the OAuth authorization URL (Facebook Login).
	 *
	 * @return string
	 */
	public function get_auth_url() {
		$app_id = $this->get_app_id();

		if ( empty( $app_id ) ) {
			return '';
		}

		$state = wp_generate_password( 32, false );

		set_transient( 'wpts_instagram_oauth_state_' . get_current_user_id(), $state, 600 );

		$params = array(
			'client_id'     => $app_id,
			'redirect_uri'  => $this->get_redirect_uri(),
			'state'         => $state,
			'scope'         => 'instagram_basic,instagram_content_publish,pages_read_engagement,pages_show_list',
			'response_type' => 'code',
		);

		return self::AUTH_URL . '?' . http_build_query( $params );
	}

	/**
	 * Handle OAuth callback — exchange code, get long-lived token, discover IG account.
	 *
	 * @param string $code  Authorization code.
	 * @param string $state State parameter for CSRF verification.
	 * @return true|WP_Error
	 */
	public function handle_callback( $code, $state ) {
		// Verify state.
		$transient_key = 'wpts_instagram_oauth_state_' . get_current_user_id();
		$saved_state   = get_transient( $transient_key );
		delete_transient( $transient_key );

		if ( ! $saved_state || ! hash_equals( $saved_state, $state ) ) {
			return new WP_Error( 'invalid_state', __( 'Invalid OAuth state. Please try again.', 'wp-to-social' ) );
		}

		// Step 1: Exchange code for short-lived token.
		$response = wp_remote_get( add_query_arg( array(
			'client_id'     => $this->get_app_id(),
			'client_secret' => $this->get_app_secret(),
			'redirect_uri'  => $this->get_redirect_uri(),
			'code'          => $code,
		), self::TOKEN_URL ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			$error = sanitize_text_field( $body['error']['message'] ?? '' );
			return new WP_Error( 'token_error', mb_substr( $error ?: __( 'Failed to obtain access token.', 'wp-to-social' ), 0, 200 ) );
		}

		// Step 2: Exchange for long-lived token.
		$ll_response = wp_remote_get( add_query_arg( array(
			'grant_type'        => 'fb_exchange_token',
			'client_id'         => $this->get_app_id(),
			'client_secret'     => $this->get_app_secret(),
			'fb_exchange_token' => $body['access_token'],
		), self::TOKEN_URL ) );

		if ( is_wp_error( $ll_response ) ) {
			return $ll_response;
		}

		$ll_body = json_decode( wp_remote_retrieve_body( $ll_response ), true );

		if ( empty( $ll_body['access_token'] ) ) {
			return new WP_Error( 'token_error', __( 'Failed to obtain long-lived token.', 'wp-to-social' ) );
		}

		$access_token = $ll_body['access_token'];
		$expires_in   = $ll_body['expires_in'] ?? 5184000; // Default 60 days.

		// Step 3: Discover Instagram Business Account.
		$ig_account = $this->discover_ig_account( $access_token );

		if ( is_wp_error( $ig_account ) ) {
			return $ig_account;
		}

		$token_data = array(
			'access_token' => $access_token,
			'expires_at'   => time() + $expires_in,
			'ig_user_id'   => $ig_account['ig_id'],
			'ig_username'  => $ig_account['ig_username'],
			'page_name'    => $ig_account['page_name'],
		);

		$encrypted = WPTS_Encryption::encrypt( wp_json_encode( $token_data ) );
		update_option( 'wpts_instagram_token', $encrypted );

		return true;
	}

	/**
	 * Discover the Instagram Business Account linked to the user's Facebook Pages.
	 *
	 * @param string $access_token Facebook access token.
	 * @return array|WP_Error { ig_id, ig_username, page_name }
	 */
	private function discover_ig_account( $access_token ) {
		$response = wp_remote_get( add_query_arg(
			array(
				'fields'       => 'id,name,instagram_business_account{id,username}',
				'access_token' => $access_token,
			),
			self::API_BASE . '/me/accounts'
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['data'] ) ) {
			return new WP_Error( 'no_pages', __( 'No Facebook Pages found. Your Instagram Business account must be linked to a Facebook Page.', 'wp-to-social' ) );
		}

		// Find the first page with a linked Instagram Business Account.
		foreach ( $body['data'] as $page ) {
			if ( ! empty( $page['instagram_business_account']['id'] ) ) {
				return array(
					'ig_id'       => $page['instagram_business_account']['id'],
					'ig_username' => $page['instagram_business_account']['username'] ?? '',
					'page_name'   => $page['name'] ?? '',
				);
			}
		}

		return new WP_Error( 'no_ig_account', __( 'No Instagram Business or Creator account found linked to your Facebook Pages. Please convert your Instagram account to a Business or Creator account and link it to a Facebook Page.', 'wp-to-social' ) );
	}

	/**
	 * Publish a post to Instagram (two-step: create container, then publish).
	 *
	 * @param array   $values Resolved field values.
	 * @param WP_Post $post   WP Post object.
	 * @return string|WP_Error Platform post ID on success, WP_Error on failure.
	 */
	public function publish( $values, $post ) {
		$token_data = $this->get_token_data();

		if ( ! $token_data ) {
			return new WP_Error( 'not_connected', __( 'Instagram is not connected. Please reconnect in settings.', 'wp-to-social' ) );
		}

		if ( $this->is_token_expired( $token_data ) ) {
			$refresh = $this->refresh_token( $token_data );
			if ( is_wp_error( $refresh ) ) {
				return $refresh;
			}
			$token_data = $this->get_token_data();
		}

		// Instagram requires an image.
		$image_url = $values['image_url'] ?? '';
		if ( empty( $image_url ) ) {
			return new WP_Error( 'missing_image', __( 'Instagram posts require an image. Please set a featured image for this post.', 'wp-to-social' ) );
		}

		// Pre-flight: verify image is publicly accessible.
		$head = wp_remote_head( $image_url, array( 'timeout' => 10 ) );
		if ( is_wp_error( $head ) || 200 !== wp_remote_retrieve_response_code( $head ) ) {
			return new WP_Error( 'image_not_accessible', sprintf(
				/* translators: %s: image URL */
				__( 'Instagram requires your image to be publicly accessible. Could not reach: %s', 'wp-to-social' ),
				esc_url( $image_url )
			) );
		}

		// Build caption.
		$caption = $values['caption'] ?? '';
		if ( mb_strlen( $caption ) > 2200 ) {
			$caption = mb_substr( $caption, 0, 2197 ) . '...';
		}

		// Step 1: Create media container.
		$container_args = array(
			'image_url'    => $image_url,
			'caption'      => $caption,
			'access_token' => $token_data['access_token'],
		);

		$alt_text = $values['alt_text'] ?? '';
		if ( ! empty( $alt_text ) ) {
			$container_args['alt_text'] = mb_substr( $alt_text, 0, 1000 );
		}

		$container_response = wp_remote_post(
			self::API_BASE . '/' . $token_data['ig_user_id'] . '/media',
			array(
				'body'    => $container_args,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $container_response ) ) {
			return $container_response;
		}

		$container_body = json_decode( wp_remote_retrieve_body( $container_response ), true );

		if ( empty( $container_body['id'] ) ) {
			$error_msg = $container_body['error']['message'] ?? __( 'Failed to create media container.', 'wp-to-social' );
			return new WP_Error( 'container_error', sanitize_text_field( mb_substr( $error_msg, 0, 200 ) ) );
		}

		$container_id = $container_body['id'];

		// Step 2: Wait for container to be ready (usually instant for images).
		$ready   = false;
		$retries = 0;
		while ( $retries < 3 && ! $ready ) {
			$status_response = wp_remote_get( add_query_arg(
				array(
					'fields'       => 'status_code',
					'access_token' => $token_data['access_token'],
				),
				self::API_BASE . '/' . $container_id
			) );

			if ( ! is_wp_error( $status_response ) ) {
				$status_body = json_decode( wp_remote_retrieve_body( $status_response ), true );
				$status_code = $status_body['status_code'] ?? '';

				if ( 'FINISHED' === $status_code ) {
					$ready = true;
					break;
				}
				if ( 'ERROR' === $status_code ) {
					return new WP_Error( 'container_failed', __( 'Instagram rejected the media. Please check image format and dimensions.', 'wp-to-social' ) );
				}
			}
			$retries++;
			if ( ! $ready ) {
				sleep( 2 );
			}
		}

		if ( ! $ready ) {
			return new WP_Error( 'container_timeout', __( 'Instagram media processing timed out. Please try again.', 'wp-to-social' ) );
		}

		// Step 3: Publish the container.
		$publish_response = wp_remote_post(
			self::API_BASE . '/' . $token_data['ig_user_id'] . '/media_publish',
			array(
				'body'    => array(
					'creation_id'  => $container_id,
					'access_token' => $token_data['access_token'],
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $publish_response ) ) {
			return $publish_response;
		}

		$publish_body = json_decode( wp_remote_retrieve_body( $publish_response ), true );

		if ( empty( $publish_body['id'] ) ) {
			$error_msg = $publish_body['error']['message'] ?? __( 'Failed to publish to Instagram.', 'wp-to-social' );
			return new WP_Error( 'publish_error', sanitize_text_field( mb_substr( $error_msg, 0, 200 ) ) );
		}

		return $publish_body['id'];
	}

	/**
	 * Disconnect Instagram (remove stored tokens).
	 */
	public function disconnect() {
		delete_option( 'wpts_instagram_token' );
	}

	/**
	 * Check if Instagram is connected.
	 *
	 * @return bool
	 */
	public function is_connected() {
		$data = $this->get_token_data();
		return ! empty( $data['access_token'] );
	}

	/**
	 * Get connection status info.
	 *
	 * @return array
	 */
	public function get_connection_status() {
		$data = $this->get_token_data();

		if ( empty( $data ) ) {
			return array(
				'connected'    => false,
				'profile_name' => '',
				'expired'      => false,
			);
		}

		$name = $data['ig_username'] ?? '';
		if ( ! empty( $name ) ) {
			$name = '@' . $name;
		}

		return array(
			'connected'    => true,
			'profile_name' => $name,
			'expired'      => $this->is_token_expired( $data ),
		);
	}

	/**
	 * Get decrypted token data.
	 *
	 * @return array|null
	 */
	private function get_token_data() {
		$encrypted = get_option( 'wpts_instagram_token', '' );
		if ( empty( $encrypted ) ) {
			return null;
		}

		$json = WPTS_Encryption::decrypt( $encrypted );
		$data = json_decode( $json, true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Check if token is expired (with 60-second buffer).
	 *
	 * @param array $data Token data.
	 * @return bool
	 */
	private function is_token_expired( $data ) {
		return ( $data['expires_at'] ?? 0 ) < ( time() + 60 );
	}

	/**
	 * Refresh the long-lived token.
	 *
	 * Facebook long-lived tokens are refreshed by exchanging them again.
	 * This only works while the token is still valid.
	 *
	 * @param array $data Current token data.
	 * @return true|WP_Error
	 */
	private function refresh_token( $data ) {
		$response = wp_remote_get( add_query_arg( array(
			'grant_type'        => 'fb_exchange_token',
			'client_id'         => $this->get_app_id(),
			'client_secret'     => $this->get_app_secret(),
			'fb_exchange_token' => $data['access_token'],
		), self::TOKEN_URL ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			$this->disconnect();
			return new WP_Error( 'refresh_failed', __( 'Token refresh failed. Please reconnect Instagram.', 'wp-to-social' ) );
		}

		$data['access_token'] = $body['access_token'];
		$data['expires_at']   = time() + ( $body['expires_in'] ?? 5184000 );

		$encrypted = WPTS_Encryption::encrypt( wp_json_encode( $data ) );
		update_option( 'wpts_instagram_token', $encrypted );

		return true;
	}

	/**
	 * Get stored App ID.
	 */
	private function get_app_id() {
		$encrypted = get_option( 'wpts_instagram_app_id', '' );
		return WPTS_Encryption::decrypt( $encrypted );
	}

	/**
	 * Get stored App Secret.
	 */
	private function get_app_secret() {
		$encrypted = get_option( 'wpts_instagram_app_secret', '' );
		return WPTS_Encryption::decrypt( $encrypted );
	}

	/**
	 * Get the OAuth redirect URI.
	 *
	 * @return string
	 */
	public function get_redirect_uri() {
		return admin_url( 'admin.php?page=wpts-settings&wpts_oauth_callback=instagram' );
	}
}
