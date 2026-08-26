<?php
/**
 * Analytics REST endpoints.
 *
 * @package WooCommerceReviewReminder\REST
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\REST;

use WooCommerceReviewReminder\Analytics\AnalyticsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class AnalyticsController
 */
final class AnalyticsController extends RestController {

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			RestRouter::NAMESPACE,
			'/analytics/time-series',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'time_series' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => array(
					'metric'      => array(
						'type'     => 'string',
						'required' => true,
					),
					'from'        => array(
						'type'     => 'string',
						'required' => true,
					),
					'to'          => array(
						'type'     => 'string',
						'required' => true,
					),
					'campaign_id' => array(
						'type'     => 'integer',
						'required' => false,
					),
				),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/analytics/campaigns',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'campaigns' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * Time series data for a metric.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function time_series( $request ) {
		$metric = $this->param( $request, 'metric', 'sent' );
		$from   = $this->param( $request, 'from', '' );
		$to     = $this->param( $request, 'to', '' );

		if ( '' === $from || '' === $to ) {
			return rest_ensure_response( new \WP_Error( 'invalid_range', __( 'A valid date range is required.', 'woocommerce-review-reminder' ), array( 'status' => 400 ) ) );
		}

		$allowed = array( 'scheduled', 'sent', 'opened', 'clicked', 'reviewed', 'failed', 'unsubscribed' );
		if ( ! in_array( $metric, $allowed, true ) ) {
			return rest_ensure_response( new \WP_Error( 'invalid_metric', __( 'Unknown metric.', 'woocommerce-review-reminder' ), array( 'status' => 400 ) ) );
		}

		$repo = $this->service( AnalyticsRepository::class );

		return rest_ensure_response(
			array(
				'metric' => $metric,
				'from'   => $from,
				'to'     => $to,
				'series' => $repo->time_series( $metric, $from, $to, ( 0 === $this->int_param( $request, 'campaign_id' ) ) ? null : $this->int_param( $request, 'campaign_id' ) ),
			)
		);
	}

	/**
	 * Campaign comparison.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function campaigns( $request ) {
		$repo = $this->service( AnalyticsRepository::class );
		return rest_ensure_response(
			array(
				'campaigns' => $repo->campaign_comparison(),
			)
		);
	}
}
