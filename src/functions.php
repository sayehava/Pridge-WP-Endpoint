<?php
/**
 * Public Pridge integration functions.
 *
 * @package PridgeWPEndpoint
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'pridge_wp_submit_job' ) ) {
	/**
	 * Submit raw print data through the configured Pridge endpoint.
	 *
	 * @param string               $payload Raw payload bytes.
	 * @param array<string, mixed> $args    Optional content_type, metadata, endpoint_id, and document_type values.
	 * @return array<string, mixed>|WP_Error
	 */
	function pridge_wp_submit_job( $payload, array $args = array() ) {
		$plugin = \Pridge\Plugin::instance();

		if ( null === $plugin ) {
			return new WP_Error(
				'pridge_not_ready',
				__( 'Pridge has not finished loading.', 'pridge-wp-endpoint' )
			);
		}

		return $plugin->jobs()->submit( $payload, $args );
	}
}

if ( ! function_exists( 'pridge_wp_is_configured' ) ) {
	/**
	 * Determine whether a Pridge server and endpoint token are configured.
	 *
	 * @return bool
	 */
	function pridge_wp_is_configured() {
		$plugin = \Pridge\Plugin::instance();

		return null !== $plugin && $plugin->settings()->is_configured();
	}
}
