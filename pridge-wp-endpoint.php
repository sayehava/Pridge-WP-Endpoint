<?php
/**
 * Plugin Name:       Pridge WP Endpoint
 * Description:       Connects WordPress and WooCommerce to Pridge Server print endpoints.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Pridge
 * Text Domain:       pridge-wp-endpoint
 * Domain Path:       /languages
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package PridgeWPEndpoint
 */

defined( 'ABSPATH' ) || exit;

define( 'PRIDGE_WP_VERSION', '1.0.0' );
define( 'PRIDGE_WP_FILE', __FILE__ );
define( 'PRIDGE_WP_DIR', plugin_dir_path( __FILE__ ) );
define( 'PRIDGE_WP_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'Pridge\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$file           = PRIDGE_WP_DIR . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'Pridge\\Lifecycle', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Pridge\\Lifecycle', 'deactivate' ) );

require_once PRIDGE_WP_DIR . 'src/functions.php';

add_action( 'before_woocommerce_init', array( 'Pridge\\Plugin', 'declare_woocommerce_compatibility' ) );
add_action( 'plugins_loaded', array( 'Pridge\\Plugin', 'boot' ) );
