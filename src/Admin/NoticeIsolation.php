<?php
/**
 * Scoped third-party admin notice isolation.
 *
 * @package PridgeWPEndpoint
 */

namespace Pridge\Admin;

defined( 'ABSPATH' ) || exit;

final class NoticeIsolation {
	/** @var string[] */
	private $screen_ids;

	/**
	 * @param string[] $screen_ids Exact WordPress screen IDs.
	 */
	public function __construct( array $screen_ids ) {
		$this->screen_ids = $screen_ids;
	}

	/**
	 * Register notice isolation before WordPress renders admin notices.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'in_admin_header', array( $this, 'suppress_third_party_notices' ), -1000 );
	}

	/**
	 * Remove callbacks originating from other plugins on the Pridge screen only.
	 *
	 * WordPress core and Pridge callbacks are preserved so security and plugin-specific
	 * messages remain available.
	 *
	 * @return void
	 */
	public function suppress_third_party_notices() {
		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->id, $this->screen_ids, true ) ) {
			return;
		}

		foreach ( array( 'admin_notices', 'all_admin_notices', 'network_admin_notices', 'user_admin_notices' ) as $hook_name ) {
			$this->filter_hook( $hook_name );
		}
	}

	/**
	 * Remove third-party callbacks from one notice hook.
	 *
	 * @param string $hook_name WordPress action hook.
	 * @return void
	 */
	private function filter_hook( $hook_name ) {
		global $wp_filter;

		if ( empty( $wp_filter[ $hook_name ] ) || ! $wp_filter[ $hook_name ] instanceof \WP_Hook ) {
			return;
		}

		foreach ( $wp_filter[ $hook_name ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback_data ) {
				$callback = $callback_data['function'];
				$file     = $this->callback_file( $callback );

				if ( $file && ! $this->is_allowed_file( $file ) ) {
					remove_action( $hook_name, $callback, $priority );
				}
			}
		}
	}

	/**
	 * Resolve the source file for a callable when reflection supports it.
	 *
	 * @param callable $callback Hook callback.
	 * @return string
	 */
	private function callback_file( $callback ) {
		try {
			if ( is_array( $callback ) && 2 === count( $callback ) ) {
				$reflection = new \ReflectionMethod( $callback[0], $callback[1] );
			} elseif ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
				list( $class_name, $method_name ) = explode( '::', $callback, 2 );
				$reflection                       = new \ReflectionMethod( $class_name, $method_name );
			} elseif ( is_object( $callback ) && ! $callback instanceof \Closure ) {
				$reflection = new \ReflectionMethod( $callback, '__invoke' );
			} else {
				$reflection = new \ReflectionFunction( $callback );
			}

			return (string) $reflection->getFileName();
		} catch ( \Throwable $exception ) {
			return '';
		}
	}

	/**
	 * Preserve WordPress core and Pridge-owned notice callbacks.
	 *
	 * @param string $file Callback source file.
	 * @return bool
	 */
	private function is_allowed_file( $file ) {
		$file = wp_normalize_path( $file );

		$allowed_paths = array(
			wp_normalize_path( ABSPATH . 'wp-admin/' ),
			wp_normalize_path( ABSPATH . WPINC . '/' ),
			wp_normalize_path( PRIDGE_WP_DIR ),
		);

		foreach ( $allowed_paths as $allowed_path ) {
			if ( 0 === strpos( $file, $allowed_path ) ) {
				return true;
			}
		}

		return false;
	}
}
