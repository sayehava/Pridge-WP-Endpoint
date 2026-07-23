<?php
/**
 * Pridge WordPress administration area.
 *
 * @package PridgeWPEndpoint
 */

namespace Pridge\Admin;

use Pridge\ArchiveRepository;
use Pridge\Cron;
use Pridge\EndpointRepository;
use Pridge\Integration\Germanized;
use Pridge\Integration\GermanizedDocuments;
use Pridge\IntegrationSettings;
use Pridge\JobService;
use Pridge\Settings;
use Pridge\UpdateChecker;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

final class Admin {
	public const PAGE_OVERVIEW = 'pridge-wp-endpoint';
	public const PAGE_SETTINGS = 'pridge-wp-settings';
	public const PAGE_ARCHIVE  = 'pridge-wp-archive';

	/** Settings page sub-tab keys, in display order. */
	public const SETTINGS_TABS = array( 'general', 'integrations', 'endpoints' );

	/** @var Settings */
	private $settings;

	/** @var JobService */
	private $jobs;

	/** @var EndpointRepository */
	private $endpoints;

	/** @var IntegrationSettings */
	private $integration_settings;

	/** @var ArchiveRepository */
	private $archive;

	/** @var Cron */
	private $cron;

	/** @var string[] */
	private $hook_suffixes = array();

	/**
	 * @param Settings            $settings             General settings.
	 * @param JobService          $jobs                 Job submission service.
	 * @param EndpointRepository  $endpoints            Named endpoints and routes.
	 * @param IntegrationSettings $integration_settings Integration switches.
	 * @param ArchiveRepository   $archive              Print archive.
	 * @param Cron                $cron                 Pending-order polling.
	 */
	public function __construct( Settings $settings, JobService $jobs, EndpointRepository $endpoints, IntegrationSettings $integration_settings, ArchiveRepository $archive, Cron $cron ) {
		$this->settings             = $settings;
		$this->jobs                 = $jobs;
		$this->endpoints            = $endpoints;
		$this->integration_settings = $integration_settings;
		$this->archive              = $archive;
		$this->cron                 = $cron;
	}

	/**
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_pridge_wp_test_print', array( $this, 'handle_test_print' ) );
		add_action( 'admin_post_pridge_wp_test_germanized_order', array( $this, 'handle_germanized_test_print' ) );
		add_action( 'admin_post_pridge_wp_archive_payload', array( $this, 'handle_archive_payload' ) );
		add_action( 'admin_post_pridge_wp_restore_backup', array( $this, 'handle_restore_backup' ) );
		add_action( 'admin_post_pridge_wp_delete_backup', array( $this, 'handle_delete_backup' ) );
		add_action( 'admin_post_pridge_wp_backup_now', array( $this, 'handle_backup_now' ) );
		add_action( 'admin_post_pridge_wp_run_cron_check', array( $this, 'handle_run_cron_check' ) );
		add_action( 'admin_post_pridge_wp_send_pending_order', array( $this, 'handle_send_pending_order' ) );
		add_action( 'wp_ajax_pridge_wp_archive_detail', array( $this, 'handle_archive_detail' ) );
		add_action( 'wp_ajax_pridge_wp_cron_status', array( $this, 'handle_cron_status_ajax' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PRIDGE_WP_FILE ), array( $this, 'add_plugin_action_link' ) );

		$isolation = new NoticeIsolation(
			array(
				'toplevel_page_' . self::PAGE_OVERVIEW,
				'pridge_page_' . self::PAGE_SETTINGS,
				'pridge_page_' . self::PAGE_ARCHIVE,
			)
		);
		$isolation->register();
	}

	/**
	 * @return void
	 */
	public function add_menu_pages() {
		$this->hook_suffixes[] = add_menu_page(
			__( 'Pridge', 'pridge-wp-endpoint' ),
			__( 'Pridge', 'pridge-wp-endpoint' ),
			'manage_options',
			self::PAGE_OVERVIEW,
			array( $this, 'render_overview' ),
			'dashicons-printer',
			56
		);

		$this->hook_suffixes[] = add_submenu_page(
			self::PAGE_OVERVIEW,
			__( 'Overview', 'pridge-wp-endpoint' ),
			__( 'Overview', 'pridge-wp-endpoint' ),
			'manage_options',
			self::PAGE_OVERVIEW,
			array( $this, 'render_overview' )
		);
		$this->hook_suffixes[] = add_submenu_page(
			self::PAGE_OVERVIEW,
			__( 'Settings', 'pridge-wp-endpoint' ),
			__( 'Settings', 'pridge-wp-endpoint' ),
			'manage_options',
			self::PAGE_SETTINGS,
			array( $this, 'render_settings' )
		);
		$this->hook_suffixes[] = add_submenu_page(
			self::PAGE_OVERVIEW,
			__( 'Print Archive', 'pridge-wp-endpoint' ),
			__( 'Print Archive', 'pridge-wp-endpoint' ),
			'manage_options',
			self::PAGE_ARCHIVE,
			array( $this, 'render_archive' )
		);
	}

