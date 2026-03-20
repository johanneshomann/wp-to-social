<?php
/**
 * Settings page — modules, post types, field mapping.
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Settings_Page {

	/** @var WPTS_Module_Registry */
	private $registry;

	public function __construct( WPTS_Module_Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Initialize — handle form submissions and OAuth callbacks.
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * Handle form submissions and OAuth callbacks.
	 */
	public function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = $_GET['page'] ?? '';
		if ( 'wpts-settings' !== $page ) {
			return;
		}

		// Handle OAuth callback.
		if ( isset( $_GET['wpts_oauth_callback'] ) && 'linkedin' === $_GET['wpts_oauth_callback'] ) {
			$this->handle_oauth_callback();
			return;
		}

		// Handle form submissions.
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}

		$action = sanitize_text_field( $_POST['wpts_action'] ?? '' );

		switch ( $action ) {
			case 'save_credentials':
				$this->save_credentials();
				break;

			case 'toggle_module':
				$this->toggle_module();
				break;

			case 'save_post_types':
				$this->save_post_types();
				break;

			case 'save_field_mapping':
				$this->save_field_mapping();
				break;

			case 'disconnect':
				$this->disconnect_module();
				break;
		}
	}

	/**
	 * Handle LinkedIn OAuth callback.
	 */
	private function handle_oauth_callback() {
		if ( isset( $_GET['error'] ) ) {
			set_transient(
				'wpts_admin_notice_' . get_current_user_id(),
				sprintf( __( 'LinkedIn authorization failed: %s', 'wp-to-social' ), mb_substr( sanitize_text_field( $_GET['error_description'] ?? $_GET['error'] ?? '' ), 0, 200 ) ),
				60
			);
			wp_safe_redirect( admin_url( 'admin.php?page=wpts-settings' ) );
			exit;
		}

		$code  = sanitize_text_field( $_GET['code'] ?? '' );
		$state = sanitize_text_field( $_GET['state'] ?? '' );

		if ( empty( $code ) || empty( $state ) ) {
			return;
		}

		$module = $this->registry->get( 'linkedin' );
		if ( ! $module ) {
			return;
		}

		$result = $module->handle_callback( $code, $state );

		if ( is_wp_error( $result ) ) {
			set_transient(
				'wpts_admin_notice_' . get_current_user_id(),
				$result->get_error_message(),
				60
			);
			wp_safe_redirect( admin_url( 'admin.php?page=wpts-settings' ) );
		} else {
			// Auto-activate the module on successful connection.
			$this->registry->activate( 'linkedin' );
			wp_safe_redirect( admin_url( 'admin.php?page=wpts-settings&wpts_connected=linkedin' ) );
		}

		exit;
	}

	/**
	 * Save API credentials.
	 */
	private function save_credentials() {
		check_admin_referer( 'wpts_save_credentials' );

		$module = sanitize_text_field( $_POST['wpts_module'] ?? '' );

		if ( 'linkedin' === $module ) {
			$client_id     = sanitize_text_field( $_POST['wpts_client_id'] ?? '' );
			$client_secret = sanitize_text_field( $_POST['wpts_client_secret'] ?? '' );

			// Only update if a real value was submitted (not the placeholder or empty).
			if ( ! empty( $client_id ) && false === strpos( $client_id, '•' ) ) {
				update_option( 'wpts_linkedin_client_id', WPTS_Encryption::encrypt( $client_id ) );
			}
			if ( ! empty( $client_secret ) ) {
				update_option( 'wpts_linkedin_client_secret', WPTS_Encryption::encrypt( $client_secret ) );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wpts-settings&tab=modules&saved=1' ) );
		exit;
	}

	/**
	 * Toggle a module on/off.
	 */
	private function toggle_module() {
		check_admin_referer( 'wpts_toggle_module' );

		$module = sanitize_text_field( $_POST['wpts_module'] ?? '' );
		$enable = ! empty( $_POST['wpts_enable'] );

		if ( $enable ) {
			$this->registry->activate( $module );
		} else {
			$this->registry->deactivate( $module );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wpts-settings&tab=modules&saved=1' ) );
		exit;
	}

	/**
	 * Save eligible post types.
	 */
	private function save_post_types() {
		check_admin_referer( 'wpts_save_post_types' );

		$module     = sanitize_text_field( $_POST['wpts_module'] ?? '' );
		$post_types = array_map( 'sanitize_text_field', (array) ( $_POST['wpts_post_types'] ?? array() ) );

		$eligible            = get_option( 'wpts_eligible_post_types', array() );
		$eligible[ $module ] = $post_types;
		update_option( 'wpts_eligible_post_types', $eligible );

		wp_safe_redirect( admin_url( 'admin.php?page=wpts-settings&tab=post-types&saved=1' ) );
		exit;
	}

	/**
	 * Save field mapping.
	 */
	private function save_field_mapping() {
		check_admin_referer( 'wpts_save_field_mapping' );

		$module    = sanitize_text_field( $_POST['wpts_module'] ?? '' );
		$post_type = sanitize_text_field( $_POST['wpts_post_type'] ?? '' );
		$mapping   = array_map( 'sanitize_text_field', (array) ( $_POST['wpts_mapping'] ?? array() ) );

		WPTS_Field_Mapper::save_mapping( $module, $post_type, $mapping );

		wp_safe_redirect( admin_url( 'admin.php?page=wpts-settings&tab=field-mapping&saved=1' ) );
		exit;
	}

	/**
	 * Disconnect a module.
	 */
	private function disconnect_module() {
		check_admin_referer( 'wpts_disconnect_module' );

		$module_slug = sanitize_text_field( $_POST['wpts_module'] ?? '' );
		$module      = $this->registry->get( $module_slug );

		if ( $module && method_exists( $module, 'disconnect' ) ) {
			$module->disconnect();
		}

		$this->registry->deactivate( $module_slug );

		wp_safe_redirect( admin_url( 'admin.php?page=wpts-settings&tab=modules' ) );
		exit;
	}

	/**
	 * Render the settings page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-to-social' ) );
		}
		$tab = sanitize_text_field( $_GET['tab'] ?? 'modules' );
		include WPTS_PLUGIN_DIR . 'templates/admin/settings.php';
	}
}
