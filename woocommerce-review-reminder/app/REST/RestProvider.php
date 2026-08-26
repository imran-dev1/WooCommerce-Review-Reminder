<?php
/**
 * REST module service registration.
 *
 * @package WooCommerceReviewReminder\REST
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\REST;

use WooCommerceReviewReminder\Core\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Class RestProvider
 */
final class RestProvider {

	/**
	 * Register services and hooks.
	 *
	 * @param Container $container Container.
	 */
	public static function register( Container $container ): void {
		$container->register(
			RestRouter::class,
			static fn() => new RestRouter()
		);
	}

	/**
	 * Wire hooks (runs after all services are registered).
	 *
	 * @param Container $container Container.
	 */
	public static function hooks( Container $container ): void {
		$container->get( RestRouter::class )->register();
	}
}
