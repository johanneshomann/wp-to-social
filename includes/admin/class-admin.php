<?php
/**
 * Admin — menu registration, asset loading, notices.
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Admin {

	/** @var WPTS_Module_Registry */
	private $registry;

	public function __construct( WPTS_Module_Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'display_notices' ) );
	}

	/**
	 * Register admin menu pages.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'WP to Social', 'wp-to-social' ),
			__( 'WP to Social', 'wp-to-social' ),
			'manage_options',
			'wpts-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-share',
			80
		);

		add_submenu_page(
			'wpts-settings',
			__( 'Settings', 'wp-to-social' ),
			__( 'Settings', 'wp-to-social' ),
			'manage_options',
			'wpts-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'wpts-settings',
			__( 'Activity', 'wp-to-social' ),
			__( 'Activity', 'wp-to-social' ),
			'manage_options',
			'wpts-activity',
			array( $this, 'render_activity_page' )
		);
	}

	/**
	 * Enqueue admin CSS and JS on plugin pages only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( $hook_suffix ) {
		$plugin_pages = array(
			'toplevel_page_wpts-settings',
			'wp-to-social_page_wpts-activity',
		);

		if ( in_array( $hook_suffix, $plugin_pages, true ) ) {
			wp_enqueue_style(
				'wpts-admin',
				WPTS_PLUGIN_URL . 'assets/css/admin.css',
				array(),
				WPTS_VERSION
			);

			wp_enqueue_script(
				'wpts-admin',
				WPTS_PLUGIN_URL . 'assets/js/admin.js',
				array( 'jquery' ),
				WPTS_VERSION,
				true
			);

			wp_localize_script( 'wpts-admin', 'wpts', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wpts_retry_nonce' ),
				'i18n'     => array(
					'confirm_disconnect' => __( 'Disconnect this account? You will need to re-authorize.', 'wp-to-social' ),
					'retrying'           => __( 'Retrying...', 'wp-to-social' ),
					'retry_success'      => __( 'Retry triggered. Refresh to see results.', 'wp-to-social' ),
					'retry_failed'       => __( 'Retry failed.', 'wp-to-social' ),
				),
			) );
		}
	}

	/**
	 * Display admin notices (e.g., after failed social posts).
	 */
	public function display_notices() {
		$notice = get_transient( 'wpts_admin_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'wpts_admin_notice_' . get_current_user_id() );
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( $notice )
			);
		}

		// Success notice after OAuth — only on the plugin settings page.
		$screen = get_current_screen();
		if ( $screen && 'toplevel_page_wpts-settings' === $screen->id
			&& isset( $_GET['wpts_connected'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$connected_module = sanitize_text_field( $_GET['wpts_connected'] );
			if ( $this->registry->get( $connected_module ) ) {
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					/* translators: %s: platform name */
					esc_html( sprintf( __( '%s connected successfully!', 'wp-to-social' ), ucfirst( $connected_module ) ) )
				);
			}
		}
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		$settings_page = new WPTS_Settings_Page( $this->registry );
		$settings_page->render();
	}

	/**
	 * Render the activity page.
	 */
	public function render_activity_page() {
		$activity_page = new WPTS_Activity_Page();
		$activity_page->render();
	}
}
