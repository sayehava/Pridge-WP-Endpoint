<?php
/**
 * Named Pridge endpoint token storage.
 *
 * @package PridgeWPEndpoint
 */

namespace Pridge;

defined( 'ABSPATH' ) || exit;

final class EndpointRepository {
	public const OPTION_NAME = 'pridge_wp_endpoints';

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'default_endpoint' => '',
			'endpoints'        => array(),
			'routes'           => array(),
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
	 * @return array<int, array<string, string>>
	 */
	public function fetch_all() {
		$value = $this->all();

		return is_array( $value['endpoints'] ) ? array_values( $value['endpoints'] ) : array();
	}

	/**
	 * @param string $endpoint_id Endpoint identifier.
	 * @return array<string, string>|null
	 */
	public function find( $endpoint_id ) {
		$endpoint_id = sanitize_key( $endpoint_id );

		foreach ( $this->fetch_all() as $endpoint ) {
			if ( isset( $endpoint['id'] ) && $endpoint_id === $endpoint['id'] ) {
				return $endpoint;
			}
		}

		return null;
	}

	/**
	 * Resolve an explicit endpoint or the configured default.
	 *
	 * @param string $endpoint_id Optional endpoint identifier.
	 * @return array<string, string>|null
	 */
	public function resolve( $endpoint_id = '' ) {
		$value       = $this->all();
		$endpoint_id = sanitize_key( $endpoint_id ?: (string) $value['default_endpoint'] );

		return $this->find( $endpoint_id );
	}

	/**
	 * Resolve the endpoint assigned to a document route.
	 *
	 * @param string $document_type Document route key.
	 * @return array<string, string>|null
	 */
	public function resolve_route( $document_type ) {
		$value  = $this->all();
		$routes = is_array( $value['routes'] ) ? $value['routes'] : array();
		$key    = sanitize_key( $document_type );

		return isset( $routes[ $key ] ) ? $this->find( $routes[ $key ] ) : null;
	}

	/**
	 * @param string $document_type Document route key.
	 * @return string
	 */
	public function route_endpoint_id( $document_type ) {
		$value  = $this->all();
		$routes = is_array( $value['routes'] ) ? $value['routes'] : array();
		$key    = sanitize_key( $document_type );

		return isset( $routes[ $key ] ) ? sanitize_key( $routes[ $key ] ) : '';
	}

	/**
	 * Sanitize endpoint and route settings while preserving saved secrets left blank.
	 *
	 * @param mixed $input Raw settings.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ) {
		$input            = is_array( $input ) ? $input : array();
		$current_by_id    = array();
		$clean_endpoints  = array();
		$valid_ids        = array();

		foreach ( $this->fetch_all() as $endpoint ) {
			if ( ! empty( $endpoint['id'] ) ) {
				$current_by_id[ $endpoint['id'] ] = $endpoint;
			}
		}

		$submitted = isset( $input['endpoints'] ) && is_array( $input['endpoints'] ) ? $input['endpoints'] : array();
		foreach ( $submitted as $endpoint ) {
			if ( ! is_array( $endpoint ) || ! empty( $endpoint['remove'] ) ) {
				continue;
			}

			$id    = isset( $endpoint['id'] ) ? sanitize_key( (string) $endpoint['id'] ) : '';
			$name  = isset( $endpoint['name'] ) ? sanitize_text_field( (string) $endpoint['name'] ) : '';
			$token = isset( $endpoint['token'] ) ? sanitize_text_field( (string) $endpoint['token'] ) : '';

			if ( '' === $id || isset( $valid_ids[ $id ] ) ) {
				$id = 'endpoint-' . wp_generate_uuid4();
			}

			if ( '' === $token && isset( $current_by_id[ $id ]['token'] ) ) {
				$token = (string) $current_by_id[ $id ]['token'];
			}

			if ( '' === $name && '' === $token ) {
				continue;
			}

			if ( '' === $name ) {
				$name = __( 'Unnamed endpoint', 'pridge-wp-endpoint' );
			}

			$clean_endpoints[] = array(
				'id'    => $id,
				'name'  => $name,
				'token' => $token,
			);
			$valid_ids[ $id ]  = true;
		}

		$default_endpoint = isset( $input['default_endpoint'] ) ? sanitize_key( (string) $input['default_endpoint'] ) : '';
		if ( ! isset( $valid_ids[ $default_endpoint ] ) ) {
			$default_endpoint = $clean_endpoints ? $clean_endpoints[0]['id'] : '';
		}

		$routes           = array();
		$submitted_routes = isset( $input['routes'] ) && is_array( $input['routes'] ) ? $input['routes'] : array();
		foreach ( $submitted_routes as $document_type => $endpoint_id ) {
			$document_type = sanitize_key( $document_type );
			$endpoint_id   = sanitize_key( $endpoint_id );

			if ( '' !== $document_type && isset( $valid_ids[ $endpoint_id ] ) ) {
				$routes[ $document_type ] = $endpoint_id;
			}
		}

		return array(
			'default_endpoint' => $default_endpoint,
			'endpoints'        => $clean_endpoints,
			'routes'           => $routes,
		);
	}
}
