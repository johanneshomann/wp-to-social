<?php
/**
 * Settings page template.
 *
 * @var string              $tab      Current active tab.
 * @var WPTS_Settings_Page  $this     Settings page instance.
 */

defined( 'ABSPATH' ) || exit;

$tabs = array(
	'modules'       => __( 'Modules', 'wp-to-social' ),
	'post-types'    => __( 'Post Types', 'wp-to-social' ),
	'field-mapping' => __( 'Field Mapping', 'wp-to-social' ),
	'help'          => __( 'Help', 'wp-to-social' ),
	'info'          => __( 'Information', 'wp-to-social' ),
);

$saved = isset( $_GET['saved'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>

<div class="wrap wpts-wrap">
	<h1><?php esc_html_e( 'WP to Social', 'wp-to-social' ); ?></h1>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'wp-to-social' ); ?></p></div>
	<?php endif; ?>

	<div class="wpts-settings-layout">
		<nav class="wpts-tabs">
			<?php foreach ( $tabs as $slug => $label ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpts-settings&tab=' . $slug ) ); ?>"
				   class="wpts-tab <?php echo $tab === $slug ? 'wpts-tab--active' : ''; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="wpts-tab-content">
			<?php
			switch ( $tab ) {
				case 'post-types':
					include WPTS_PLUGIN_DIR . 'templates/admin/partials/post-types.php';
					break;

				case 'field-mapping':
					include WPTS_PLUGIN_DIR . 'templates/admin/partials/field-mapping.php';
					break;

				case 'help':
					include WPTS_PLUGIN_DIR . 'templates/admin/partials/help.php';
					break;

				case 'info':
					include WPTS_PLUGIN_DIR . 'templates/admin/partials/info.php';
					break;

				default:
					include WPTS_PLUGIN_DIR . 'templates/admin/partials/module-card.php';
					break;
			}
			?>
		</div>
	</div>
</div>
