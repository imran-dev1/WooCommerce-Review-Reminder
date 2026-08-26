<?php
/**
 * Uninstall handler.
 *
 * Removes plugin data only when the "delete data on uninstall" setting is
 * enabled. Customer, order and product data belonging to WooCommerce is
 * never touched.
 *
 * @package WooCommerceReviewReminder
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$wrr_uninstall_data   = get_option( 'wrr_settings', array() );
$wrr_uninstall_delete = isset( $wrr_uninstall_data['advanced']['delete_on_uninstall'] )
	? (bool) $wrr_uninstall_data['advanced']['delete_on_uninstall']
	: false;

if ( ! $wrr_uninstall_delete ) {
	return;
}

require_once __DIR__ . '/app/Core/Autoloader.php';

( new \WooCommerceReviewReminder\Core\Autoloader( __DIR__ . '/app' ) )->register();

$wrr_schema    = new \WooCommerceReviewReminder\Database\Schema();
$wrr_config    = new \WooCommerceReviewReminder\Core\Config();
$wrr_logger    = new \WooCommerceReviewReminder\Core\Logger( $wrr_config );
$wrr_installer = new \WooCommerceReviewReminder\Database\Installer( $wrr_schema, $wrr_logger );
$wrr_installer->uninstall();
