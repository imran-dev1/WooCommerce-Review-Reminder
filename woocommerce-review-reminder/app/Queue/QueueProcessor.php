<?php
/**
 * Processes due review requests and sends emails.
 *
 * @package WooCommerceReviewReminder\Queue
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Queue;

use WooCommerceReviewReminder\Campaigns\Repository\CampaignRepository;
use WooCommerceReviewReminder\Analytics\EventRepository;
use WooCommerceReviewReminder\Core\Config;
use WooCommerceReviewReminder\Core\Logger;
use WooCommerceReviewReminder\Emails\EmailManager;
use WooCommerceReviewReminder\Privacy\SuppressionRepository;
use WooCommerceReviewReminder\Reviews\ReviewDetector;

defined( 'ABSPATH' ) || exit;

/**
 * Class QueueProcessor
 */
final class QueueProcessor {

	/**
	 * Request repository.
	 *
	 * @var RequestRepository
	 */
	private RequestRepository $requests;

	/**
	 * Campaign repository.
	 *
	 * @var CampaignRepository
	 */
	private CampaignRepository $campaigns;

	/**
	 * Email manager.
	 *
	 * @var EmailManager
	 */
	private EmailManager $email;

	/**
	 * Review detector.
	 *
	 * @var ReviewDetector
	 */
	private ReviewDetector $reviews;

	/**
	 * Suppression repository.
	 *
	 * @var SuppressionRepository
	 */
	private SuppressionRepository $suppressions;

	/**
	 * Event repository.
	 *
	 * @var EventRepository
	 */
	private EventRepository $events;

	/**
	 * Config.
	 *
	 * @var Config
	 */
	private Config $config;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * QueueProcessor constructor.
	 *
	 * @param RequestRepository    $requests     Requests.
	 * @param CampaignRepository   $campaigns    Campaigns.
	 * @param EmailManager         $email        Email manager.
	 * @param ReviewDetector       $reviews      Review detector.
	 * @param SuppressionRepository $suppressions Suppressions.
	 * @param EventRepository      $events       Events.
	 * @param Config               $config       Config.
	 * @param Logger               $logger       Logger.
	 */
	public function __construct(
		RequestRepository $requests,
		CampaignRepository $campaigns,
		EmailManager $email,
		ReviewDetector $reviews,
		SuppressionRepository $suppressions,
		EventRepository $events,
		Config $config,
		Logger $logger
	) {
		$this->requests     = $requests;
		$this->campaigns    = $campaigns;
		$this->email        = $email;
		$this->reviews      = $reviews;
		$this->suppressions = $suppressions;
		$this->events       = $events;
		$this->config       = $config;
		$this->logger       = $logger;
	}

	/**
	 * Process a batch of due requests.
	 *
	 * @param int $limit Max requests to process this tick.
	 * @return array{processed: int, sent: int, failed: int}
	 */
	public function process_due( int $limit = 50 ): array {
		$due       = $this->requests->find_due( max( 1, min( 200, $limit ) ) );
		$processed = 0;
		$sent      = 0;
		$failed    = 0;

		foreach ( $due as $request ) {
			$result = $this->process_one( $request );
			++$processed;

			if ( true === $result ) {
				++$sent;
			} elseif ( false === $result ) {
				++$failed;
			}
		}

		$this->logger->info(
			'Queue processed.',
			array(
				'processed' => $processed,
				'sent'      => $sent,
				'failed'    => $failed,
			)
		);

		update_option( 'wrr_last_queue_run', current_time( 'mysql' ), false );

		return array(
			'processed' => $processed,
			'sent'      => $sent,
			'failed'    => $failed,
		);
	}

	/**
	 * Process a single request.
	 *
	 * @param ReviewRequest $request Request.
	 * @return bool|null True=sent, false=permanent failure, null=skipped/cancelled.
	 */
	public function process_one( ReviewRequest $request ): ?bool {
		// Atomic claim to prevent duplicate execution across cron ticks.
		$claimed = $this->claims_request( $request->id() );
		if ( ! $claimed ) {
			return null;
		}

		$verdict = $this->verify_before_send( $request );
		if ( 'ok' !== $verdict ) {
			$this->requests->update(
				$request->id(),
				array(
					'status'     => ReviewRequest::STATUS_CANCELLED,
					'last_error' => $verdict,
				)
			);
			$this->events->record(
				'cancelled',
				array(
					'request_id'     => $request->id(),
					'campaign_id'    => $request->campaign_id(),
					'order_id'       => $request->order_id(),
					'customer_email' => $request->customer_email(),
					'meta'           => array( 'reason' => $verdict ),
				)
			);
			return null;
		}

		$campaign = $this->campaigns->find( $request->campaign_id() );
		if ( null === $campaign ) {
			$this->requests->set_status( $request->id(), ReviewRequest::STATUS_CANCELLED );
			return null;
		}

		$result = $this->email->send( $request, $campaign );

		if ( $result->success ) {
			$this->on_sent( $request, $campaign );
			return true;
		}

		$this->on_failure( $request, $result->message );
		return false;
	}

