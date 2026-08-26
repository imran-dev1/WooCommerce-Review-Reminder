<?php
/**
 * Review request REST endpoints.
 *
 * @package WooCommerceReviewReminder\REST
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\REST;

use WooCommerceReviewReminder\Analytics\EventRepository;
use WooCommerceReviewReminder\Queue\RequestRepository;
use WooCommerceReviewReminder\Queue\ReviewRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Class RequestsController
 */
final class RequestsController extends RestController {

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			RestRouter::NAMESPACE,
			'/requests',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'index' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => array(
					'status'      => array(
						'type'     => 'string',
						'required' => false,
					),
					'campaign_id' => array(
						'type'     => 'integer',
						'required' => false,
					),
					'search'      => array(
						'type'     => 'string',
						'required' => false,
					),
					'page'        => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page'    => array(
						'type'    => 'integer',
						'default' => 20,
					),
				),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/requests/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'cancel' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/requests/(?P<id>\d+)/timeline',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'timeline' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * Paginated list with filters.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function index( $request ) {
		$repo = $this->service( RequestRepository::class );

		$result = $repo->paginate(
			array(
				'status'      => $this->param( $request, 'status', '' ),
				'campaign_id' => $this->int_param( $request, 'campaign_id' ),
				'search'      => $this->param( $request, 'search', '' ),
				'page'        => $this->int_param( $request, 'page', 1 ),
				'per_page'    => $this->int_param( $request, 'per_page', 20 ),
			)
		);

		$items = array_map(
			static function ( ReviewRequest $item ): array {
				return $item->to_array();
			},
			$result['items']
		);

		$status_counts = array();
		foreach ( array( 'scheduled', 'processing', 'sent', 'failed', 'cancelled', 'reviewed' ) as $status ) {
			$status_counts[ $status ] = $repo->count( $status );
		}

		return rest_ensure_response(
			array(
				'items'  => $items,
				'total'  => $result['total'],
				'page'   => $result['page'],
				'pages'  => $result['pages'],
				'counts' => $status_counts,
			)
		);
	}

	/**
	 * Single request detail.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function show( $request ) {
		$id   = $this->int_param( $request, 'id' );
		$repo = $this->service( RequestRepository::class );
		$item = $repo->find( $id );

		if ( null === $item ) {
			return rest_ensure_response( new \WP_Error( 'not_found', __( 'Request not found.', 'woocommerce-review-reminder' ), array( 'status' => 404 ) ) );
		}

		$order   = wc_get_order( $item->order_id() );
		$product = $item->product_id() > 0 ? wc_get_product( $item->product_id() ) : null;

		return rest_ensure_response(
			array(
				'item' => array_merge(
					$item->to_array(),
					array(
						'order_total'  => $order ? (float) $order->get_total() : null,
						'order_status' => $order ? $order->get_status() : '',
						'product_name' => $product ? $product->get_name() : '',
						'product_url'  => $product ? get_permalink( $item->product_id() ) : '',
					)
				),
			)
		);
	}

	/**
	 * Cancel a pending request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function cancel( $request ) {
		$id   = $this->int_param( $request, 'id' );
		$repo = $this->service( RequestRepository::class );
		$item = $repo->find( $id );

		if ( null === $item ) {
			return rest_ensure_response( new \WP_Error( 'not_found', __( 'Request not found.', 'woocommerce-review-reminder' ), array( 'status' => 404 ) ) );
		}

		if ( ! $item->is_pending() ) {
			return rest_ensure_response( new \WP_Error( 'not_pending', __( 'Only pending requests can be cancelled.', 'woocommerce-review-reminder' ), array( 'status' => 400 ) ) );
		}

		$repo->set_status( $id, ReviewRequest::STATUS_CANCELLED );

		return rest_ensure_response( array( 'cancelled' => true ) );
	}

	/**
	 * Request timeline.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function timeline( $request ) {
		$id   = $this->int_param( $request, 'id' );
		$repo = $this->service( RequestRepository::class );
		$item = $repo->find( $id );

		if ( null === $item ) {
			return rest_ensure_response( new \WP_Error( 'not_found', __( 'Request not found.', 'woocommerce-review-reminder' ), array( 'status' => 404 ) ) );
		}

		$events   = $this->service( EventRepository::class );
		$timeline = array();

		foreach ( $events->for_request( $id ) as $row ) {
			$timeline[] = array(
				'type'       => $row['event_type'],
				'created_at' => $row['created_at'],
				'human_time' => human_time_diff( strtotime( $row['created_at'] ) ) . ' ' . __( 'ago', 'woocommerce-review-reminder' ),
				'meta'       => $row['meta'] ? json_decode( $row['meta'], true ) : array(),
			);
		}

		return rest_ensure_response( array( 'timeline' => $timeline ) );
	}
}
