<?php
/** @package PridgeWPEndpoint */
defined( 'ABSPATH' ) || exit;
$active_page      = 'overview';
$page_title       = __( 'Overview', 'pridge-wp-endpoint' );
$page_description = __( 'Your Pridge Server connection and printing activity at a glance.', 'pridge-wp-endpoint' );
$is_configured    = $this->settings->is_configured();
require PRIDGE_WP_DIR . 'views/partials/admin-header.php';
?>
<div class="pridge-status-grid">
	<article class="pridge-stat-card"><span><?php esc_html_e( 'Server', 'pridge-wp-endpoint' ); ?></span><strong><?php echo $is_configured ? esc_html__( 'Ready', 'pridge-wp-endpoint' ) : esc_html__( 'Setup required', 'pridge-wp-endpoint' ); ?></strong><small><?php echo ! empty( $settings['server_url'] ) ? esc_html( wp_parse_url( $settings['server_url'], PHP_URL_HOST ) ) : esc_html__( 'No server URL', 'pridge-wp-endpoint' ); ?></small></article>
	<article class="pridge-stat-card"><span><?php esc_html_e( 'Endpoints', 'pridge-wp-endpoint' ); ?></span><strong><?php echo esc_html( count( $endpoints ) ); ?></strong><small><?php esc_html_e( 'Named printer destinations', 'pridge-wp-endpoint' ); ?></small></article>
	<article class="pridge-stat-card"><span><?php esc_html_e( 'Archived jobs', 'pridge-wp-endpoint' ); ?></span><strong><?php echo esc_html( $archive_count ); ?></strong><small><?php esc_html_e( 'Successful and failed attempts', 'pridge-wp-endpoint' ); ?></small></article>
</div>
<form class="pridge-settings-form" action="options.php" method="post">
	<?php settings_fields( 'pridge_wp_general_group' ); ?>
	<section class="pridge-panel is-visible">
		<div class="pridge-panel-heading"><div><span class="pridge-kicker"><?php esc_html_e( 'Server connection', 'pridge-wp-endpoint' ); ?></span><h2><?php esc_html_e( 'Pridge Server', 'pridge-wp-endpoint' ); ?></h2><p><?php esc_html_e( 'All named endpoint tokens use this server base URL.', 'pridge-wp-endpoint' ); ?></p></div><button class="button pridge-button is-secondary" type="button" data-pridge-modal-open="pridge-test-modal" <?php disabled( ! $is_configured ); ?>><?php esc_html_e( 'Test default endpoint', 'pridge-wp-endpoint' ); ?></button></div>
		<div class="pridge-fields-grid">
			<label class="pridge-field is-wide"><span><?php esc_html_e( 'Server URL', 'pridge-wp-endpoint' ); ?></span><input type="url" name="pridge_wp_settings[server_url]" value="<?php echo esc_attr( $settings['server_url'] ); ?>" placeholder="https://pridge.example.com"><small><?php esc_html_e( 'The /api/plugin/jobs path is added automatically.', 'pridge-wp-endpoint' ); ?></small></label>
			<label class="pridge-field"><span><?php esc_html_e( 'Default payload type', 'pridge-wp-endpoint' ); ?></span><select name="pridge_wp_settings[default_content_type]"><?php foreach ( array( 'text/plain', 'application/octet-stream', 'application/pdf', 'image/png' ) as $type ) : ?><option value="<?php echo esc_attr( $type ); ?>" <?php selected( $settings['default_content_type'], $type ); ?>><?php echo esc_html( $type ); ?></option><?php endforeach; ?></select></label>
		</div>
	</section>
	<details class="pridge-danger-zone"><summary><?php esc_html_e( 'Data lifecycle', 'pridge-wp-endpoint' ); ?></summary><label><input type="checkbox" name="pridge_wp_settings[delete_on_uninstall]" value="1" <?php checked( ! empty( $settings['delete_on_uninstall'] ) ); ?>><span><?php esc_html_e( 'Delete Pridge configuration and archive when uninstalled.', 'pridge-wp-endpoint' ); ?></span></label></details>
	<div class="pridge-save-bar"><div><strong><?php esc_html_e( 'Server configuration', 'pridge-wp-endpoint' ); ?></strong><span><?php esc_html_e( 'Tokens are managed separately under Endpoints & Routing.', 'pridge-wp-endpoint' ); ?></span></div><?php submit_button( __( 'Save settings', 'pridge-wp-endpoint' ), 'primary pridge-button is-primary', 'submit', false ); ?></div>
