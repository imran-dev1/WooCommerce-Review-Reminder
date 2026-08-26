<?php
/**
 * Email suppression repository.
 *
 * @package WooCommerceReviewReminder\Privacy
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Privacy;

use WooCommerceReviewReminder\Database\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Class SuppressionRepository
 */
final class SuppressionRepository extends Repository {

	/**
	 * Whether an email is suppressed.
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	public function is_suppressed( string $email ): bool {
		$email = strtolower( sanitize_email( $email ) );
		if ( '' === $email ) {
			return false;
		}

		$count = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE email = %s',
				$this->table( 'suppressions' ),
				$email
			)
		);
		return $count > 0;
	}

	/**
	 * Suppress an email address.
	 *
	 * @param string $email  Email address.
	 * @param string $reason Reason (unsubscribed|manual).
	 * @return bool True when newly added.
	 */
	public function add( string $email, string $reason = 'unsubscribed' ): bool {
		$email = strtolower( sanitize_email( $email ) );
		if ( '' === $email ) {
			return false;
		}

		if ( $this->is_suppressed( $email ) ) {
			return false;
		}

		$result = $this->wpdb->insert(
			$this->table( 'suppressions' ),
			array(
				'email'      => $email,
				'reason'     => sanitize_key( $reason ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		do_action( 'wrr_customer_suppressed', $email, $reason );
		return true;
	}

	/**
	 * Remove an email from the suppression list.
	 *
	 * @param string $email Email address.
	 */
	public function remove( string $email ): void {
		$this->wpdb->delete(
			$this->table( 'suppressions' ),
			array( 'email' => strtolower( sanitize_email( $email ) ) ),
			array( '%s' )
		);
	}

	/**
	 * Paginated suppression list.
	 *
	 * @param int $per_page Per page.
	 * @param int $page     Page.
	 * @return array{items: array<int, array<string, mixed>>, total: int, pages: int}
	 */
	public function paginate( int $per_page = 20, int $page = 1 ): array {
		$per_page = max( 1, min( 100, $per_page ) );
		$page     = max( 1, $page );
		$offset   = ( $page - 1 ) * $per_page;

		$total = (int) $this->wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table( 'suppressions' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				'SELECT * FROM %i ORDER BY created_at DESC LIMIT %d OFFSET %d',
				$this->table( 'suppressions' ),
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return array(
			'items' => is_array( $rows ) ? $rows : array(),
			'total' => $total,
			'pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
		);
	}

	/**
	 * Total suppressed emails.
	 *
	 * @return int
	 */
	public function count(): int {
		return (int) $this->wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table( 'suppressions' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