	/**
	 * Atomically move a request from scheduled to processing.
	 *
	 * @param int $id Request id.
	 * @return bool
	 */
	private function claims_request( int $id ): bool {
		global $wpdb;

		$affected = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET status = %s, updated_at = %s WHERE id = %d AND status = %s',
				$this->requests->schema()->table( 'requests' ),
				ReviewRequest::STATUS_PROCESSING,
				current_time( 'mysql' ),
				$id,
				ReviewRequest::STATUS_SCHEDULED
			)
		);

		return 1 === $affected;
	}

	/**
	 * Re-check exclusion rules right before sending.
	 *
	 * @param ReviewRequest $request Request.
	 * @return string 'ok' or a human-readable skip reason.
	 */
	private function verify_before_send( ReviewRequest $request ): string {
		$order = wc_get_order( $request->order_id() );
		if ( ! $order ) {
			return __( 'Order no longer exists.', 'woocommerce-review-reminder' );
		}

		$status = $order->get_status();
		if ( in_array( $status, array( 'cancelled', 'refunded' ), true ) ) {
			return sprintf(
				/* translators: %s: order status. */
				__( 'Order status is %s.', 'woocommerce-review-reminder' ),
				$status
			);
		}

		$campaign = $this->campaigns->find( $request->campaign_id() );
		if ( null === $campaign ) {
			return __( 'Campaign no longer exists.', 'woocommerce-review-reminder' );
		}

		$config = $campaign->config();

		if ( $config->skip_suppressed() && $this->suppressions->is_suppressed( $request->customer_email() ) ) {
			return __( 'Customer opted out.', 'woocommerce-review-reminder' );
		}

		if ( $config->skip_reviewed() ) {
			if ( $request->product_id() > 0 ) {
				if ( $this->reviews->has_review( $request->product_id(), $request->customer_id(), $request->customer_email() ) ) {
					return __( 'Customer already reviewed the product.', 'woocommerce-review-reminder' );
				}
			} else {
				$eligible = $this->reviews->unreviewed_products(
					$this->order_product_ids( $order ),
					$request->customer_id(),
					$request->customer_email()
				);
				if ( empty( $eligible ) ) {
					return __( 'Customer already reviewed all products.', 'woocommerce-review-reminder' );
				}
			}
		}

		return 'ok';
	}

	/**
	 * Handle a successful send.
	 *
	 * @param ReviewRequest $request  Request.
	 * @param \WooCommerceReviewReminder\Campaigns\Campaign $campaign Campaign.
	 */
	private function on_sent( ReviewRequest $request, $campaign ): void {
		$now = current_time( 'mysql' );

		$this->requests->update(
			$request->id(),
			array(
				'status'  => ReviewRequest::STATUS_SENT,
				'sent_at' => $now,
			)
		);

		$this->events->record(
			'sent',
			array(
				'request_id'     => $request->id(),
				'campaign_id'    => $request->campaign_id(),
				'order_id'       => $request->order_id(),
				'customer_email' => $request->customer_email(),
			)
		);

		do_action( 'wrr_review_request_sent', $request->id() );

		// Schedule the next follow-up when configured.
		$this->schedule_next_followup( $request, $campaign );
	}

	/**
	 * Schedule the next follow-up for a sent request.
	 *
	 * @param ReviewRequest $request Request.
	 * @param \WooCommerceReviewReminder\Campaigns\Campaign $campaign Campaign.
	 */
	private function schedule_next_followup( ReviewRequest $request, $campaign ): void {
		$service = new QueueService(
			$this->requests,
			$this->events,
			$this->suppressions,
			$this->reviews,
			new ScheduleCalculator(),
			$this->logger
		);

		$next = $service->schedule_followup( $request, $campaign );
		if ( $next ) {
			$this->logger->debug(
				'Follow-up scheduled.',
				array(
					'request_id'  => $request->id(),
					'followup_id' => $next,
				)
			);
		}
	}

	/**
	 * Handle a failed send with retry logic.
	 *
	 * @param ReviewRequest $request Request.
	 * @param string        $reason  Failure reason.
	 */
	private function on_failure( ReviewRequest $request, string $reason ): void {
		$attempts  = $request->attempts() + 1;
		$max_retry = max( 1, (int) $this->config->get( 'automation.retry_count', 3 ) );

		$this->events->record(
			'failed',
			array(
				'request_id'     => $request->id(),
				'campaign_id'    => $request->campaign_id(),
				'order_id'       => $request->order_id(),
				'customer_email' => $request->customer_email(),
				'meta'           => array(
					'attempt' => $attempts,
					'reason'  => $reason,
				),
			)
		);

		if ( $attempts >= $max_retry ) {
			$this->requests->update(
				$request->id(),
				array(
					'status'     => ReviewRequest::STATUS_FAILED,
					'attempts'   => $attempts,
					'last_error' => $reason,
				)
			);
			$this->logger->error(
				'Request failed permanently.',
				array(
					'request_id' => $request->id(),
					'reason'     => $reason,
				)
			);
			return;
		}

		// Requeue with a retry delay.
		$retry_delay = max( 30, (int) $this->config->get( 'automation.retry_delay', 60 ) );
		$retry_at    = gmdate( 'Y-m-d H:i:s', time() + $retry_delay );

		$this->requests->update(
			$request->id(),
			array(
				'status'       => ReviewRequest::STATUS_SCHEDULED,
				'attempts'     => $attempts,
				'last_error'   => $reason,
				'scheduled_at' => $retry_at,
			)
		);

		$this->logger->warning(
			'Request failed, will retry.',
			array(
				'request_id' => $request->id(),
				'attempt'    => $attempts,
				'reason'     => $reason,
			)
		);
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
}
