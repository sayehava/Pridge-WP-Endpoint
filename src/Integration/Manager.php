<?php
/**
 * Optional platform integration manager.
 *
 * @package PridgeWPEndpoint
 */

namespace Pridge\Integration;

use Pridge\EndpointRepository;
use Pridge\JobService;
use Pridge\IntegrationSettings;

defined( 'ABSPATH' ) || exit;

final class Manager {
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
	 * Register integrations whose dependencies are available.
	 *
	 * @return void
	 */
	public function register() {
		if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_order' ) ) {
			$woocommerce = new WooCommerce( $this->settings, $this->jobs, $this->endpoints );
			$woocommerce->register();

			if ( $this->settings->get( 'woocommerce_enabled', false ) && function_exists( 'wc_stc_get_shipping_providers' ) ) {
				$shiptastic = new Shiptastic( $this->jobs, $this->endpoints );
				$shiptastic->register();
			}

			if ( $this->settings->get( 'germanized_enabled', false ) && Germanized::is_available() ) {
				$germanized = new Germanized();
				$germanized->register();
			}
		}
	}
}
