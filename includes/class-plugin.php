<?php
/**
 * Plugin bootstrap — singleton that wires everything together.
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Plugin {

	/** @var self|null */
	private static $instance = null;

	/** @var WPTS_Module_Registry */
	public $registry;

	/**
	 * Get or create the singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — register modules and init components.
	 */
	private function __construct() {
		$this->registry = new WPTS_Module_Registry();

		// Register modules.
		$this->registry->register( 'linkedin', new WPTS_LinkedIn_Module() );
		$this->registry->register( 'instagram', new WPTS_Instagram_Module() );

		// Admin.
		if ( is_admin() ) {
			$admin = new WPTS_Admin( $this->registry );
			$admin->init();

			$settings_page = new WPTS_Settings_Page( $this->registry );
			$settings_page->init();

			$meta_box = new WPTS_Meta_Box( $this->registry );
			$meta_box->init();
		}

		// Post handler (runs on both admin and REST contexts).
		$post_handler = new WPTS_Post_Handler( $this->registry );
		$post_handler->init();

		// Run DB upgrades if needed.
		$this->maybe_upgrade();

		// Load textdomain.
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Run database upgrades when the plugin version changes.
	 */
	private function maybe_upgrade() {
		$db_version = get_option( 'wpts_db_version', '0' );

		if ( version_compare( $db_version, WPTS_Activity_Logger::DB_VERSION, '<' ) ) {
			WPTS_Activity_Logger::create_table();
		}
	}

	/**
	 * Plugin activation.
	 */
	public static function activate() {
		WPTS_Activity_Logger::create_table();
	}

	/**
	 * Plugin deactivation.
	 */
	public static function deactivate() {
		// Nothing to clean on deactivation — only on uninstall.
	}

	/**
	 * Load plugin translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wp-to-social', false, dirname( WPTS_PLUGIN_BASENAME ) . '/languages' );
	}
}
