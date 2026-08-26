<?php
/**
 * Queue management REST endpoints.
 *
 * @package WooCommerceReviewReminder\REST
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\REST;

use WooCommerceReviewReminder\Queue\QueueProcessor;
use WooCommerceReviewReminder\Queue\QueueScheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Class QueueController
 */
final class QueueController extends RestController {

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			RestRouter::NAMESPACE,
			'/queue/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/queue/process',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'process' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * Queue status.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function status( $request ) {
		$scheduler = $this->service( QueueScheduler::class );
		$requests  = $this->service( \WooCommerceReviewReminder\Queue\RequestRepository::class );

		return rest_ensure_response(
			array(
				'scheduled'     => $scheduler->has_schedule(),
				'due_count'     => count( $requests->find_due( 1 ) ),
				'pending_count' => $requests->count( \WooCommerceReviewReminder\Queue\ReviewRequest::STATUS_SCHEDULED ),
				'last_run'      => get_option( 'wrr_last_queue_run', '' ),
				'server_time'   => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Manually trigger a queue run (useful for debugging).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function process( $request ) {
		$processor = $this->service( QueueProcessor::class );
		$result    = $processor->process_due( 50 );

		update_option( 'wrr_last_queue_run', current_time( 'mysql' ), false );

		return rest_ensure_response( $result );
	}
}
