<?php
/**
 * Analytics queries and metrics.
 *
 * @package WooCommerceReviewReminder\Analytics
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Analytics;

use WooCommerceReviewReminder\Database\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Class AnalyticsRepository
 */
final class AnalyticsRepository extends Repository {

	/**
	 * Dashboard overview metrics.
	 *
	 * @param string|null $from Start date.
	 * @param string|null $to   End date.
	 * @return array<string, mixed>
	 */
	public function overview( ?string $from = null, ?string $to = null ): array {
		$events = new EventRepository( $this->schema, $this->logger );

		$scheduled = $events->count_type( 'scheduled', $from, $to );
		$sent      = $events->count_type( 'sent', $from, $to );
		$opened    = $events->count_type( 'opened', $from, $to );
		$clicked   = $events->count_type( 'clicked', $from, $to );
		$reviewed  = $events->count_type( 'reviewed', $from, $to );

		return array(
			'requests_total'     => $this->count_requests( $from, $to ),
			'scheduled'          => $scheduled,
			'sent'               => $sent,
			'opened'             => $opened,
			'clicked'            => $clicked,
			'reviewed'           => $reviewed,
			'open_rate'          => $this->rate( $opened, $sent ),
			'click_rate'         => $this->rate( $clicked, $sent ),
			'conversion_rate'    => $this->rate( $reviewed, $sent ),
			'avg_time_to_review' => $this->average_time_to_review( $from, $to ),
		);
	}

	/**
	 * Time series for a metric type between two dates.
	 *
	 * @param string      $metric Event type.
	 * @param string      $from   Start date.
	 * @param string      $to     End date.
	 * @param int|null    $campaign_id Campaign filter.
	 * @return array<int, array{date: string, count: int}>
	 */
	public function time_series( string $metric, string $from, string $to, ?int $campaign_id = null ): array {
		$events = new EventRepository( $this->schema, $this->logger );
		$counts = $events->daily_counts( $metric, $from, $to, $campaign_id );

		$series   = array();
		$cursor   = new \DateTimeImmutable( $from );
		$end      = new \DateTimeImmutable( $to );
		$interval = new \DateInterval( 'P1D' );

		while ( $cursor <= $end ) {
			$date     = $cursor->format( 'Y-m-d' );
			$series[] = array(
				'date'  => $date,
				'count' => $counts[ $date ] ?? 0,
			);
			$cursor   = $cursor->add( $interval );
		}

		return $series;
	}

