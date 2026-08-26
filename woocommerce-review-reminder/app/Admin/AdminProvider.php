<?php
/**
 * Admin module service registration.
 *
 * @package WooCommerceReviewReminder\Admin
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Admin;

use WooCommerceReviewReminder\Core\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Class AdminProvider
 */
final class AdminProvider {

	/**
	 * Register services and hooks.
	 *
	 * @param Container $container Container.
	 */
	public static function register( Container $container ): void {
		$container->register(
			AdminMenu::class,
			static fn() => new AdminMenu()
		);

		$container->register(
			Assets::class,
			static fn( Container $c ) => new Assets(
				$c->get( \WooCommerceReviewReminder\Core\Config::class )
			)
		);
	}

	/**
	 * Wire hooks (runs after all services are registered).
	 *
	 * @param Container $container Container.
	 */
	public static function hooks( Container $container ): void {
		$container->get( AdminMenu::class )->register();
		$container->get( Assets::class )->register();
	}
}
