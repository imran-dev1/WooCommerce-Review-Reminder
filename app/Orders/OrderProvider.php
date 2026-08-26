<?php
/**
 * Orders module service registration.
 *
 * @package WooCommerceReviewReminder\Orders
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Orders;

use WooCommerceReviewReminder\Core\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Class OrderProvider
 */
final class OrderProvider {

	/**
	 * Register services and hooks.
	 *
	 * @param Container $container Container.
	 */
	public static function register( Container $container ): void {
		$container->register(
			OrderMatcher::class,
			static fn() => new OrderMatcher()
		);

		$container->register(
			OrderObserver::class,
			static fn( Container $c ) => new OrderObserver(
				$c->get( \WooCommerceReviewReminder\Campaigns\Repository\CampaignRepository::class ),
				$c->get( OrderMatcher::class ),
				$c->get( \WooCommerceReviewReminder\Queue\QueueService::class ),
				$c->get( \WooCommerceReviewReminder\Core\Logger::class )
			)
		);
	}

	/**
	 * Wire hooks (runs after all services are registered).
	 *
	 * @param Container $container Container.
	 */
	public static function hooks( Container $container ): void {
		$container->get( OrderObserver::class )->register();
	}
}
