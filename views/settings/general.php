<?php
/**
 * @package PridgeWPEndpoint
 *
 * @var array<string, mixed> $settings
 */
defined( 'ABSPATH' ) || exit;
?>
<form class="pridge-settings-form" action="options.php" method="post">
	<?php settings_fields( 'pridge_wp_general_group' ); ?>
	<section class="pridge-panel is-visible">
		<div class="pridge-panel-heading"><div><span class="pridge-kicker"><?php esc_html_e( 'Server connection', 'pridge-wp-endpoint' ); ?></span><h2><?php esc_html_e( 'Pridge Server', 'pridge-wp-endpoint' ); ?></h2><p><?php esc_html_e( 'All named endpoint tokens use this server base URL.', 'pridge-wp-endpoint' ); ?></p></div></div>
		<div class="pridge-fields-grid">
			<label class="pridge-field is-wide"><span><?php esc_html_e( 'Server URL', 'pridge-wp-endpoint' ); ?></span><input type="url" name="pridge_wp_settings[server_url]" value="<?php echo esc_attr( $settings['server_url'] ); ?>" placeholder="https://pridge.example.com"><small><?php esc_html_e( 'The /api/plugin/jobs path is added automatically.', 'pridge-wp-endpoint' ); ?></small></label>
			<label class="pridge-field"><span><?php esc_html_e( 'Default payload type', 'pridge-wp-endpoint' ); ?></span><select name="pridge_wp_settings[default_content_type]"><?php foreach ( array( 'text/plain', 'application/octet-stream', 'application/pdf', 'image/png' ) as $type ) : ?><option value="<?php echo esc_attr( $type ); ?>" <?php selected( $settings['default_content_type'], $type ); ?>><?php echo esc_html( $type ); ?></option><?php endforeach; ?></select></label>
		</div>
	</section>
	<details class="pridge-danger-zone"><summary><?php esc_html_e( 'Data lifecycle', 'pridge-wp-endpoint' ); ?></summary><label><input type="checkbox" name="pridge_wp_settings[delete_on_uninstall]" value="1" <?php checked( ! empty( $settings['delete_on_uninstall'] ) ); ?>><span><?php esc_html_e( 'Delete Pridge configuration and archive when uninstalled.', 'pridge-wp-endpoint' ); ?></span></label></details>
	<div class="pridge-save-bar"><div><strong><?php esc_html_e( 'Server configuration', 'pridge-wp-endpoint' ); ?></strong><span><?php esc_html_e( 'Test the connection from the Overview page after saving.', 'pridge-wp-endpoint' ); ?></span></div><?php submit_button( __( 'Save settings', 'pridge-wp-endpoint' ), 'primary pridge-button is-primary', 'submit', false ); ?></div>
</form>
