<?php
/**
 * Module registry — register, activate, and query platform modules.
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Module_Registry {

	/** @var array Registered module instances keyed by slug. */
	private $modules = array();

	/**
	 * Register a module.
	 *
	 * @param string $slug   Unique module identifier (e.g. 'linkedin').
	 * @param object $module Module instance implementing required methods.
	 */
	public function register( $slug, $module ) {
		$this->modules[ $slug ] = $module;
	}

	/**
	 * Get all registered modules.
	 *
	 * @return array
	 */
	public function get_all() {
		return $this->modules;
	}

	/**
	 * Get a single module by slug.
	 *
	 * @param string $slug Module slug.
	 * @return object|null
	 */
	public function get( $slug ) {
		return $this->modules[ $slug ] ?? null;
	}

	/**
	 * Get slugs of currently active modules.
	 *
	 * @return array
	 */
	public function get_active_slugs() {
		return get_option( 'wpts_active_modules', array() );
	}

	/**
	 * Check if a module is active.
	 *
	 * @param string $slug Module slug.
	 * @return bool
	 */
	public function is_active( $slug ) {
		return in_array( $slug, $this->get_active_slugs(), true );
	}

	/**
	 * Activate a module.
	 *
	 * @param string $slug Module slug.
	 */
	public function activate( $slug ) {
		$active = $this->get_active_slugs();
		if ( ! in_array( $slug, $active, true ) && isset( $this->modules[ $slug ] ) ) {
			$active[] = $slug;
			update_option( 'wpts_active_modules', $active );
		}
	}

	/**
	 * Deactivate a module.
	 *
	 * @param string $slug Module slug.
	 */
	public function deactivate( $slug ) {
		$active = $this->get_active_slugs();
		$active = array_values( array_diff( $active, array( $slug ) ) );
		update_option( 'wpts_active_modules', $active );
	}

	/**
	 * Get only the active module instances.
	 *
	 * @return array
	 */
	public function get_active() {
		$result = array();
		foreach ( $this->get_active_slugs() as $slug ) {
			if ( isset( $this->modules[ $slug ] ) ) {
				$result[ $slug ] = $this->modules[ $slug ];
			}
		}
		return $result;
	}
}
