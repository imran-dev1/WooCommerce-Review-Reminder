<?php
/**
 * WooCommerce compatibility checks.
 *
 * @package WooCommerceReviewReminder\Core
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Compatibility
 */
final class Compatibility {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Compatibility constructor.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) && defined( 'WC_VERSION' );
	}

	/**
	 * Whether WooCommerce meets the minimum required version.
	 *
	 * @return bool
	 */
	public function has_min_woocommerce_version(): bool {
		if ( ! $this->is_woocommerce_active() ) {
			return false;
		}
		return version_compare( (string) WC_VERSION, '7.0', '>=' );
	}

	/**
	 * Current WooCommerce version string, or empty.
	 *
	 * @return string
	 */
	public function woocommerce_version(): string {
		return $this->is_woocommerce_active() ? (string) WC_VERSION : '';
	}

	/**
	 * Register compatibility checks. Adds an admin notice when WooCommerce is
	 * missing or too old. The plugin never fatals without WooCommerce.
	 */
	public function check(): void {
		if ( $this->is_woocommerce_active() ) {
			if ( $this->has_min_woocommerce_version() ) {
				return;
			}
			$this->logger->warning(
				'WooCommerce version below minimum supported.',
				array( 'version' => $this->woocommerce_version() )
			);
		}
	}
}
