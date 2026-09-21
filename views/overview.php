<?php
/**
 * @package PridgeWPEndpoint
 *
 * @var array<string, mixed> $settings
 * @var bool                 $is_configured
 * @var array<int, array>    $endpoints
 * @var int                  $archive_count
 * @var array<int, array>    $backups
 * @var bool                 $germanized_enabled
 * @var int                  $cron_interval_minutes
 * @var int                  $cron_last_run
 * @var int                  $cron_next_run
 * @var bool                 $cron_healthy
 * @var int                  $pending_count
 * @var \WC_Order[]          $attention_orders
 * @var \WC_Order[]          $test_orders
 */
defined( 'ABSPATH' ) || exit;
$active_page      = 'overview';
$page_title       = __( 'Overview', 'pridge-wp-endpoint' );
$page_description = __( 'Your Pridge Server connection and printing activity at a glance.', 'pridge-wp-endpoint' );
require PRIDGE_WP_DIR . 'views/partials/admin-header.php';
?>
<div class="pridge-status-grid">
	<article class="pridge-stat-card"><span><?php esc_html_e( 'Server', 'pridge-wp-endpoint' ); ?></span><strong><?php echo $is_configured ? esc_html__( 'Ready', 'pridge-wp-endpoint' ) : esc_html__( 'Setup required', 'pridge-wp-endpoint' ); ?></strong><small><?php echo ! empty( $settings['server_url'] ) ? esc_html( wp_parse_url( $settings['server_url'], PHP_URL_HOST ) ) : esc_html__( 'No server URL', 'pridge-wp-endpoint' ); ?></small></article>
	<article class="pridge-stat-card"><span><?php esc_html_e( 'Endpoints', 'pridge-wp-endpoint' ); ?></span><strong><?php echo esc_html( count( $endpoints ) ); ?></strong><small><?php esc_html_e( 'Named printer destinations', 'pridge-wp-endpoint' ); ?></small></article>
	<article class="pridge-stat-card"><span><?php esc_html_e( 'Archived jobs', 'pridge-wp-endpoint' ); ?></span><strong><?php echo esc_html( $archive_count ); ?></strong><small><?php esc_html_e( 'Successful and failed attempts', 'pridge-wp-endpoint' ); ?></small></article>
