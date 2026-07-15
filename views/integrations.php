<?php
/** @package PridgeWPEndpoint */
defined( 'ABSPATH' ) || exit;
$active_page      = 'integrations';
$page_title       = __( 'Integrations', 'pridge-wp-endpoint' );
$page_description = __( 'Choose which commerce systems may create automatic print jobs.', 'pridge-wp-endpoint' );
require PRIDGE_WP_DIR . 'views/partials/admin-header.php';
?>
<form class="pridge-settings-form" action="options.php" method="post">
	<?php settings_fields( 'pridge_wp_integrations_group' ); ?>
	<section class="pridge-panel is-visible">
		<div class="pridge-panel-heading"><div><span class="pridge-kicker"><?php esc_html_e( 'Commerce engine', 'pridge-wp-endpoint' ); ?></span><h2><?php esc_html_e( 'WooCommerce', 'pridge-wp-endpoint' ); ?></h2><p><?php esc_html_e( 'Enable order-driven document printing without affecting checkout.', 'pridge-wp-endpoint' ); ?></p></div></div>
		<div class="pridge-toggle-row <?php echo $woocommerce_active ? '' : 'is-disabled'; ?>"><div><strong><?php esc_html_e( 'WooCommerce integration', 'pridge-wp-endpoint' ); ?></strong><span><?php echo $woocommerce_active ? esc_html__( 'WooCommerce is active and HPOS-compatible printing is available.', 'pridge-wp-endpoint' ) : esc_html__( 'WooCommerce is not active.', 'pridge-wp-endpoint' ); ?></span></div><label class="pridge-switch"><span class="screen-reader-text"><?php esc_html_e( 'Enable WooCommerce integration', 'pridge-wp-endpoint' ); ?></span><input type="checkbox" name="pridge_wp_integrations[woocommerce_enabled]" value="1" data-pridge-woo-toggle <?php checked( ! empty( $settings['woocommerce_enabled'] ) ); ?> <?php disabled( ! $woocommerce_active ); ?>><span aria-hidden="true"></span></label></div>
		<label class="pridge-field pridge-status-field"><span><?php esc_html_e( 'Print when order becomes', 'pridge-wp-endpoint' ); ?></span><select name="pridge_wp_integrations[trigger_status]" <?php disabled( ! $woocommerce_active ); ?>><?php foreach ( $order_statuses as $status_key => $status_label ) : $status_value = 0 === strpos( $status_key, 'wc-' ) ? substr( $status_key, 3 ) : $status_key; ?><option value="<?php echo esc_attr( $status_value ); ?>" <?php selected( $settings['trigger_status'], $status_value ); ?>><?php echo esc_html( $status_label ); ?></option><?php endforeach; ?><?php if ( $shiptastic_available ) : ?><option value="shipped" <?php selected( $settings['trigger_status'], 'shipped' ); ?>><?php esc_html_e( 'Shipped (Shiptastic)', 'pridge-wp-endpoint' ); ?></option><?php endif; ?></select><small><?php esc_html_e( 'Shipped follows Shiptastic’s order shipping status, not a WooCommerce order status.', 'pridge-wp-endpoint' ); ?></small></label>
	</section>
	<section class="pridge-panel is-visible">
		<div class="pridge-panel-heading"><div><span class="pridge-kicker"><?php esc_html_e( 'Optional legal integration', 'pridge-wp-endpoint' ); ?></span><h2><?php esc_html_e( 'Germanized', 'pridge-wp-endpoint' ); ?></h2><p><?php esc_html_e( 'Germanized can only be enabled while the WooCommerce integration is enabled.', 'pridge-wp-endpoint' ); ?></p></div><button class="button pridge-button is-secondary" type="button" data-pridge-modal-open="pridge-germanized-test-modal" <?php disabled( empty( $settings['germanized_enabled'] ) || empty( $test_orders ) ); ?>><?php esc_html_e( 'Test Germanized PDFs', 'pridge-wp-endpoint' ); ?></button></div>
		<div class="pridge-toggle-row <?php echo $germanized_available ? '' : 'is-disabled'; ?>"><div><strong><?php esc_html_e( 'Germanized integration', 'pridge-wp-endpoint' ); ?></strong><span><?php echo $germanized_available ? esc_html__( 'Germanized is active.', 'pridge-wp-endpoint' ) : esc_html__( 'Germanized for WooCommerce is not active.', 'pridge-wp-endpoint' ); ?></span></div><label class="pridge-switch"><span class="screen-reader-text"><?php esc_html_e( 'Enable Germanized integration', 'pridge-wp-endpoint' ); ?></span><input type="checkbox" name="pridge_wp_integrations[germanized_enabled]" value="1" data-pridge-germanized-toggle data-plugin-available="<?php echo $germanized_available ? '1' : '0'; ?>" <?php checked( ! empty( $settings['germanized_enabled'] ) ); ?> <?php disabled( ! $germanized_available || empty( $settings['woocommerce_enabled'] ) ); ?>><span aria-hidden="true"></span></label></div>
	</section>
	<div class="pridge-save-bar"><div><strong><?php esc_html_e( 'Integration switches', 'pridge-wp-endpoint' ); ?></strong><span><?php esc_html_e( 'Document destinations are configured under Endpoints & Routing.', 'pridge-wp-endpoint' ); ?></span></div><?php submit_button( __( 'Save integrations', 'pridge-wp-endpoint' ), 'primary pridge-button is-primary', 'submit', false ); ?></div>
