<?php
/**
 * Existing Germanized Pro and Shiptastic PDF document submission.
 *
 * @package PridgeWPEndpoint
 */

namespace Pridge\Integration;

use Pridge\EndpointRepository;
use Pridge\IntegrationSettings;
use Pridge\JobService;

defined( 'ABSPATH' ) || exit;

final class GermanizedDocuments {
	/** @var JobService */
	private $jobs;

	/** @var EndpointRepository */
	private $endpoints;

	/** @var IntegrationSettings|null */
	private $settings;

	/**
	 * @param JobService               $jobs      Print job service.
	 * @param EndpointRepository       $endpoints Document routes.
	 * @param IntegrationSettings|null $settings  Integration settings, used to apply the post-print shipment status.
	 */
	public function __construct( JobService $jobs, EndpointRepository $endpoints, IntegrationSettings $settings = null ) {
		$this->jobs      = $jobs;
		$this->endpoints = $endpoints;
		$this->settings  = $settings;
	}

	/**
	 * Submit the selected order's existing invoice, packing slips, and shipping labels.
	 *
	 * No missing document is generated as part of a test.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array<int, array<string, mixed>|\WP_Error>|\WP_Error
	 */
	public function test_order( $order_id ) {
		return $this->submit_order( $order_id, true );
	}

	/**
	 * Document route keys that are configured but do not yet have a readable document.
	 *
	 * Used to wait until every routed document for an order (invoice, packing slips,
	 * shipping labels) exists before printing any of them, since Germanized and
	 * Shiptastic can take time after the order event to finish generating them.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return string[]
	 */
	public function missing_documents( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return array( 'order' );
		}

		$missing = array();

		if ( '' !== $this->endpoints->route_endpoint_id( 'invoice' ) && ! $this->find_invoice( $order ) ) {
			$missing[] = 'invoice';
		}

		if ( '' !== $this->endpoints->route_endpoint_id( 'packing_slip' ) && ! $this->has_any_packing_slip( $order ) ) {
			$missing[] = 'packing_slip';
		}

