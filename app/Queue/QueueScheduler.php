<?php
/**
 * Schedules queue processing using Action Scheduler (preferred) or WP-Cron.
 *
 * @package WooCommerceReviewReminder\Queue
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Queue;

use WooCommerceReviewReminder\Core\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Class QueueScheduler
 */
final class QueueScheduler {

	/**
	 * Action hook name.
	 *
	 * @var string
	 */
	public const ACTION = 'wrr_process_queue';

	/**
	 * Hook used to process a single request (Action Scheduler).
	 *
	 * @var string
	 */
	public const SINGLE_ACTION = 'wrr_process_request';

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * QueueScheduler constructor.
	 *
	 * @param Logger $logger Logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Register the recurring schedule.
	 */
	public function register(): void {
		add_action( self::ACTION, array( $this, 'run_queue' ) );
		add_action( self::SINGLE_ACTION, array( $this, 'run_single' ), 10, 1 );
		add_filter( 'cron_schedules', array( $this, 'add_schedule' ) );

		// Keep the recurring schedule alive.
		add_action( 'init', array( $this, 'ensure_scheduled' ), 20 );
	}

	/**
	 * Ensure the recurring queue job is scheduled.
	 */
	public function ensure_scheduled(): void {
		if ( ! $this->has_schedule() ) {
			$this->schedule();
		}
	}

	/**
	 * Whether a recurring queue job is scheduled.
	 *
	 * @return bool
	 */
	public function has_schedule(): bool {
		if ( function_exists( 'as_has_scheduled_action' ) ) {
			return as_has_scheduled_action( self::ACTION );
		}
		return false !== wp_next_scheduled( self::ACTION );
	}

	/**
	 * Schedule the recurring queue job.
	 */
	public function schedule(): void {
		if ( $this->has_schedule() ) {
			return;
		}

		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			as_schedule_recurring_action( time() + 60, MINUTE_IN_SECONDS, self::ACTION );
			$this->logger->debug( 'Queue scheduled via Action Scheduler.' );
			return;
		}

		if ( false === wp_next_scheduled( self::ACTION ) ) {
			wp_schedule_event( time() + 60, 'wrr_every_minute', self::ACTION );
			$this->logger->debug( 'Queue scheduled via WP-Cron.' );
		}
	}

	/**
	 * Clear the recurring queue job.
	 */
	public function clear(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ACTION );
			as_unschedule_all_actions( self::SINGLE_ACTION );
		}

		$timestamp = wp_next_scheduled( self::ACTION );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::ACTION );
		}
	}

	/**
	 * Process the queue (called by the recurring action).
	 */
	public function run_queue(): void {
		if ( ! class_exists( '\WooCommerceReviewReminder\Core\Plugin' ) ) {
			return;
		}
		$processor = \WooCommerceReviewReminder\Core\Plugin::instance()->get( QueueProcessor::class );
		$processor->process_due();
	}

	/**
	 * Process a single request (used for on-demand sends).
	 *
	 * @param int $request_id Request id.
	 */
	public function run_single( int $request_id ): void {
		if ( ! class_exists( '\WooCommerceReviewReminder\Core\Plugin' ) ) {
			return;
		}
		$plugin   = \WooCommerceReviewReminder\Core\Plugin::instance();
		$requests = $plugin->get( RequestRepository::class );
		$request  = $requests->find( absint( $request_id ) );
		if ( null === $request ) {
			return;
		}
		$plugin->get( QueueProcessor::class )->process_one( $request );
	}

	/**
	 * Add the plugin's cron schedule interval.
	 *
	 * @param array<string, array{interval: int, display: string}> $schedules Schedules.
	 * @return array<string, array{interval: int, display: string}>
	 */
	public function add_schedule( array $schedules ): array {
		$schedules['wrr_every_minute'] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Every minute (WooCommerce Review Reminder)', 'woocommerce-review-reminder' ),
		);
		return $schedules;
	}
}