</form>
<div class="pridge-modal" id="pridge-germanized-test-modal" role="dialog" aria-modal="true" aria-labelledby="pridge-germanized-test-title" hidden>
	<div class="pridge-modal-backdrop" data-pridge-modal-close></div>
	<div class="pridge-modal-card" role="document">
		<button class="pridge-modal-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'pridge-wp-endpoint' ); ?>" data-pridge-modal-close>&times;</button>
		<span class="pridge-kicker"><?php esc_html_e( 'Germanized document test', 'pridge-wp-endpoint' ); ?></span>
		<h2 id="pridge-germanized-test-title"><?php esc_html_e( 'Print existing order PDFs', 'pridge-wp-endpoint' ); ?></h2>
		<p><?php esc_html_e( 'This fetches the selected order’s existing Germanized invoice PDF, packing-slip PDFs, and routed Shiptastic label PDFs. Unassigned or missing documents are not generated.', 'pridge-wp-endpoint' ); ?></p>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="pridge_wp_test_germanized_order">
			<?php wp_nonce_field( 'pridge_wp_test_germanized_order' ); ?>
			<label class="pridge-field">
				<span><?php esc_html_e( 'WooCommerce order', 'pridge-wp-endpoint' ); ?></span>
				<select name="order_id" required>
					<option value=""><?php esc_html_e( 'Select a recent order', 'pridge-wp-endpoint' ); ?></option>
					<?php foreach ( $test_orders as $test_order ) : ?>
						<?php
						$customer_name = trim( $test_order->get_billing_first_name() . ' ' . $test_order->get_billing_last_name() );
						$customer_name = $customer_name ?: __( 'Guest', 'pridge-wp-endpoint' );
						$order_label   = sprintf(
							/* translators: 1: order number, 2: customer name, 3: order status. */
							__( '#%1$s — %2$s — %3$s', 'pridge-wp-endpoint' ),
							$test_order->get_order_number(),
							$customer_name,
							wc_get_order_status_name( $test_order->get_status() )
						);
						?>
						<option value="<?php echo esc_attr( $test_order->get_id() ); ?>"><?php echo esc_html( $order_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<small><?php esc_html_e( 'The 50 most recent orders are available.', 'pridge-wp-endpoint' ); ?></small>
			</label>
			<div class="pridge-modal-actions"><button class="button pridge-button is-secondary" type="button" data-pridge-modal-close><?php esc_html_e( 'Cancel', 'pridge-wp-endpoint' ); ?></button><button class="button pridge-button is-primary" type="submit"><?php esc_html_e( 'Send existing PDFs', 'pridge-wp-endpoint' ); ?></button></div>
		</form>
	</div>
</div>
<?php require PRIDGE_WP_DIR . 'views/partials/admin-footer.php'; ?>
