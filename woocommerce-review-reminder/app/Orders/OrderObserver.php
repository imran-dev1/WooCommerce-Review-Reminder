<?php
/**
 * Observes order lifecycle events and schedules review requests.
 *
 * @package WooCommerceReviewReminder\Orders
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Orders;

use WooCommerceReviewReminder\Campaigns\Campaign;
use WooCommerceReviewReminder\Campaigns\Repository\CampaignRepository;
use WooCommerceReviewReminder\Core\Logger;
use WooCommerceReviewReminder\Queue\QueueService;

defined( 'ABSPATH' ) || exit;

/**
 * Class OrderObserver
 */
final class OrderObserver {

	/**
	 * Campaign repository.
	 *
	 * @var CampaignRepository
	 */
	private CampaignRepository $campaigns;

	/**
	 * Order matcher.
	 *
	 * @var OrderMatcher
	 */
	private OrderMatcher $matcher;

	/**
	 * Queue service.
	 *
	 * @var QueueService
	 */
	private QueueService $queue;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * OrderObserver constructor.
	 *
	 * @param CampaignRepository $campaigns Campaign repository.
	 * @param OrderMatcher       $matcher   Order matcher.
	 * @param QueueService       $queue     Queue service.
	 * @param Logger             $logger    Logger.
	 */
	public function __construct(
		CampaignRepository $campaigns,
		OrderMatcher $matcher,
		QueueService $queue,
		Logger $logger
	) {
		$this->campaigns = $campaigns;
		$this->matcher   = $matcher;
		$this->queue     = $queue;
		$this->logger    = $logger;
	}

	/**
	 * Register WooCommerce hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 20, 4 );

		add_action( 'woocommerce_order_refunded', array( $this, 'on_refunded' ), 10, 1 );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'on_cancelled' ), 10, 1 );
	}

	/**
	 * Handle order status transitions.
	 *
	 * @param int      $order_id    Order id.
	 * @param string   $from        Previous status.
	 * @param string   $to          New status.
	 * @param \WC_Order $order      Order object.
	 */
	public function on_status_changed( int $order_id, string $from, string $to, $order ): void {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}

		// Refunds and cancellations cancel pending requests.
		if ( 'refunded' === $to ) {
			$this->on_refunded( $order_id );
			return;
		}
		if ( 'cancelled' === $to ) {
			$this->on_cancelled( $order_id );
			return;
		}

		$this->maybe_schedule( $order );
	}

	/**
	 * Cancel pending requests when an order is refunded.
	 *
	 * @param int $order_id Order id.
	 */
	public function on_refunded( int $order_id ): void {
		$this->queue->cancel_by_order( $order_id );
		do_action( 'wrr_order_refunded', $order_id );
	}

	/**
	 * Cancel pending requests when an order is cancelled.
	 *
	 * @param int $order_id Order id.
	 */
	public function on_cancelled( int $order_id ): void {
		$this->queue->cancel_by_order( $order_id );
		do_action( 'wrr_order_cancelled', $order_id );
	}

	/**
	 * Evaluate active campaigns against the order and schedule requests.
	 *
	 * @param \WC_Order $order Order.
	 */
	private function maybe_schedule( $order ): void {
		if ( ! $order ) {
			return;
		}

		$campaigns = $this->campaigns->active();
		foreach ( $campaigns as $campaign ) {
			$this->process_campaign( $order, $campaign );
		}
	}

	/**
	 * Process one campaign against an order.
	 *
	 * @param \WC_Order $order    Order.
	 * @param Campaign  $campaign Campaign.
	 */
	public function process_campaign( $order, Campaign $campaign ): void {
		$config = $campaign->config();

		// Trigger check: order status must be among the campaign's trigger statuses.
		$status = $order->get_status();
		if ( ! in_array( $status, $config->trigger_order_statuses(), true ) ) {
			return;
		}

		// Audience check.
		if ( ! $this->matcher->matches_audience( $order, $campaign ) ) {
			$this->logger->debug(
				'Order does not match campaign audience.',
				array(
					'order_id'    => $order->get_id(),
					'campaign_id' => $campaign->id(),
				)
			);
			return;
		}

		// Eligible products.
		$product_ids = $this->matcher->eligible_products( $order, $campaign );
		if ( empty( $product_ids ) ) {
			$this->logger->debug(
				'No eligible products for campaign.',
				array(
					'order_id'    => $order->get_id(),
					'campaign_id' => $campaign->id(),
				)
			);
			return;
		}

		$context = array(
			'customer_id'    => (int) $order->get_customer_id(),
			'customer_email' => (string) $order->get_billing_email(),
			'customer_name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
		);

		$created = $this->queue->create_requests( $order->get_id(), $campaign, $product_ids, $context );

		/**
		 * Notify after review requests are scheduled for an order.
		 *
		 * @param int      $order_id    Order id.
		 * @param int      $campaign_id Campaign id.
		 * @param int[]    $created     Created request ids.
		 */
		do_action( 'wrr_requests_created', $order->get_id(), $campaign->id(), $created );
	}
}
