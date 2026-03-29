<?php
/**
 * Information tab partial — plugin details, author, environment.
 */

defined( 'ABSPATH' ) || exit;

$active_modules = array();
foreach ( $this->registry->get_active() as $slug => $module ) {
	$info             = $module->get_info();
	$active_modules[] = $info['name'];
}
?>

<div class="wpts-info">

	<!-- Plugin info -->
	<div class="wpts-info-section">
		<h2><?php esc_html_e( 'Plugin', 'wp-to-social' ); ?></h2>
		<table class="wpts-info-table">
			<tr>
				<th><?php esc_html_e( 'Name', 'wp-to-social' ); ?></th>
				<td><?php esc_html_e( 'WP to Social', 'wp-to-social' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Version', 'wp-to-social' ); ?></th>
				<td><?php echo esc_html( WPTS_VERSION ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'License', 'wp-to-social' ); ?></th>
				<td>GPL-2.0-or-later</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Active Modules', 'wp-to-social' ); ?></th>
				<td><?php echo ! empty( $active_modules ) ? esc_html( implode( ', ', $active_modules ) ) : '<em>' . esc_html__( 'None', 'wp-to-social' ) . '</em>'; ?></td>
			</tr>
		</table>
	</div>

	<!-- Author info -->
	<div class="wpts-info-section">
		<h2><?php esc_html_e( 'Author', 'wp-to-social' ); ?></h2>
		<table class="wpts-info-table">
			<tr>
				<th><?php esc_html_e( 'Developer', 'wp-to-social' ); ?></th>
				<td>Johannes Homann</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Website', 'wp-to-social' ); ?></th>
				<td><a href="https://www.johanneshomann.de" target="_blank" rel="noopener">www.johanneshomann.de</a></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Contact', 'wp-to-social' ); ?></th>
				<td><a href="mailto:hi@johanneshomann.de">hi@johanneshomann.de</a></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'GitHub', 'wp-to-social' ); ?></th>
				<td><a href="https://github.com/johanneshomann/wp-to-social" target="_blank" rel="noopener">johanneshomann/wp-to-social</a></td>
			</tr>
		</table>
	</div>

	<!-- Environment info -->
	<div class="wpts-info-section">
		<h2><?php esc_html_e( 'Environment', 'wp-to-social' ); ?></h2>
		<table class="wpts-info-table">
			<tr>
				<th><?php esc_html_e( 'WordPress Version', 'wp-to-social' ); ?></th>
				<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'PHP Version', 'wp-to-social' ); ?></th>
				<td><?php echo esc_html( PHP_VERSION ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'OpenSSL', 'wp-to-social' ); ?></th>
				<td>
					<?php if ( extension_loaded( 'openssl' ) ) : ?>
						<span class="wpts-info-ok"><?php echo esc_html( OPENSSL_VERSION_TEXT ); ?></span>
					<?php else : ?>
						<span class="wpts-info-warn"><?php esc_html_e( 'Not installed — encryption will not work', 'wp-to-social' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'AUTH_SALT defined', 'wp-to-social' ); ?></th>
				<td>
					<?php if ( defined( 'AUTH_SALT' ) && strlen( AUTH_SALT ) >= 16 ) : ?>
						<span class="wpts-info-ok"><?php esc_html_e( 'Yes', 'wp-to-social' ); ?></span>
					<?php else : ?>
						<span class="wpts-info-warn"><?php esc_html_e( 'Missing or too short — required for credential encryption', 'wp-to-social' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Site URL', 'wp-to-social' ); ?></th>
				<td><?php echo esc_html( get_site_url() ); ?></td>
			</tr>
		</table>
	</div>

	<!-- Debug: LinkedIn Org API Response -->
	<?php $org_debug = get_option( 'wpts_linkedin_org_debug', '' ); ?>
	<?php if ( ! empty( $org_debug ) ) : ?>
	<div class="wpts-info-section">
		<h2><?php esc_html_e( 'LinkedIn Org API Debug', 'wp-to-social' ); ?></h2>
		<pre style="background:#f6f7f7;padding:12px;overflow-x:auto;font-size:12px;max-height:300px;"><?php echo esc_html( $org_debug ); ?></pre>
	</div>
	<?php endif; ?>

</div>
