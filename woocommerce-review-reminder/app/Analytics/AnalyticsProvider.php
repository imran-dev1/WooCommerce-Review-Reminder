<?php
/**
 * Analytics module service registration.
 *
 * @package WooCommerceReviewReminder\Analytics
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Analytics;

use WooCommerceReviewReminder\Core\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Class AnalyticsProvider
 */
final class AnalyticsProvider {

	/**
	 * Register services and hooks.
	 *
	 * @param Container $container Container.
	 */
	public static function register( Container $container ): void {
		$container->register(
			EventRepository::class,
			static fn( Container $c ) => new EventRepository(
				$c->get( \WooCommerceReviewReminder\Database\Schema::class ),
				$c->get( \WooCommerceReviewReminder\Core\Logger::class )
			)
		);

		$container->register(
			AnalyticsRepository::class,
			static fn( Container $c ) => new AnalyticsRepository(
				$c->get( \WooCommerceReviewReminder\Database\Schema::class ),
				$c->get( \WooCommerceReviewReminder\Core\Logger::class )
			)
		);
	}
}
