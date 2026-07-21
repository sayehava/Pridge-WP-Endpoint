<?php
/**
 * Shared Pridge admin header.
 *
 * @var string $active_page
 * @var string $page_title
 * @var string $page_description
 *
 * @package PridgeWPEndpoint
 */

defined( 'ABSPATH' ) || exit;

$notice = isset( $_GET['pb_notice'] ) ? sanitize_key( wp_unslash( $_GET['pb_notice'] ) ) : '';
?>
<div class="wrap pridge-admin" data-pridge-admin>
	<div class="pridge-ambient" aria-hidden="true"><span></span><span></span><span></span></div>
	<header class="pridge-hero is-compact">
		<div>
			<div class="pridge-eyebrow"><?php esc_html_e( 'Pridge control center', 'pridge-wp-endpoint' ); ?></div>
			<h1><?php echo esc_html( $page_title ); ?></h1>
			<p><?php echo esc_html( $page_description ); ?></p>
		</div>
		<div class="pridge-version"><span><?php esc_html_e( 'WP Endpoint', 'pridge-wp-endpoint' ); ?></span><strong>v<?php echo esc_html( PRIDGE_WP_VERSION ); ?></strong></div>
	</header>
	<nav class="pridge-tabs" aria-label="<?php esc_attr_e( 'Pridge sections', 'pridge-wp-endpoint' ); ?>">
		<?php
		$tabs = array(
			'overview'     => array( \Pridge\Admin\Admin::PAGE_OVERVIEW, __( 'Overview', 'pridge-wp-endpoint' ) ),
			'integrations' => array( \Pridge\Admin\Admin::PAGE_INTEGRATIONS, __( 'Integrations', 'pridge-wp-endpoint' ) ),
			'endpoints'    => array( \Pridge\Admin\Admin::PAGE_ENDPOINTS, __( 'Endpoints & Routing', 'pridge-wp-endpoint' ) ),
			'archive'      => array( \Pridge\Admin\Admin::PAGE_ARCHIVE, __( 'Print Archive', 'pridge-wp-endpoint' ) ),
		);
		foreach ( $tabs as $tab_key => $tab ) :
			?>
			<a class="<?php echo $active_page === $tab_key ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $tab[0] ) ); ?>"><?php echo esc_html( $tab[1] ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php if ( 'test-success' === $notice ) : ?>
		<div class="pridge-message is-success" role="status"><strong><?php esc_html_e( 'Test job accepted.', 'pridge-wp-endpoint' ); ?></strong> <?php printf( esc_html__( 'Pridge created job #%d.', 'pridge-wp-endpoint' ), isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0 ); ?></div>
	<?php elseif ( 'test-error' === $notice ) : ?>
		<div class="pridge-message is-error" role="alert"><strong><?php esc_html_e( 'Test job failed.', 'pridge-wp-endpoint' ); ?></strong> <?php printf( esc_html__( 'Error: %s', 'pridge-wp-endpoint' ), esc_html( isset( $_GET['error_code'] ) ? sanitize_key( wp_unslash( $_GET['error_code'] ) ) : 'unknown' ) ); ?></div>
	<?php elseif ( 'germanized-test-success' === $notice ) : ?>
		<div class="pridge-message is-success" role="status"><strong><?php esc_html_e( 'Germanized PDF test finished.', 'pridge-wp-endpoint' ); ?></strong> <?php printf( esc_html__( '%1$d PDF jobs accepted and %2$d missing or failed.', 'pridge-wp-endpoint' ), isset( $_GET['sent_count'] ) ? absint( $_GET['sent_count'] ) : 0, isset( $_GET['failed_count'] ) ? absint( $_GET['failed_count'] ) : 0 ); ?></div>
	<?php elseif ( 'germanized-test-error' === $notice ) : ?>
		<div class="pridge-message is-error" role="alert"><strong><?php esc_html_e( 'Germanized PDF test did not send a job.', 'pridge-wp-endpoint' ); ?></strong> <?php printf( esc_html__( 'Error: %s', 'pridge-wp-endpoint' ), esc_html( isset( $_GET['error_code'] ) ? sanitize_key( wp_unslash( $_GET['error_code'] ) ) : 'unknown' ) ); ?></div>
	<?php elseif ( isset( $_GET['settings-updated'] ) ) : ?>
		<div class="pridge-message is-success" role="status"><strong><?php esc_html_e( 'Settings saved.', 'pridge-wp-endpoint' ); ?></strong></div>
	<?php endif; ?>
	<?php $compatibility_warning = get_option( \Pridge\JobService::COMPATIBILITY_WARNING_OPTION, '' ); ?>
	<?php if ( $compatibility_warning ) : ?>
		<div class="pridge-message is-warning" role="status"><strong><?php esc_html_e( 'Version mismatch:', 'pridge-wp-endpoint' ); ?></strong> <?php echo esc_html( $compatibility_warning ); ?></div>
	<?php endif; ?>
