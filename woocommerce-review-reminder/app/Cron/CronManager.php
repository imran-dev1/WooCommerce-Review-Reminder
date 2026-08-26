<?php
/**
 * Cron health utilities.
 *
 * @package WooCommerceReviewReminder\Cron
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Cron;

use WooCommerceReviewReminder\Core\Logger;
use WooCommerceReviewReminder\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class CronManager
 */
final class CronManager {

	/**
	 * Schema instance.
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * CronManager constructor.
	 *
	 * @param Schema $schema Schema.
	 * @param Logger $logger Logger.
	 */
	public function __construct( Schema $schema, Logger $logger ) {
		$this->schema = $schema;
		$this->logger = $logger;
	}

	/**
	 * Clear every plugin cron event (used on deactivation).
	 */
	public function clear_all(): void {
		$scheduler = new \WooCommerceReviewReminder\Queue\QueueScheduler( $this->logger );
		$scheduler->clear();

		$this->clear_cron_event( 'wrr_daily_maintenance' );
		$this->clear_cron_event( 'wrr_retention_purge' );
	}

	/**
	 * Current cron status for the advanced settings screen.
	 *
	 * @return array<string, mixed>
	 */
	public function status(): array {
		$scheduler = new \WooCommerceReviewReminder\Queue\QueueScheduler( $this->logger );

		$events = array(
			'queue'       => $scheduler->has_schedule(),
			'maintenance' => false !== wp_next_scheduled( 'wrr_daily_maintenance' ),
		);

		return array(
			'wp_cron_disabled'       => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'last_run'               => get_option( 'wrr_last_queue_run', '' ),
			'events'                 => $events,
			'server_time'            => current_time( 'mysql' ),
			'using_action_scheduler' => function_exists( 'as_has_scheduled_action' ),
		);
	}

	/**
	 * Clear a single cron event hook.
	 *
	 * @param string $hook Hook name.
	 */
	private function clear_cron_event( string $hook ): void {
		$timestamp = wp_next_scheduled( $hook );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}
	}
}
