<?php
/**
 * Public print-job orchestration service.
 *
 * @package PridgeWPEndpoint
 */

namespace Pridge;

use Pridge\Http\Client;

defined( 'ABSPATH' ) || exit;

final class JobService {
	/** @var Settings */
	private $settings;

	/** @var Client */
	private $client;

	/** @var EndpointRepository */
	private $endpoints;

	/** @var ArchiveRepository */
	private $archive;

	/**
	 * @param Settings           $settings  Plugin settings.
	 * @param Client             $client    Pridge HTTP client.
	 * @param EndpointRepository $endpoints Named endpoint tokens.
	 * @param ArchiveRepository  $archive   Submission archive.
	 */
	public function __construct( Settings $settings, Client $client, EndpointRepository $endpoints, ArchiveRepository $archive ) {
		$this->settings  = $settings;
		$this->client    = $client;
		$this->endpoints = $endpoints;
		$this->archive   = $archive;
	}

	/**
	 * Submit a raw print job using the configured endpoint.
	 *
	 * Supported arguments:
	 * - content_type: MIME type for the raw payload.
	 * - metadata: Small non-sensitive metadata array.
	 * - endpoint_id: Named endpoint identifier. Uses the default when omitted.
	 * - document_type: Archive and routing document key.
	 *
	 * @param string               $payload Raw print payload bytes.
	 * @param array<string, mixed> $args    Submission arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function submit( $payload, array $args = array() ) {
		if ( ! is_string( $payload ) ) {
			return new \WP_Error(
				'pridge_invalid_payload_type',
				__( 'The print payload must be a string of raw bytes.', 'pridge-wp-endpoint' )
			);
		}

		$defaults = array(
			'content_type' => (string) $this->settings->get( 'default_content_type', 'text/plain' ),
			'metadata'     => array( 'source' => 'wordpress' ),
			'endpoint_id'  => '',
			'document_type'=> 'custom',
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( ! is_array( $args['metadata'] ) ) {
			return new \WP_Error(
				'pridge_invalid_metadata',
				__( 'Print job metadata must be an array.', 'pridge-wp-endpoint' )
			);
		}

		$context = array(
			'byte_count'   => strlen( $payload ),
			'content_type' => (string) $args['content_type'],
			'document_type'=> sanitize_key( (string) $args['document_type'] ),
		);

		/**
		 * Filters non-sensitive metadata immediately before a Pridge job is submitted.
		 *
		 * @param array<string, mixed> $metadata Job metadata.
		 * @param array<string, mixed> $context  Safe submission context.
		 */
		$metadata = apply_filters( 'pridge_wp_job_metadata', $args['metadata'], $context );

		if ( ! is_array( $metadata ) ) {
			return new \WP_Error(
				'pridge_invalid_filtered_metadata',
				__( 'Filtered Pridge metadata must remain an array.', 'pridge-wp-endpoint' )
			);
		}

		/**
		 * Fires immediately before submission without exposing the token or raw payload.
		 *
		 * @param array<string, mixed> $context Safe submission context.
		 */
		do_action( 'pridge_wp_before_submit', $context );

		$endpoint = $this->endpoints->resolve( (string) $args['endpoint_id'] );
		if ( ! $endpoint && '' === (string) $args['endpoint_id'] && $this->settings->get( 'endpoint_token', '' ) ) {
			$endpoint = array(
				'id'    => 'legacy-default',
				'name'  => __( 'Default printer', 'pridge-wp-endpoint' ),
				'token' => (string) $this->settings->get( 'endpoint_token', '' ),
			);
		}

		if ( ! $endpoint || empty( $endpoint['token'] ) ) {
			$result = new \WP_Error(
				'pridge_endpoint_not_configured',
				__( 'The selected Pridge endpoint is not configured.', 'pridge-wp-endpoint' )
			);
		} else {
			$context['endpoint_id'] = $endpoint['id'];
			$result                 = $this->client->submit_job(
				(string) $this->settings->get( 'server_url', '' ),
				(string) $endpoint['token'],
				$payload,
				(string) $args['content_type'],
				$metadata
			);
		}

		$archive_id = $this->archive->record(
			array(
				'job_id'       => is_wp_error( $result ) ? 0 : (int) $result['job_id'],
				'endpoint_id'  => $endpoint['id'] ?? sanitize_key( (string) $args['endpoint_id'] ),
				'endpoint_name'=> $endpoint['name'] ?? '',
				'document_type'=> $context['document_type'] ?: 'custom',
				'source'       => sanitize_key( $metadata['source'] ?? 'wordpress' ),
				'order_id'     => isset( $metadata['order_id'] ) ? (int) $metadata['order_id'] : 0,
				'status'       => is_wp_error( $result ) ? 'failed' : 'sent',
				'content_type' => (string) $args['content_type'],
				'payload'      => $payload,
				'metadata'     => $metadata,
				'error_code'   => is_wp_error( $result ) ? $result->get_error_code() : '',
			)
		);

		if ( ! is_wp_error( $result ) ) {
			$result['archive_id']    = $archive_id;
			$result['endpoint_id']   = $endpoint['id'];
			$result['endpoint_name'] = $endpoint['name'];
		}

		/**
		 * Fires after submission without exposing the token or raw payload.
		 *
		 * @param array<string, mixed>|\WP_Error $result  Submission result.
		 * @param array<string, mixed>           $context Safe submission context.
		 */
		do_action( 'pridge_wp_after_submit', $result, $context );

		return $result;
	}
}
