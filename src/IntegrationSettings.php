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

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'woocommerce_enabled' => false,
			'trigger_status'       => 'processing',
			'germanized_enabled'  => false,
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

		return array(
			'woocommerce_enabled' => $woo_enabled,
			'trigger_status'       => $status ?: 'processing',
			'germanized_enabled'  => $woo_enabled && ! empty( $input['germanized_enabled'] ) && \Pridge\Integration\Germanized::is_available(),
		);
	}
}
