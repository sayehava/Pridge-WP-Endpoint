<?php
/**
 * Checks GitHub for new releases and hooks into WordPress's own plugin update
 * mechanism so this plugin shows up as updatable on the native Plugins page,
 * using WordPress's own "click Update Now" confirmation and installer. This
 * class only supplies the release info and takes a backup first - the actual
 * download/extract/replace, and the automatic rollback if the new code
 * fatals, are WordPress core's.
 *
 * @package PridgeWPEndpoint
 */

namespace Pridge;

use RuntimeException;
use Throwable;
use WP_Error;
use ZipArchive;

defined( 'ABSPATH' ) || exit;

final class UpdateChecker {
	private const REPO             = 'sayehava/Pridge-WP-Endpoint';
	private const RELEASES_API     = 'https://api.github.com/repos/sayehava/Pridge-WP-Endpoint/releases/latest';
	private const CHECK_TRANSIENT  = 'pridge_wp_update_check';
	private const CHECK_INTERVAL   = 12 * HOUR_IN_SECONDS;
	public const BACKUP_OPTION     = 'pridge_wp_last_backup';
	private const MAX_BACKUPS_KEPT = 5;

	/**
	 * @return void
	 */
	public static function register() {
		add_filter( 'pre_set_site_transient_update_plugins', array( self::class, 'inject_update' ) );
		add_filter( 'plugins_api', array( self::class, 'plugin_info' ), 10, 3 );
		add_action( 'upgrader_pre_install', array( self::class, 'before_install' ), 10, 2 );
		add_filter( 'upgrader_source_selection', array( self::class, 'fix_source_directory' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( self::class, 'after_install' ), 10, 2 );
	}

	/**
	 * Icon shown on the native Plugins/Update screens only - the admin menu keeps its own icon.
	 *
	 * @return array{'1x': string, '2x': string, 'default': string}
	 */
	public static function icons() {
		$icons = array();

		if ( is_readable( PRIDGE_WP_DIR . 'assets/images/icon-128.png' ) ) {
			$icons['1x']      = PRIDGE_WP_URL . 'assets/images/icon-128.png';
			$icons['default'] = $icons['1x'];
		}

		if ( is_readable( PRIDGE_WP_DIR . 'assets/images/icon-256.png' ) ) {
			$icons['2x'] = PRIDGE_WP_URL . 'assets/images/icon-256.png';
		}

		return $icons;
	}

	/**
	 * @return string
	 */
	public static function plugin_basename() {
		return plugin_basename( PRIDGE_WP_FILE );
	}

	/**
	 * @param mixed $transient The update_plugins site transient.
	 * @return mixed
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$release = self::latest_release( ! empty( $_GET['force-check'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, mirrors WordPress core's own force-check flag on the Updates screen.
		if ( null === $release || version_compare( $release['version'], PRIDGE_WP_VERSION, '<=' ) ) {
			return $transient;
		}

		$basename = self::plugin_basename();

		$transient->response[ $basename ] = (object) array(
			'id'           => 'github.com/' . self::REPO,
			'slug'         => dirname( $basename ),
			'plugin'       => $basename,
			'new_version'  => $release['version'],
			'url'          => 'https://github.com/' . self::REPO,
			'package'      => $release['zip_url'],
			'tested'       => '',
			'requires'     => '',
			'requires_php' => '',
			'icons'        => self::icons(),
			'banners'      => array(),
		);

		return $transient;
	}

	/**
	 * @param mixed  $result The plugins_api result so far.
	 * @param string $action The plugins_api action.
	 * @param mixed  $args   The plugins_api args.
	 * @return mixed
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== dirname( self::plugin_basename() ) ) {
			return $result;
		}

		$release = self::latest_release( ! empty( $_GET['force-check'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, mirrors WordPress core's own force-check flag on the Updates screen.
		if ( null === $release ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Pridge WP Endpoint',
			'slug'          => $args->slug,
			'version'       => $release['version'],
			'author'        => '<a href="https://github.com/sayehava">Pridge</a>',
			'homepage'      => 'https://github.com/' . self::REPO,
			'sections'      => array(
				'description' => __( 'Connects WordPress and WooCommerce to Pridge Server print endpoints.', 'pridge-wp-endpoint' ),
				'changelog'   => wpautop( wp_kses_post( $release['notes'] ) ),
			),
			'icons'         => self::icons(),
			'download_link' => $release['zip_url'],
		);
	}

	/**
	 * Takes a backup immediately before WordPress installs an update to this
	 * plugin specifically. Returning a WP_Error here stops the update.
	 *
	 * @param bool  $true True to proceed with the install.
	 * @param array $args Install args, includes 'plugin' for a plugin update.
	 * @return bool|WP_Error
	 */
	public static function before_install( $true, $args ) {
		if ( empty( $args['plugin'] ) || $args['plugin'] !== self::plugin_basename() ) {
			return $true;
		}

		try {
			self::create_backup();
		} catch ( Throwable $exception ) {
			return new WP_Error(
				'pridge_backup_failed',
				sprintf(
					/* translators: %s: error message. */
					__( 'Pridge WP Endpoint update stopped: could not create a backup first (%s).', 'pridge-wp-endpoint' ),
					$exception->getMessage()
				)
			);
		}

		return $true;
	}

	/**
	 * GitHub's release zipball extracts to a commit-hash-named folder (e.g.
	 * "sayehava-Pridge-WP-Endpoint-abc1234"), not "pridge-wp-endpoint". Left
	 * alone, WordPress installs the update under that mismatched folder name
	 * while the original plugin folder is removed, which orphans the
	 * previously active plugin path - it disappears from the Plugins page
	 * instead of being updated in place. Renaming the extracted folder here,
	 * before WordPress moves it into wp-content/plugins, keeps the slug
	 * stable across updates.
	 *
	 * @param string|WP_Error $source        Path to the extracted source directory.
	 * @param string          $remote_source Path to the parent temporary directory.
	 * @param mixed           $upgrader      Unused.
	 * @param array           $hook_extra    Extra arguments, includes 'plugin' for a plugin update.
	 * @return string|WP_Error
	 */
	public static function fix_source_directory( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		unset( $upgrader );

		if ( is_wp_error( $source ) || empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== self::plugin_basename() ) {
			return $source;
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem instanceof \WP_Filesystem_Base ) {
			return $source;
		}

		$desired = trailingslashit( $remote_source ) . dirname( self::plugin_basename() );

		if ( untrailingslashit( $source ) === untrailingslashit( $desired ) ) {
			return $source;
		}

		if ( $wp_filesystem->exists( $desired ) ) {
			$wp_filesystem->delete( $desired, true );
		}

		if ( ! $wp_filesystem->move( $source, $desired, true ) ) {
			return new WP_Error(
				'pridge_source_rename_failed',
				__( 'Pridge WP Endpoint update stopped: could not rename the downloaded release to the plugin folder name.', 'pridge-wp-endpoint' )
			);
		}

		return trailingslashit( $desired );
	}

	/**
	 * @param mixed $upgrader_object Unused.
	 * @param array $data            Upgrader process data.
	 * @return void
	 */
	public static function after_install( $upgrader_object, $data ) {
		if ( 'update' === ( $data['action'] ?? '' ) && 'plugin' === ( $data['type'] ?? '' ) ) {
			delete_transient( self::CHECK_TRANSIENT );
			if ( function_exists( 'opcache_reset' ) ) {
				@opcache_reset(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
	}

	/**
	 * @return string
	 */
	public static function backups_dir() {
		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . 'pridge-wp-endpoint-backups';

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $dir . '/.htaccess', "Require all denied\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		return $dir;
	}

	/**
	 * @return array<int, array{name:string, path:string, size:int, created_at:string}>
	 */
	public static function list_backups() {
		$files = glob( self::backups_dir() . '/backup-*.zip' ) ?: array();
		rsort( $files );

		$backups = array();
		foreach ( $files as $file ) {
			$backups[] = array(
				'name'       => basename( $file ),
				'path'       => $file,
				'size'       => (int) filesize( $file ),
				'created_at' => gmdate( 'Y-m-d H:i:s', (int) filemtime( $file ) ),
			);
		}

		return $backups;
	}

	/**
	 * @return string Path to the created backup archive.
	 */
	public static function create_backup() {
		if ( ! class_exists( ZipArchive::class ) ) {
			throw new RuntimeException( __( 'The PHP zip extension is required to create a backup.', 'pridge-wp-endpoint' ) );
		}

		$source = untrailingslashit( PRIDGE_WP_DIR );
		$path   = self::backups_dir() . '/backup-' . gmdate( 'Ymd-His' ) . '-v' . PRIDGE_WP_VERSION . '.zip';

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			throw new RuntimeException( __( 'Could not create the backup archive.', 'pridge-wp-endpoint' ) );
		}

		self::add_directory_to_zip( $zip, $source, $source );
		$zip->close();

		update_option(
			self::BACKUP_OPTION,
			array(
				'path'       => $path,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			false
		);

		foreach ( array_slice( self::list_backups(), self::MAX_BACKUPS_KEPT ) as $old ) {
			@unlink( $old['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		return $path;
	}

	/**
	 * Restores this plugin's files from a backup taken by create_backup().
	 * $backup_path must be a file inside backups_dir(); anything else is rejected.
	 *
	 * @param string $backup_path Absolute path to the backup zip.
	 * @return void
	 */
	public static function restore_backup( $backup_path ) {
		$backups_dir = realpath( self::backups_dir() );
		$real_path   = realpath( $backup_path );

		if ( false === $backups_dir || false === $real_path || 0 !== strpos( $real_path, $backups_dir . DIRECTORY_SEPARATOR ) ) {
			throw new RuntimeException( __( 'Invalid backup file.', 'pridge-wp-endpoint' ) );
		}

		if ( ! class_exists( ZipArchive::class ) ) {
			throw new RuntimeException( __( 'The PHP zip extension is required to restore a backup.', 'pridge-wp-endpoint' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $real_path ) ) {
			throw new RuntimeException( __( 'Could not open the backup archive.', 'pridge-wp-endpoint' ) );
		}

		$destination = untrailingslashit( PRIDGE_WP_DIR );
		$extracted   = $zip->extractTo( $destination );
		$zip->close();

		if ( ! $extracted ) {
			throw new RuntimeException( __( 'Could not extract the backup archive.', 'pridge-wp-endpoint' ) );
		}

		delete_transient( self::CHECK_TRANSIENT );
		if ( function_exists( 'opcache_reset' ) ) {
			@opcache_reset(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * @param ZipArchive $zip         Open archive to add to.
	 * @param string     $source_root Root directory backups are relative to.
	 * @param string     $current_dir Directory currently being walked.
	 * @return void
	 */
	private static function add_directory_to_zip( ZipArchive $zip, $source_root, $current_dir ) {
		$entries = scandir( $current_dir ) ?: array();
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$full_path     = $current_dir . '/' . $entry;
			$relative_path = ltrim( substr( $full_path, strlen( $source_root ) ), '/' );

			if ( is_dir( $full_path ) ) {
				$zip->addEmptyDir( $relative_path );
				self::add_directory_to_zip( $zip, $source_root, $full_path );
			} else {
				$zip->addFile( $full_path, $relative_path );
			}
		}
	}

	/**
	 * Fetch the latest GitHub release, honoring WordPress's own cached result until it
	 * expires. WordPress core itself only recalculates the update_plugins transient on
	 * a schedule or when the admin clicks "Check Again" on the Updates screen (which
	 * sets the force-check flag this class reads in inject_update()/plugin_info()) - a
	 * stale local cache here would otherwise make even that native forced check keep
	 * reporting no update available.
	 *
	 * @param bool $force Bypass the cached result and query GitHub now.
	 * @return array{version:string, notes:string, zip_url:string}|null
	 */
	public static function latest_release( $force = false ) {
		$cached = $force ? false : get_transient( self::CHECK_TRANSIENT );
		if ( is_array( $cached ) ) {
			return array() === $cached ? null : $cached;
		}

		$response = wp_remote_get(
			self::RELEASES_API,
			array(
				'timeout' => 15,
				'headers' => array( 'Accept' => 'application/vnd.github+json' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_transient( self::CHECK_TRANSIENT, array(), HOUR_IN_SECONDS );
			return null;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			set_transient( self::CHECK_TRANSIENT, array(), HOUR_IN_SECONDS );
			return null;
		}

		$zip_url = self::release_asset_zip_url( $data );
		if ( '' === $zip_url && ! empty( $data['zipball_url'] ) ) {
			$zip_url = (string) $data['zipball_url'];
		}

		if ( '' === $zip_url ) {
			set_transient( self::CHECK_TRANSIENT, array(), HOUR_IN_SECONDS );
			return null;
		}

		$release = array(
			'version' => ltrim( (string) $data['tag_name'], 'v' ),
			'notes'   => isset( $data['body'] ) && is_string( $data['body'] ) ? $data['body'] : '',
			'zip_url' => $zip_url,
		);

		set_transient( self::CHECK_TRANSIENT, $release, self::CHECK_INTERVAL );

		return $release;
	}

	/**
	 * The release workflow attaches a pridge-wp-endpoint.zip asset that always extracts
	 * to a "pridge-wp-endpoint" folder. Preferred over GitHub's own auto-generated
	 * source zip/zipball, both of which embed the version or a commit hash in the
	 * folder name and would otherwise install as a new, differently-named plugin on
	 * every release.
	 *
	 * @param array<string, mixed> $data Decoded GitHub release API response.
	 * @return string
	 */
	private static function release_asset_zip_url( array $data ) {
		if ( empty( $data['assets'] ) || ! is_array( $data['assets'] ) ) {
			return '';
		}

		foreach ( $data['assets'] as $asset ) {
			if ( is_array( $asset ) && 'pridge-wp-endpoint.zip' === ( $asset['name'] ?? '' ) && ! empty( $asset['browser_download_url'] ) ) {
				return (string) $asset['browser_download_url'];
			}
		}

		return '';
	}
}
