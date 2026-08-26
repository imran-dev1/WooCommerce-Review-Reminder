<?php
/**
 * Evaluates whether an order matches a campaign's rules.
 *
 * @package WooCommerceReviewReminder\Orders
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Orders;

use WooCommerceReviewReminder\Campaigns\Campaign;

defined( 'ABSPATH' ) || exit;

/**
 * Class OrderMatcher
 */
final class OrderMatcher {

	/**
	 * Whether an order matches the campaign's audience rules.
	 *
	 * @param \WC_Order $order    Order.
	 * @param Campaign  $campaign Campaign.
	 * @return bool
	 */
	public function matches_audience( $order, Campaign $campaign ): bool {
		$config = $campaign->config();

		// Order status.
		if ( ! empty( $config->trigger_order_statuses() ) ) {
			if ( ! in_array( $order->get_status(), $config->trigger_order_statuses(), true ) ) {
				return false;
			}
		}

		// Customer type.
		$customer_id = $order->get_customer_id();
		switch ( $config->customer_type() ) {
			case 'guest':
				if ( $customer_id > 0 ) {
					return false;
				}
				break;
			case 'registered':
				if ( $customer_id <= 0 ) {
					return false;
				}
				break;
		}

		// Customer roles.
		$roles = $config->customer_roles();
		if ( ! empty( $roles ) && $customer_id > 0 ) {
			$user = get_userdata( $customer_id );
			if ( ! $user || empty( array_intersect( $roles, (array) $user->roles ) ) ) {
				return false;
			}
		}

		// Order value.
		$total = (float) $order->get_total();
		if ( null !== $config->min_order_value() && $total < $config->min_order_value() ) {
			return false;
		}
		if ( null !== $config->max_order_value() && $total > $config->max_order_value() ) {
			return false;
		}

		// Payment method.
		$payment_methods = $config->payment_methods();
		if ( ! empty( $payment_methods ) && ! in_array( $order->get_payment_method(), $payment_methods, true ) ) {
			return false;
		}

		// Shipping method.
		$shipping_methods = $config->shipping_methods();
		if ( ! empty( $shipping_methods ) && ! $this->order_uses_shipping_method( $order, $shipping_methods ) ) {
			return false;
		}

		// Customer history.
		if ( 'first_time' === $config->customer_history() && $this->previous_order_count( $order ) > 0 ) {
			return false;
		}
		if ( 'returning' === $config->customer_history() && $this->previous_order_count( $order ) < $config->min_previous_orders() ) {
			return false;
		}

		return true;
	}

	/**
	 * Eligible product ids in the order for a campaign.
	 *
	 * @param \WC_Order $order    Order.
	 * @param Campaign  $campaign Campaign.
	 * @return int[]
	 */
	public function eligible_products( $order, Campaign $campaign ): array {
		$config = $campaign->config();

		$product_ids = array();
		foreach ( $order->get_items() as $item ) {
			$product_id = (int) $item->get_product_id();
			if ( $product_id > 0 ) {
				$product_ids[] = $product_id;
			}
		}
		$product_ids = array_values( array_unique( $product_ids ) );

		if ( empty( $product_ids ) ) {
			return array();
		}

		$excluded_products = $config->exclude_product_ids();
		$excluded_cats     = $config->exclude_category_ids();

		$product_ids = array_filter(
			$product_ids,
			function ( int $product_id ) use ( $excluded_products, $excluded_cats ): bool {
				if ( in_array( $product_id, $excluded_products, true ) ) {
					return false;
				}
				if ( ! empty( $excluded_cats ) ) {
					$cats = wc_get_product_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
					if ( ! empty( array_intersect( $excluded_cats, (array) $cats ) ) ) {
						return false;
					}
				}
				return true;
			}
		);

		switch ( $config->product_include() ) {
			case 'specific':
				$included    = $config->include_product_ids();
				$product_ids = array_filter( $product_ids, static fn( int $id ) => in_array( $id, $included, true ) );
				break;

			case 'categories':
				$categories  = $config->include_category_ids();
				$product_ids = array_filter(
					$product_ids,
					function ( int $product_id ) use ( $categories ): bool {
						$cats = wc_get_product_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
						return ! empty( array_intersect( $categories, (array) $cats ) );
					}
				);
				break;

			case 'tags':
				$tags        = $config->include_tag_ids();
				$product_ids = array_filter(
					$product_ids,
					function ( int $product_id ) use ( $tags ): bool {
						$product_tags = wc_get_product_terms( $product_id, 'product_tag', array( 'fields' => 'ids' ) );
						return ! empty( array_intersect( $tags, (array) $product_tags ) );
					}
				);
				break;
		}

		/**
		 * Filter the eligible product ids for a campaign + order.
		 *
		 * @param int[]     $product_ids Eligible product ids.
		 * @param \WC_Order $order       Order.
		 * @param Campaign  $campaign    Campaign.
		 */
		return array_values( array_unique( apply_filters( 'wrr_eligible_products', array_values( $product_ids ), $order, $campaign ) ) );
	}

	/**
	 * Whether the order uses one of the given shipping methods.
	 *
	 * @param \WC_Order $order Order.
	 * @param string[]  $methods Shipping method ids.
	 * @return bool
	 */
	private function order_uses_shipping_method( $order, array $methods ): bool {
		foreach ( $order->get_shipping_methods() as $shipping ) {
			if ( in_array( $shipping->get_method_id(), $methods, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Number of previous paid orders for the customer (excluding this order).
	 *
	 * @param \WC_Order $order Order.
	 * @return int
	 */
	private function previous_order_count( $order ): int {
		$customer_id = $order->get_customer_id();
		$email       = strtolower( (string) $order->get_billing_email() );

		if ( $customer_id > 0 ) {
			$count = wc_get_customer_order_count( $customer_id );
			$count = max( 0, $count - 1 );
			/**
			 * Filter the previous order count used for audience matching.
			 *
			 * @param int       $count Count.
			 * @param \WC_Order $order Order.
			 */
			return apply_filters( 'wrr_previous_order_count', $count, $order );
		}

		// Guest customers: count orders with the same billing email.
		$ids = wc_get_orders(
			array(
				'billing_email' => $email,
				'limit'         => -1,
				'return'        => 'ids',
			)
		);

		$count = is_array( $ids ) ? max( 0, count( $ids ) - 1 ) : 0;

		/**
		 * Filter the previous order count used for audience matching.
		 *
		 * @param int       $count Count.
		 * @param \WC_Order $order Order.
		 */
		return apply_filters( 'wrr_previous_order_count', $count, $order );
	}
}
