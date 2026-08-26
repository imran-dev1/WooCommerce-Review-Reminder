<?php
/**
 * Admin notices for plugin-level issues.
 *
 * @package WooCommerceReviewReminder\Admin
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class AdminNotice
 */
final class AdminNotice {

	/**
	 * Register the WooCommerce required notice.
	 */
	public function register(): void {
		add_action(
			'admin_notices',
			function (): void {
				if ( ! current_user_can( 'manage_options' ) ) {
					return;
				}
				printf(
					'<div class="notice notice-error"><p>%s <a href="%s" class="button button-small" style="vertical-align:middle;">%s</a></p></div>',
					esc_html__( 'WooCommerce Review Reminder requires WooCommerce to be installed and active.', 'woocommerce-review-reminder' ),
					esc_url( is_plugin_active( 'woocommerce/woocommerce.php' ) ? admin_url( 'plugins.php' ) : admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ),
					esc_html__( 'Install WooCommerce', 'woocommerce-review-reminder' )
				);
			}
		);
	}
}
