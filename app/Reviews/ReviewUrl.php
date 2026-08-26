<?php
/**
 * Generates the review CTA destination URLs.
 *
 * @package WooCommerceReviewReminder\Reviews
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Reviews;

use WooCommerceReviewReminder\Queue\ReviewRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReviewUrl
 */
final class ReviewUrl {

	/**
	 * Review URL for a single product.
	 *
	 * Defaults to the product page's reviews section. Extendable via the
	 * `wrr_review_url` filter so themes using a different review implementation
	 * (e.g. third-party plugins) can supply their own URLs.
	 *
	 * @param int               $product_id Product id.
	 * @param ReviewRequest|null $request    Optional request context.
	 * @return string
	 */
	public function for_product( int $product_id, ?ReviewRequest $request = null ): string {
		$url = get_permalink( $product_id );

		if ( false === $url || empty( $url ) ) {
			$url = home_url( '/' );
		}

		$url = trailingslashit( $url ) . '#reviews';

		/**
		 * Filter the review destination URL for a product.
		 *
		 * @param string             $url        Review URL.
		 * @param int                $product_id Product id.
		 * @param ReviewRequest|null $request    Request context.
		 */
		return apply_filters( 'wrr_review_url', $url, $product_id, $request );
	}

	/**
	 * Review URL for a request. Grouped requests use the primary product.
	 *
	 * @param ReviewRequest $request Request.
	 * @return string
	 */
	public function for_request( ReviewRequest $request ): string {
		$product_id = $request->product_id();

		if ( $product_id <= 0 ) {
			// Grouped request: use the first order item.
			$order = wc_get_order( $request->order_id() );
			if ( $order ) {
				foreach ( $order->get_items() as $item ) {
					$product_id = (int) $item->get_product_id();
					if ( $product_id > 0 ) {
						break;
					}
				}
			}
		}

		return $this->for_product( $product_id, $request );
	}
}
