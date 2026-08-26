<?php
/**
 * Review request repository.
 *
 * @package WooCommerceReviewReminder\Queue
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Queue;

use WooCommerceReviewReminder\Database\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Class RequestRepository
 */
final class RequestRepository extends Repository {

	/**
	 * Create a review request.
	 *
	 * @param array<string, mixed> $data Request data.
	 * @return int New request id or 0.
	 */
	public function create( array $data ): int {
		$now = current_time( 'mysql' );

		$result = $this->wpdb->insert(
			$this->table( 'requests' ),
			array(
				'campaign_id'         => absint( $data['campaign_id'] ?? 0 ),
				'order_id'            => absint( $data['order_id'] ?? 0 ),
				'product_id'          => absint( $data['product_id'] ?? 0 ),
				'customer_id'         => absint( $data['customer_id'] ?? 0 ),
				'customer_email'      => sanitize_email( $data['customer_email'] ?? '' ),
				'customer_name'       => sanitize_text_field( $data['customer_name'] ?? '' ),
				'status'              => sanitize_key( $data['status'] ?? ReviewRequest::STATUS_SCHEDULED ),
				'request_type'        => sanitize_key( $data['request_type'] ?? 'initial' ),
				'followup_number'     => absint( $data['followup_number'] ?? 0 ),
				'scheduled_at'        => (string) ( $data['scheduled_at'] ?? $now ),
				'sent_at'             => isset( $data['sent_at'] ) ? (string) $data['sent_at'] : null,
				'opened_at'           => isset( $data['opened_at'] ) ? (string) $data['opened_at'] : null,
				'clicked_at'          => isset( $data['clicked_at'] ) ? (string) $data['clicked_at'] : null,
				'review_submitted_at' => isset( $data['review_submitted_at'] ) ? (string) $data['review_submitted_at'] : null,
				'attempts'            => absint( $data['attempts'] ?? 0 ),
				'max_attempts'        => absint( $data['max_attempts'] ?? 3 ),
				'last_error'          => isset( $data['last_error'] ) ? wp_kses_post( (string) $data['last_error'] ) : null,
				'email_subject'       => isset( $data['email_subject'] ) ? sanitize_text_field( (string) $data['email_subject'] ) : null,
				'email_body'          => isset( $data['email_body'] ) ? (string) $data['email_body'] : null,
				'token'               => isset( $data['token'] ) ? substr( sanitize_text_field( (string) $data['token'] ), 0, 64 ) : null,
				'source'              => sanitize_key( $data['source'] ?? 'order' ),
				'created_at'          => $now,
				'updated_at'          => $now,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			// Likely a duplicate (unique key) — do not treat as a hard error.
			$this->logger->debug( 'Request insert returned false.', array( 'db_error' => $this->wpdb->last_error ) );
			return 0;
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Find a request by id.
	 *
	 * @param int $id Request id.
	 * @return ReviewRequest|null
	 */
	public function find( int $id ): ?ReviewRequest {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d LIMIT 1',
				$this->table( 'requests' ),
				$id
			),
			ARRAY_A
		);
		return is_array( $row ) ? new ReviewRequest( $row ) : null;
	}

	/**
	 * Find a request by tracking token.
	 *
	 * @param string $token Token.
	 * @return ReviewRequest|null
	 */
	public function find_by_token( string $token ): ?ReviewRequest {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE token = %s LIMIT 1',
				$this->table( 'requests' ),
				substr( sanitize_text_field( $token ), 0, 64 )
			),
			ARRAY_A
		);
		return is_array( $row ) ? new ReviewRequest( $row ) : null;
	}

	/**
	 * Whether a request already exists for a given order/product/campaign.
	 *
	 * @param int $order_id   Order id.
	 * @param int $product_id Product id (0 = grouped).
	 * @param int $campaign_id Campaign id.
	 * @return bool
	 */
	public function exists( int $order_id, int $product_id, int $campaign_id ): bool {
		$count = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE order_id = %d AND product_id = %d AND campaign_id = %d',
				$this->table( 'requests' ),
				$order_id,
				$product_id,
				$campaign_id
			)
		);
		return $count > 0;
	}

	/**
	 * Requests scheduled to be processed before a cutoff, newest first.
	 *
	 * @param int $limit Maximum number to fetch.
	 * @return ReviewRequest[]
	 */
	public function find_due( int $limit = 50 ): array {
		$now = current_time( 'mysql' );
		return $this->query(
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE status = %s AND scheduled_at <= %s ORDER BY scheduled_at ASC LIMIT %d',
				$this->table( 'requests' ),
				ReviewRequest::STATUS_SCHEDULED,
				$now,
				$limit
			)
		);
	}

	/**
	 * Pending requests for an order (any active campaign).
	 *
	 * @param int $order_id Order id.
	 * @return ReviewRequest[]
	 */
	public function pending_for_order( int $order_id ): array {
		return $this->query(
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE order_id = %d AND status IN (%s, %s) ORDER BY followup_number ASC',
				$this->table( 'requests' ),
				$order_id,
				ReviewRequest::STATUS_SCHEDULED,
				ReviewRequest::STATUS_PROCESSING
			)
		);
	}

	/**
	 * Pending follow-ups for an order + product + campaign.
	 *
	 * @param int $order_id Order id.
	 * @param int $product_id Product id (0 for grouped).
	 * @param int $campaign_id Campaign id.
	 * @return ReviewRequest|null The latest pending request, if any.
	 */
	public function latest_pending( int $order_id, int $product_id, int $campaign_id ): ?ReviewRequest {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE order_id = %d AND product_id = %d AND campaign_id = %d AND status IN (%s, %s) ORDER BY followup_number DESC LIMIT 1',
				$this->table( 'requests' ),
				$order_id,
				$product_id,
				$campaign_id,
				ReviewRequest::STATUS_SCHEDULED,
				ReviewRequest::STATUS_PROCESSING
			),
			ARRAY_A
		);
		return is_array( $row ) ? new ReviewRequest( $row ) : null;
	}

	/**
	 * Paginated request list with optional filters.
	 *
	 * @param array<string, mixed> $filters Filters: status, campaign_id, search, per_page, page.
	 * @return array{items: ReviewRequest[], total: int}
	 */
	public function paginate( array $filters = array() ): array {
		$where    = array( '1=1' );
		$params   = array();
		$per_page = max( 1, min( 100, absint( $filters['per_page'] ?? 20 ) ) );
		$page     = max( 1, absint( $filters['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( (string) $filters['status'] );
		}
		if ( ! empty( $filters['campaign_id'] ) ) {
			$where[]  = 'campaign_id = %d';
			$params[] = absint( $filters['campaign_id'] );
		}
		if ( ! empty( $filters['order_id'] ) ) {
			$where[]  = 'order_id = %d';
			$params[] = absint( $filters['order_id'] );
		}
		if ( ! empty( $filters['search'] ) ) {
			$like     = '%' . $this->wpdb->esc_like( (string) $filters['search'] ) . '%';
			$where[]  = '(customer_email LIKE %s OR customer_name LIKE %s OR order_id LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );

		$total = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE ' . $where_sql, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( $this->table( 'requests' ) ), $params )
			)
		);

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE ' . $where_sql . ' ORDER BY scheduled_at DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( $this->table( 'requests' ) ), $params, array( $per_page, $offset ) )
			),
			ARRAY_A
		);

		$items = is_array( $rows ) ? array_map(
			static function ( array $row ): ReviewRequest {
				return new ReviewRequest( $row );
			},
			$rows
		) : array();

		return array(
			'items' => $items,
			'total' => $total,
			'page'  => $page,
			'pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
		);
	}

	/**
	 * Update request fields.
	 *
	 * @param int                  $id   Request id.
	 * @param array<string, mixed> $data Fields to update.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		$fields = array();
		$format = array();

		$string_fields = array( 'customer_email', 'customer_name', 'status', 'request_type', 'last_error', 'email_subject', 'email_body', 'token', 'source' );
		$date_fields   = array( 'scheduled_at', 'sent_at', 'opened_at', 'clicked_at', 'review_submitted_at' );
		$int_fields    = array( 'campaign_id', 'order_id', 'product_id', 'customer_id', 'followup_number', 'attempts', 'max_attempts' );

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $string_fields, true ) ) {
				$fields[ $key ] = ( 'email_body' === $key ) ? (string) $value : sanitize_text_field( (string) $value );
				$format[]       = '%s';
			} elseif ( in_array( $key, $date_fields, true ) ) {
				$fields[ $key ] = ( null === $value ) ? null : (string) $value;
				$format[]       = ( null === $value ) ? '%s' : '%s';
			} elseif ( in_array( $key, $int_fields, true ) ) {
				$fields[ $key ] = absint( $value );
				$format[]       = '%d';
			}
		}

		if ( empty( $fields ) ) {
			return true;
		}

		$fields['updated_at'] = current_time( 'mysql' );
		$format[]             = '%s';

		$result = $this->wpdb->update(
			$this->table( 'requests' ),
			$fields,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);

		if ( false === $result ) {
			$this->logger->error(
				'Failed to update request.',
				array(
					'id'       => $id,
					'db_error' => $this->wpdb->last_error,
				)
			);
			return false;
		}

		return true;
	}

	/**
	 * Set status with timestamp bookkeeping.
	 *
	 * @param int    $id     Request id.
	 * @param string $status Status.
	 * @return bool
	 */
	public function set_status( int $id, string $status ): bool {
		return $this->update( $id, array( 'status' => sanitize_key( $status ) ) );
	}

	/**
	 * Cancel pending requests by campaign id.
	 *
	 * @param int $campaign_id Campaign id.
	 */
	public function cancel_by_campaign( int $campaign_id ): void {
		$this->wpdb->query(
			$this->wpdb->prepare(
				'UPDATE %i SET status = %s, updated_at = %s WHERE campaign_id = %d AND status IN (%s, %s)',
				$this->table( 'requests' ),
				ReviewRequest::STATUS_CANCELLED,
				current_time( 'mysql' ),
				$campaign_id,
				ReviewRequest::STATUS_SCHEDULED,
				ReviewRequest::STATUS_PROCESSING
			)
		);
	}

	/**
	 * Cancel pending requests by order id.
	 *
	 * @param int $order_id Order id.
	 */
	public function cancel_by_order( int $order_id ): void {
		$this->wpdb->query(
			$this->wpdb->prepare(
				'UPDATE %i SET status = %s, updated_at = %s WHERE order_id = %d AND status IN (%s, %s)',
				$this->table( 'requests' ),
				ReviewRequest::STATUS_CANCELLED,
				current_time( 'mysql' ),
				$order_id,
				ReviewRequest::STATUS_SCHEDULED,
				ReviewRequest::STATUS_PROCESSING
			)
		);
	}

	/**
	 * Mark requests as reviewed when the customer submits a review.
	 *
	 * @param int $order_id   Order id.
	 * @param int $product_id Product id (0 = grouped requests too).
	 */
	public function mark_reviewed( int $order_id, int $product_id ): void {
		$now = current_time( 'mysql' );
		$this->wpdb->query(
			$this->wpdb->prepare(
				'UPDATE %i SET status = %s, review_submitted_at = %s, updated_at = %s WHERE order_id = %d AND (product_id = %d OR product_id = 0) AND status IN (%s, %s, %s)',
				$this->table( 'requests' ),
				ReviewRequest::STATUS_REVIEWED,
				$now,
				$now,
				$order_id,
				$product_id,
				ReviewRequest::STATUS_SCHEDULED,
				ReviewRequest::STATUS_PROCESSING,
				ReviewRequest::STATUS_SENT
			)
		);
	}

	/**
	 * Count requests by status.
	 *
	 * @param string|null $status Status or null for all.
	 * @return int
	 */
	public function count( ?string $status = null ): int {
		if ( null === $status ) {
			return (int) $this->wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table( 'requests' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE status = %s',
				$this->table( 'requests' ),
				sanitize_key( $status )
			)
		);
	}

	/**
	 * Query rows into ReviewRequest entities.
	 *
	 * @param string $sql SQL.
	 * @return ReviewRequest[]
	 */
	private function query( string $sql ): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		return array_map(
			static function ( array $row ): ReviewRequest {
				return new ReviewRequest( $row );
			},
			$rows
		);
	}
}
