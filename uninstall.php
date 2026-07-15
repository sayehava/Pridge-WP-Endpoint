<?php
/**
 * Pridge WP Endpoint uninstall handler.
 *
 * Data is retained by default and removed only after an explicit administrator opt-in.
 *
 * @package PridgeWPEndpoint
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = get_option( 'pridge_wp_settings', array() );

if ( is_array( $settings ) && ! empty( $settings['delete_on_uninstall'] ) ) {
	global $wpdb;

	delete_option( 'pridge_wp_settings' );
	delete_option( 'pridge_wp_endpoints' );
	delete_option( 'pridge_wp_integrations' );
	delete_option( 'pridge_wp_schema_version' );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pridge_archive" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
