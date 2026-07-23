<?php
/**
 * @package PridgeWPEndpoint
 *
 * @var array<string, mixed>  $endpoint_settings
 * @var array<int, array>     $endpoints
 * @var array<string, string> $document_types
 */
defined( 'ABSPATH' ) || exit;
?>
<form class="pridge-settings-form" action="options.php" method="post" data-pridge-endpoints-form>
	<?php settings_fields( 'pridge_wp_endpoints_group' ); ?>
	<section class="pridge-panel is-visible">
		<div class="pridge-panel-heading"><div><span class="pridge-kicker"><?php esc_html_e( 'Printer destinations', 'pridge-wp-endpoint' ); ?></span><h2><?php esc_html_e( 'Named endpoint tokens', 'pridge-wp-endpoint' ); ?></h2><p><?php esc_html_e( 'Each token identifies one virtual printer on Pridge Server.', 'pridge-wp-endpoint' ); ?></p></div><button class="button pridge-button is-secondary" type="button" data-pridge-add-endpoint><?php esc_html_e( 'Add endpoint', 'pridge-wp-endpoint' ); ?></button></div>
		<div class="pridge-endpoint-list" data-pridge-endpoint-list>
			<?php foreach ( $endpoints as $index => $endpoint ) : ?>
				<div class="pridge-endpoint-row" data-endpoint-id="<?php echo esc_attr( $endpoint['id'] ); ?>"><input type="hidden" name="pridge_wp_endpoints[endpoints][<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $endpoint['id'] ); ?>"><label class="pridge-field"><span><?php esc_html_e( 'Name', 'pridge-wp-endpoint' ); ?></span><input type="text" data-endpoint-name name="pridge_wp_endpoints[endpoints][<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $endpoint['name'] ); ?>"></label><label class="pridge-field"><span><?php esc_html_e( 'Endpoint token', 'pridge-wp-endpoint' ); ?></span><input type="password" name="pridge_wp_endpoints[endpoints][<?php echo esc_attr( $index ); ?>][token]" value="" placeholder="<?php esc_attr_e( 'Saved securely — enter to replace', 'pridge-wp-endpoint' ); ?>" autocomplete="new-password"></label><button class="button pridge-icon-button" type="button" data-pridge-remove-endpoint aria-label="<?php esc_attr_e( 'Remove endpoint', 'pridge-wp-endpoint' ); ?>">&times;</button></div>
			<?php endforeach; ?>
		</div>
		<label class="pridge-field pridge-status-field"><span><?php esc_html_e( 'Default endpoint', 'pridge-wp-endpoint' ); ?></span><select name="pridge_wp_endpoints[default_endpoint]" data-endpoint-select><option value=""><?php esc_html_e( 'Select endpoint', 'pridge-wp-endpoint' ); ?></option><?php foreach ( $endpoints as $endpoint ) : ?><option value="<?php echo esc_attr( $endpoint['id'] ); ?>" <?php selected( $endpoint_settings['default_endpoint'], $endpoint['id'] ); ?>><?php echo esc_html( $endpoint['name'] ); ?></option><?php endforeach; ?></select><small><?php esc_html_e( 'Used by manual tests and other plugins that do not select an endpoint.', 'pridge-wp-endpoint' ); ?></small></label>
	</section>
	<section class="pridge-panel is-visible">
		<div class="pridge-panel-heading"><div><span class="pridge-kicker"><?php esc_html_e( 'Document routing', 'pridge-wp-endpoint' ); ?></span><h2><?php esc_html_e( 'What prints where?', 'pridge-wp-endpoint' ); ?></h2><p><?php esc_html_e( 'Leaving a document unassigned disables automatic printing for that type.', 'pridge-wp-endpoint' ); ?></p></div></div>
		<div class="pridge-routing-list"><?php foreach ( $document_types as $document_key => $document_label ) : ?><label class="pridge-route-row"><span><?php echo esc_html( $document_label ); ?></span><select name="pridge_wp_endpoints[routes][<?php echo esc_attr( $document_key ); ?>]" data-endpoint-select><option value=""><?php esc_html_e( 'Do not print', 'pridge-wp-endpoint' ); ?></option><?php foreach ( $endpoints as $endpoint ) : ?><option value="<?php echo esc_attr( $endpoint['id'] ); ?>" <?php selected( $this->endpoints->route_endpoint_id( $document_key ), $endpoint['id'] ); ?>><?php echo esc_html( $endpoint['name'] ); ?></option><?php endforeach; ?></select></label><?php endforeach; ?></div>
	</section>
	<div class="pridge-save-bar"><div><strong><?php esc_html_e( 'Printer routing', 'pridge-wp-endpoint' ); ?></strong><span><?php esc_html_e( 'Blank token fields keep their saved secrets.', 'pridge-wp-endpoint' ); ?></span></div><?php submit_button( __( 'Save endpoints & routing', 'pridge-wp-endpoint' ), 'primary pridge-button is-primary', 'submit', false ); ?></div>
</form>
<template id="pridge-endpoint-template"><div class="pridge-endpoint-row" data-endpoint-id="__ID__"><input type="hidden" name="pridge_wp_endpoints[endpoints][__INDEX__][id]" value="__ID__"><label class="pridge-field"><span><?php esc_html_e( 'Name', 'pridge-wp-endpoint' ); ?></span><input type="text" data-endpoint-name name="pridge_wp_endpoints[endpoints][__INDEX__][name]" value="" placeholder="<?php esc_attr_e( 'Kitchen printer', 'pridge-wp-endpoint' ); ?>"></label><label class="pridge-field"><span><?php esc_html_e( 'Endpoint token', 'pridge-wp-endpoint' ); ?></span><input type="password" name="pridge_wp_endpoints[endpoints][__INDEX__][token]" value="" placeholder="<?php esc_attr_e( 'Paste endpoint token', 'pridge-wp-endpoint' ); ?>" autocomplete="new-password"></label><button class="button pridge-icon-button" type="button" data-pridge-remove-endpoint aria-label="<?php esc_attr_e( 'Remove endpoint', 'pridge-wp-endpoint' ); ?>">&times;</button></div></template>
