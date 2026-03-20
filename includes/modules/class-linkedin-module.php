<?php
/**
 * LinkedIn module — OAuth 2.0 authentication and post publishing.
 */

defined( 'ABSPATH' ) || exit;

class WPTS_LinkedIn_Module {

	const API_BASE  = 'https://api.linkedin.com/v2';
	const AUTH_URL  = 'https://www.linkedin.com/oauth/v2/authorization';
	const TOKEN_URL = 'https://www.linkedin.com/oauth/v2/accessToken';

	/**
	 * Module metadata.
	 *
	 * @return array
	 */
	public function get_info() {
		return array(
			'name'        => __( 'LinkedIn', 'wp-to-social' ),
			'slug'        => 'linkedin',
			'icon'        => 'dashicons-linkedin',
			'description' => __( 'Share posts to your LinkedIn profile.', 'wp-to-social' ),
		);
	}

	/**
	 * Credential field labels.
	 *
	 * @return array
	 */
	public function get_credential_labels() {
		return array(
			'id'     => __( 'Client ID', 'wp-to-social' ),
			'secret' => __( 'Client Secret', 'wp-to-social' ),
		);
	}

	/**
	 * Check if API credentials have been saved.
	 *
	 * @return bool
	 */
	public function has_credentials() {
		return ! empty( get_option( 'wpts_linkedin_client_id', '' ) );
	}

	/**
	 * Get platform fields that can be mapped.
	 *
	 * @return array
	 */
	public function get_platform_fields() {
		return array(
			'title' => __( 'Title', 'wp-to-social' ),
			'body'  => __( 'Body Text', 'wp-to-social' ),
			'url'   => __( 'Share URL', 'wp-to-social' ),
			'image' => __( 'Image', 'wp-to-social' ),
		);
	}

	/**
	 * Get the OAuth authorization URL.
	 *
	 * @return string
	 */
	public function get_auth_url() {
		$client_id = $this->get_client_id();

		if ( empty( $client_id ) ) {
			return '';
		}

		$state = wp_generate_password( 32, false );

		set_transient( 'wpts_linkedin_oauth_state_' . get_current_user_id(), $state, 600 );

		$params = array(
			'response_type' => 'code',
			'client_id'     => $client_id,
			'redirect_uri'  => $this->get_redirect_uri(),
			'state'         => $state,
			'scope'         => 'openid profile w_member_social',
		);

		return self::AUTH_URL . '?' . http_build_query( $params );
	}

