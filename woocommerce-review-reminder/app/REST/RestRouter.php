<?php
/**
 * REST API route registration.
 *
 * @package WooCommerceReviewReminder\REST
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\REST;

defined( 'ABSPATH' ) || exit;

/**
 * Class RestRouter
 */
final class RestRouter {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	public const NAMESPACE = 'woocommerce-review-reminder/v1';

	/**
	 * Register all routes.
	 */
	public function register(): void {
		add_action(
			'rest_api_init',
			function (): void {
				( new DashboardController() )->register_routes();
				( new CampaignsController() )->register_routes();
				( new RequestsController() )->register_routes();
				( new AnalyticsController() )->register_routes();
				( new ReviewsController() )->register_routes();
				( new TemplatesController() )->register_routes();
				( new SettingsController() )->register_routes();
				( new EmailsController() )->register_routes();
				( new SuppressionController() )->register_routes();
				( new SearchController() )->register_routes();
				( new QueueController() )->register_routes();
			}
		);
	}
}
