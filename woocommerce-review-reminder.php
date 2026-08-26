<?php
/**
 * Plugin Name:       WooCommerce Review Reminder
 * Plugin URI:        https://woocommerce-review-reminder.example.com
 * Description:       Automatically request product reviews from customers after they purchase and receive their orders. Build, schedule and track beautiful review-request campaigns from a modern dashboard.
 * Version:           1.2.4
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Author:            WooCommerce Review Reminder
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woocommerce-review-reminder
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:      11.0
 *
 * @package WooCommerceReviewReminder
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'WRR_VERSION', '1.2.4' );
define( 'WRR_PLUGIN_FILE', __FILE__ );
define( 'WRR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WRR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WRR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WRR_MIN_PHP_VERSION', '8.1' );
define( 'WRR_MIN_WP_VERSION', '6.2' );
define( 'WRR_TEXT_DOMAIN', 'woocommerce-review-reminder' );

/*
 * Abort loading when the required PHP version is not available. The plugin
 * intentionally uses modern PHP (8.1+) features, so it will not run on
 * older hosts even if WordPress itself allows it.
 */
if ( version_compare( PHP_VERSION, WRR_MIN_PHP_VERSION, '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: minimum required PHP version. */
						__( 'WooCommerce Review Reminder requires PHP %s or newer. Please ask your host to upgrade PHP.', 'woocommerce-review-reminder' ),
						WRR_MIN_PHP_VERSION
					)
				)
			);
		}
	);
	return;
}

require_once WRR_PLUGIN_DIR . 'app/Core/Autoloader.php';

( new \WooCommerceReviewReminder\Core\Autoloader( WRR_PLUGIN_DIR . 'app' ) )->register();

/**
 * Declare compatibility with enabled WooCommerce features.
 *
 * The plugin only uses WooCommerce CRUD APIs (wc_get_order, wc_get_orders,
 * WC_Order::get_meta) and does not interact with the cart or checkout, so it is
 * compatible with the custom order tables (HPOS) and the cart/checkout blocks.
 * Declaring this prevents WooCommerce from flagging the plugin as incompatible
 * with enabled features.
 */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}
		$declared_features = array(
			'custom_order_tables',
			'cart_checkout_blocks',
			'analytics',
			'marketplace',
			'order_attribution',
			'site_visibility_badge',
			'remote_logging',
			'email_improvements',
			'blueprint',
			'point_of_sale',
			'push_notifications',
			'product_block_editor',
		);
		foreach ( $declared_features as $feature_id ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( $feature_id, WRR_PLUGIN_FILE, true );
		}
	},
	20
);

/**
 * Boot the plugin.
 *
 * Returns the shared plugin container instance so other files can hook in
 * late (e.g. from themes or mu-plugins) without touching the plugin internals.
 *
 * @return \WooCommerceReviewReminder\Core\Plugin
 */
function wrr(): \WooCommerceReviewReminder\Core\Plugin {
	return \WooCommerceReviewReminder\Core\Plugin::instance( WRR_PLUGIN_FILE )->boot();
}

wrr();