	/**
	 * Handle OAuth callback — exchange code for tokens.
	 *
	 * @param string $code  Authorization code.
	 * @param string $state State parameter for CSRF verification.
	 * @return true|WP_Error
	 */
	public function handle_callback( $code, $state ) {
		// Verify state (scoped to current user).
		$transient_key = 'wpts_linkedin_oauth_state_' . get_current_user_id();
		$saved_state   = get_transient( $transient_key );
		delete_transient( $transient_key );

		if ( ! $saved_state || ! hash_equals( $saved_state, $state ) ) {
			return new WP_Error( 'invalid_state', __( 'Invalid OAuth state. Please try again.', 'wp-to-social' ) );
		}

		// Exchange code for token.
		$response = wp_remote_post( self::TOKEN_URL, array(
			'body' => array(
				'grant_type'    => 'authorization_code',
				'code'          => $code,
				'redirect_uri'  => $this->get_redirect_uri(),
				'client_id'     => $this->get_client_id(),
				'client_secret' => $this->get_client_secret(),
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			$error_desc = sanitize_text_field( $body['error_description'] ?? '' );
			if ( empty( $error_desc ) ) {
				$error_desc = __( 'Unknown error during token exchange.', 'wp-to-social' );
			}
			return new WP_Error( 'token_error', mb_substr( $error_desc, 0, 200 ) );
		}

		// Fetch profile info.
		$profile = $this->fetch_profile( $body['access_token'] );

		$token_data = array(
			'access_token'  => $body['access_token'],
			'expires_at'    => time() + ( $body['expires_in'] ?? 5184000 ), // Default 60 days.
			'refresh_token' => $body['refresh_token'] ?? '',
			'person_id'     => $profile['sub'] ?? '',
			'profile_name'  => $profile['name'] ?? '',
		);

		$encrypted = WPTS_Encryption::encrypt( wp_json_encode( $token_data ) );
		update_option( 'wpts_linkedin_token', $encrypted );

		return true;
	}

	/**
	 * Fetch LinkedIn profile using the userinfo endpoint.
	 *
	 * @param string $access_token Access token.
	 * @return array Profile data.
	 */
	private function fetch_profile( $access_token ) {
		$response = wp_remote_get( 'https://api.linkedin.com/v2/userinfo', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		return json_decode( wp_remote_retrieve_body( $response ), true ) ?: array();
	}

	/**
	 * Publish a post to LinkedIn.
	 *
	 * @param array   $values Resolved field values.
	 * @param WP_Post $post   WP Post object.
	 * @return string|WP_Error Platform post ID on success, WP_Error on failure.
	 */
	public function publish( $values, $post ) {
		$token_data = $this->get_token_data();

		if ( ! $token_data ) {
			return new WP_Error( 'not_connected', __( 'LinkedIn is not connected. Please reconnect in settings.', 'wp-to-social' ) );
		}

		if ( $this->is_token_expired( $token_data ) ) {
			$refresh = $this->refresh_token( $token_data );
			if ( is_wp_error( $refresh ) ) {
				return $refresh;
			}
			$token_data = $this->get_token_data();
		}

		$body_text = $values['body'] ?? '';
		// LinkedIn has a 3000 char limit on share commentary.
		if ( mb_strlen( $body_text ) > 2900 ) {
			$body_text = mb_substr( $body_text, 0, 2897 ) . '...';
		}

		$payload = array(
			'author'          => 'urn:li:person:' . $token_data['person_id'],
			'lifecycleState'  => 'PUBLISHED',
			'specificContent' => array(
				'com.linkedin.ugc.ShareContent' => array(
					'shareCommentary' => array(
						'text' => $body_text,
					),
					'shareMediaCategory' => 'ARTICLE',
					'media' => array(
						array(
							'status'      => 'READY',
							'originalUrl' => $values['url'] ?? get_permalink( $post->ID ),
							'title'       => array(
								'text' => $values['title'] ?? $post->post_title,
							),
						),
					),
				),
			),
			'visibility' => array(
				'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
			),
		);

		// Add image thumbnail if available.
		$image_url = $values['image'] ?? '';
		if ( ! empty( $image_url ) ) {
			$payload['specificContent']['com.linkedin.ugc.ShareContent']['media'][0]['thumbnails'] = array(
				array( 'url' => $image_url ),
			);
		}

		$response = wp_remote_post( self::API_BASE . '/ugcPosts', array(
			'headers' => array(
				'Authorization'  => 'Bearer ' . $token_data['access_token'],
				'Content-Type'   => 'application/json',
				'X-Restli-Protocol-Version' => '2.0.0',
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = $body['message'] ?? wp_remote_retrieve_response_message( $response );
			return new WP_Error( 'linkedin_api_error', sprintf(
				/* translators: 1: HTTP status code, 2: error message */
				__( 'LinkedIn API error (%1$d): %2$s', 'wp-to-social' ),
				$code,
				$message
			) );
		}

		return $body['id'] ?? '';
	}

	/**
	 * Disconnect LinkedIn (remove stored tokens).
	 */
	public function disconnect() {
		delete_option( 'wpts_linkedin_token' );
	}

	/**
	 * Check if LinkedIn is connected.
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

		return array(
			'connected'    => true,
			'profile_name' => $data['profile_name'] ?? '',
			'expired'      => $this->is_token_expired( $data ),
		);
	}

	/**
	 * Get decrypted token data.
	 *
	 * @return array|null
	 */
	private function get_token_data() {
		$encrypted = get_option( 'wpts_linkedin_token', '' );
		if ( empty( $encrypted ) ) {
			return null;
		}

		$json = WPTS_Encryption::decrypt( $encrypted );
		$data = json_decode( $json, true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Check if token is expired.
	 *
	 * @param array $data Token data.
	 * @return bool
	 */
	private function is_token_expired( $data ) {
		return ( $data['expires_at'] ?? 0 ) < ( time() + 60 );
	}

	/**
	 * Attempt to refresh the access token.
	 *
	 * @param array $data Current token data.
	 * @return true|WP_Error
	 */
	private function refresh_token( $data ) {
		if ( empty( $data['refresh_token'] ) ) {
			$this->disconnect();
			return new WP_Error( 'token_expired', __( 'LinkedIn token expired and no refresh token available. Please reconnect.', 'wp-to-social' ) );
		}

		$response = wp_remote_post( self::TOKEN_URL, array(
			'body' => array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $data['refresh_token'],
				'client_id'     => $this->get_client_id(),
				'client_secret' => $this->get_client_secret(),
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			$this->disconnect();
			return new WP_Error( 'refresh_failed', __( 'Token refresh failed. Please reconnect LinkedIn.', 'wp-to-social' ) );
		}

		$data['access_token'] = $body['access_token'];
		$data['expires_at']   = time() + ( $body['expires_in'] ?? 5184000 );

		if ( ! empty( $body['refresh_token'] ) ) {
			$data['refresh_token'] = $body['refresh_token'];
		}

		$encrypted = WPTS_Encryption::encrypt( wp_json_encode( $data ) );
		update_option( 'wpts_linkedin_token', $encrypted );

		return true;
	}

	/**
	 * Get stored client ID.
	 */
	private function get_client_id() {
		$encrypted = get_option( 'wpts_linkedin_client_id', '' );
		return WPTS_Encryption::decrypt( $encrypted );
	}

	/**
	 * Get stored client secret.
	 */
	private function get_client_secret() {
		$encrypted = get_option( 'wpts_linkedin_client_secret', '' );
		return WPTS_Encryption::decrypt( $encrypted );
	}

	/**
	 * Get the OAuth redirect URI.
	 *
	 * @return string
	 */
	public function get_redirect_uri() {
		return admin_url( 'admin.php?page=wpts-settings&wpts_oauth_callback=linkedin' );
	}
}
