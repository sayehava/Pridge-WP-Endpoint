<?php
/**
 * WooCommerce order document printing integration.
 *
 * @package PridgeWPEndpoint
 */

namespace Pridge\Integration;

use Pridge\Cron;
use Pridge\EndpointRepository;
use Pridge\IntegrationSettings;
use Pridge\JobService;

defined( 'ABSPATH' ) || exit;

final class WooCommerce {
	private const MAX_RETRIES = 3;

	/** @var IntegrationSettings */
	private $settings;

	/** @var JobService */
	private $jobs;

	/** @var EndpointRepository */
	private $endpoints;

	/**
	 * @param IntegrationSettings $settings  Integration settings.
	 * @param JobService          $jobs      Print job service.
	 * @param EndpointRepository  $endpoints Document routes.
	 */
	public function __construct( IntegrationSettings $settings, JobService $jobs, EndpointRepository $endpoints ) {
		$this->settings  = $settings;
		$this->jobs      = $jobs;
		$this->endpoints = $endpoints;
	}

	/**
	 * Register order transition and retry hooks.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! $this->settings->get( 'woocommerce_enabled', false ) ) {
			return;
		}

		$status = sanitize_key( (string) $this->settings->get( 'trigger_status', 'processing' ) );
		if ( 'shipped' === $status && function_exists( 'wc_stc_get_shipment_order' ) ) {
			add_action( 'woocommerce_shiptastic_order_update_shipping_status', array( $this, 'submit_shipped_order' ), 20, 3 );
		} elseif ( 'shipped' !== $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( $this, 'submit_order' ), 20, 2 );
		}

		add_action( 'pridge_wp_retry_woocommerce_document', array( $this, 'retry_document' ), 10, 3 );
	}

	/**
	 * Submit routed documents after a WooCommerce status transition.
	 *
	 * @param int            $order_id WooCommerce order ID.
	 * @param \WC_Order|null $order    Order instance when supplied by WooCommerce.
	 * @return void
	 */
	public function submit_order( $order_id, $order = null ) {
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order_id );
		}

		if ( $order instanceof \WC_Order ) {
			$this->process_order( $order );
		}
	}

	/**
	 * Submit routed documents when Shiptastic marks an order shipped.
	 *
	 * @param \WC_Order $order      WooCommerce order.
	 * @param string    $new_status New Shiptastic shipping status.
	 * @param string    $old_status Previous Shiptastic shipping status.
	 * @return void
	 */
	public function submit_shipped_order( $order, $new_status, $old_status ) {
		unset( $old_status );

		if ( $order instanceof \WC_Order && 'shipped' === sanitize_key( (string) $new_status ) ) {
			$this->process_order( $order );
		}
	}

	/**
	 * Retry one transient document submission failure.
	 *
	 * @param int    $order_id     WooCommerce order ID.
	 * @param string $document_type Document route key.
	 * @param int    $attempt      One-based retry attempt.
	 * @return void
	 */
	public function retry_document( $order_id, $document_type, $attempt ) {
		$order = wc_get_order( $order_id );

		if ( $order instanceof \WC_Order ) {
			$this->process_document( $order, sanitize_key( $document_type ), absint( $attempt ) );
		}
	}

	/**
	 * Process every order document that has an assigned route.
	 *
	 * Built-in WooCommerce documents (receipt, invoice, packing slip) are generated
	 * on the spot from order data and are always ready immediately. Germanized's
	 * invoice, packing-slip, and shipping-label PDFs are files that can take time to
	 * finish generating after the order event fires, so those are only sent once
	 * every routed one of them exists - an order still missing one is left for
	 * Cron to pick up and print as soon as they are all ready.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return void
	 */
	private function process_order( \WC_Order $order ) {
		$document_types = array( 'receipt', 'invoice', 'packing_slip' );
		$germanized_pending = false;

		if ( $this->settings->get( 'germanized_enabled', false ) && Germanized::is_available() ) {
			$document_types      = array( 'receipt' );
			$germanized_pending = $this->dispatch_germanized_documents( $order );
		}

		foreach ( $document_types as $document_type ) {
			$this->process_document( $order, $document_type, 0 );
		}

		if ( ! $germanized_pending ) {
			$this->apply_post_print_status( $order );
		}
	}

	/**
	 * Send an order's Germanized documents now if they are all ready, or mark it
	 * pending for Cron to retry once they are.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return bool True when the order still has documents pending and was not sent.
	 */
	private function dispatch_germanized_documents( \WC_Order $order ) {
		$germanized = new GermanizedDocuments( $this->jobs, $this->endpoints, $this->settings );

		if ( ! empty( $germanized->missing_documents( $order->get_id() ) ) ) {
			Cron::mark_pending( $order->get_id() );
			return true;
		}

		$germanized->submit_order( $order->get_id(), false );

		return false;
	}

	/**
	 * Move the order to the configured post-print WooCommerce status.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return void
	 */
	public function apply_post_print_status( \WC_Order $order ) {
		$target = sanitize_key( (string) $this->settings->get( 'post_print_woocommerce_status', '' ) );

		if ( '' !== $target && $target !== $order->get_status() ) {
			$order->update_status( $target, __( 'Pridge: order documents printed.', 'pridge-wp-endpoint' ) );
		}
	}

	/**
	 * Build and submit one routed order document.
	 *
	 * @param \WC_Order $order         WooCommerce order.
	 * @param string    $document_type Document route key.
	 * @param int       $attempt       Current retry attempt.
	 * @return void
	 */
	private function process_document( \WC_Order $order, $document_type, $attempt ) {
		$endpoint_id = $this->endpoints->route_endpoint_id( $document_type );
		if ( '' === $endpoint_id ) {
			return;
		}

		$trigger  = sanitize_key( (string) $this->settings->get( 'trigger_status', 'processing' ) );
		$meta_key = '_pridge_wp_' . $trigger . '_' . sanitize_key( $document_type );
		if ( $order->get_meta( $meta_key, true ) ) {
			return;
		}

		$payload = $this->build_document( $document_type, $order );
		if ( '' === $payload ) {
			return;
		}

		$result = $this->jobs->submit(
			$payload,
			array(
				'content_type'  => 'text/plain',
				'document_type' => $document_type,
				'endpoint_id'   => $endpoint_id,
				'metadata'      => array(
					'source'        => 'woocommerce',
					'order_id'      => (string) $order->get_id(),
					'order_number'  => (string) $order->get_order_number(),
					'document_type' => $document_type,
					'event'         => 'order_' . $trigger,
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->log_failure( $order, $document_type, $result, $attempt );

			if ( $attempt < self::MAX_RETRIES && $this->is_transient_error( $result ) ) {
				$this->schedule_retry( $order->get_id(), $document_type, $attempt + 1 );
			}

			return;
		}

		$order->update_meta_data( $meta_key, (int) $result['job_id'] );
		$order->save_meta_data();
	}

	/**
	 * @param string    $document_type Document route key.
	 * @param \WC_Order $order         WooCommerce order.
	 * @return string
	 */
	private function build_document( $document_type, \WC_Order $order ) {
		switch ( $document_type ) {
			case 'receipt':
				return $this->build_receipt( $order );
			case 'invoice':
				return $this->build_invoice( $order );
			case 'packing_slip':
				return $this->build_packing_slip( $order );
			default:
				return '';
		}
	}

	/**
	 * Build a compact plain-text receipt from WooCommerce CRUD objects.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return string
	 */
	private function build_receipt( \WC_Order $order ) {
		$lines = $this->document_header( $order, __( 'RECEIPT', 'pridge-wp-endpoint' ) );
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$lines[] = sprintf(
				/* translators: 1: item quantity, 2: item name, 3: formatted line total. */
				__( '%1$s x %2$s  %3$s', 'pridge-wp-endpoint' ),
				$this->clean_text( (string) $item->get_quantity() ),
				$this->clean_text( $item->get_name() ),
				$this->format_money( $order, (float) $item->get_total() + (float) $item->get_total_tax() )
			);
		}

		$lines[] = str_repeat( '-', 32 );
		$lines[] = sprintf(
			/* translators: %s: formatted order total. */
			__( 'Total: %s', 'pridge-wp-endpoint' ),
			$this->format_money( $order, (float) $order->get_total() )
		);

		/**
		 * Filters receipt lines before they become a raw text payload.
		 *
		 * @param string[]  $lines Receipt lines.
		 * @param \WC_Order $order WooCommerce order.
		 */
		$lines = apply_filters( 'pridge_wp_woocommerce_receipt_lines', $lines, $order );

		return $this->lines_to_payload( $lines );
	}

	/**
	 * Build a printable invoice representation without requiring a PDF extension.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return string
	 */
	private function build_invoice( \WC_Order $order ) {
		$lines   = $this->document_header( $order, __( 'INVOICE', 'pridge-wp-endpoint' ) );
		$lines[] = __( 'Bill to:', 'pridge-wp-endpoint' );
		$lines   = array_merge( $lines, $this->address_lines( $order, 'billing' ), array( str_repeat( '-', 32 ) ) );

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$lines[] = sprintf(
				/* translators: 1: item quantity, 2: item name, 3: formatted line total. */
				__( '%1$s x %2$s  %3$s', 'pridge-wp-endpoint' ),
				$this->clean_text( (string) $item->get_quantity() ),
				$this->clean_text( $item->get_name() ),
				$this->format_money( $order, (float) $item->get_total() + (float) $item->get_total_tax() )
			);
		}

		$lines[] = str_repeat( '-', 32 );
		$lines[] = sprintf( __( 'Subtotal: %s', 'pridge-wp-endpoint' ), $this->format_money( $order, (float) $order->get_subtotal() ) );
		$lines[] = sprintf( __( 'Shipping: %s', 'pridge-wp-endpoint' ), $this->format_money( $order, (float) $order->get_shipping_total() + (float) $order->get_shipping_tax() ) );
		$lines[] = sprintf( __( 'Tax: %s', 'pridge-wp-endpoint' ), $this->format_money( $order, (float) $order->get_total_tax() ) );
		$lines[] = sprintf( __( 'Total: %s', 'pridge-wp-endpoint' ), $this->format_money( $order, (float) $order->get_total() ) );

		/**
		 * Filters invoice lines before they become a raw text payload.
		 *
		 * @param string[]  $lines Invoice lines.
		 * @param \WC_Order $order WooCommerce order.
		 */
		$lines = apply_filters( 'pridge_wp_woocommerce_invoice_lines', $lines, $order );

		return $this->lines_to_payload( $lines );
	}

	/**
	 * Build a price-free packing slip.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @return string
	 */
	private function build_packing_slip( \WC_Order $order ) {
		$lines   = $this->document_header( $order, __( 'PACKING SLIP', 'pridge-wp-endpoint' ) );
		$lines[] = __( 'Ship to:', 'pridge-wp-endpoint' );
		$lines   = array_merge( $lines, $this->address_lines( $order, $order->has_shipping_address() ? 'shipping' : 'billing' ), array( str_repeat( '-', 32 ) ) );

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$sku       = $item->get_product() ? $item->get_product()->get_sku() : '';
			$lines[]   = sprintf(
				/* translators: 1: item quantity, 2: item name. */
				__( '%1$s x %2$s', 'pridge-wp-endpoint' ),
				$this->clean_text( (string) $item->get_quantity() ),
				$this->clean_text( $item->get_name() )
			);
			if ( '' !== $sku ) {
				$lines[] = sprintf( __( 'SKU: %s', 'pridge-wp-endpoint' ), $this->clean_text( $sku ) );
			}
		}

		/**
		 * Filters packing-slip lines before they become a raw text payload.
		 *
		 * @param string[]  $lines Packing-slip lines.
		 * @param \WC_Order $order WooCommerce order.
		 */
		$lines = apply_filters( 'pridge_wp_woocommerce_packing_slip_lines', $lines, $order );

		return $this->lines_to_payload( $lines );
	}

	/**
	 * @param \WC_Order $order WooCommerce order.
	 * @param string    $title Document title.
	 * @return string[]
	 */
	private function document_header( \WC_Order $order, $title ) {
		$lines = array(
			$this->clean_text( get_bloginfo( 'name' ) ),
			$this->clean_text( $title ),
			sprintf( __( 'Order #%s', 'pridge-wp-endpoint' ), $this->clean_text( (string) $order->get_order_number() ) ),
		);

		if ( $order->get_date_created() ) {
			$lines[] = sprintf(
				__( 'Date: %s', 'pridge-wp-endpoint' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $order->get_date_created()->getTimestamp() )
			);
		}

		$lines[] = str_repeat( '-', 32 );

		return $lines;
	}

	/**
	 * @param \WC_Order $order WooCommerce order.
	 * @param string    $type  Billing or shipping.
	 * @return string[]
	 */
	private function address_lines( \WC_Order $order, $type ) {
		$address = $order->get_address( $type );
		$lines   = array(
			trim( (string) ( $address['first_name'] ?? '' ) . ' ' . (string) ( $address['last_name'] ?? '' ) ),
			(string) ( $address['company'] ?? '' ),
			(string) ( $address['address_1'] ?? '' ),
			(string) ( $address['address_2'] ?? '' ),
			trim( (string) ( $address['postcode'] ?? '' ) . ' ' . (string) ( $address['city'] ?? '' ) ),
			(string) ( $address['state'] ?? '' ),
			(string) ( $address['country'] ?? '' ),
		);

		return array_values( array_filter( array_map( array( $this, 'clean_text' ), $lines ), 'strlen' ) );
	}

	/**
	 * @param mixed $lines Candidate document lines.
	 * @return string
	 */
	private function lines_to_payload( $lines ) {
		if ( ! is_array( $lines ) ) {
			return '';
		}

		return implode( "\n", array_map( array( $this, 'clean_text' ), $lines ) ) . "\n";
	}

	/**
	 * Convert a WooCommerce price to printer-safe plain text.
	 *
	 * @param \WC_Order $order  WooCommerce order.
	 * @param float     $amount Numeric amount.
	 * @return string
	 */
	private function format_money( \WC_Order $order, $amount ) {
		return $this->clean_text( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) );
	}

	/**
	 * Reduce HTML-formatted WooCommerce values to printable text.
	 *
	 * @param mixed $value Text value.
	 * @return string
	 */
	private function clean_text( $value ) {
		return trim( html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' ) );
	}

	/**
	 * Schedule a bounded asynchronous retry.
	 *
	 * @param int    $order_id     WooCommerce order ID.
	 * @param string $document_type Document route key.
	 * @param int    $attempt      One-based retry attempt.
	 * @return void
	 */
	private function schedule_retry( $order_id, $document_type, $attempt ) {
		$timestamp = time() + ( 60 * ( 2 ** max( 0, $attempt - 1 ) ) );
		$args      = array( (int) $order_id, sanitize_key( $document_type ), (int) $attempt );

		if ( function_exists( 'as_schedule_single_action' ) ) {
			if ( ! function_exists( 'as_has_scheduled_action' ) || ! as_has_scheduled_action( 'pridge_wp_retry_woocommerce_document', $args, 'pridge' ) ) {
				as_schedule_single_action( $timestamp, 'pridge_wp_retry_woocommerce_document', $args, 'pridge' );
			}
			return;
		}

		if ( ! wp_next_scheduled( 'pridge_wp_retry_woocommerce_document', $args ) ) {
			wp_schedule_single_event( $timestamp, 'pridge_wp_retry_woocommerce_document', $args );
		}
	}

	/**
	 * @param \WP_Error $error Submission error.
	 * @return bool
	 */
	private function is_transient_error( \WP_Error $error ) {
		if ( 'pridge_network_error' === $error->get_error_code() ) {
			return true;
		}

		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;

		return 'pridge_server_error' === $error->get_error_code() && 500 <= $status;
	}

	/**
	 * Record a safe diagnostic without tokens, payloads, or customer data.
	 *
	 * @param \WC_Order $order         WooCommerce order.
	 * @param string    $document_type Document route key.
	 * @param \WP_Error $error         Submission error.
	 * @param int       $attempt       Retry attempt.
	 * @return void
	 */
	private function log_failure( \WC_Order $order, $document_type, \WP_Error $error, $attempt ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error(
				sprintf(
					'Pridge %1$s submission failed for order %2$d on attempt %3$d: %4$s',
					sanitize_key( $document_type ),
					$order->get_id(),
					$attempt + 1,
					$error->get_error_code()
				),
				array( 'source' => 'pridge-wp-endpoint' )
			);
		}
	}
}
