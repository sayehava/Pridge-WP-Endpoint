<?php
/** @package PridgeWPEndpoint */
defined( 'ABSPATH' ) || exit;
?>
	<footer class="pridge-legal">
		<?php
		printf(
			/* translators: %s: plugin version. */
			esc_html__( 'Pridge WP Endpoint v%s', 'pridge-wp-endpoint' ),
			esc_html( PRIDGE_WP_VERSION )
		);
		?>
		&middot; <?php esc_html_e( 'Original author: Sayeh Ava Pazouki', 'pridge-wp-endpoint' ); ?>
		&middot; <?php echo esc_html( sprintf( 'Copyright © %s Sayeh Ava Pazouki', gmdate( 'Y' ) ) ); ?>
		&middot; <?php esc_html_e( 'Licensed under GPLv3 or later, with additional terms.', 'pridge-wp-endpoint' ); ?>
	</footer>
</div>
