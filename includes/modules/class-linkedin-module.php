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
			'description' => __( 'Share posts to your LinkedIn profile or company page.', 'wp-to-social' ),
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
	 * Step-by-step setup instructions shown in the module card accordion.
	 *
	 * @return array
	 */
	public function get_setup_steps() {
		return array(
			array(
				'title' => __( 'Create a LinkedIn App', 'wp-to-social' ),
				'desc'  => __( 'Go to the <a href="https://www.linkedin.com/developers/apps/new" target="_blank" rel="noopener">LinkedIn Developer Portal</a> and click "Create App". Fill in your app name, your LinkedIn Page, and upload a logo.', 'wp-to-social' ),
			),
			array(
				'title' => __( 'Add the required products', 'wp-to-social' ),
				'desc'  => __( 'In your app dashboard, go to the "Products" tab. Request access to <strong>Sign In with LinkedIn using OpenID Connect</strong>, <strong>Share on LinkedIn</strong>, and <strong>Advertising on LinkedIn</strong> (required for company page posting). The first two are approved instantly; Advertising may require verification.', 'wp-to-social' ),
			),
			array(
				'title' => __( 'Copy your credentials', 'wp-to-social' ),
				'desc'  => __( 'Go to the "Auth" tab in your LinkedIn app. Copy the <strong>Client ID</strong> and <strong>Client Secret</strong>, then paste them into the fields above and click <strong>Save Credentials</strong>.', 'wp-to-social' ),
			),
			array(
				'title' => __( 'Set the Redirect URI', 'wp-to-social' ),
				'desc'  => __( 'Still on the "Auth" tab, scroll to "OAuth 2.0 settings". Click "Add redirect URL" and paste the <strong>Redirect URI</strong> shown below the Save button on this page.', 'wp-to-social' ),
			),
			array(
				'title' => __( 'Verify your app on your company page (for company page posting)', 'wp-to-social' ),
				'desc'  => __( 'In the "Settings" tab of your LinkedIn app, click <strong>Verify</strong> next to your associated company page. A page admin must confirm. This unlocks the <code>w_organization_social</code> scope needed to post as your company.', 'wp-to-social' ),
			),
			array(
				'title' => __( 'Connect your account', 'wp-to-social' ),
				'desc'  => __( 'Click the <strong>Connect with LinkedIn</strong> button that appears after saving your credentials. You will be redirected to LinkedIn to authorize access. Once approved, you can choose whether to post as your personal profile or as a company page.', 'wp-to-social' ),
			),
		);
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
			'scope'         => $this->is_org_posting_enabled()
				? 'openid profile w_member_social r_organization_social w_organization_social'
				: 'openid profile w_member_social',
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

		// Fetch administered organizations (only if org posting is enabled).
		$organizations = $this->is_org_posting_enabled()
			? $this->fetch_organizations( $body['access_token'] )
			: array();

		$token_data = array(
			'access_token'  => $body['access_token'],
			'expires_at'    => time() + ( $body['expires_in'] ?? 5184000 ), // Default 60 days.
			'refresh_token' => $body['refresh_token'] ?? '',
			'person_id'     => $profile['sub'] ?? '',
			'profile_name'  => $profile['name'] ?? '',
			'organizations' => $organizations,
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
	 * Fetch organizations the authenticated user administers.
	 *
	 * Uses the LinkedIn Community Management (versioned REST) API.
	 *
	 * @param string $access_token Access token.
	 * @return array Array of [ 'id' => org_id, 'name' => org_name ].
	 */
	private function fetch_organizations( $access_token ) {
		$headers = array(
			'Authorization'    => 'Bearer ' . $access_token,
			'LinkedIn-Version' => '202603',
		);

		// Step 1: Get organization ACLs for the authenticated user.
		$response = wp_remote_get( 'https://api.linkedin.com/rest/organizationAcls?q=roleAssignee', array(
			'headers' => $headers,
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			update_option( 'wpts_linkedin_org_debug', 'WP_Error: ' . $response->get_error_message() );
			return array();
		}

		$raw_body    = wp_remote_retrieve_body( $response );
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( $raw_body, true );

		// Store debug info so we can see what LinkedIn returned.
		update_option( 'wpts_linkedin_org_debug', wp_json_encode( array(
			'status' => $status_code,
			'body'   => $body,
		) ) );

		if ( empty( $body['elements'] ) || ! is_array( $body['elements'] ) ) {
			return array();
		}

		// Step 2: Extract org IDs and fetch each organization's name.
		$orgs = array();
		foreach ( $body['elements'] as $element ) {
			$org_urn = $element['organization'] ?? '';

			if ( preg_match( '/urn:li:organization:(\d+)/', $org_urn, $matches ) ) {
				$org_id   = $matches[1];
				$org_name = $this->fetch_organization_name( $access_token, $org_id );

				$orgs[] = array(
					'id'   => $org_id,
					'name' => $org_name ?: __( 'Organization', 'wp-to-social' ) . ' ' . $org_id,
				);
			}
		}

		return $orgs;
	}

	/**
	 * Fetch a single organization's name by ID.
	 *
	 * @param string $access_token Access token.
	 * @param string $org_id       Organization ID.
	 * @return string Organization name, or empty string on failure.
	 */
	private function fetch_organization_name( $access_token, $org_id ) {
		$response = wp_remote_get( 'https://api.linkedin.com/rest/organizations/' . $org_id, array(
			'headers' => array(
				'Authorization'    => 'Bearer ' . $access_token,
				'LinkedIn-Version' => '202603',
			),
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return $body['localizedName'] ?? '';
	}

	/**
	 * Check if company page posting is enabled.
	 *
	 * @return bool
	 */
	public function is_org_posting_enabled() {
		return (bool) get_option( 'wpts_linkedin_org_posting', false );
	}

	/**
	 * Enable or disable company page posting.
	 *
	 * @param bool $enabled Whether to enable org posting.
	 */
	public function set_org_posting_enabled( $enabled ) {
		update_option( 'wpts_linkedin_org_posting', $enabled ? 1 : 0 );
	}

	/**
	 * Get available posting targets (personal profile + organizations).
	 *
	 * @return array Array of [ 'value' => urn_string, 'label' => display_name ].
	 */
	public function get_posting_targets() {
		$data = $this->get_token_data();
		if ( empty( $data ) ) {
			return array();
		}

		$targets = array(
			array(
				'value' => 'person:' . $data['person_id'],
				'label' => sprintf(
					/* translators: %s: profile name */
					__( 'Personal profile (%s)', 'wp-to-social' ),
					$data['profile_name'] ?: __( 'Unknown', 'wp-to-social' )
				),
			),
		);

		if ( ! empty( $data['organizations'] ) ) {
			foreach ( $data['organizations'] as $org ) {
				$targets[] = array(
					'value' => 'organization:' . $org['id'],
					'label' => sprintf(
						/* translators: %s: organization name */
						__( 'Company page: %s', 'wp-to-social' ),
						$org['name']
					),
				);
			}
		}

		return $targets;
	}

	/**
	 * Get the currently selected posting target.
	 *
	 * @return string Target value (e.g. "person:abc123" or "organization:12345").
	 */
	public function get_posting_target() {
		$target = get_option( 'wpts_linkedin_posting_target', '' );

		if ( empty( $target ) ) {
			$data = $this->get_token_data();
			return $data ? 'person:' . $data['person_id'] : '';
		}

		return $target;
	}

	/**
	 * Save the posting target.
	 *
	 * @param string $target Target value.
	 */
	public function save_posting_target( $target ) {
		update_option( 'wpts_linkedin_posting_target', sanitize_text_field( $target ) );
	}

	/**
	 * Build the author URN for the API payload.
	 *
	 * @return string URN string like "urn:li:person:abc" or "urn:li:organization:123".
	 */
	private function get_author_urn() {
		$target = $this->get_posting_target();

		if ( str_starts_with( $target, 'organization:' ) ) {
			$org_id = substr( $target, strlen( 'organization:' ) );
			return 'urn:li:organization:' . $org_id;
		}

		$data = $this->get_token_data();
		return 'urn:li:person:' . ( $data['person_id'] ?? '' );
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

		$share_url = $values['url'] ?? get_permalink( $post->ID );

		$payload = array(
			'author'          => $this->get_author_urn(),
			'lifecycleState'  => 'PUBLISHED',
			'visibility'      => 'PUBLIC',
			'commentary'      => $body_text,
			'distribution'    => array(
				'feedDistribution'               => 'MAIN_FEED',
				'targetEntities'                 => array(),
				'thirdPartyDistributionChannels' => array(),
			),
			'content'         => array(
				'article' => array(
					'source'      => $share_url,
					'title'       => $values['title'] ?? $post->post_title,
				),
			),
		);

		// Add image thumbnail if available.
		$image_url = $values['image'] ?? '';
		if ( ! empty( $image_url ) ) {
			$payload['content']['article']['thumbnail'] = $image_url;
		}

		$response = wp_remote_post( 'https://api.linkedin.com/rest/posts', array(
			'headers' => array(
				'Authorization'    => 'Bearer ' . $token_data['access_token'],
				'Content-Type'     => 'application/json',
				'LinkedIn-Version' => '202603',
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

		// The versioned Posts API returns the post URN in the x-restli-id header.
		$post_urn = wp_remote_retrieve_header( $response, 'x-restli-id' );
		return $post_urn ?: ( $body['id'] ?? '' );
	}

	/**
	 * Disconnect LinkedIn (remove stored tokens).
	 */
	public function disconnect() {
		delete_option( 'wpts_linkedin_token' );
		delete_option( 'wpts_linkedin_posting_target' );
		delete_option( 'wpts_linkedin_org_posting' );
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

		// Determine display name based on posting target.
		$target      = $this->get_posting_target();
		$target_name = $data['profile_name'] ?? '';

		if ( str_starts_with( $target, 'organization:' ) ) {
			$org_id = substr( $target, strlen( 'organization:' ) );
			foreach ( ( $data['organizations'] ?? array() ) as $org ) {
				if ( $org['id'] === $org_id ) {
					$target_name = $org['name'];
					break;
				}
			}
		}

		return array(
			'connected'       => true,
			'profile_name'    => $target_name,
			'expired'         => $this->is_token_expired( $data ),
			'has_targets'     => count( $this->get_posting_targets() ) > 1,
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
