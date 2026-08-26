<?php
/**
 * Cron module service registration.
 *
 * @package WooCommerceReviewReminder\Cron
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Cron;

use WooCommerceReviewReminder\Core\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Class CronProvider
 */
final class CronProvider {

	/**
	 * Register services and hooks.
	 *
	 * @param Container $container Container.
	 */
	public static function register( Container $container ): void {
		$container->register(
			CronManager::class,
			static fn( Container $c ) => new CronManager(
				$c->get( \WooCommerceReviewReminder\Database\Schema::class ),
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
		// No global hooks required beyond the queue scheduler.
	}
}
