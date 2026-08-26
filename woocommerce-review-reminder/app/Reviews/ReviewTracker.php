<?php
/**
 * Detects new product reviews and updates requests.
 *
 * @package WooCommerceReviewReminder\Reviews
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Reviews;

use WooCommerceReviewReminder\Analytics\EventRepository;
use WooCommerceReviewReminder\Core\Logger;
use WooCommerceReviewReminder\Queue\QueueService;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReviewTracker
 */
final class ReviewTracker {

	/**
	 * Queue service.
	 *
	 * @var QueueService
	 */
	private QueueService $queue;

	/**
	 * Event repository.
	 *
	 * @var EventRepository
	 */
	private EventRepository $events;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * ReviewTracker constructor.
	 *
	 * @param QueueService    $queue  Queue service.
	 * @param EventRepository $events Event repository.
	 * @param Logger          $logger Logger.
	 */
	public function __construct( QueueService $queue, EventRepository $events, Logger $logger ) {
		$this->queue  = $queue;
		$this->events = $events;
		$this->logger = $logger;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'wp_insert_comment', array( $this, 'on_comment_inserted' ), 10, 2 );
		add_action( 'comment_post', array( $this, 'on_comment_posted' ), 10, 3 );
		add_action( 'transition_comment_status', array( $this, 'on_comment_approved' ), 10, 3 );
	}

	/**
	 * Handle comment status transitions to 'approved'.
	 *
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 * @param \WP_Comment $comment Comment.
	 */
	public function on_comment_approved( string $new_status, string $old_status, $comment ): void {
		if ( 'approved' !== $new_status || 'approved' === $old_status ) {
			return;
		}
		if ( ! $comment || ! is_object( $comment ) ) {
			return;
		}
		$this->maybe_process_review( (int) $comment->comment_ID, (array) $comment );
	}

	/**
	 * Handle a new comment posted (may be unapproved initially).
	 *
	 * @param int $comment_id Comment id.
	 * @param int $approved   Approval state.
	 * @param array<string, mixed> $commentdata Comment data.
	 */
	public function on_comment_posted( int $comment_id, int $approved, array $commentdata ): void {
		if ( 1 !== $approved ) {
			// Wait for approval via transition_comment_status.
			return;
		}
		$this->maybe_process_review( $comment_id, $commentdata );
	}

	/**
	 * Handle wp_insert_comment as a fallback.
	 *
	 * @param int          $comment_id Comment id.
	 * @param \WP_Comment  $comment    Comment.
	 */
	public function on_comment_inserted( int $comment_id, $comment ): void {
		if ( ! $comment || ! is_object( $comment ) ) {
			return;
		}
		$this->maybe_process_review( $comment_id, (array) $comment );
	}

	/**
	 * Process a review comment if it matches the plugin criteria.
	 *
	 * @param int   $comment_id Comment id.
	 * @param array<string, mixed> $comment Comment data.
	 */
	private function maybe_process_review( int $comment_id, array $comment ): void {
		$comment_type     = (string) ( $comment['comment_type'] ?? '' );
		$comment_approved = (string) ( $comment['comment_approved'] ?? '0' );

		// Only product reviews.
		if ( '' !== $comment_type && 'review' !== $comment_type ) {
			return;
		}

		if ( ! in_array( $comment_approved, array( '1', 1 ), true ) ) {
			return;
		}

		$product_id = (int) ( $comment['comment_post_ID'] ?? 0 );
		$post_type  = $product_id > 0 ? get_post_type( $product_id ) : '';

		if ( 'product' !== $post_type ) {
			return;
		}

		$user_id = (int) ( $comment['user_id'] ?? 0 );
		$email   = (string) ( $comment['comment_author_email'] ?? '' );

		// Find orders matching this customer + product, and mark requests reviewed.
		$this->mark_orders_reviewed( $product_id, $user_id, $email, $comment_id );

		do_action( 'wrr_review_detected', $comment_id, $product_id, $user_id, $email );
	}

	/**
	 * Mark request(s) as reviewed for orders that contain the product.
	 *
	 * @param int    $product_id Product id.
	 * @param int    $user_id    Customer user id.
	 * @param string $email      Customer email.
	 * @param int    $comment_id Comment id.
	 */
	private function mark_orders_reviewed( int $product_id, int $user_id, string $email, int $comment_id ): void {
		$args = array(
			'limit'  => -1,
			'return' => 'ids',
		);

		if ( $user_id > 0 ) {
			$args['customer_id'] = $user_id;
		} elseif ( $email ) {
			$args['billing_email'] = $email;
		} else {
			return;
		}

		$order_ids = wc_get_orders( $args );
		if ( ! is_array( $order_ids ) ) {
			return;
		}

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			$has_product = false;
			foreach ( $order->get_items() as $item ) {
				if ( (int) $item->get_product_id() === $product_id ) {
					$has_product = true;
					break;
				}
			}

			if ( ! $has_product ) {
				continue;
			}

			$this->queue->mark_reviewed( (int) $order_id, $product_id );

			$pending = $this->pending_requests_for( (int) $order_id, $product_id );
			foreach ( $pending as $request ) {
				$this->events->record(
					'reviewed',
					array(
						'request_id'     => $request['id'],
						'campaign_id'    => $request['campaign_id'],
						'order_id'       => (int) $order_id,
						'customer_email' => $email,
						'meta'           => array( 'comment_id' => $comment_id ),
					)
				);
				do_action( 'wrr_review_submitted', (int) $request['id'] );
			}
		}
	}

	/**
	 * Pending requests for an order + product (used for event recording).
	 *
	 * @param int $order_id   Order id.
	 * @param int $product_id Product id.
	 * @return array<int, array<string, mixed>>
	 */
	private function pending_requests_for( int $order_id, int $product_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, campaign_id, status FROM %i WHERE order_id = %d AND (product_id = %d OR product_id = 0) ORDER BY id ASC',
				\WooCommerceReviewReminder\Core\Plugin::instance()->get( \WooCommerceReviewReminder\Database\Schema::class )->table( 'requests' ),
				$order_id,
				$product_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}
}
