<?php
/**
 * Privacy module service registration.
 *
 * @package WooCommerceReviewReminder\Privacy
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Privacy;

use WooCommerceReviewReminder\Core\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Class PrivacyProvider
 */
final class PrivacyProvider {

	/**
	 * Register services and hooks.
	 *
	 * @param Container $container Container.
	 */
	public static function register( Container $container ): void {
		$container->register(
			SuppressionRepository::class,
			static fn( Container $c ) => new SuppressionRepository(
				$c->get( \WooCommerceReviewReminder\Database\Schema::class ),
				$c->get( \WooCommerceReviewReminder\Core\Logger::class )
			)
		);

		$container->register(
			UnsubscribeController::class,
			static fn( Container $c ) => new UnsubscribeController(
				$c->get( SuppressionRepository::class ),
				$c->get( \WooCommerceReviewReminder\Queue\RequestRepository::class ),
				$c->get( \WooCommerceReviewReminder\Analytics\EventRepository::class ),
				$c->get( \WooCommerceReviewReminder\Core\Config::class ),
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
		$container->get( UnsubscribeController::class )->register();
	}
}