	/**
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'pridge_wp_general_group',
			Settings::OPTION_NAME,
			array(
				'default'           => Settings::defaults(),
				'sanitize_callback' => array( $this->settings, 'sanitize' ),
				'show_in_rest'      => false,
				'type'              => 'array',
			)
		);
		register_setting(
			'pridge_wp_integrations_group',
			IntegrationSettings::OPTION_NAME,
			array(
				'default'           => IntegrationSettings::defaults(),
				'sanitize_callback' => array( $this->integration_settings, 'sanitize' ),
				'show_in_rest'      => false,
				'type'              => 'array',
			)
		);
		register_setting(
			'pridge_wp_endpoints_group',
			EndpointRepository::OPTION_NAME,
			array(
				'default'           => EndpointRepository::defaults(),
				'sanitize_callback' => array( $this->endpoints, 'sanitize' ),
				'show_in_rest'      => false,
				'type'              => 'array',
			)
		);
	}

	/**
	 * @param string $hook_suffix Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array_filter( $this->hook_suffixes ), true ) ) {
			return;
		}

		wp_enqueue_style( 'pridge-wp-admin', PRIDGE_WP_URL . 'assets/css/admin.css', array(), PRIDGE_WP_VERSION );
		wp_enqueue_script( 'pridge-wp-admin', PRIDGE_WP_URL . 'assets/js/admin.js', array(), PRIDGE_WP_VERSION, true );
		wp_localize_script(
			'pridge-wp-admin',
			'PridgeAdmin',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'archiveNonce'   => wp_create_nonce( 'pridge_wp_archive_detail' ),
				'cronStatusNonce'=> wp_create_nonce( 'pridge_wp_cron_status' ),
				'loadingText'  => __( 'Loading archived payload…', 'pridge-wp-endpoint' ),
				'errorText'    => __( 'The archived payload could not be loaded.', 'pridge-wp-endpoint' ),
				'emptyText'    => __( 'No payload content.', 'pridge-wp-endpoint' ),
				'cronLabels'   => array(
					'running'      => __( 'Running', 'pridge-wp-endpoint' ),
					'notConfirmed' => __( 'Not confirmed', 'pridge-wp-endpoint' ),
				),
				'archiveLabels'=> array(
					'document' => __( 'Document', 'pridge-wp-endpoint' ),
					'endpoint' => __( 'Endpoint', 'pridge-wp-endpoint' ),
					'status'   => __( 'Status', 'pridge-wp-endpoint' ),
					'created'  => __( 'Created', 'pridge-wp-endpoint' ),
					'order'    => __( 'Order', 'pridge-wp-endpoint' ),
					'size'     => __( 'Size', 'pridge-wp-endpoint' ),
					'source'   => __( 'Source', 'pridge-wp-endpoint' ),
					'job'      => __( 'Server job', 'pridge-wp-endpoint' ),
					'error'    => __( 'Error', 'pridge-wp-endpoint' ),
					'bytes'    => __( 'bytes', 'pridge-wp-endpoint' ),
				),
			)
		);
	}

	/**
	 * @return void
	 */
	public function render_overview() {
		$this->authorize();
		$settings              = $this->settings->all();
		$is_configured         = $this->settings->is_configured();
		$endpoints             = $this->endpoints->fetch_all();
		$archive_count         = $this->archive->count();
		$backups               = UpdateChecker::list_backups();
		$germanized_enabled    = $this->integration_settings->get( 'germanized_enabled', false ) && Germanized::is_available();
		$cron_interval_minutes = $this->cron->interval_minutes();
		$cron_last_run         = Cron::last_run();
		$cron_next_run         = Cron::next_run();
		$cron_healthy          = Cron::is_healthy( $cron_interval_minutes );
		$pending_count         = Cron::pending_count();
		$attention_orders      = Cron::orders_needing_attention();
		$test_orders           = $germanized_enabled && function_exists( 'wc_get_orders' )
			? wc_get_orders(
				array(
					'limit'   => 50,
					'orderby' => 'date',
					'order'   => 'DESC',
					'return'  => 'objects',
				)
			)
			: array();
		require PRIDGE_WP_DIR . 'views/overview.php';
	}

