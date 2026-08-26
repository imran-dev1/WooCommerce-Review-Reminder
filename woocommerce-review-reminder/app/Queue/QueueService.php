<?php
/**
 * Review request queue business logic.
 *
 * @package WooCommerceReviewReminder\Queue
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Queue;

use WooCommerceReviewReminder\Campaigns\Campaign;
use WooCommerceReviewReminder\Campaigns\CampaignConfig;
use WooCommerceReviewReminder\Analytics\EventRepository;
use WooCommerceReviewReminder\Core\Logger;
use WooCommerceReviewReminder\Privacy\SuppressionRepository;
use WooCommerceReviewReminder\Reviews\ReviewDetector;

defined( 'ABSPATH' ) || exit;

/**
 * Class QueueService
 */
final class QueueService {

	/**
	 * Request repository.
	 *
	 * @var RequestRepository
	 */
	private RequestRepository $requests;

	/**
	 * Event repository.
	 *
	 * @var EventRepository
	 */
	private EventRepository $events;

	/**
	 * Suppression repository.
	 *
	 * @var SuppressionRepository
	 */
	private SuppressionRepository $suppressions;

	/**
	 * Review detector.
	 *
	 * @var ReviewDetector
	 */
	private ReviewDetector $reviews;

	/**
	 * Schedule calculator.
	 *
	 * @var ScheduleCalculator
	 */
	private ScheduleCalculator $scheduler;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * QueueService constructor.
	 *
	 * @param RequestRepository    $requests Requests repository.
	 * @param EventRepository      $events   Events repository.
	 * @param SuppressionRepository $suppressions Suppression repository.
	 * @param ReviewDetector       $reviews  Review detector.
	 * @param ScheduleCalculator   $scheduler Schedule calculator.
	 * @param Logger               $logger   Logger.
	 */
	public function __construct(
		RequestRepository $requests,
		EventRepository $events,
		SuppressionRepository $suppressions,
		ReviewDetector $reviews,
		ScheduleCalculator $scheduler,
		Logger $logger
	) {
		$this->requests     = $requests;
		$this->events       = $events;
		$this->suppressions = $suppressions;
		$this->reviews      = $reviews;
		$this->scheduler    = $scheduler;
		$this->logger       = $logger;
	}

	/**
	 * Create review request(s) for a matched order + campaign.
	 *
	 * Applies deduplication, suppression, review detection and the campaign's
	 * request strategy (one grouped email vs. one per product).
	 *
	 * @param int            $order_id    Order id.
	 * @param Campaign       $campaign    Campaign entity.
	 * @param int[]          $product_ids Eligible product ids (already campaign-matched).
	 * @param array<string, mixed> $context Order context: customer_id, customer_email, customer_name.
	 * @return int[] Created request ids.
	 */
	public function create_requests( int $order_id, Campaign $campaign, array $product_ids, array $context = array() ): array {
		$campaign_id = $campaign->id();
		$config      = $campaign->config();

		if ( ! $campaign->is_active() ) {
			return array();
		}

		$customer_id    = absint( $context['customer_id'] ?? 0 );
		$customer_email = sanitize_email( (string) ( $context['customer_email'] ?? '' ) );
		$customer_name  = sanitize_text_field( (string) ( $context['customer_name'] ?? '' ) );

		if ( '' === $customer_email ) {
			$this->logger->debug(
				'Skipping request creation: no customer email.',
				array(
					'order_id'    => $order_id,
					'campaign_id' => $campaign_id,
				)
			);
			return array();
		}

		// Suppression check.
		if ( $config->skip_suppressed() && $this->suppressions->is_suppressed( $customer_email ) ) {
			$this->logger->debug(
				'Skipping suppressed customer.',
				array(
					'order_id' => $order_id,
					'email'    => $customer_email,
				)
			);
			return array();
		}

		// Review detection: drop already-reviewed products.
		if ( $config->skip_reviewed() ) {
			$product_ids = $this->reviews->unreviewed_products( $product_ids, $customer_id, $customer_email );
		}

		if ( empty( $product_ids ) ) {
			$this->logger->debug(
				'No unreviewed products remain for order.',
				array(
					'order_id'    => $order_id,
					'campaign_id' => $campaign_id,
				)
			);
			return array();
		}

		$scheduled_at = $this->scheduler->calculate( $config->delay(), $config->delay_unit(), $config->send_time() );
		$created      = array();

		if ( 'per_product' === $config->request_strategy() ) {
			foreach ( $product_ids as $product_id ) {
				if ( $this->requests->exists( $order_id, $product_id, $campaign_id ) ) {
					continue;
				}
				$id = $this->create_request(
					$order_id,
					$campaign_id,
					$product_id,
					$customer_id,
					$customer_email,
					$customer_name,
					$scheduled_at,
					'initial',
					0
				);
				if ( $id > 0 ) {
					$created[] = $id;
				}
			}
			return $created;
		}

		// Grouped strategy: one request covering the whole order.
		if ( $this->requests->exists( $order_id, 0, $campaign_id ) ) {
			return array();
		}
		$id = $this->create_request(
			$order_id,
			$campaign_id,
			0,
			$customer_id,
			$customer_email,
			$customer_name,
			$scheduled_at,
			'initial',
			0
		);
		if ( $id > 0 ) {
			$created[] = $id;
		}
		return $created;
	}

