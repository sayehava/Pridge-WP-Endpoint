<?php
/**
 * Plugin settings access and validation.
 *
 * @package PridgeWPEndpoint
 */

namespace Pridge;

defined( 'ABSPATH' ) || exit;

final class Settings {
	public const OPTION_NAME = 'pridge_wp_settings';

	/**
	 * Return default plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'server_url'            => '',
			'default_content_type'  => 'text/plain',
			'delete_on_uninstall'   => false,
		);
	}

	/**
	 * Fetch all settings with defaults applied.
	 *
	 * @return array<string, mixed>
	 */
	public function all() {
		$stored = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * Fetch one setting.
	 *
	 * @param string $key     Setting name.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$settings = $this->all();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Determine whether the connection has enough data to submit a job.
	 *
	 * @return bool
	 */
	public function is_configured() {
		$endpoint = ( new EndpointRepository() )->resolve();

		return '' !== $this->get( 'server_url', '' ) && ( ( $endpoint && ! empty( $endpoint['token'] ) ) || '' !== $this->get( 'endpoint_token', '' ) );
	}

	/**
	 * Sanitize settings submitted by an administrator.
	 *
	 * An empty token field preserves the existing secret so the saved token never needs to be
	 * rendered back into an HTML form.
	 *
	 * @param mixed $input Raw settings input.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$current  = $this->all();
		$defaults = self::defaults();

		$server_url = isset( $input['server_url'] ) ? untrailingslashit( esc_url_raw( trim( (string) $input['server_url'] ) ) ) : '';

		if ( '' !== $server_url && ! wp_http_validate_url( $server_url ) ) {
			add_settings_error(
				self::OPTION_NAME,
				'pridge_invalid_server_url',
				__( 'Enter a valid Pridge Server URL.', 'pridge-wp-endpoint' )
			);
			$server_url = (string) $current['server_url'];
		}

		$content_types = array(
			'application/octet-stream',
			'application/pdf',
			'image/png',
			'text/plain',
		);
		$content_type  = isset( $input['default_content_type'] ) ? sanitize_text_field( (string) $input['default_content_type'] ) : $defaults['default_content_type'];

		if ( ! in_array( $content_type, $content_types, true ) ) {
			$content_type = $defaults['default_content_type'];
		}

		return array(
			'server_url'            => $server_url,
			'default_content_type'  => $content_type,
			'delete_on_uninstall'   => ! empty( $input['delete_on_uninstall'] ),
		);
	}
}
