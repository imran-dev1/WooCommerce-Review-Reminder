<?php
/**
 * Sample context used for email previews and test emails.
 *
 * @package WooCommerceReviewReminder\Emails
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Class SampleContext
 */
final class SampleContext {

	/**
	 * Build a realistic sample render context.
	 *
	 * @return array<string, mixed>
	 */
	public static function build(): array {
		$store_name = get_bloginfo( 'name' );
		$store_url  = home_url( '/' );
		$product    = self::sample_product();
		$first      = 'John';

		return array(
			'customer'        => array(
				'first_name' => $first,
				'last_name'  => 'Smith',
				'name'       => 'John Smith',
				'email'      => 'john@example.com',
			),
			'order'           => array(
				'number' => '1045',
				'date'   => date_i18n( get_option( 'date_format' ) ),
			),
			'product'         => $product,
			'products'        => array( $product ),
			'review_url'      => $store_url . '#reviews',
			'unsubscribe_url' => $store_url . '?wrr_unsub=sample',
			'store_name'      => $store_name,
			'store_url'       => $store_url,
			'campaign'        => array(
				'id'   => 0,
				'name' => __( 'Sample campaign', 'woocommerce-review-reminder' ),
			),
		);
	}

	/**
	 * A sample product context.
	 *
	 * @return array<string, string>
	 */
	private static function sample_product(): array {
		$image = '';
		$logo  = get_custom_logo();
		if ( $logo ) {
			preg_match( '/src="([^"]+)"/', $logo, $matches );
			if ( ! empty( $matches[1] ) ) {
				$image = $matches[1];
			}
		}

		return array(
			'name'  => __( 'Premium Wireless Headphones', 'woocommerce-review-reminder' ),
			'url'   => home_url( '/' ),
			'image' => $image,
		);
	}
}
