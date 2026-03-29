<?php
/**
 * GitHub-based plugin updater.
 *
 * Checks the GitHub Releases API for new versions and integrates
 * with the WordPress plugin update system.
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Updater {

	/** @var string GitHub repository slug (owner/repo). */
	private $repo = 'johanneshomann/wp-to-social';

	/** @var string Transient key for caching the API response. */
	private $transient_key = 'wpts_github_release';

	/** @var int Cache lifetime in seconds (12 hours). */
	private $cache_ttl = 43200;

	/**
	 * Register hooks.
	 */
	public function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
	}

	/**
	 * Query GitHub for the latest release and inject update info if newer.
	 *
	 * @param object $transient The update_plugins transient.
	 * @return object
	 */
	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$remote_version = ltrim( $release['tag_name'], 'v' );

		if ( version_compare( $remote_version, WPTS_VERSION, '>' ) ) {
			$transient->response[ WPTS_PLUGIN_BASENAME ] = (object) array(
				'slug'        => 'wp-to-social',
				'plugin'      => WPTS_PLUGIN_BASENAME,
				'new_version' => $remote_version,
				'url'         => "https://github.com/{$this->repo}",
				'package'     => $this->get_download_url( $release ),
				'icons'       => array(),
				'banners'     => array(),
			);
		}

		return $transient;
	}

	/**
	 * Provide plugin details for the "View details" modal.
	 *
	 * @param false|object|array $result
	 * @param string             $action
	 * @param object             $args
	 * @return false|object
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || 'wp-to-social' !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		$remote_version = ltrim( $release['tag_name'], 'v' );

		return (object) array(
			'name'          => 'WP to Social',
			'slug'          => 'wp-to-social',
			'version'       => $remote_version,
			'author'        => '<a href="https://www.johanneshomann.de">Johannes Homann</a>',
			'homepage'      => "https://github.com/{$this->repo}",
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'downloaded'    => 0,
			'last_updated'  => $release['published_at'],
			'sections'      => array(
				'description' => 'Post WordPress content to social media platforms. Supports LinkedIn and Instagram.',
				'changelog'   => nl2br( esc_html( $release['body'] ) ),
			),
			'download_link' => $this->get_download_url( $release ),
		);
	}

	/**
	 * Ensure the extracted folder name matches the plugin slug.
	 *
	 * GitHub ZIPs extract as "repo-tag/" — rename to "wp-to-social/".
	 *
	 * @param bool  $response
	 * @param array $hook_extra
	 * @param array $result
	 * @return array|WP_Error
	 */
	public function after_install( $response, $hook_extra, $result ) {
		if ( ! isset( $hook_extra['plugin'] ) || WPTS_PLUGIN_BASENAME !== $hook_extra['plugin'] ) {
			return $result;
		}

		global $wp_filesystem;

		$plugin_dir  = WP_PLUGIN_DIR . '/wp-to-social/';
		$source      = $result['destination'];

		if ( $source !== $plugin_dir ) {
			$wp_filesystem->move( $source, $plugin_dir );
			$result['destination'] = $plugin_dir;
		}

		activate_plugin( WPTS_PLUGIN_BASENAME );

		return $result;
	}

	/**
	 * Fetch the latest release from GitHub (cached).
	 *
	 * @return array|null
	 */
	private function get_latest_release() {
		$cached = get_transient( $this->transient_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$url      = "https://api.github.com/repos/{$this->repo}/releases/latest";
		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Accept' => 'application/vnd.github.v3+json',
			),
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			return null;
		}

		set_transient( $this->transient_key, $body, $this->cache_ttl );

		return $body;
	}

	/**
	 * Get the download URL from a release.
	 *
	 * Prefers a wp-to-social.zip asset; falls back to the source zipball.
	 *
	 * @param array $release GitHub release data.
	 * @return string
	 */
	private function get_download_url( $release ) {
		if ( ! empty( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( 'wp-to-social.zip' === $asset['name'] ) {
					return $asset['browser_download_url'];
				}
			}
		}

		return $release['zipball_url'];
	}
}