	/**
	 * Schedule the next follow-up for a request that has been sent.
	 *
	 * @param ReviewRequest $request  The sent request.
	 * @param Campaign      $campaign Campaign entity.
	 * @return int|null New request id or null.
	 */
	public function schedule_followup( ReviewRequest $request, Campaign $campaign ): ?int {
		$config = $campaign->config();

		if ( ! $config->followup_enabled() ) {
			return null;
		}

		$next_number = $request->followup_number() + 1;
		if ( $next_number > ( $config->max_reminders() - 1 ) ) {
			return null;
		}

		if ( ! $request->product_id() > 0 ) {
			// Grouped requests: re-check that at least one product is still unreviewed.
			$order = wc_get_order( $request->order_id() );
			if ( ! $order ) {
				return null;
			}
			$eligible = $this->reviews->unreviewed_products( $this->order_product_ids( $order ), $request->customer_id(), $request->customer_email() );
			if ( empty( $eligible ) ) {
				$this->requests->set_status( $request->id(), ReviewRequest::STATUS_REVIEWED );
				return null;
			}
		}

		$scheduled_at = $this->scheduler->calculate(
			$config->followup_delay(),
			$config->followup_delay_unit(),
			$config->send_time()
		);

		$id = $this->create_request(
			$request->order_id(),
			$request->campaign_id(),
			$request->product_id(),
			$request->customer_id(),
			$request->customer_email(),
			$request->customer_name(),
			$scheduled_at,
			'followup',
			$next_number
		);

		return $id > 0 ? $id : null;
	}

	/**
	 * Cancel pending requests for a campaign.
	 *
	 * @param int $campaign_id Campaign id.
	 */
	public function cancel_by_campaign( int $campaign_id ): void {
		$this->requests->cancel_by_campaign( $campaign_id );
	}

	/**
	 * Cancel pending requests for an order.
	 *
	 * @param int $order_id Order id.
	 */
	public function cancel_by_order( int $order_id ): void {
		$this->requests->cancel_by_order( $order_id );
	}

	/**
	 * Mark requests as reviewed for an order + product.
	 *
	 * @param int $order_id   Order id.
	 * @param int $product_id Product id.
	 */
	public function mark_reviewed( int $order_id, int $product_id ): void {
		$this->requests->mark_reviewed( $order_id, $product_id );
	}

	/**
	 * Create a single request row.
	 *
	 * @return int Request id.
	 */
	private function create_request(
		int $order_id,
		int $campaign_id,
		int $product_id,
		int $customer_id,
		string $customer_email,
		string $customer_name,
		string $scheduled_at,
		string $request_type,
		int $followup_number
	): int {
		$id = $this->requests->create(
			array(
				'campaign_id'     => $campaign_id,
				'order_id'        => $order_id,
				'product_id'      => $product_id,
				'customer_id'     => $customer_id,
				'customer_email'  => $customer_email,
				'customer_name'   => $customer_name,
				'status'          => ReviewRequest::STATUS_SCHEDULED,
				'request_type'    => $request_type,
				'followup_number' => $followup_number,
				'scheduled_at'    => $scheduled_at,
				'token'           => $this->generate_token(),
				'source'          => 'order',
			)
		);

		if ( $id > 0 ) {
			$this->events->record(
				'scheduled',
				array(
					'request_id'     => $id,
					'campaign_id'    => $campaign_id,
					'order_id'       => $order_id,
					'customer_email' => $customer_email,
				)
			);
			$this->logger->debug(
				'Review request scheduled.',
				array(
					'request_id'   => $id,
					'order_id'     => $order_id,
					'campaign_id'  => $campaign_id,
					'scheduled_at' => $scheduled_at,
				)
			);
		}

		return $id;
	}

	/**
	 * Product ids in an order.
	 *
	 * @param \WC_Order $order Order.
	 * @return int[]
	 */
	private function order_product_ids( $order ): array {
		$ids = array();
		foreach ( $order->get_items() as $item ) {
			$ids[] = (int) $item->get_product_id();
		}
		return array_values( array_filter( $ids ) );
	}

	/**
	 * Generate a unique tracking token.
	 *
	 * @return string
	 */
	public function generate_token(): string {
		return bin2hex( random_bytes( 16 ) );
	}
}