	/**
	 * Single Settings page with sub-tabs; every configuration form on the plugin lives here.
	 *
	 * @return void
	 */
	public function render_settings() {
		$this->authorize();

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		if ( ! in_array( $tab, self::SETTINGS_TABS, true ) ) {
			$tab = 'general';
		}

		$settings             = $this->settings->all();
		$integration_settings = $this->integration_settings->all();
		$endpoint_settings    = $this->endpoints->all();
		$endpoints            = $this->endpoints->fetch_all();
		$document_types       = $this->document_types();

		$woocommerce_active   = class_exists( 'WooCommerce' );
		$germanized_available = Germanized::is_available();
		$shiptastic_available = function_exists( 'wc_stc_get_shipment_order' );
		$order_statuses       = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
		$shipment_statuses    = function_exists( 'wc_stc_get_shipment_statuses' )
			? wc_stc_get_shipment_statuses()
			: array(
				'draft'      => __( 'Draft', 'pridge-wp-endpoint' ),
				'processing' => __( 'Processing', 'pridge-wp-endpoint' ),
				'shipped'    => __( 'Shipped', 'pridge-wp-endpoint' ),
				'delivered'  => __( 'Delivered', 'pridge-wp-endpoint' ),
				'returned'   => __( 'Returned', 'pridge-wp-endpoint' ),
			);
		$cron_intervals = IntegrationSettings::CRON_INTERVALS;

		require PRIDGE_WP_DIR . 'views/settings.php';
	}

	/**
	 * Archive rendering is implemented by the archive feature layer.
	 *
	 * @return void
	 */
	public function render_archive() {
		$this->authorize();
		$page         = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$archive_rows = $this->archive->fetch_page( $page, 20 );
		$total_rows   = $this->archive->count();
		require PRIDGE_WP_DIR . 'views/archive.php';
	}

	/**
	 * @return array<string, string>
	 */
	private function document_types() {
		$types = array(
			'receipt'      => __( 'Receipt', 'pridge-wp-endpoint' ),
			'invoice'      => __( 'Invoice', 'pridge-wp-endpoint' ),
			'packing_slip' => __( 'Packing slip', 'pridge-wp-endpoint' ),
		);

		if ( function_exists( 'wc_stc_get_shipping_providers' ) ) {
			foreach ( (array) wc_stc_get_shipping_providers() as $provider ) {
				if ( ! is_object( $provider ) || ! is_callable( array( $provider, 'is_activated' ) ) || ! $provider->is_activated() ) {
					continue;
				}

				$name  = sanitize_key( $provider->get_name() );
				$title = sanitize_text_field( $provider->get_title() );
				$types[ 'shipping_label__' . $name ] = sprintf(
					/* translators: %s: active Shiptastic shipping provider. */
					__( 'Shipping label — %s', 'pridge-wp-endpoint' ),
					$title
				);
			}
		}

		return $types;
	}

