<?php
/**
 * Plugin composition root.
 *
 * @package PridgeWPEndpoint
 */

namespace Pridge;

use Pridge\Admin\Admin;
use Pridge\Http\Client;
use Pridge\Integration\Manager as IntegrationManager;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	/** @var self|null */
	private static $instance;

	/** @var Settings */
	private $settings;

	/** @var JobService */
	private $jobs;

	/** @var EndpointRepository */
	private $endpoints;

	/** @var ArchiveRepository */
	private $archive;

	/** @var IntegrationSettings */
	private $integration_settings;

	/** @var IntegrationManager */
	private $integrations;

	/** @var Admin|null */
	private $admin;

	/**
	 * Construct core services.
	 */
	private function __construct() {
		global $wpdb;

		$this->settings             = new Settings();
		$this->endpoints            = new EndpointRepository();
		$this->archive              = new ArchiveRepository( $wpdb );
		$this->integration_settings = new IntegrationSettings();
		$this->jobs                 = new JobService( $this->settings, new Client(), $this->endpoints, $this->archive );
		$this->integrations         = new IntegrationManager( $this->integration_settings, $this->jobs, $this->endpoints );
		$this->admin                = is_admin() ? new Admin( $this->settings, $this->jobs, $this->endpoints, $this->integration_settings, $this->archive ) : null;
	}

	/**
	 * Register the plugin with WordPress.
	 *
	 * @return void
	 */
	public static function boot() {
		if ( null !== self::$instance ) {
			return;
		}

		Lifecycle::maybe_upgrade();
		self::$instance = new self();
		self::$instance->register_hooks();
	}

	/**
	 * Return the loaded plugin instance.
	 *
	 * @return self|null
	 */
	public static function instance() {
		return self::$instance;
	}

	/**
	 * Return the settings service.
	 *
	 * @return Settings
	 */
	public function settings() {
		return $this->settings;
	}

	/**
	 * Return the public job service.
	 *
	 * @return JobService
	 */
	public function jobs() {
		return $this->jobs;
	}

	/**
	 * @return EndpointRepository
	 */
	public function endpoints() {
		return $this->endpoints;
	}

	/**
	 * @return ArchiveRepository
	 */
	public function archive() {
		return $this->archive;
	}

	/**
	 * Register foundational hooks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		$this->integrations->register();

		if ( null !== $this->admin ) {
			$this->admin->register();
		}
	}

	/**
	 * Load translations from the plugin languages directory.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'pridge-wp-endpoint',
			false,
			dirname( plugin_basename( PRIDGE_WP_FILE ) ) . '/languages'
		);
	}

	/**
	 * Declare compatibility with WooCommerce custom order tables.
	 *
	 * @return void
	 */
	public static function declare_woocommerce_compatibility() {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				PRIDGE_WP_FILE,
				true
			);
		}
	}
}
