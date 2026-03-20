<?php
/**
 * Field mapping partial.
 *
 * @var WPTS_Settings_Page $this Settings page instance.
 */

defined( 'ABSPATH' ) || exit;

$active_modules = $this->registry->get_active();
$eligible       = get_option( 'wpts_eligible_post_types', array() );

// Determine which post type is currently selected.
$current_post_type = sanitize_text_field( $_GET['cpt'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( empty( $active_modules ) ) : ?>
	<div class="wpts-empty-state">
		<span class="dashicons dashicons-info-outline"></span>
		<p><?php esc_html_e( 'No active modules. Activate a module in the Modules tab first.', 'wp-to-social' ); ?></p>
	</div>
<?php else : ?>

	<?php foreach ( $active_modules as $slug => $module ) :
		$info           = $module->get_info();
		$platform_types = $eligible[ $slug ] ?? array();

		if ( empty( $platform_types ) ) {
			continue;
		}

		$platform_fields = $module->get_platform_fields();

		// Default to first eligible type if none selected.
		if ( empty( $current_post_type ) || ! in_array( $current_post_type, $platform_types, true ) ) {
			$current_post_type = $platform_types[0];
		}

		$wp_fields     = WPTS_Field_Mapper::get_available_fields( $current_post_type );
		$saved_mapping = WPTS_Field_Mapper::get_mapping( $slug, $current_post_type );
	?>
		<div class="wpts-section">
			<h3>
				<?php
				printf(
					/* translators: %s: platform name */
					esc_html__( 'Field Mapping for %s', 'wp-to-social' ),
					esc_html( $info['name'] )
				);
				?>
			</h3>

			<?php if ( count( $platform_types ) > 1 ) : ?>
				<div class="wpts-cpt-selector">
					<label for="wpts-cpt-select"><?php esc_html_e( 'Post Type:', 'wp-to-social' ); ?></label>
					<select id="wpts-cpt-select" onchange="location.href=this.value;">
						<?php foreach ( $platform_types as $pt ) :
							$pt_obj = get_post_type_object( $pt );
							$url    = admin_url( 'admin.php?page=wpts-settings&tab=field-mapping&cpt=' . $pt );
						?>
							<option value="<?php echo esc_url( $url ); ?>" <?php selected( $current_post_type, $pt ); ?>>
								<?php echo esc_html( $pt_obj ? $pt_obj->labels->singular_name : $pt ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'wpts_save_field_mapping' ); ?>
				<input type="hidden" name="wpts_action" value="save_field_mapping" />
				<input type="hidden" name="wpts_module" value="<?php echo esc_attr( $slug ); ?>" />
				<input type="hidden" name="wpts_post_type" value="<?php echo esc_attr( $current_post_type ); ?>" />

				<table class="wpts-mapping-table">
					<thead>
						<tr>
							<th><?php echo esc_html( $info['name'] . ' ' . __( 'Field', 'wp-to-social' ) ); ?></th>
							<th><?php esc_html_e( 'WordPress Field', 'wp-to-social' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $platform_fields as $pf_key => $pf_label ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $pf_label ); ?></strong></td>
								<td>
									<select name="wpts_mapping[<?php echo esc_attr( $pf_key ); ?>]" class="regular-text">
										<option value=""><?php esc_html_e( '— Not mapped —', 'wp-to-social' ); ?></option>
										<?php foreach ( $wp_fields as $wf_key => $wf_label ) : ?>
											<option value="<?php echo esc_attr( $wf_key ); ?>"
												<?php selected( $saved_mapping[ $pf_key ] ?? '', $wf_key ); ?>>
												<?php echo esc_html( $wf_label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Mapping', 'wp-to-social' ); ?>
					</button>
				</p>
			</form>
		</div>
	<?php endforeach; ?>

	<?php
	// Show message if no eligible types configured.
	$has_any = false;
	foreach ( $active_modules as $slug => $module ) {
		if ( ! empty( $eligible[ $slug ] ?? array() ) ) {
			$has_any = true;
			break;
		}
	}

	if ( ! $has_any ) : ?>
		<div class="wpts-empty-state">
			<span class="dashicons dashicons-info-outline"></span>
			<p><?php esc_html_e( 'No post types selected. Choose eligible post types in the Post Types tab first.', 'wp-to-social' ); ?></p>
		</div>
	<?php endif; ?>

<?php endif; ?>