</form>
<div class="pridge-modal" id="pridge-test-modal" role="dialog" aria-modal="true" aria-labelledby="pridge-test-title" hidden><div class="pridge-modal-backdrop" data-pridge-modal-close></div><div class="pridge-modal-card" role="document"><button class="pridge-modal-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'pridge-wp-endpoint' ); ?>" data-pridge-modal-close>&times;</button><span class="pridge-kicker"><?php esc_html_e( 'Live endpoint check', 'pridge-wp-endpoint' ); ?></span><h2 id="pridge-test-title"><?php esc_html_e( 'Send a test print?', 'pridge-wp-endpoint' ); ?></h2><p><?php esc_html_e( 'This creates a real job on the default endpoint.', 'pridge-wp-endpoint' ); ?></p><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="pridge_wp_test_print"><?php wp_nonce_field( 'pridge_wp_test_print' ); ?><div class="pridge-modal-actions"><button class="button pridge-button is-secondary" type="button" data-pridge-modal-close><?php esc_html_e( 'Cancel', 'pridge-wp-endpoint' ); ?></button><button class="button pridge-button is-primary" type="submit"><?php esc_html_e( 'Send test job', 'pridge-wp-endpoint' ); ?></button></div></form></div></div>
<div class="pridge-settings-form">
	<section class="pridge-panel is-visible">
		<div class="pridge-panel-heading">
			<div>
				<span class="pridge-kicker"><?php esc_html_e( 'Self-update', 'pridge-wp-endpoint' ); ?></span>
				<h2><?php esc_html_e( 'Updates &amp; backups', 'pridge-wp-endpoint' ); ?></h2>
				<p>
					<?php
					printf(
						/* translators: %s: opening and closing <a> tags linking to the Plugins page. */
						esc_html__( 'Updates for Pridge WP Endpoint appear on the %1$sPlugins page%2$s like any other plugin, using WordPress\'s own update flow. A backup is taken automatically right before an update installs.', 'pridge-wp-endpoint' ),
						'<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">',
						'</a>'
					);
					?>
				</p>
			</div>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="pridge_wp_check_updates">
				<?php wp_nonce_field( 'pridge_wp_check_updates' ); ?>
				<button class="button pridge-button is-secondary" type="submit"><?php esc_html_e( 'Check for updates now', 'pridge-wp-endpoint' ); ?></button>
			</form>
		</div>
		<?php if ( empty( $backups ) ) : ?>
			<p class="pridge-empty-state"><?php esc_html_e( 'No backups yet. One is created automatically the first time an update installs.', 'pridge-wp-endpoint' ); ?></p>
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
									<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" onsubmit="return confirm('<?php echo esc_js( __( 'This replaces the current plugin files with this backup. Continue?', 'pridge-wp-endpoint' ) ); ?>');">
										<input type="hidden" name="action" value="pridge_wp_restore_backup">
										<input type="hidden" name="backup" value="<?php echo esc_attr( $backup['name'] ); ?>">
										<?php wp_nonce_field( 'pridge_wp_restore_backup' ); ?>
										<button class="button pridge-button is-secondary" type="submit"><?php esc_html_e( 'Restore this backup', 'pridge-wp-endpoint' ); ?></button>
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
<?php require PRIDGE_WP_DIR . 'views/partials/admin-footer.php'; ?>
