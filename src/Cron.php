<?php
/**
 * WP-Cron polling for orders whose Germanized documents were not all ready yet.
 *
 * @package PridgeWPEndpoint
 */

namespace Pridge;

use Pridge\Integration\GermanizedDocuments;
use Pridge\Integration\WooCommerce as WooCommerceIntegration;

defined( 'ABSPATH' ) || exit;

final class Cron {
	public const HOOK             = 'pridge_wp_check_pending_orders';
	public const SCHEDULE         = 'pridge_wp_interval';
	public const LAST_RUN_OPTION  = 'pridge_wp_cron_last_run';
	public const PENDING_META     = '_pridge_wp_pending_since';
	public const NEEDS_ATTENTION_META = '_pridge_wp_needs_manual_print';
	private const BATCH_SIZE      = 20;

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
	 * Flag an order as waiting on documents that are not all ready yet.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public static function mark_pending( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		if ( ! $order->get_meta( self::PENDING_META, true ) ) {
			$order->update_meta_data( self::PENDING_META, time() );
		}
		$order->delete_meta_data( self::NEEDS_ATTENTION_META );
		$order->save_meta_data();
	}

	/**
	 * @return void
	 */
	public function register() {
		add_filter( 'cron_schedules', array( $this, 'register_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'init', array( $this, 'ensure_scheduled' ) );
		add_action( 'update_option_' . IntegrationSettings::OPTION_NAME, array( $this, 'reschedule' ) );
	}

	/**
	 * @param array<string, array{interval:int, display:string}> $schedules Registered cron schedules.
	 * @return array<string, array{interval:int, display:string}>
	 */
	public function register_schedule( $schedules ) {
		$minutes = $this->interval_minutes();

		$schedules[ self::SCHEDULE ] = array(
			'interval' => $minutes * MINUTE_IN_SECONDS,
			'display'  => sprintf(
				/* translators: %d: number of minutes. */
				__( 'Every %d minutes (Pridge)', 'pridge-wp-endpoint' ),
				$minutes
			),
		);

		return $schedules;
	}

	/**
	 * @return int
	 */
	public function interval_minutes() {
		$minutes = absint( $this->settings->get( 'cron_interval_minutes', 15 ) );

		return in_array( $minutes, IntegrationSettings::CRON_INTERVALS, true ) ? $minutes : 15;
	}

	/**
	 * @return void
	 */
	public function ensure_scheduled() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::SCHEDULE, self::HOOK );
		}
	}

	/**
	 * Re-arm the schedule so an interval change made in the admin takes effect on the next run.
	 *
	 * @return void
	 */
	public function reschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Check every pending order for document readiness and print the ones that are complete.
	 *
	 * @return void
	 */
	public function run() {
		update_option( self::LAST_RUN_OPTION, time(), false );

		if ( ! function_exists( 'wc_get_orders' ) || ! \Pridge\Integration\Germanized::is_available() ) {
			return;
		}

		$order_ids = wc_get_orders(
			array(
				'limit'      => self::BATCH_SIZE,
				'return'     => 'ids',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => self::PENDING_META,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		if ( empty( $order_ids ) ) {
			return;
		}

		$germanized      = new GermanizedDocuments( $this->jobs, $this->endpoints, $this->settings );
		$woocommerce     = new WooCommerceIntegration( $this->settings, $this->jobs, $this->endpoints );
		$timeout_seconds = absint( $this->settings->get( 'pending_timeout_hours', 24 ) ) * HOUR_IN_SECONDS;
		$timeout_seconds = $timeout_seconds ?: DAY_IN_SECONDS;

		foreach ( (array) $order_ids as $order_id ) {
			$this->process_pending_order( $germanized, $woocommerce, (int) $order_id, $timeout_seconds );
		}
	}

	/**
	 * @param GermanizedDocuments     $germanized      Germanized document sender.
	 * @param WooCommerceIntegration $woocommerce     WooCommerce status helper.
	 * @param int                    $order_id        WooCommerce order ID.
	 * @param int                    $timeout_seconds Seconds to wait before flagging for manual attention.
	 * @return void
	 */
	private function process_pending_order( GermanizedDocuments $germanized, WooCommerceIntegration $woocommerce, $order_id, $timeout_seconds ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$missing = $germanized->missing_documents( $order_id );

		if ( empty( $missing ) ) {
			$germanized->submit_order( $order_id, false );
			$order->delete_meta_data( self::PENDING_META );
			$order->delete_meta_data( self::NEEDS_ATTENTION_META );
			$order->save_meta_data();
			$woocommerce->apply_post_print_status( $order );
			return;
		}

		$pending_since = (int) $order->get_meta( self::PENDING_META, true );
		if ( $pending_since && ( time() - $pending_since ) < $timeout_seconds ) {
			return;
		}

		$order->delete_meta_data( self::PENDING_META );
		$order->update_meta_data( self::NEEDS_ATTENTION_META, 1 );
		$order->save_meta_data();

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->warning(
				sprintf(
					'Pridge: order %1$d still missing documents (%2$s) after the configured wait - needs a manual print.',
					$order_id,
					implode( ', ', $missing )
				),
				array( 'source' => 'pridge-wp-endpoint' )
			);
		}
	}

	/**
	 * @return int Unix timestamp of the last completed run, or 0 if it has never run.
	 */
	public static function last_run() {
		return (int) get_option( self::LAST_RUN_OPTION, 0 );
	}

	/**
	 * @return int Unix timestamp of the next scheduled run, or 0 if none is scheduled.
	 */
	public static function next_run() {
		$timestamp = wp_next_scheduled( self::HOOK );

		return $timestamp ? (int) $timestamp : 0;
	}

	/**
	 * Whether the cron heartbeat looks healthy: it has run recently relative to its own interval.
	 *
	 * @param int $interval_minutes Configured polling interval, in minutes.
	 * @return bool
	 */
	public static function is_healthy( $interval_minutes ) {
		$last_run = self::last_run();
		if ( 0 === $last_run ) {
			return false;
		}

		return ( time() - $last_run ) < ( 2 * max( 1, $interval_minutes ) * MINUTE_IN_SECONDS );
	}

	/**
	 * Orders whose Germanized documents timed out and need a shop manager to print them manually.
	 *
	 * @param int $limit Maximum number of orders to return.
	 * @return \WC_Order[]
	 */
	public static function orders_needing_attention( $limit = 20 ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		return wc_get_orders(
			array(
				'limit'      => $limit,
				'return'     => 'objects',
				'orderby'    => 'date',
				'order'      => 'DESC',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => self::NEEDS_ATTENTION_META,
						'compare' => 'EXISTS',
					),
				),
			)
		);
	}

	/**
	 * @return int Count of orders currently waiting on documents.
	 */
	public static function pending_count() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}

		$ids = wc_get_orders(
			array(
				'limit'      => -1,
				'return'     => 'ids',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => self::PENDING_META,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		return is_array( $ids ) ? count( $ids ) : 0;
	}
}
