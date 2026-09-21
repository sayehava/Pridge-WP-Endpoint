<?php
/**
 * Manual Print tab: test printing without waiting for an order event.
 *
 * @package PridgeWPEndpoint
 *
 * @var bool          $is_configured
 * @var bool          $germanized_enabled
 * @var \WC_Order[]   $test_orders
 */
defined( 'ABSPATH' ) || exit;
$active_page      = 'manual';
$page_title       = __( 'Manual Print', 'pridge-wp-endpoint' );
$page_description = __( 'Send a real job without waiting for an order event.', 'pridge-wp-endpoint' );
require PRIDGE_WP_DIR . 'views/partials/admin-header.php';
?>
<div class="pridge-fields-grid">
	<section class="pridge-panel is-visible">
		<div class="pridge-panel-heading">
			<div>
				<span class="pridge-kicker"><?php esc_html_e( 'Live endpoint check', 'pridge-wp-endpoint' ); ?></span>
				<h2><?php esc_html_e( 'Test default endpoint', 'pridge-wp-endpoint' ); ?></h2>
				<p><?php esc_html_e( 'This creates a real job on the default endpoint.', 'pridge-wp-endpoint' ); ?></p>
			</div>
		</div>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="pridge_wp_test_print">
			<?php wp_nonce_field( 'pridge_wp_test_print' ); ?>
			<div class="pridge-button-row">
				<button class="button pridge-button is-primary" type="submit" <?php disabled( ! $is_configured ); ?>><?php esc_html_e( 'Send test job', 'pridge-wp-endpoint' ); ?></button>
			</div>
			<?php if ( ! $is_configured ) : ?>
				<p class="description"><?php esc_html_e( 'Configure the Pridge Server connection in Settings first.', 'pridge-wp-endpoint' ); ?></p>
			<?php endif; ?>
		</form>
	</section>
	<?php if ( $germanized_enabled ) : ?>
	<section class="pridge-panel is-visible">
		<div class="pridge-panel-heading">
			<div>
				<span class="pridge-kicker"><?php esc_html_e( 'Germanized document test', 'pridge-wp-endpoint' ); ?></span>
				<h2><?php esc_html_e( 'Test Germanized PDFs', 'pridge-wp-endpoint' ); ?></h2>
				<p><?php esc_html_e( 'This fetches the selected order’s existing Germanized invoice PDF, packing-slip PDFs, and routed Shiptastic label PDFs. Unassigned or missing documents are not generated.', 'pridge-wp-endpoint' ); ?></p>
			</div>
		</div>
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
			<div class="pridge-button-row">
				<button class="button pridge-button is-primary" type="submit" <?php disabled( empty( $test_orders ) ); ?>><?php esc_html_e( 'Send existing PDFs', 'pridge-wp-endpoint' ); ?></button>
			</div>
		</form>
	</section>
	<?php endif; ?>
</div>
<?php require PRIDGE_WP_DIR . 'views/partials/admin-footer.php'; ?>
