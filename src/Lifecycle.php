<?php
/**
 * Plugin lifecycle operations.
 *
 * @package PridgeWPEndpoint
 */

namespace Pridge;

defined( 'ABSPATH' ) || exit;

final class Lifecycle {
	public const SCHEMA_VERSION = '2';

	/**
	 * Initialize persistent plugin state without overwriting existing settings.
	 *
	 * @return void
	 */
	public static function activate() {
		add_option( 'pridge_wp_schema_version', '0', '', false );
		add_option( Settings::OPTION_NAME, Settings::defaults(), '', false );
		self::maybe_upgrade();
		add_option( EndpointRepository::OPTION_NAME, EndpointRepository::defaults(), '', false );
		add_option( IntegrationSettings::OPTION_NAME, IntegrationSettings::defaults(), '', false );
	}

	/**
	 * Install database changes and migrate legacy single-endpoint settings.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed_version = (string) get_option( 'pridge_wp_schema_version', '0' );

		if ( version_compare( $installed_version, self::SCHEMA_VERSION, '>=' ) ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = $wpdb->prefix . 'pridge_archive';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_id bigint(20) unsigned NULL,
			endpoint_id varchar(100) NOT NULL DEFAULT '',
			endpoint_name varchar(190) NOT NULL DEFAULT '',
			document_type varchar(100) NOT NULL DEFAULT 'custom',
			source varchar(100) NOT NULL DEFAULT 'wordpress',
			order_id bigint(20) unsigned NULL,
			status varchar(30) NOT NULL DEFAULT 'failed',
			content_type varchar(100) NOT NULL DEFAULT 'application/octet-stream',
			payload longtext NOT NULL,
			metadata longtext NOT NULL,
			error_code varchar(100) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY status (status),
			KEY order_id (order_id)
		) {$charset_collate};";

		dbDelta( $sql );
		self::migrate_legacy_settings();
		update_option( 'pridge_wp_schema_version', self::SCHEMA_VERSION, false );
	}

	/**
	 * @return void
	 */
	private static function migrate_legacy_settings() {
		$legacy = get_option( Settings::OPTION_NAME, array() );

		if ( ! is_array( $legacy ) ) {
			return;
		}

		$endpoint_settings = get_option( EndpointRepository::OPTION_NAME, EndpointRepository::defaults() );
		if ( empty( $endpoint_settings['endpoints'] ) && ! empty( $legacy['endpoint_token'] ) ) {
			$endpoint_settings = array(
				'default_endpoint' => 'default',
				'endpoints'        => array(
					array(
						'id'    => 'default',
						'name'  => __( 'Default printer', 'pridge-wp-endpoint' ),
						'token' => (string) $legacy['endpoint_token'],
					),
				),
				'routes'           => ! empty( $legacy['woocommerce_enabled'] ) ? array( 'receipt' => 'default' ) : array(),
			);
			update_option( EndpointRepository::OPTION_NAME, $endpoint_settings, false );
		}

		$integration_settings = get_option( IntegrationSettings::OPTION_NAME, array() );
		if ( empty( $integration_settings ) ) {
			update_option(
				IntegrationSettings::OPTION_NAME,
				array(
					'woocommerce_enabled' => ! empty( $legacy['woocommerce_enabled'] ),
					'trigger_status'       => sanitize_key( $legacy['woocommerce_status'] ?? 'processing' ),
					'germanized_enabled'  => ! empty( $legacy['germanized_enabled'] ),
				),
				false
			);
		}
	}

	/**
	 * Deactivation deliberately preserves settings and operational data.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'pridge_wp_retry_woocommerce_order' );
		wp_clear_scheduled_hook( 'pridge_wp_retry_woocommerce_document' );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'pridge_wp_retry_woocommerce_order', array(), 'pridge' );
			as_unschedule_all_actions( 'pridge_wp_retry_woocommerce_document', array(), 'pridge' );
		}
	}
}
