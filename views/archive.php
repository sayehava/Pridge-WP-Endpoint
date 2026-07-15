<?php
/**
 * Print archive administration view.
 *
 * @package PridgeWPEndpoint
 */

defined( 'ABSPATH' ) || exit;
$active_page      = 'archive';
$page_title       = __( 'Print Archive', 'pridge-wp-endpoint' );
$page_description = __( 'Review every payload Pridge attempted to deliver.', 'pridge-wp-endpoint' );
$page_count       = max( 1, (int) ceil( $total_rows / 20 ) );
require PRIDGE_WP_DIR . 'views/partials/admin-header.php';
?>
<section class="pridge-panel is-visible">
	<div class="pridge-panel-heading">
		<div>
			<span class="pridge-kicker"><?php esc_html_e( 'Delivery history', 'pridge-wp-endpoint' ); ?></span>
			<h2><?php esc_html_e( 'Archived print jobs', 'pridge-wp-endpoint' ); ?></h2>
			<p><?php printf( esc_html__( '%d total attempts, including failed deliveries.', 'pridge-wp-endpoint' ), $total_rows ); ?></p>
		</div>
	</div>
	<?php if ( empty( $archive_rows ) ) : ?>
		<p class="pridge-empty-state"><?php esc_html_e( 'No print attempts have been archived yet.', 'pridge-wp-endpoint' ); ?></p>
	<?php else : ?>
		<div class="pridge-table-wrap">
			<table class="pridge-archive-table">
				<thead><tr><th><?php esc_html_e( 'Date', 'pridge-wp-endpoint' ); ?></th><th><?php esc_html_e( 'Document', 'pridge-wp-endpoint' ); ?></th><th><?php esc_html_e( 'Endpoint', 'pridge-wp-endpoint' ); ?></th><th><?php esc_html_e( 'Order', 'pridge-wp-endpoint' ); ?></th><th><?php esc_html_e( 'Status', 'pridge-wp-endpoint' ); ?></th><th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'pridge-wp-endpoint' ); ?></span></th></tr></thead>
				<tbody>
				<?php foreach ( $archive_rows as $row ) : ?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Date', 'pridge-wp-endpoint' ); ?>"><?php echo esc_html( get_date_from_gmt( $row['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'Document', 'pridge-wp-endpoint' ); ?>"><strong><?php echo esc_html( $this->document_label( $row['document_type'] ) ); ?></strong><small><?php echo esc_html( $row['content_type'] ); ?></small></td>
						<td data-label="<?php esc_attr_e( 'Endpoint', 'pridge-wp-endpoint' ); ?>"><?php echo esc_html( $row['endpoint_name'] ?: $row['endpoint_id'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Order', 'pridge-wp-endpoint' ); ?>"><?php echo $row['order_id'] ? esc_html( '#' . $row['order_id'] ) : '&mdash;'; ?></td>
						<td data-label="<?php esc_attr_e( 'Status', 'pridge-wp-endpoint' ); ?>"><span class="pridge-badge is-<?php echo esc_attr( sanitize_html_class( $row['status'] ) ); ?>"><?php echo esc_html( ucfirst( $row['status'] ) ); ?></span></td>
						<td><button class="button pridge-button is-secondary" type="button" data-pridge-archive-view="<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'View', 'pridge-wp-endpoint' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php if ( 1 < $page_count ) : ?>
			<nav class="pridge-pagination" aria-label="<?php esc_attr_e( 'Print archive pages', 'pridge-wp-endpoint' ); ?>">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( array( 'page' => \Pridge\Admin\Admin::PAGE_ARCHIVE, 'paged' => '%#%' ), admin_url( 'admin.php' ) ),
							'format'    => '',
							'current'   => $page,
							'total'     => $page_count,
							'prev_text' => __( 'Previous', 'pridge-wp-endpoint' ),
							'next_text' => __( 'Next', 'pridge-wp-endpoint' ),
						)
					)
				);
				?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</section>
<div class="pridge-modal" id="pridge-archive-modal" role="dialog" aria-modal="true" aria-labelledby="pridge-archive-title" hidden>
	<div class="pridge-modal-backdrop" data-pridge-modal-close></div>
	<div class="pridge-modal-card is-wide" role="document">
		<button class="pridge-modal-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'pridge-wp-endpoint' ); ?>" data-pridge-modal-close>&times;</button>
		<span class="pridge-kicker"><?php esc_html_e( 'Archived payload', 'pridge-wp-endpoint' ); ?></span>
		<h2 id="pridge-archive-title"><?php esc_html_e( 'Print attempt', 'pridge-wp-endpoint' ); ?></h2>
		<div class="pridge-archive-summary" data-pridge-archive-summary></div>
		<div class="pridge-archive-preview" data-pridge-archive-preview><p><?php esc_html_e( 'Loading archived payload…', 'pridge-wp-endpoint' ); ?></p></div>
		<details class="pridge-archive-metadata"><summary><?php esc_html_e( 'Job metadata', 'pridge-wp-endpoint' ); ?></summary><pre data-pridge-archive-metadata></pre></details>
	</div>
</div>
<?php require PRIDGE_WP_DIR . 'views/partials/admin-footer.php'; ?>
