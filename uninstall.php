<?php
/**
 * WP to Social — Uninstall.
 *
 * Removes all plugin data when the plugin is deleted via the WP admin.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Remove options.
$options = array(
	'wpts_active_modules',
	'wpts_eligible_post_types',
	'wpts_field_mapping',
	'wpts_linkedin_token',
	'wpts_linkedin_client_id',
	'wpts_linkedin_client_secret',
	'wpts_db_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Drop activity table.
$table = $wpdb->prefix . 'wpts_activity';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Remove post meta.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_wpts_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
