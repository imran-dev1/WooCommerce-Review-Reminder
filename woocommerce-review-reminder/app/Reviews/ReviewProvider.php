<?php
/**
 * Reviews module service registration.
 *
 * @package WooCommerceReviewReminder\Reviews
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Reviews;

use WooCommerceReviewReminder\Core\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReviewProvider
 */
final class ReviewProvider {

	/**
	 * Register services and hooks.
	 *
	 * @param Container $container Container.
	 */
	public static function register( Container $container ): void {
		$container->register(
			ReviewDetector::class,
			static fn( Container $c ) => new ReviewDetector(
				$c->get( \WooCommerceReviewReminder\Database\Schema::class ),
				$c->get( \WooCommerceReviewReminder\Core\Logger::class )
			)
		);

		$container->register(
			ReviewUrl::class,
			static fn() => new ReviewUrl()
		);

		$container->register(
			ReviewTracker::class,
			static fn( Container $c ) => new ReviewTracker(
				$c->get( \WooCommerceReviewReminder\Queue\QueueService::class ),
				$c->get( \WooCommerceReviewReminder\Analytics\EventRepository::class ),
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
		$container->get( ReviewTracker::class )->register();
	}
}
