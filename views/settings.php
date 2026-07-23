<?php
/** @package PridgeWPEndpoint */
defined( 'ABSPATH' ) || exit;
$active_page      = 'settings';
$page_title       = __( 'Settings', 'pridge-wp-endpoint' );
$page_description = __( 'Server connection, commerce integrations, and printer routing.', 'pridge-wp-endpoint' );
require PRIDGE_WP_DIR . 'views/partials/admin-header.php';

$sub_tabs = array(
	'general'      => __( 'General', 'pridge-wp-endpoint' ),
	'integrations' => __( 'Integrations', 'pridge-wp-endpoint' ),
	'endpoints'    => __( 'Endpoints & Routing', 'pridge-wp-endpoint' ),
);
?>
<nav class="pridge-subtabs" aria-label="<?php esc_attr_e( 'Settings sections', 'pridge-wp-endpoint' ); ?>">
	<?php foreach ( $sub_tabs as $tab_key => $tab_label ) : ?>
		<a class="<?php echo $tab === $tab_key ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => \Pridge\Admin\Admin::PAGE_SETTINGS, 'tab' => $tab_key ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $tab_label ); ?></a>
	<?php endforeach; ?>
</nav>
<?php
switch ( $tab ) {
	case 'integrations':
		require PRIDGE_WP_DIR . 'views/settings/integrations.php';
		break;
	case 'endpoints':
		require PRIDGE_WP_DIR . 'views/settings/endpoints.php';
		break;
	default:
		require PRIDGE_WP_DIR . 'views/settings/general.php';
}
require PRIDGE_WP_DIR . 'views/partials/admin-footer.php';
