<?php
/**
 * Queue module service registration.
 *
 * @package WooCommerceReviewReminder\Queue
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Queue;

use WooCommerceReviewReminder\Core\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Class QueueProvider
 */
final class QueueProvider {

	/**
	 * Register services and hooks.
	 *
	 * @param Container $container Container.
	 */
	public static function register( Container $container ): void {
		$container->register(
			RequestRepository::class,
			static fn( Container $c ) => new RequestRepository(
				$c->get( \WooCommerceReviewReminder\Database\Schema::class ),
				$c->get( \WooCommerceReviewReminder\Core\Logger::class )
			)
		);

		$container->register(
			ScheduleCalculator::class,
			static fn() => new ScheduleCalculator()
		);

		$container->register(
			QueueService::class,
			static fn( Container $c ) => new QueueService(
				$c->get( RequestRepository::class ),
				$c->get( \WooCommerceReviewReminder\Analytics\EventRepository::class ),
				$c->get( \WooCommerceReviewReminder\Privacy\SuppressionRepository::class ),
				$c->get( \WooCommerceReviewReminder\Reviews\ReviewDetector::class ),
				$c->get( ScheduleCalculator::class ),
				$c->get( \WooCommerceReviewReminder\Core\Logger::class )
			)
		);

		$container->register(
			QueueProcessor::class,
			static fn( Container $c ) => new QueueProcessor(
				$c->get( RequestRepository::class ),
				$c->get( \WooCommerceReviewReminder\Campaigns\Repository\CampaignRepository::class ),
				$c->get( \WooCommerceReviewReminder\Emails\EmailManager::class ),
				$c->get( \WooCommerceReviewReminder\Reviews\ReviewDetector::class ),
				$c->get( \WooCommerceReviewReminder\Privacy\SuppressionRepository::class ),
				$c->get( \WooCommerceReviewReminder\Analytics\EventRepository::class ),
				$c->get( \WooCommerceReviewReminder\Core\Config::class ),
				$c->get( \WooCommerceReviewReminder\Core\Logger::class )
			)
		);

		$container->register(
			QueueScheduler::class,
			static fn( Container $c ) => new QueueScheduler(
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
		$container->get( QueueScheduler::class )->register();
	}
}
