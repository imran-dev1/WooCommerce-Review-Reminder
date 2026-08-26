<?php
/**
 * Dashboard REST endpoints.
 *
 * @package WooCommerceReviewReminder\REST
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\REST;

use WooCommerceReviewReminder\Analytics\AnalyticsRepository;
use WooCommerceReviewReminder\Analytics\EventRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class DashboardController
 */
final class DashboardController extends RestController {

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			RestRouter::NAMESPACE,
			'/dashboard/overview',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'overview' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => array(
					'from' => array(
						'type'     => 'string',
						'required' => false,
					),
					'to'   => array(
						'type'     => 'string',
						'required' => false,
					),
				),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/dashboard/activity',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'activity' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => array(
					'limit' => array(
						'type'     => 'integer',
						'default'  => 15,
						'required' => false,
					),
				),
			)
		);
	}

	/**
	 * Overview metrics.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function overview( $request ) {
		$from = $this->param( $request, 'from', '' );
		$to   = $this->param( $request, 'to', '' );

		/** @var AnalyticsRepository $repo */
		$repo = $this->service( AnalyticsRepository::class );

		$campaign_count = 0;
		$requests_repo  = $this->service( \WooCommerceReviewReminder\Campaigns\Repository\CampaignRepository::class );
		$campaign_count = $requests_repo->count();

		return rest_ensure_response(
			array_merge(
				$repo->overview( $from ? $from : null, $to ? $to : null ),
				array( 'campaign_count' => $campaign_count )
			)
		);
	}

	/**
	 * Recent activity feed.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function activity( $request ) {
		$limit = min( 50, $this->int_param( $request, 'limit', 15 ) );

		/** @var EventRepository $events */
		$events = $this->service( EventRepository::class );

		$activity = array_map(
			function ( array $row ): array {
				$meta = array();
				if ( ! empty( $row['meta'] ) ) {
					$decoded = json_decode( $row['meta'], true );
					$meta    = is_array( $decoded ) ? $decoded : array();
				}

				$label = '';
				if ( ! empty( $row['customer_email'] ) ) {
					$label = $row['customer_email'];
				}

				return array(
					'id'          => (int) $row['id'],
					'event_type'  => $row['event_type'],
					'customer'    => $label,
					'campaign_id' => (int) $row['campaign_id'],
					'order_id'    => (int) $row['order_id'],
					'meta'        => $meta,
					'created_at'  => $row['created_at'],
					'human_time'  => human_time_diff( strtotime( $row['created_at'] ) ) . ' ' . __( 'ago', 'woocommerce-review-reminder' ),
				);
			},
			$events->recent( $limit )
		);

		return rest_ensure_response( array( 'items' => $activity ) );
	}
}
