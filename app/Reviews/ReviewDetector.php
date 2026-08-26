<?php
/**
 * Review detection and tracking.
 *
 * @package WooCommerceReviewReminder\Reviews
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Reviews;

use WooCommerceReviewReminder\Database\Repository;
use WooCommerceReviewReminder\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReviewDetector
 */
final class ReviewDetector extends Repository {

	/**
	 * Whether a customer has reviewed a product.
	 *
	 * Uses WooCommerce's review comments. Registered customers match on user id,
	 * guest customers match on the billing email (which is the review author email
	 * for WooCommerce reviews placed through an order).
	 *
	 * @param int         $product_id Product id.
	 * @param int|null    $user_id    WP user id (registered customers).
	 * @param string|null $email      Customer email (used for guests).
	 * @return bool
	 */
	public function has_review( int $product_id, ?int $user_id = null, ?string $email = null ): bool {
		if ( $product_id <= 0 ) {
			return false;
		}

		$conditions = array();
		$params     = array();

		if ( $user_id && $user_id > 0 ) {
			$conditions[] = '(c.user_id = %d)';
			$params[]     = $user_id;
		}

		if ( $email && is_email( $email ) ) {
			$conditions[] = '(LOWER(c.comment_author_email) = LOWER(%s))';
			$params[]     = $email;
		}

		if ( empty( $conditions ) ) {
			return false;
		}

		$where = implode( ' OR ', $conditions );

		$sql = $this->wpdb->prepare(
			'SELECT COUNT(*) FROM %i c WHERE c.comment_post_ID = %d AND c.comment_type = %s AND c.comment_approved = %s AND (' . $where . ')', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array_merge(
				array( $this->wpdb->comments, $product_id, 'review', '1' ),
				$params
			)
		);

		return (int) $this->wpdb->get_var( $sql ) > 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Whether the customer reviewed any product in an order.
	 *
	 * @param int         $order_id Order id.
	 * @param int|null    $user_id  WP user id.
	 * @param string|null $email    Customer email.
	 * @return bool
	 */
	public function has_review_for_order( int $order_id, ?int $user_id = null, ?string $email = null ): bool {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		foreach ( $order->get_items() as $item ) {
			$product_id = (int) $item->get_product_id();
			if ( $this->has_review( $product_id, $user_id, $email ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Filter an array of product ids down to those the customer has NOT reviewed.
	 *
	 * @param int[]       $product_ids Product ids.
	 * @param int|null    $user_id     WP user id.
	 * @param string|null $email       Customer email.
	 * @return int[]
	 */
	public function unreviewed_products( array $product_ids, ?int $user_id = null, ?string $email = null ): array {
		return array_values(
			array_filter(
				array_map( 'absint', $product_ids ),
				function ( int $product_id ) use ( $user_id, $email ): bool {
					return $product_id > 0 && ! $this->has_review( $product_id, $user_id, $email );
				}
			)
		);
	}
}