		return array_merge( $missing, ( new Shiptastic( $this->jobs, $this->endpoints, $this->settings ) )->missing_label_routes( $order ) );
	}

	/**
	 * @param \WC_Order $order WooCommerce order.
	 * @return bool
	 */
	private function has_any_packing_slip( \WC_Order $order ) {
		if ( ! function_exists( 'wc_stc_get_shipments_by_order' ) ) {
			return false;
		}

		foreach ( (array) wc_stc_get_shipments_by_order( $order ) as $shipment ) {
			if ( ! is_object( $shipment ) || ! is_callable( array( $shipment, 'get_attachment' ) ) ) {
				continue;
			}

			if ( '' !== $this->object_path( $shipment->get_attachment( 'packing_slip' ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Submit existing Germanized documents for an automatic or manual order event.
	 *
	 * @param int  $order_id WooCommerce order ID.
	 * @param bool $force    Ignore normal document duplicate prevention.
	 * @return array<int, array<string, mixed>|\WP_Error>|\WP_Error
	 */
	public function submit_order( $order_id, $force = false ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return new \WP_Error(
				'pridge_order_not_found',
				__( 'The selected WooCommerce order could not be found.', 'pridge-wp-endpoint' )
			);
		}

		$results = array();
		$this->append_invoice( $results, $order, $force );
		$this->append_packing_slips( $results, $order, $force );
		$this->append_shipping_labels( $results, $order, $force );

		return $results;
	}

	/**
	 * @param array<int, array<string, mixed>|\WP_Error> $results Submission results.
	 * @param \WC_Order                                  $order   WooCommerce order.
	 * @param bool                                       $force   Ignore duplicate prevention.
	 * @return void
	 */
	private function append_invoice( array &$results, \WC_Order $order, $force ) {
		$endpoint_id = $this->endpoints->route_endpoint_id( 'invoice' );
		if ( '' === $endpoint_id ) {
			return;
		}

		$invoice = $this->find_invoice( $order );
		if ( ! $invoice ) {
			$results[] = new \WP_Error( 'pridge_invoice_pdf_not_found', __( 'No finalized Germanized invoice PDF exists for this order.', 'pridge-wp-endpoint' ) );
			return;
		}

		$path = $this->object_path( $invoice );
		if ( '' === $path ) {
			$results[] = new \WP_Error( 'pridge_invoice_pdf_not_found', __( 'The Germanized invoice PDF file could not be read.', 'pridge-wp-endpoint' ) );
			return;
		}

		$dedupe_key = $this->dedupe_key( 'invoice', $this->object_id( $invoice ), $endpoint_id );
		if ( ! $force && $order->get_meta( $dedupe_key, true ) ) {
			return;
		}

		$result = $this->submit_pdf(
			$path,
			$this->object_filename( $invoice, 'invoice-' . $order->get_order_number() . '.pdf' ),
			'invoice',
			$endpoint_id,
			$order,
			array( 'invoice_id' => $this->object_id( $invoice ) ),
			$force
		);
		$results[] = $result;
		$this->mark_submitted( $order, $dedupe_key, $result, $force );
	}

	/**
	 * @param array<int, array<string, mixed>|\WP_Error> $results Submission results.
	 * @param \WC_Order                                  $order   WooCommerce order.
	 * @param bool                                       $force   Ignore duplicate prevention.
	 * @return void
	 */
	private function append_packing_slips( array &$results, \WC_Order $order, $force ) {
		$endpoint_id = $this->endpoints->route_endpoint_id( 'packing_slip' );
		if ( '' === $endpoint_id ) {
			return;
		}

		$found = false;
		$paths = array();
		if ( function_exists( 'wc_stc_get_shipments_by_order' ) ) {
			foreach ( (array) wc_stc_get_shipments_by_order( $order ) as $shipment ) {
				if ( ! is_object( $shipment ) || ! is_callable( array( $shipment, 'get_attachment' ) ) ) {
					continue;
				}

				$attachment = $shipment->get_attachment( 'packing_slip' );
				$path       = $this->object_path( $attachment );
				$path_key   = '' !== $path ? ( realpath( $path ) ?: $path ) : '';
				if ( '' === $path || isset( $paths[ $path_key ] ) ) {
					continue;
				}

				$paths[ $path_key ] = true;
				$found              = true;
				$attachment_id      = $this->object_id( $attachment );
				$dedupe_key         = $this->dedupe_key( 'packing_slip', $attachment_id ?: md5( $path_key ), $endpoint_id );
				if ( ! $force && $order->get_meta( $dedupe_key, true ) ) {
					continue;
				}

				$result = $this->submit_pdf(
					$path,
					$this->object_filename( $attachment, 'packing-slip-' . $order->get_order_number() . '.pdf' ),
					'packing_slip',
					$endpoint_id,
					$order,
					array(
						'shipment_id'   => $this->object_id( $shipment ),
						'attachment_id' => $attachment_id,
					),
					$force
				);
				$results[] = $result;
				$this->mark_submitted( $order, $dedupe_key, $result, $force );
			}
		}

		if ( ! $found ) {
			$results[] = new \WP_Error( 'pridge_packing_slip_pdf_not_found', __( 'No Germanized packing-slip PDF exists for this order.', 'pridge-wp-endpoint' ) );
		}
	}

	/**
	 * @param array<int, array<string, mixed>|\WP_Error> $results Submission results.
	 * @param \WC_Order                                  $order   WooCommerce order.
	 * @param bool                                       $force   Ignore duplicate prevention.
	 * @return void
	 */
	private function append_shipping_labels( array &$results, \WC_Order $order, $force ) {
		if ( ! $this->has_shipping_label_route() ) {
			return;
		}

		$label_results = ( new Shiptastic( $this->jobs, $this->endpoints, $this->settings ) )->submit_order_labels( $order->get_id(), $force );
		if ( is_wp_error( $label_results ) ) {
			$results[] = $label_results;
			return;
		}

		if ( empty( $label_results ) && $force ) {
			$results[] = new \WP_Error( 'pridge_shipping_label_pdf_not_found', __( 'No existing Shiptastic label with a configured carrier route was found.', 'pridge-wp-endpoint' ) );
			return;
		}

		$results = array_merge( $results, $label_results );
	}

	/**
	 * @param \WC_Order $order WooCommerce order.
	 * @return object|null
	 */
	private function find_invoice( \WC_Order $order ) {
		if ( function_exists( 'sab_get_order' ) ) {
			$accounting_order = sab_get_order( $order->get_id() );
			if ( is_object( $accounting_order ) && is_callable( array( $accounting_order, 'get_latest_finalized_invoice' ) ) ) {
				$invoice = $accounting_order->get_latest_finalized_invoice();
				if ( is_object( $invoice ) ) {
					return $invoice;
				}
			}
		}

		if ( function_exists( 'wc_gzdp_get_order_last_invoice' ) ) {
			$invoice = wc_gzdp_get_order_last_invoice( $order );
			if ( is_object( $invoice ) ) {
				return $invoice;
			}
		}

		return null;
	}

	/**
	 * @param object|null $object Document-like object.
	 * @return string
	 */
	private function object_path( $object ) {
		if ( ! is_object( $object ) ) {
			return '';
		}

		foreach ( array( 'get_path', 'get_pdf_path', 'get_file' ) as $method ) {
			if ( is_callable( array( $object, $method ) ) ) {
				$path = $object->{$method}();
				if ( is_string( $path ) && '' !== $path && is_readable( $path ) ) {
					return $path;
				}
			}
		}

		return '';
	}

	/**
	 * @param object|null $object   Document-like object.
	 * @param string      $fallback Fallback filename.
	 * @return string
	 */
	private function object_filename( $object, $fallback ) {
		if ( is_object( $object ) && is_callable( array( $object, 'get_filename' ) ) ) {
			$filename = sanitize_file_name( (string) $object->get_filename() );
			if ( '' !== $filename ) {
				return $filename;
			}
		}

		return sanitize_file_name( $fallback );
	}

	/**
	 * @param object|null $object Document-like object.
	 * @return string
	 */
	private function object_id( $object ) {
		return is_object( $object ) && is_callable( array( $object, 'get_id' ) ) ? (string) $object->get_id() : '';
	}

	/**
	 * @return bool
	 */
	private function has_shipping_label_route() {
		$settings = $this->endpoints->all();
		$routes   = isset( $settings['routes'] ) && is_array( $settings['routes'] ) ? $settings['routes'] : array();

		foreach ( $routes as $document_type => $endpoint_id ) {
			if ( 0 === strpos( (string) $document_type, 'shipping_label__' ) && '' !== sanitize_key( $endpoint_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $document_type Document route key.
	 * @param string $document_id   Germanized document ID.
	 * @param string $endpoint_id   Named endpoint ID.
	 * @return string
	 */
	private function dedupe_key( $document_type, $document_id, $endpoint_id ) {
		return '_pridge_wp_germanized_' . md5( $document_type . '|' . $document_id . '|' . $endpoint_id );
	}

	/**
	 * @param \WC_Order                       $order      WooCommerce order.
	 * @param string                         $dedupe_key Order metadata key.
	 * @param array<string, mixed>|\WP_Error $result Submission result.
	 * @param bool                           $force      Ignore duplicate prevention.
	 * @return void
	 */
	private function mark_submitted( \WC_Order $order, $dedupe_key, $result, $force ) {
		if ( ! $force && ! is_wp_error( $result ) ) {
			$order->update_meta_data( $dedupe_key, (int) $result['job_id'] );
			$order->save_meta_data();
		}
	}

	/**
	 * @param string               $path          Absolute PDF path.
	 * @param string               $filename      Archive filename.
	 * @param string               $document_type Document route key.
	 * @param string               $endpoint_id   Named endpoint ID.
	 * @param \WC_Order            $order         WooCommerce order.
	 * @param array<string, mixed> $metadata      Document-specific metadata.
	 * @param bool                 $force         Whether this is a manual test.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function submit_pdf( $path, $filename, $document_type, $endpoint_id, \WC_Order $order, array $metadata = array(), $force = false ) {
		$payload = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_string( $payload ) || '' === $payload ) {
			return new \WP_Error( 'pridge_pdf_read_failed', __( 'A Germanized PDF file could not be read.', 'pridge-wp-endpoint' ) );
		}

		return $this->jobs->submit(
			$payload,
			array(
				'content_type'  => 'application/pdf',
				'document_type' => $document_type,
				'endpoint_id'   => $endpoint_id,
				'metadata'      => array_merge(
					array(
						'source'        => 'germanized',
						'order_id'      => (string) $order->get_id(),
						'order_number'  => (string) $order->get_order_number(),
						'document_type' => $document_type,
						'filename'      => $filename,
						'event'         => $force ? 'manual_test' : 'order_' . sanitize_key( $order->get_status() ),
					),
					$metadata
				),
			)
		);
	}
}
