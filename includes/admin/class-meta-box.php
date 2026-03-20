<?php
/**
 * Meta box — publish checkbox for Classic Editor + Gutenberg sidebar panel.
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Meta_Box {

	/** @var WPTS_Module_Registry */
	private $registry;

	public function __construct( WPTS_Module_Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_gutenberg' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_fields' ) );
	}

	/**
	 * Register the meta box for Classic Editor.
	 */
	public function register_meta_box() {
		$eligible_types = $this->get_all_eligible_types();

		if ( empty( $eligible_types ) ) {
			return;
		}

		foreach ( $eligible_types as $post_type ) {
			add_meta_box(
				'wpts-social-publish',
				__( 'WP to Social', 'wp-to-social' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render the Classic Editor meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'wpts_meta_box', 'wpts_meta_nonce' );

		$eligible = get_option( 'wpts_eligible_post_types', array() );

		foreach ( $this->registry->get_active() as $slug => $module ) {
			$platform_types = $eligible[ $slug ] ?? array();

			if ( ! in_array( $post->post_type, $platform_types, true ) ) {
				continue;
			}

			if ( ! method_exists( $module, 'is_connected' ) || ! $module->is_connected() ) {
				continue;
			}

			$meta_key = '_wpts_post_to_' . $slug;
			$checked  = get_post_meta( $post->ID, $meta_key, true );
			$already  = get_post_meta( $post->ID, '_wpts_' . $slug . '_posted', true );
			$info     = $module->get_info();

			printf(
				'<label style="display:flex;align-items:center;gap:6px;padding:4px 0;"><input type="checkbox" name="%s" value="1" %s %s /> %s</label>',
				esc_attr( $meta_key ),
				checked( $checked, 1, false ),
				$already ? 'disabled' : '',
				$already
					? sprintf( esc_html__( 'Posted to %s', 'wp-to-social' ), esc_html( $info['name'] ) ) . ' &#10003;'
					: sprintf( esc_html__( 'Post to %s', 'wp-to-social' ), esc_html( $info['name'] ) )
			);
		}
	}

	/**
	 * Save meta box data on post save.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['wpts_meta_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['wpts_meta_nonce'], 'wpts_meta_box' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( $this->registry->get_active() as $slug => $module ) {
			$meta_key = '_wpts_post_to_' . $slug;

			if ( ! empty( $_POST[ $meta_key ] ) ) {
				update_post_meta( $post_id, $meta_key, 1 );
			} else {
				delete_post_meta( $post_id, $meta_key );
			}
		}
	}

	/**
	 * Enqueue Gutenberg sidebar script.
	 */
	public function enqueue_gutenberg() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$eligible_types = $this->get_all_eligible_types();
		if ( ! in_array( $screen->post_type, $eligible_types, true ) ) {
			return;
		}

		// Build module data for the JS.
		$modules_data = array();
		$eligible      = get_option( 'wpts_eligible_post_types', array() );

		foreach ( $this->registry->get_active() as $slug => $module ) {
			$platform_types = $eligible[ $slug ] ?? array();
			if ( ! in_array( $screen->post_type, $platform_types, true ) ) {
				continue;
			}
			if ( ! method_exists( $module, 'is_connected' ) || ! $module->is_connected() ) {
				continue;
			}

			$info = $module->get_info();
			$modules_data[] = array(
				'slug' => $slug,
				'name' => $info['name'],
			);
		}

		if ( empty( $modules_data ) ) {
			return;
		}

		wp_enqueue_script(
			'wpts-editor',
			WPTS_PLUGIN_URL . 'assets/js/editor.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-compose' ),
			WPTS_VERSION,
			true
		);

		wp_localize_script( 'wpts-editor', 'wptsEditor', array(
			'modules' => $modules_data,
		) );
	}

	/**
	 * Register REST API meta fields for Gutenberg.
	 */
	public function register_rest_fields() {
		foreach ( $this->registry->get_all() as $slug => $module ) {
			$meta_key = '_wpts_post_to_' . $slug;

			register_meta( 'post', $meta_key, array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'integer',
				'auth_callback' => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			) );

			register_meta( 'post', '_wpts_' . $slug . '_posted', array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'integer',
				'auth_callback' => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			) );
		}
	}

	/**
	 * Get all post types that are eligible across all active modules.
	 *
	 * @return array
	 */
	private function get_all_eligible_types() {
		$eligible = get_option( 'wpts_eligible_post_types', array() );
		$types    = array();

		foreach ( $this->registry->get_active() as $slug => $module ) {
			$platform_types = $eligible[ $slug ] ?? array();
			$types          = array_merge( $types, $platform_types );
		}

		return array_unique( $types );
	}
}
