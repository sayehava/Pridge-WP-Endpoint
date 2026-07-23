<?php
/**
 * Optional commerce integration settings.
 *
 * @package PridgeWPEndpoint
 */

namespace Pridge;

defined( 'ABSPATH' ) || exit;

final class IntegrationSettings {
	public const OPTION_NAME = 'pridge_wp_integrations';

	/** Allowed WP-Cron polling intervals, in minutes. */
	public const CRON_INTERVALS = array( 5, 15, 30, 60 );

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'woocommerce_enabled'          => false,
			'trigger_status'                => 'processing',
			'germanized_enabled'           => false,
			'post_print_woocommerce_status' => '',
			'post_print_shiptastic_status'  => '',
			'cron_interval_minutes'        => 15,
			'pending_timeout_hours'        => 24,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function all() {
		$value = get_option( self::OPTION_NAME, array() );

		return wp_parse_args( is_array( $value ) ? $value : array(), self::defaults() );
	}

	/**
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$value = $this->all();

		return array_key_exists( $key, $value ) ? $value[ $key ] : $default;
	}

	/**
	 * @param mixed $input Raw settings.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ) {
		$input       = is_array( $input ) ? $input : array();
		$woo_enabled = ! empty( $input['woocommerce_enabled'] ) && class_exists( 'WooCommerce' );
		$status       = isset( $input['trigger_status'] ) ? sanitize_key( (string) $input['trigger_status'] ) : 'processing';

		if ( 0 === strpos( $status, 'wc-' ) ) {
			$status = substr( $status, 3 );
		}

		$status = $status ?: 'processing';

		$post_print_status = isset( $input['post_print_woocommerce_status'] ) ? sanitize_key( (string) $input['post_print_woocommerce_status'] ) : '';
		if ( 0 === strpos( $post_print_status, 'wc-' ) ) {
			$post_print_status = substr( $post_print_status, 3 );
		}
		if ( '' !== $post_print_status && ( $post_print_status === $status || ! $this->is_valid_order_status( $post_print_status ) ) ) {
			add_settings_error(
				self::OPTION_NAME,
				'pridge_invalid_post_print_status',
				__( 'The post-print WooCommerce status must differ from the print trigger status and be a real order status. It was left unset.', 'pridge-wp-endpoint' )
			);
			$post_print_status = '';
		}

		$post_print_shipment_status = isset( $input['post_print_shiptastic_status'] ) ? sanitize_key( (string) $input['post_print_shiptastic_status'] ) : '';
		if ( '' !== $post_print_shipment_status && ! $this->is_valid_shipment_status( $post_print_shipment_status ) ) {
			$post_print_shipment_status = '';
		}

		$cron_interval = isset( $input['cron_interval_minutes'] ) ? absint( $input['cron_interval_minutes'] ) : 15;
		if ( ! in_array( $cron_interval, self::CRON_INTERVALS, true ) ) {
			$cron_interval = 15;
		}

		$pending_timeout = isset( $input['pending_timeout_hours'] ) ? absint( $input['pending_timeout_hours'] ) : 24;
		$pending_timeout = min( 168, max( 1, $pending_timeout ?: 24 ) );

		return array(
			'woocommerce_enabled'           => $woo_enabled,
			'trigger_status'                => $status,
			'germanized_enabled'           => $woo_enabled && ! empty( $input['germanized_enabled'] ) && \Pridge\Integration\Germanized::is_available(),
			'post_print_woocommerce_status' => $post_print_status,
			'post_print_shiptastic_status'  => $post_print_shipment_status,
			'cron_interval_minutes'        => $cron_interval,
			'pending_timeout_hours'        => $pending_timeout,
		);
	}

	/**
	 * @param string $status Candidate WooCommerce order status without the "wc-" prefix.
	 * @return bool
	 */
	private function is_valid_order_status( $status ) {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return false;
		}

		foreach ( array_keys( wc_get_order_statuses() ) as $status_key ) {
			$normalized = 0 === strpos( $status_key, 'wc-' ) ? substr( $status_key, 3 ) : $status_key;
			if ( $normalized === $status ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $status Candidate Shiptastic shipment status.
	 * @return bool
	 */
	private function is_valid_shipment_status( $status ) {
		if ( function_exists( 'wc_stc_get_shipment_statuses' ) ) {
			return array_key_exists( $status, wc_stc_get_shipment_statuses() );
		}

		return in_array( $status, array( 'draft', 'processing', 'shipped', 'delivered', 'returned' ), true );
	}
}