</div>
<?php if ( $germanized_enabled ) : ?>
<div class="pridge-settings-form">
	<section class="pridge-panel is-visible" data-pridge-cron-monitor data-last-run="<?php echo esc_attr( $cron_last_run ); ?>">
		<div class="pridge-panel-heading">
			<div>
				<span class="pridge-kicker"><?php esc_html_e( 'WP-Cron', 'pridge-wp-endpoint' ); ?></span>
				<h2><?php esc_html_e( 'Pending-order automation', 'pridge-wp-endpoint' ); ?> <span class="pridge-live-dot <?php echo $cron_healthy ? 'is-healthy' : ''; ?>" data-cron-live-dot aria-hidden="true"></span></h2>
				<p><?php esc_html_e( 'Checks orders waiting on Germanized documents (invoice, packing slips, shipping labels) and prints them once every routed one exists. This panel updates live.', 'pridge-wp-endpoint' ); ?></p>
			</div>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="pridge_wp_run_cron_check">
				<?php wp_nonce_field( 'pridge_wp_run_cron_check' ); ?>
				<button class="button pridge-button is-secondary" type="submit"><?php esc_html_e( 'Run check now', 'pridge-wp-endpoint' ); ?></button>
			</form>
		</div>
		<div class="pridge-status-grid">
			<article class="pridge-stat-card <?php echo $cron_healthy ? 'is-healthy' : 'is-unhealthy'; ?>" data-cron-health-card><span><?php esc_html_e( 'Cron health', 'pridge-wp-endpoint' ); ?></span><strong data-cron-health><?php echo $cron_healthy ? esc_html__( 'Running', 'pridge-wp-endpoint' ) : esc_html__( 'Not confirmed', 'pridge-wp-endpoint' ); ?></strong><small data-cron-last-run><?php echo $cron_last_run ? esc_html( sprintf( /* translators: %s: human-readable time since the last run. */ __( 'Last ran %s ago', 'pridge-wp-endpoint' ), human_time_diff( $cron_last_run ) ) ) : esc_html__( 'Never run yet', 'pridge-wp-endpoint' ); ?></small></article>
			<article class="pridge-stat-card"><span><?php esc_html_e( 'Next run', 'pridge-wp-endpoint' ); ?></span><strong data-cron-next-run><?php echo $cron_next_run ? esc_html( sprintf( /* translators: %s: human-readable time until the next run. */ __( 'In %s', 'pridge-wp-endpoint' ), human_time_diff( time(), $cron_next_run ) ) ) : esc_html__( 'Not scheduled', 'pridge-wp-endpoint' ); ?></strong><small><?php echo esc_html( sprintf( /* translators: %d: minutes between checks. */ __( 'Checks every %d minutes', 'pridge-wp-endpoint' ), $cron_interval_minutes ) ); ?></small></article>
			<article class="pridge-stat-card"><span><?php esc_html_e( 'Waiting on documents', 'pridge-wp-endpoint' ); ?></span><strong data-cron-pending><?php echo esc_html( $pending_count ); ?></strong><small><?php esc_html_e( 'Orders not yet fully ready to print', 'pridge-wp-endpoint' ); ?></small></article>
		</div>
		<div class="pridge-message is-warning" role="status" data-cron-warning <?php echo $cron_healthy ? 'hidden' : ''; ?>><strong><?php esc_html_e( 'WP-Cron may not be running.', 'pridge-wp-endpoint' ); ?></strong> <?php esc_html_e( 'WordPress normally fires scheduled tasks on site visits. On a low-traffic site, or if wp-cron.php was disabled, set up a real system cron job to hit wp-cron.php on a schedule.', 'pridge-wp-endpoint' ); ?></div>
		<?php if ( empty( $attention_orders ) ) : ?>
			<p class="pridge-empty-state"><?php esc_html_e( 'No orders currently need a manual print.', 'pridge-wp-endpoint' ); ?></p>
		<?php else : ?>
			<div class="pridge-table-wrap">
				<table class="pridge-archive-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Order', 'pridge-wp-endpoint' ); ?></th>
							<th><?php esc_html_e( 'Status', 'pridge-wp-endpoint' ); ?></th>
							<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'pridge-wp-endpoint' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $attention_orders as $attention_order ) : ?>
							<tr>
								<td data-label="<?php esc_attr_e( 'Order', 'pridge-wp-endpoint' ); ?>">#<?php echo esc_html( $attention_order->get_order_number() ); ?></td>
								<td data-label="<?php esc_attr_e( 'Status', 'pridge-wp-endpoint' ); ?>"><?php echo esc_html( wc_get_order_status_name( $attention_order->get_status() ) ); ?></td>
								<td>
									<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
										<input type="hidden" name="action" value="pridge_wp_send_pending_order">
										<input type="hidden" name="order_id" value="<?php echo esc_attr( $attention_order->get_id() ); ?>">
										<?php wp_nonce_field( 'pridge_wp_send_pending_order' ); ?>
										<button class="button pridge-button is-secondary" type="submit"><?php esc_html_e( 'Send what exists now', 'pridge-wp-endpoint' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
</div>
<?php endif; ?>
<div class="pridge-settings-form">
	<section class="pridge-panel is-visible">
		<div class="pridge-panel-heading">
			<div>
				<span class="pridge-kicker"><?php esc_html_e( 'Self-update', 'pridge-wp-endpoint' ); ?></span>
				<h2><?php esc_html_e( 'Updates &amp; backups', 'pridge-wp-endpoint' ); ?></h2>
				<p>
					<?php
					printf(
						/* translators: 1: opening <a> tag linking to the Plugins page, 2: closing </a> tag, 3: opening <a> tag linking to the Updates page, 4: closing </a> tag. */
						esc_html__( 'Updates for Pridge WP Endpoint appear on the %1$sPlugins page%2$s like any other plugin, using WordPress\'s own update flow. WordPress checks for a new version on its usual schedule; to check right now, use "Check Again" on the %3$sUpdates page%4$s. A backup is taken automatically right before an update installs.', 'pridge-wp-endpoint' ),
						'<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">',
						'</a>',
						'<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">',
						'</a>'
					);
					?>
				</p>
			</div>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="pridge_wp_backup_now">
				<?php wp_nonce_field( 'pridge_wp_backup_now' ); ?>
				<button class="button pridge-button is-secondary" type="submit"><?php esc_html_e( 'Backup now', 'pridge-wp-endpoint' ); ?></button>
			</form>
		</div>
		<?php if ( empty( $backups ) ) : ?>
			<p class="pridge-empty-state"><?php esc_html_e( 'No backups yet. Create one any time with "Backup now", or it happens automatically right before an update installs.', 'pridge-wp-endpoint' ); ?></p>
		<?php else : ?>
			<div class="pridge-table-wrap">
				<table class="pridge-archive-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Created', 'pridge-wp-endpoint' ); ?></th>
							<th><?php esc_html_e( 'Size', 'pridge-wp-endpoint' ); ?></th>
							<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'pridge-wp-endpoint' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $backups as $backup ) : ?>
							<tr>
								<td data-label="<?php esc_attr_e( 'Created', 'pridge-wp-endpoint' ); ?>"><?php echo esc_html( $backup['created_at'] ); ?> UTC</td>
								<td data-label="<?php esc_attr_e( 'Size', 'pridge-wp-endpoint' ); ?>"><?php echo esc_html( number_format_i18n( $backup['size'] / 1048576, 1 ) ); ?> MB</td>
								<td>
									<div class="pridge-button-row">
										<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" onsubmit="return confirm('<?php echo esc_js( __( 'This replaces the current plugin files with this backup. Continue?', 'pridge-wp-endpoint' ) ); ?>');">
											<input type="hidden" name="action" value="pridge_wp_restore_backup">
											<input type="hidden" name="backup" value="<?php echo esc_attr( $backup['name'] ); ?>">
											<?php wp_nonce_field( 'pridge_wp_restore_backup' ); ?>
											<button class="button pridge-button is-secondary" type="submit"><?php esc_html_e( 'Restore this backup', 'pridge-wp-endpoint' ); ?></button>
										</form>
										<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this backup permanently? This cannot be undone.', 'pridge-wp-endpoint' ) ); ?>');">
											<input type="hidden" name="action" value="pridge_wp_delete_backup">
											<input type="hidden" name="backup" value="<?php echo esc_attr( $backup['name'] ); ?>">
											<?php wp_nonce_field( 'pridge_wp_delete_backup' ); ?>
											<button class="button pridge-button is-secondary" type="submit"><?php esc_html_e( 'Delete', 'pridge-wp-endpoint' ); ?></button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
</div>
<?php require PRIDGE_WP_DIR . 'views/partials/admin-footer.php'; ?>
