<?php
/**
 * Module cards partial.
 *
 * @var WPTS_Settings_Page $this Settings page instance.
 */

defined( 'ABSPATH' ) || exit;

$modules = $this->registry->get_all();
?>

<div class="wpts-modules">
	<?php foreach ( $modules as $slug => $module ) :
		$info      = $module->get_info();
		$is_active = $this->registry->is_active( $slug );
		$status    = method_exists( $module, 'get_connection_status' ) ? $module->get_connection_status() : array( 'connected' => false );

		$has_credentials = method_exists( $module, 'has_credentials' ) ? $module->has_credentials() : false;
		$labels          = method_exists( $module, 'get_credential_labels' )
			? $module->get_credential_labels()
			: array( 'id' => __( 'Client ID', 'wp-to-social' ), 'secret' => __( 'Client Secret', 'wp-to-social' ) );
	?>
		<div class="wpts-module-card <?php echo $is_active ? 'wpts-module-card--active' : ''; ?>">
			<div class="wpts-module-card__header">
				<span class="dashicons <?php echo esc_attr( $info['icon'] ); ?> wpts-module-card__icon"></span>
				<h3 class="wpts-module-card__title"><?php echo esc_html( $info['name'] ); ?></h3>

				<?php if ( $status['connected'] ) : ?>
					<span class="wpts-status wpts-status--connected"><?php esc_html_e( 'Connected', 'wp-to-social' ); ?></span>
				<?php elseif ( $is_active ) : ?>
					<span class="wpts-status wpts-status--disconnected"><?php esc_html_e( 'Not connected', 'wp-to-social' ); ?></span>
				<?php else : ?>
					<span class="wpts-status wpts-status--inactive"><?php esc_html_e( 'Inactive', 'wp-to-social' ); ?></span>
				<?php endif; ?>
			</div>

			<p class="wpts-module-card__desc"><?php echo esc_html( $info['description'] ); ?></p>

			<?php if ( $status['connected'] ) : ?>
				<p class="wpts-module-card__account">
					<?php
					echo wp_kses(
						sprintf(
							/* translators: %s: profile name */
							__( 'Account: %s', 'wp-to-social' ),
							'<strong>' . esc_html( $status['profile_name'] ?: __( 'Unknown', 'wp-to-social' ) ) . '</strong>'
						),
						array( 'strong' => array() )
					);
					?>
					<?php if ( ! empty( $status['expired'] ) ) : ?>
						<span class="wpts-status wpts-status--expired"><?php esc_html_e( 'Token expired', 'wp-to-social' ); ?></span>
					<?php endif; ?>
				</p>

				<div class="wpts-module-card__actions">
					<a href="<?php echo esc_url( $module->get_auth_url() ); ?>" class="button">
						<?php esc_html_e( 'Reconnect', 'wp-to-social' ); ?>
					</a>

					<form method="post" style="display:inline;">
						<?php wp_nonce_field( 'wpts_disconnect_module' ); ?>
						<input type="hidden" name="wpts_action" value="disconnect" />
						<input type="hidden" name="wpts_module" value="<?php echo esc_attr( $slug ); ?>" />
						<button type="submit" class="button wpts-btn-disconnect" data-confirm="disconnect">
							<?php esc_html_e( 'Disconnect', 'wp-to-social' ); ?>
						</button>
					</form>
				</div>

			<?php else : ?>
				<?php
				$setup_steps = method_exists( $module, 'get_setup_steps' ) ? $module->get_setup_steps() : array();
				$allowed_html = array(
					'a'      => array( 'href' => array(), 'target' => array(), 'rel' => array() ),
					'strong' => array(),
					'code'   => array(),
				);
				if ( ! empty( $setup_steps ) ) : ?>
					<div class="wpts-setup-guide">
						<button type="button" class="wpts-setup-guide__toggle" aria-expanded="false">
							<span class="dashicons dashicons-editor-help"></span>
							<?php esc_html_e( 'How to connect', 'wp-to-social' ); ?>
							<span class="dashicons dashicons-arrow-down-alt2 wpts-setup-guide__arrow"></span>
						</button>
						<div class="wpts-setup-guide__content" hidden>
							<ol class="wpts-setup-guide__steps">
								<?php foreach ( $setup_steps as $i => $step ) : ?>
									<li>
										<strong><?php echo esc_html( $step['title'] ); ?></strong>
										<p><?php echo wp_kses( $step['desc'], $allowed_html ); ?></p>
									</li>
								<?php endforeach; ?>
							</ol>
						</div>
					</div>
				<?php endif; ?>

				<!-- Credentials form -->
				<form method="post" class="wpts-credentials-form">
					<?php wp_nonce_field( 'wpts_save_credentials' ); ?>
					<input type="hidden" name="wpts_action" value="save_credentials" />
					<input type="hidden" name="wpts_module" value="<?php echo esc_attr( $slug ); ?>" />

					<div class="wpts-field">
						<label for="wpts_client_id_<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $labels['id'] ); ?></label>
						<input type="text" id="wpts_client_id_<?php echo esc_attr( $slug ); ?>" name="wpts_client_id"
						       value="<?php echo $has_credentials ? '••••••••' : ''; ?>"
						       placeholder="<?php echo esc_attr( sprintf( __( 'Enter %s', 'wp-to-social' ), $labels['id'] ) ); ?>"
						       class="regular-text" autocomplete="off" />
					</div>

					<div class="wpts-field">
						<label for="wpts_client_secret_<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $labels['secret'] ); ?></label>
						<input type="password" id="wpts_client_secret_<?php echo esc_attr( $slug ); ?>" name="wpts_client_secret"
						       placeholder="<?php echo esc_attr( sprintf( __( 'Enter %s', 'wp-to-social' ), $labels['secret'] ) ); ?>"
						       class="regular-text" autocomplete="off" />
					</div>

					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Credentials', 'wp-to-social' ); ?>
					</button>
				</form>

				<?php if ( $has_credentials ) : ?>
					<div class="wpts-module-card__actions" style="margin-top:12px;">
						<a href="<?php echo esc_url( $module->get_auth_url() ); ?>" class="button button-primary">
							<?php
							/* translators: %s: platform name */
							echo esc_html( sprintf( __( 'Connect with %s', 'wp-to-social' ), $info['name'] ) );
							?>
						</a>
					</div>

					<p class="wpts-help-text">
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s: redirect URI */
								__( 'Set this as your OAuth redirect URI: %s', 'wp-to-social' ),
								'<code>' . esc_html( $module->get_redirect_uri() ) . '</code>'
							),
							array( 'code' => array() )
						);
						?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>

	<div class="wpts-module-card wpts-module-card--coming-soon">
		<div class="wpts-module-card__header">
			<span class="dashicons dashicons-plus-alt2 wpts-module-card__icon"></span>
			<h3 class="wpts-module-card__title"><?php esc_html_e( 'More coming soon', 'wp-to-social' ); ?></h3>
		</div>
		<p class="wpts-module-card__desc"><?php esc_html_e( 'Additional social platforms will be available in future updates.', 'wp-to-social' ); ?></p>
	</div>
</div>
