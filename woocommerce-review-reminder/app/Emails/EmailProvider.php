<?php
/**
 * Emails module service registration.
 *
 * @package WooCommerceReviewReminder\Emails
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Emails;

use WooCommerceReviewReminder\Core\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Class EmailProvider
 */
final class EmailProvider {

	/**
	 * Register services and hooks.
	 *
	 * @param Container $container Container.
	 */
	public static function register( Container $container ): void {
		$container->register(
			Variables::class,
			static fn() => new Variables()
		);

		$container->register(
			EmailRenderer::class,
			static fn( Container $c ) => new EmailRenderer( $c->get( Variables::class ) )
		);

		$container->register(
			TemplateRepository::class,
			static fn( Container $c ) => new TemplateRepository(
				$c->get( \WooCommerceReviewReminder\Database\Schema::class ),
				$c->get( \WooCommerceReviewReminder\Core\Logger::class )
			)
		);

		$container->register(
			EmailManager::class,
			static fn( Container $c ) => new EmailManager(
				$c->get( EmailRenderer::class ),
				$c->get( Variables::class ),
				$c->get( \WooCommerceReviewReminder\Core\Config::class ),
				$c->get( \WooCommerceReviewReminder\Core\Logger::class ),
				$c->get( \WooCommerceReviewReminder\Tracking\Tracker::class ),
				$c->get( \WooCommerceReviewReminder\Reviews\ReviewUrl::class ),
				$c->get( \WooCommerceReviewReminder\Privacy\UnsubscribeController::class )
			)
		);
	}

	/**
	 * Wire hooks (runs after all services are registered).
	 *
	 * @param Container $container Container.
	 */
	public static function hooks( Container $container ): void {
		// Future email-provider hooks can be registered here.
	}
}