	/**
	 * Per-campaign performance comparison.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function campaign_comparison(): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$campaigns = $this->wpdb->get_results(
			'SELECT c.id, c.name, c.status,
				SUM( CASE WHEN r.status = "sent" OR r.status = "reviewed" THEN 1 ELSE 0 END ) AS sent_count,
				SUM( CASE WHEN r.status = "reviewed" THEN 1 ELSE 0 END ) AS reviewed_count
			FROM ' . $this->schema->table( 'campaigns' ) . ' c
			LEFT JOIN ' . $this->schema->table( 'requests' ) . ' r ON r.campaign_id = c.id
			GROUP BY c.id
			ORDER BY sent_count DESC',
			ARRAY_A
		);

		if ( ! is_array( $campaigns ) ) {
			return array();
		}

		return array_map(
			function ( array $row ): array {
				$sent = (int) $row['sent_count'];
				$rev  = (int) $row['reviewed_count'];
				return array(
					'id'              => (int) $row['id'],
					'name'            => $row['name'],
					'status'          => $row['status'],
					'sent'            => $sent,
					'reviews'         => $rev,
					'conversion_rate' => $this->rate( $rev, $sent ),
				);
			},
			$campaigns
		);
	}

	/**
	 * Reviews dashboard data.
	 *
	 * @return array<string, mixed>
	 */
	public function reviews_dashboard(): array {
		global $wpdb;

		$rating_meta = 'rating';

		// Reviews generated since plugin activation (tracked via events).
		$events    = new EventRepository( $this->schema, $this->logger );
		$generated = $events->count_type( 'reviewed' );

		// Average rating of product reviews on the store.
		$avg = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT AVG(meta_value)
				 FROM %i c
				 INNER JOIN %i m ON m.comment_id = c.comment_ID AND m.meta_key = %s
				 WHERE c.comment_type = %s AND c.comment_approved = %s',
				$wpdb->comments,
				$wpdb->commentmeta,
				$rating_meta,
				'review',
				'1'
			)
		);

		// Top reviewed products (by new review comments).
		$top = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT c.comment_post_ID AS product_id, COUNT(*) AS review_count
				 FROM %i c
				 WHERE c.comment_type = %s AND c.comment_approved = %s
				 GROUP BY c.comment_post_ID
				 ORDER BY review_count DESC
				 LIMIT 10',
				$wpdb->comments,
				'review',
				'1'
			),
			ARRAY_A
		);

		// Products without any reviews (limit for performance).
		$no_reviews = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT p.ID AS product_id, p.post_title AS name
				 FROM %i p
				 WHERE p.post_type = %s AND p.post_status = %s
				 AND NOT EXISTS (
					SELECT 1 FROM %i c WHERE c.comment_post_ID = p.ID AND c.comment_type = %s AND c.comment_approved = %s
				 )
				 ORDER BY p.ID DESC
				 LIMIT 10',
				$wpdb->posts,
				'product',
				'publish',
				$wpdb->comments,
				'review',
				'1'
			),
			ARRAY_A
		);

		$top_products = array();
		if ( is_array( $top ) ) {
			foreach ( $top as $row ) {
				$product_id     = (int) $row['product_id'];
				$name           = get_the_title( $product_id );
				$top_products[] = array(
					'product_id'   => $product_id,
					'name'         => $name ? $name : sprintf( '#%d', $product_id ),
					'review_count' => (int) $row['review_count'],
				);
			}
		}

		$empty_products = array();
		if ( is_array( $no_reviews ) ) {
			foreach ( $no_reviews as $row ) {
				$empty_products[] = array(
					'product_id' => (int) $row['product_id'],
					'name'       => $row['name'],
				);
			}
		}

		return array(
			'total_generated'          => $generated,
			'average_rating'           => null !== $avg ? round( (float) $avg, 1 ) : 0.0,
			'top_products'             => $top_products,
			'products_without_reviews' => $empty_products,
		);
	}

	/**
	 * Average time (days) between email sent and review submitted.
	 *
	 * @param string|null $from Start date.
	 * @param string|null $to   End date.
	 * @return float
	 */
	public function average_time_to_review( ?string $from = null, ?string $to = null ): float {
		$where  = array( 'status = %s AND sent_at IS NOT NULL AND review_submitted_at IS NOT NULL' );
		$params = array( 'reviewed' );

		if ( $from ) {
			$where[]  = 'sent_at >= %s';
			$params[] = $from . ' 00:00:00';
		}
		if ( $to ) {
			$where[]  = 'sent_at <= %s';
			$params[] = $to . ' 23:59:59';
		}

		$avg = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT AVG(TIMESTAMPDIFF(HOUR, sent_at, review_submitted_at)) FROM %i WHERE ' . implode( ' AND ', $where ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( $this->schema->table( 'requests' ) ), $params )
			)
		);

		if ( null === $avg ) {
			return 0.0;
		}

		$hours = (float) $avg;
		return round( $hours / 24, 1 );
	}

	/**
	 * Count requests (any status) in a range.
	 *
	 * @param string|null $from Start date.
	 * @param string|null $to   End date.
	 * @return int
	 */
	private function count_requests( ?string $from, ?string $to ): int {
		$where  = array( '1=1' );
		$params = array();

		if ( $from ) {
			$where[]  = 'created_at >= %s';
			$params[] = $from . ' 00:00:00';
		}
		if ( $to ) {
			$where[]  = 'created_at <= %s';
			$params[] = $to . ' 23:59:59';
		}

		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE ' . implode( ' AND ', $where ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( $this->schema->table( 'requests' ) ), $params )
			)
		);
	}

	/**
	 * Percentage helper.
	 *
	 * @param int $part Part.
	 * @param int $total Total.
	 * @return float
	 */
	private function rate( int $part, int $total ): float {
		if ( $total <= 0 ) {
			return 0.0;
		}
		return round( ( $part / $total ) * 100, 1 );
	}
}
