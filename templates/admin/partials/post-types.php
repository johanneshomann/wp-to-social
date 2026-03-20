<?php
/**
 * Post types selection partial.
 *
 * @var WPTS_Settings_Page $this Settings page instance.
 */

defined( 'ABSPATH' ) || exit;

$active_modules = $this->registry->get_active();
$eligible       = get_option( 'wpts_eligible_post_types', array() );

// Get all public post types.
$post_types = get_post_types( array( 'public' => true ), 'objects' );
unset( $post_types['attachment'] );

if ( empty( $active_modules ) ) : ?>
	<div class="wpts-empty-state">
		<span class="dashicons dashicons-info-outline"></span>
		<p><?php esc_html_e( 'No active modules. Activate a module in the Modules tab first.', 'wp-to-social' ); ?></p>
	</div>
<?php else : ?>

	<?php foreach ( $active_modules as $slug => $module ) :
		$info             = $module->get_info();
		$selected_types   = $eligible[ $slug ] ?? array();
	?>
		<div class="wpts-section">
			<h3>
				<?php
				printf(
					/* translators: %s: platform name */
					esc_html__( 'Post types eligible for %s', 'wp-to-social' ),
					esc_html( $info['name'] )
				);
				?>
			</h3>

			<form method="post">
				<?php wp_nonce_field( 'wpts_save_post_types' ); ?>
				<input type="hidden" name="wpts_action" value="save_post_types" />
				<input type="hidden" name="wpts_module" value="<?php echo esc_attr( $slug ); ?>" />

				<div class="wpts-checkbox-list">
					<?php foreach ( $post_types as $pt ) : ?>
						<label class="wpts-checkbox-item">
							<input type="checkbox"
							       name="wpts_post_types[]"
							       value="<?php echo esc_attr( $pt->name ); ?>"
							       <?php checked( in_array( $pt->name, $selected_types, true ) ); ?> />
							<span class="wpts-checkbox-label">
								<?php echo esc_html( $pt->labels->singular_name ); ?>
								<span class="wpts-checkbox-slug"><?php echo esc_html( $pt->name ); ?></span>
							</span>
						</label>
					<?php endforeach; ?>
				</div>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Changes', 'wp-to-social' ); ?>
					</button>
				</p>
			</form>
		</div>
	<?php endforeach; ?>

<?php endif; ?>
