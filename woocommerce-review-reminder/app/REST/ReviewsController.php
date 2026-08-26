<?php
/**
 * Reviews dashboard REST endpoints.
 *
 * @package WooCommerceReviewReminder\REST
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\REST;

use WooCommerceReviewReminder\Analytics\AnalyticsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReviewsController
 */
final class ReviewsController extends RestController {

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			RestRouter::NAMESPACE,
			'/reviews',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'dashboard' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * Reviews dashboard data.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function dashboard( $request ) {
		$repo = $this->service( AnalyticsRepository::class );
		return rest_ensure_response( $repo->reviews_dashboard() );
	}
}
