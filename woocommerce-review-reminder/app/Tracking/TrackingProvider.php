<?php
/**
 * Tracking module service registration.
 *
 * @package WooCommerceReviewReminder\Tracking
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Tracking;

use WooCommerceReviewReminder\Core\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Class TrackingProvider
 */
final class TrackingProvider {

	/**
	 * Register services and hooks.
	 *
	 * @param Container $container Container.
	 */
	public static function register( Container $container ): void {
		$container->register(
			Tracker::class,
			static fn( Container $c ) => new Tracker(
				$c->get( \WooCommerceReviewReminder\Queue\RequestRepository::class ),
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
		$container->get( Tracker::class )->register();
	}
}
