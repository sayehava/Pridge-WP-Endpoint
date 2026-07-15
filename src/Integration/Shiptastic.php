<?php
/**
 * Shiptastic shipping status and label integration.
 *
 * @package PridgeWPEndpoint
 */

namespace Pridge\Integration;

use Pridge\EndpointRepository;
use Pridge\JobService;

defined( 'ABSPATH' ) || exit;

final class Shiptastic {
	/** @var JobService */
	private $jobs;

	/** @var EndpointRepository */
	private $endpoints;

	/**
	 * @param JobService         $jobs      Print job service.
	 * @param EndpointRepository $endpoints Document routes.
	 */
	public function __construct( JobService $jobs, EndpointRepository $endpoints ) {
		$this->jobs      = $jobs;
		$this->endpoints = $endpoints;
	}

	/**
	 * @return void
	 */
	public function register() {
		add_action( 'woocommerce_shiptastic_shipment_created_label', array( $this, 'submit_label' ), 30, 2 );
	}

	/**
	 * Forward a newly created label to the provider-specific Pridge route.
	 *
	 * @param object $shipment Shiptastic shipment object.
	 * @param array  $props    Label creation properties.
	 * @return void
	 */
	public function submit_label( $shipment, $props = array() ) {
		unset( $props );
		$this->process_label( $shipment, false );
	}

	/**
	 * Force every existing label for one selected order through its configured carrier route.
	 *
	 * This never asks a carrier to create or purchase a new label.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array<int, array<string, mixed>|\WP_Error>|\WP_Error
	 */
	public function test_order_labels( $order_id ) {
		return $this->submit_order_labels( $order_id, true );
	}

	/**
	 * Submit every existing label for one order through its configured carrier route.
	 *
	 * @param int  $order_id WooCommerce order ID.
	 * @param bool $force    Ignore normal label duplicate prevention.
	 * @return array<int, array<string, mixed>|\WP_Error>|\WP_Error
	 */
	public function submit_order_labels( $order_id, $force = false ) {
		if ( ! function_exists( 'wc_stc_get_shipments_by_order' ) ) {
			return array();
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return new \WP_Error(
				'pridge_order_not_found',
				__( 'The selected WooCommerce order could not be found.', 'pridge-wp-endpoint' )
			);
		}

		$results = array();
		foreach ( (array) wc_stc_get_shipments_by_order( $order ) as $shipment ) {
			$result = $this->process_label( $shipment, $force );
			if ( null !== $result ) {
				$results[] = $result;
			}
		}

		return $results;
	}

	/**
	 * @param object $shipment Shiptastic shipment object.
	 * @param bool   $force    Ignore duplicate prevention for a manual test.
	 * @return array<string, mixed>|\WP_Error|null
	 */
	private function process_label( $shipment, $force ) {
		if ( ! is_object( $shipment ) || ! is_callable( array( $shipment, 'get_label' ) ) ) {
			return null;
		}

		$label = $shipment->get_label();
		if ( ! is_object( $label ) || ! is_callable( array( $label, 'get_stream' ) ) ) {
			return null;
		}

		$provider = '';
		if ( is_callable( array( $label, 'get_shipping_provider' ) ) ) {
			$provider = sanitize_key( (string) $label->get_shipping_provider() );
		} elseif ( is_callable( array( $shipment, 'get_shipping_provider' ) ) ) {
			$provider = sanitize_key( (string) $shipment->get_shipping_provider() );
		}

		$document_type = 'shipping_label__' . $provider;
		$endpoint_id   = $this->endpoints->route_endpoint_id( $document_type );
		if ( '' === $provider || '' === $endpoint_id ) {
			return null;
		}

		$meta_key = '_pridge_wp_job_' . $endpoint_id;
		if ( ! $force && is_callable( array( $label, 'get_meta' ) ) && $label->get_meta( $meta_key, true ) ) {
			return null;
		}

		$payload = $label->get_stream();
		if ( ! is_string( $payload ) || '' === $payload ) {
			return null;
		}

		$order_id = is_callable( array( $shipment, 'get_order_id' ) ) ? (int) $shipment->get_order_id() : 0;
		$filename = is_callable( array( $label, 'get_filename' ) ) ? sanitize_file_name( (string) $label->get_filename() ) : 'shipping-label.pdf';
		$result   = $this->jobs->submit(
			$payload,
			array(
				'content_type'  => $this->content_type( $filename ),
				'document_type' => $document_type,
				'endpoint_id'   => $endpoint_id,
				'metadata'      => array(
					'source'            => 'shiptastic',
					'order_id'          => (string) $order_id,
					'document_type'     => $document_type,
					'shipping_provider' => $provider,
					'shipment_id'       => is_callable( array( $shipment, 'get_id' ) ) ? (string) $shipment->get_id() : '',
					'label_id'          => is_callable( array( $label, 'get_id' ) ) ? (string) $label->get_id() : '',
					'filename'          => $filename,
					'event'             => $force ? 'manual_test' : 'label_created',
				),
			)
		);

		if ( ! $force && ! is_wp_error( $result ) && is_callable( array( $label, 'update_meta_data' ) ) ) {
			$label->update_meta_data( $meta_key, (int) $result['job_id'] );
			if ( is_callable( array( $label, 'save' ) ) ) {
				$label->save();
			}
		}

		return $result;
	}

	/**
	 * @param string $filename Label filename.
	 * @return string
	 */
	private function content_type( $filename ) {
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$types     = array(
			'pdf'  => 'application/pdf',
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'zpl'  => 'text/plain',
		);

		return $types[ $extension ] ?? 'application/octet-stream';
	}
}
