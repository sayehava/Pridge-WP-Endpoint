<?php
/**
 * Pridge Server HTTP client.
 *
 * @package PridgeWPEndpoint
 */

namespace Pridge\Http;

defined( 'ABSPATH' ) || exit;

final class Client {
	private const REQUEST_TIMEOUT = 10;
	private const METADATA_LIMIT  = 4096;

	/**
	 * Submit raw print data to a Pridge endpoint.
	 *
	 * @param string               $server_url   Pridge Server base URL.
	 * @param string               $token        Endpoint bearer token.
	 * @param string               $payload      Raw payload bytes.
	 * @param string               $content_type Payload MIME type.
	 * @param array<string, mixed> $metadata     Optional job metadata.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function submit_job( $server_url, $token, $payload, $content_type, array $metadata = array() ) {
		if ( '' === $payload ) {
			return new \WP_Error(
				'pridge_empty_payload',
				__( 'The print payload is empty.', 'pridge-wp-endpoint' )
			);
		}

		if ( '' === $server_url || ! wp_http_validate_url( $server_url ) ) {
			return new \WP_Error(
				'pridge_invalid_server_url',
				__( 'The Pridge Server URL is missing or invalid.', 'pridge-wp-endpoint' )
			);
		}

		if ( '' === $token ) {
			return new \WP_Error(
				'pridge_missing_token',
				__( 'The Pridge endpoint token is missing.', 'pridge-wp-endpoint' )
			);
		}

		$content_type = strtolower( trim( $content_type ) );
		if ( ! preg_match( '~^[a-z0-9!#$&^_.+\-]+/[a-z0-9!#$&^_.+\-]+$~', $content_type ) ) {
			return new \WP_Error(
				'pridge_invalid_content_type',
				__( 'The print payload content type is invalid.', 'pridge-wp-endpoint' )
			);
		}

		$headers = array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => $content_type,
		);

		if ( array() !== $metadata ) {
			$encoded_metadata = wp_json_encode( $metadata );

			if ( false === $encoded_metadata || self::METADATA_LIMIT < strlen( $encoded_metadata ) ) {
				return new \WP_Error(
					'pridge_invalid_metadata',
					__( 'Print job metadata must be valid JSON and no larger than 4 KB.', 'pridge-wp-endpoint' )
				);
			}

			$headers['X-Pridge-Metadata'] = $encoded_metadata;
		}

		$response = wp_remote_post(
			untrailingslashit( $server_url ) . '/api/plugin/jobs',
			array(
				'body'        => $payload,
				'headers'     => $headers,
				'redirection' => 2,
				'sslverify'   => true,
				'timeout'     => self::REQUEST_TIMEOUT,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'pridge_network_error',
				__( 'Pridge Server could not be reached.', 'pridge-wp-endpoint' ),
				array( 'reason' => $response->get_error_message() )
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = (string) wp_remote_retrieve_body( $response );

		if ( 201 !== $status_code ) {
			return $this->response_error( $status_code, $body );
		}

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) || empty( $decoded['job_id'] ) ) {
			return new \WP_Error(
				'pridge_invalid_response',
				__( 'Pridge Server returned an invalid success response.', 'pridge-wp-endpoint' ),
				array( 'status' => $status_code )
			);
		}

		return array(
			'job_id' => (int) $decoded['job_id'],
			'status' => isset( $decoded['status'] ) ? sanitize_key( (string) $decoded['status'] ) : 'pending',
		);
	}

	/**
	 * Convert a rejected server response into a safe WordPress error.
	 *
	 * @param int    $status_code HTTP status.
	 * @param string $body        Response body.
	 * @return \WP_Error
	 */
	private function response_error( $status_code, $body ) {
		$decoded = json_decode( $body, true );
		$message = is_array( $decoded ) && isset( $decoded['error'] )
			? sanitize_text_field( (string) $decoded['error'] )
			: '';

		if ( 400 === $status_code ) {
			$code    = 'pridge_payload_rejected';
			$message = $message ?: __( 'Pridge rejected the print payload.', 'pridge-wp-endpoint' );
		} elseif ( 401 === $status_code ) {
			$code    = 'pridge_authentication_failed';
			$message = __( 'The endpoint token was rejected. Update it in Pridge settings.', 'pridge-wp-endpoint' );
		} else {
			$code    = 'pridge_server_error';
			$message = $message ?: __( 'Pridge Server rejected the print job.', 'pridge-wp-endpoint' );
		}

		return new \WP_Error(
			$code,
			$message,
			array( 'status' => $status_code )
		);
	}
}
