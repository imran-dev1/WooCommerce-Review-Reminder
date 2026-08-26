<?php
/**
 * Event repository. Events power the activity timeline and analytics.
 *
 * @package WooCommerceReviewReminder\Analytics
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Analytics;

use WooCommerceReviewReminder\Database\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Class EventRepository
 */
final class EventRepository extends Repository {

	/**
	 * Record an event.
	 *
	 * @param string              $event_type Event type.
	 * @param array<string, mixed> $data       Event data (request_id, campaign_id, order_id, customer_email, meta).
	 * @return int Event id.
	 */
	public function record( string $event_type, array $data = array() ): int {
		$result = $this->wpdb->insert(
			$this->table( 'events' ),
			array(
				'request_id'     => absint( $data['request_id'] ?? 0 ),
				'campaign_id'    => absint( $data['campaign_id'] ?? 0 ),
				'order_id'       => absint( $data['order_id'] ?? 0 ),
				'customer_email' => isset( $data['customer_email'] ) ? sanitize_email( (string) $data['customer_email'] ) : null,
				'event_type'     => sanitize_key( $event_type ),
				'meta'           => isset( $data['meta'] ) ? (string) wp_json_encode( $data['meta'] ) : null,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return false === $result ? 0 : (int) $this->wpdb->insert_id;
	}

	/**
	 * Recent events for the dashboard activity feed.
	 *
	 * @param int $limit Number of events.
	 * @return array<int, array<string, mixed>>
	 */
	public function recent( int $limit = 15 ): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				'SELECT * FROM %i ORDER BY created_at DESC LIMIT %d',
				$this->table( 'events' ),
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Timeline events for a single request.
	 *
	 * @param int $request_id Request id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_request( int $request_id ): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE request_id = %d ORDER BY created_at ASC',
				$this->table( 'events' ),
				$request_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count events of a type within a date range.
	 *
	 * @param string      $event_type Event type.
	 * @param string|null $from       Start date (Y-m-d), inclusive.
	 * @param string|null $to         End date (Y-m-d), inclusive.
	 * @param int|null    $campaign_id Campaign filter.
	 * @return int
	 */
	public function count_type( string $event_type, ?string $from = null, ?string $to = null, ?int $campaign_id = null ): int {
		$where  = array( 'event_type = %s' );
		$params = array( sanitize_key( $event_type ) );

		if ( $from ) {
			$where[]  = 'created_at >= %s';
			$params[] = $from . ' 00:00:00';
		}
		if ( $to ) {
			$where[]  = 'created_at <= %s';
			$params[] = $to . ' 23:59:59';
		}
		if ( $campaign_id ) {
			$where[]  = 'campaign_id = %d';
			$params[] = $campaign_id;
		}

		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE ' . implode( ' AND ', $where ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( $this->table( 'events' ) ), $params )
			)
		);
	}

	/**
	 * Daily event counts within a range.
	 *
	 * @param string      $event_type Event type.
	 * @param string      $from       Start date (Y-m-d).
	 * @param string      $to         End date (Y-m-d).
	 * @param int|null    $campaign_id Campaign filter.
	 * @return array<string, int> Keyed by date.
	 */
	public function daily_counts( string $event_type, string $from, string $to, ?int $campaign_id = null ): array {
		$where    = array( 'event_type = %s' );
		$params   = array( sanitize_key( $event_type ) );
		$where[]  = 'created_at >= %s';
		$params[] = $from . ' 00:00:00';
		$where[]  = 'created_at <= %s';
		$params[] = $to . ' 23:59:59';
		if ( $campaign_id ) {
			$where[]  = 'campaign_id = %d';
			$params[] = $campaign_id;
		}

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				'SELECT DATE(created_at) AS d, COUNT(*) AS c FROM %i WHERE ' . implode( ' AND ', $where ) . ' GROUP BY DATE(created_at) ORDER BY d ASC', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( $this->table( 'events' ) ), $params )
			),
			ARRAY_A
		);

		$result = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$result[ $row['d'] ] = (int) $row['c'];
			}
		}
		return $result;
	}
}
