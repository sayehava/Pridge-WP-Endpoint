<?php
/**
 * Optional Germanized for WooCommerce integration boundary.
 *
 * @package PridgeWPEndpoint
 */

namespace Pridge\Integration;

defined( 'ABSPATH' ) || exit;

final class Germanized {
	/**
	 * Determine whether Germanized has completed loading.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return defined( 'WC_GERMANIZED_VERSION' ) || function_exists( 'WC_germanized' );
	}

	/**
	 * Register the adapter without reaching into Germanized internals.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'pridge_wp_job_metadata', array( $this, 'add_job_metadata' ), 10, 2 );
	}

	/**
	 * Identify WooCommerce jobs produced while the Germanized adapter is enabled.
	 *
	 * @param array<string, mixed> $metadata Job metadata.
	 * @param array<string, mixed> $context  Safe submission context.
	 * @return array<string, mixed>
	 */
	public function add_job_metadata( $metadata, $context ) {
		if ( ! is_array( $metadata ) || 'woocommerce' !== ( $metadata['source'] ?? '' ) ) {
			return $metadata;
		}

		$metadata['germanized'] = true;

		if ( defined( 'WC_GERMANIZED_VERSION' ) ) {
			$metadata['germanized_version'] = sanitize_text_field( (string) WC_GERMANIZED_VERSION );
		}

		return $metadata;
	}
}