	/**
	 * @return void
	 */
	public function handle_test_print() {
		$this->authorize();
		check_admin_referer( 'pridge_wp_test_print' );

		$endpoint_id = isset( $_POST['endpoint_id'] ) ? sanitize_key( wp_unslash( $_POST['endpoint_id'] ) ) : '';
		$payload     = sprintf(
			/* translators: 1: site name, 2: localized date and time. */
			__( "Pridge test print\nSite: %1\$s\nSent: %2\$s\n", 'pridge-wp-endpoint' ),
			wp_strip_all_tags( get_bloginfo( 'name' ) ),
			wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
		);
		$result      = $this->jobs->submit(
			$payload,
			array(
				'content_type' => 'text/plain',
				'document_type'=> 'test',
				'endpoint_id'  => $endpoint_id,
				'metadata'     => array( 'source' => 'wordpress-manual-test' ),
			)
		);

		$args = array(
			'page'      => self::PAGE_OVERVIEW,
			'pb_notice' => is_wp_error( $result ) ? 'test-error' : 'test-success',
		);
		if ( is_wp_error( $result ) ) {
			$args['error_code'] = sanitize_key( $result->get_error_code() );
		} else {
			$args['job_id'] = (int) $result['job_id'];
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Send existing routed Germanized PDF documents for one selected order.
	 *
	 * @return void
	 */
	public function handle_germanized_test_print() {
		$this->authorize();
		check_admin_referer( 'pridge_wp_test_germanized_order' );

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_order' ) || ! Germanized::is_available() ) {
			$this->redirect_germanized_test( $order_id, 0, 0, 'germanized_unavailable' );
		}

		if ( ! $this->integration_settings->get( 'woocommerce_enabled', false ) || ! $this->integration_settings->get( 'germanized_enabled', false ) ) {
			$this->redirect_germanized_test( $order_id, 0, 0, 'germanized_integration_disabled' );
		}

		$results = ( new GermanizedDocuments( $this->jobs, $this->endpoints ) )->test_order( $order_id );
		if ( is_wp_error( $results ) ) {
			$this->redirect_germanized_test( $order_id, 0, 0, $results->get_error_code() );
		}

		$sent_count   = 0;
		$failed_count = 0;
		$error_code   = '';
		foreach ( $results as $result ) {
			if ( is_wp_error( $result ) ) {
				++$failed_count;
				$error_code = $error_code ?: $result->get_error_code();
			} else {
				++$sent_count;
			}
		}

		if ( 0 === $sent_count && '' === $error_code ) {
			$error_code = 'no_routed_documents';
		}

		$this->redirect_germanized_test(
			$order_id,
			$sent_count,
			$failed_count,
			$error_code
		);
	}

	/**
	 * @param int    $order_id     Tested WooCommerce order ID.
	 * @param int    $sent_count   Accepted Pridge jobs.
	 * @param int    $failed_count Failed Pridge jobs.
	 * @param string $error_code   First safe failure code.
	 * @return void
	 */
	private function redirect_germanized_test( $order_id, $sent_count, $failed_count, $error_code = '' ) {
		$args = array(
			'page'         => self::PAGE_OVERVIEW,
			'pb_notice'    => 0 < $sent_count ? 'germanized-test-success' : 'germanized-test-error',
			'order_id'     => absint( $order_id ),
			'sent_count'   => absint( $sent_count ),
			'failed_count' => absint( $failed_count ),
		);
		if ( '' !== $error_code ) {
			$args['error_code'] = sanitize_key( $error_code );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Return one archive record for the administration modal.
	 *
	 * @return void
	 */
	public function handle_archive_detail() {
		$this->authorize();
		check_ajax_referer( 'pridge_wp_archive_detail', 'nonce' );

		$archive_id = isset( $_POST['archive_id'] ) ? absint( $_POST['archive_id'] ) : 0;
		$row        = $this->archive->find( $archive_id );
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Archive record not found.', 'pridge-wp-endpoint' ) ), 404 );
		}

		$payload      = $this->archive->decode_payload( $row );
		$content_type = strtolower( trim( strtok( (string) $row['content_type'], ';' ) ) );
		$metadata     = json_decode( (string) $row['metadata'], true );
		$metadata     = is_array( $metadata ) ? $metadata : array();
		$is_text      = 0 === strpos( $content_type, 'text/' ) || in_array( $content_type, array( 'application/json', 'application/xml' ), true );
		$is_inline    = in_array( $content_type, array( 'application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp' ), true );

		wp_send_json_success(
			array(
				'id'            => (int) $row['id'],
				'jobId'         => (int) $row['job_id'],
				'endpoint'      => (string) ( $row['endpoint_name'] ?: $row['endpoint_id'] ),
				'documentType'  => $this->document_label( (string) $row['document_type'] ),
				'source'        => (string) $row['source'],
				'orderId'       => (int) $row['order_id'],
				'status'        => (string) $row['status'],
				'contentType'   => (string) $row['content_type'],
				'errorCode'     => (string) $row['error_code'],
				'createdAt'     => get_date_from_gmt( (string) $row['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
				'byteCount'     => strlen( $payload ),
				'metadata'      => wp_json_encode( $metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
				'previewKind'   => $is_text ? 'text' : ( $is_inline ? 'document' : 'binary' ),
				'preview'       => $is_text ? wp_check_invalid_utf8( $payload ) : ( $is_inline ? '' : $this->binary_preview( $payload ) ),
				'payloadUrl'    => $is_inline ? $this->archive_payload_url( (int) $row['id'] ) : '',
			)
		);
	}

	/**
	 * Live cron heartbeat for the Overview page's automation panel, polled by JavaScript.
	 *
	 * @return void
	 */
	public function handle_cron_status_ajax() {
		$this->authorize();
		check_ajax_referer( 'pridge_wp_cron_status', 'nonce' );

		$interval_minutes = $this->cron->interval_minutes();
		$last_run         = Cron::last_run();
		$next_run         = Cron::next_run();

		wp_send_json_success(
			array(
				'lastRun'         => $last_run,
				'lastRunRelative' => $last_run
					? sprintf(
						/* translators: %s: human-readable time since the last run. */
						__( 'Last ran %s ago', 'pridge-wp-endpoint' ),
						human_time_diff( $last_run )
					)
					: __( 'Never run yet', 'pridge-wp-endpoint' ),
				'nextRun'         => $next_run,
				'nextRunRelative' => $next_run
					? sprintf(
						/* translators: %s: human-readable time until the next run. */
						__( 'In %s', 'pridge-wp-endpoint' ),
						human_time_diff( time(), $next_run )
					)
					: __( 'Not scheduled', 'pridge-wp-endpoint' ),
				'healthy'         => Cron::is_healthy( $interval_minutes ),
				'pendingCount'    => Cron::pending_count(),
			)
		);
	}

	/**
	 * Stream an archived PDF or image only to an authorized administrator.
	 *
	 * @return void
	 */
	public function handle_archive_payload() {
		$this->authorize();

		$archive_id = isset( $_GET['archive_id'] ) ? absint( $_GET['archive_id'] ) : 0;
		check_admin_referer( 'pridge_wp_archive_payload_' . $archive_id );
		$row        = $this->archive->find( $archive_id );
		if ( ! $row ) {
			wp_die( esc_html__( 'Archive record not found.', 'pridge-wp-endpoint' ), '', array( 'response' => 404 ) );
		}

		$content_type = strtolower( trim( strtok( (string) $row['content_type'], ';' ) ) );
		$allowed      = array(
			'application/pdf' => 'pdf',
			'image/png'       => 'png',
			'image/jpeg'      => 'jpg',
			'image/gif'       => 'gif',
			'image/webp'      => 'webp',
		);
		if ( ! isset( $allowed[ $content_type ] ) ) {
			wp_die( esc_html__( 'This payload type cannot be opened inline.', 'pridge-wp-endpoint' ), '', array( 'response' => 415 ) );
		}

		$payload  = $this->archive->decode_payload( $row );
		$metadata = json_decode( (string) $row['metadata'], true );
		$filename = is_array( $metadata ) && ! empty( $metadata['filename'] ) ? sanitize_file_name( $metadata['filename'] ) : '';
		if ( '' === $filename ) {
			$filename = 'pridge-' . $archive_id . '.' . $allowed[ $content_type ];
		}

		nocache_headers();
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Disposition: inline; filename="' . str_replace( '"', '', $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $payload ) );
		header( 'X-Content-Type-Options: nosniff' );
		echo $payload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw authorized print payload.
		exit;
	}

	/**
	 * @param int $archive_id Archive record ID.
	 * @return string
	 */
	private function archive_payload_url( $archive_id ) {
		$url = add_query_arg(
			array(
				'action'     => 'pridge_wp_archive_payload',
				'archive_id' => $archive_id,
			),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, 'pridge_wp_archive_payload_' . $archive_id );
	}

	/**
	 * Render the first bytes of an opaque payload as a safe diagnostic preview.
	 *
	 * @param string $payload Raw payload bytes.
	 * @return string
	 */
	private function binary_preview( $payload ) {
		$bytes = substr( $payload, 0, 2048 );
		$hex   = strtoupper( implode( ' ', str_split( bin2hex( $bytes ), 2 ) ) );

		return $hex . ( strlen( $payload ) > strlen( $bytes ) ? "\n…" : '' );
	}

	/**
	 * @param string $document_type Stored document route key.
	 * @return string
	 */
	private function document_label( $document_type ) {
		$types = $this->document_types();

		return $types[ $document_type ] ?? ucwords( str_replace( array( '__', '_' ), array( ' — ', ' ' ), $document_type ) );
	}

	/**
	 * @return void
	 */
	public function handle_restore_backup() {
		$this->authorize();
		check_admin_referer( 'pridge_wp_restore_backup' );

		$requested = isset( $_POST['backup'] ) ? sanitize_file_name( wp_unslash( $_POST['backup'] ) ) : '';
		$path      = UpdateChecker::backups_dir() . '/' . $requested;

		$args = array( 'page' => self::PAGE_OVERVIEW );

		try {
			UpdateChecker::restore_backup( $path );
			$args['pb_notice'] = 'restore-success';
		} catch ( RuntimeException $exception ) {
			error_log( 'Pridge WP Endpoint: backup restore failed: ' . $exception->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			$args['pb_notice'] = 'restore-error';
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * @return void
	 */
	public function handle_delete_backup() {
		$this->authorize();
		check_admin_referer( 'pridge_wp_delete_backup' );

		$requested = isset( $_POST['backup'] ) ? sanitize_file_name( wp_unslash( $_POST['backup'] ) ) : '';
		$path      = UpdateChecker::backups_dir() . '/' . $requested;

		$args = array( 'page' => self::PAGE_OVERVIEW );

		try {
			UpdateChecker::delete_backup( $path );
			$args['pb_notice'] = 'backup-delete-success';
		} catch ( RuntimeException $exception ) {
			error_log( 'Pridge WP Endpoint: backup deletion failed: ' . $exception->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			$args['pb_notice'] = 'backup-delete-error';
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Create a backup on demand, independent of any pending update.
	 *
	 * @return void
	 */
	public function handle_backup_now() {
		$this->authorize();
		check_admin_referer( 'pridge_wp_backup_now' );

		$args = array( 'page' => self::PAGE_OVERVIEW );

		try {
			UpdateChecker::create_backup();
			$args['pb_notice'] = 'backup-now-success';
		} catch ( RuntimeException $exception ) {
			error_log( 'Pridge WP Endpoint: manual backup failed: ' . $exception->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			$args['pb_notice'] = 'backup-now-error';
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Run the pending-order document check immediately, bypassing the wait for the
	 * next scheduled WP-Cron tick. Also used to confirm the check itself still works.
	 *
	 * @return void
	 */
	public function handle_run_cron_check() {
		$this->authorize();
		check_admin_referer( 'pridge_wp_run_cron_check' );

		$this->cron->run();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => self::PAGE_OVERVIEW,
					'pb_notice' => 'cron-check-done',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Force-send whatever Germanized documents currently exist for one order flagged
	 * as needing manual attention, for a shop manager to finish what automation could not.
	 *
	 * @return void
	 */
	public function handle_send_pending_order() {
		$this->authorize();
		check_admin_referer( 'pridge_wp_send_pending_order' );

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : null;

		if ( $order instanceof \WC_Order ) {
			( new GermanizedDocuments( $this->jobs, $this->endpoints, $this->integration_settings ) )->submit_order( $order_id, true );
			$order->delete_meta_data( Cron::PENDING_META );
			$order->delete_meta_data( Cron::NEEDS_ATTENTION_META );
			$order->save_meta_data();
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => self::PAGE_OVERVIEW,
					'pb_notice' => 'pending-order-sent',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * @param string[] $links Plugin action links.
	 * @return string[]
	 */
	public function add_plugin_action_link( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( admin_url( 'admin.php?page=' . self::PAGE_SETTINGS ) ),
				esc_html__( 'Settings', 'pridge-wp-endpoint' )
			)
		);

		return $links;
	}

	/**
	 * @return void
	 */
	private function authorize() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Pridge.', 'pridge-wp-endpoint' ) );
		}
	}
}
