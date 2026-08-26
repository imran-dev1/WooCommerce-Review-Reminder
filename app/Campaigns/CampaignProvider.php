<?php
/**
 * Campaign module service registration.
 *
 * @package WooCommerceReviewReminder\Campaigns
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Campaigns;

use WooCommerceReviewReminder\Campaigns\Repository\CampaignRepository;
use WooCommerceReviewReminder\Core\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Class CampaignProvider
 */
final class CampaignProvider {

	/**
	 * Register services and hooks.
	 *
	 * @param Container $container Container instance.
	 */
	public static function register( Container $container ): void {
		$container->register(
			CampaignRepository::class,
			static fn( Container $c ) => new CampaignRepository(
				$c->get( \WooCommerceReviewReminder\Database\Schema::class ),
				$c->get( \WooCommerceReviewReminder\Core\Logger::class )
			)
		);

		$container->register(
			CampaignService::class,
			static fn( Container $c ) => new CampaignService(
				$c->get( CampaignRepository::class ),
				$c->get( \WooCommerceReviewReminder\Core\Logger::class )
			)
		);
	}
}
