<?php
/**
 * Reviews dashboard admin view.
 *
 * @package WooCommerceReviewReminder\Admin\Views
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Admin\Views;

use WooCommerceReviewReminder\Analytics\AnalyticsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReviewsView
 */
final class ReviewsView {

	/**
	 * Render the reviews dashboard.
	 */
	public static function render(): void {
		/** @var AnalyticsRepository $repo */
		$repo = View::service( AnalyticsRepository::class );
		$data = $repo->reviews_dashboard();

		$total_generated = (int) ( $data['total_generated'] ?? 0 );
		$average_rating  = (float) ( $data['average_rating'] ?? 0 );
		$top_products    = $data['top_products'] ?? array();
		$empty_products  = $data['products_without_reviews'] ?? array();

		View::open();
		echo View::page_header(
			__( 'Reviews', 'woocommerce-review-reminder' ),
			__( 'Product reviews captured for your store.', 'woocommerce-review-reminder' )
		);

		echo '<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">';

		self::stat( __( 'Reviews generated', 'woocommerce-review-reminder' ), View::number( $total_generated ), __( 'from your review requests', 'woocommerce-review-reminder' ), 'star' );
		self::stat( __( 'Average rating', 'woocommerce-review-reminder' ), number_format_i18n( $average_rating, 1 ), __( 'across all approved product reviews', 'woocommerce-review-reminder' ), 'star' );
		self::stat( __( 'Top products', 'woocommerce-review-reminder' ), View::number( count( $top_products ) ), __( 'with the most new reviews', 'woocommerce-review-reminder' ), 'trend' );

		echo '</div>';

		echo '<div class="mt-6 grid gap-6 lg:grid-cols-2">';
		self::product_list(
			__( 'Top reviewed products', 'woocommerce-review-reminder' ),
			__( 'Products receiving the most reviews.', 'woocommerce-review-reminder' ),
			$top_products
		);
		self::product_list(
			__( 'Products without reviews', 'woocommerce-review-reminder' ),
			__( 'Good candidates for review campaigns.', 'woocommerce-review-reminder' ),
			$empty_products,
			true
		);
		echo '</div>';

		View::close();
	}

	/**
	 * Stat card.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $sub   Sub text.
	 * @param string $icon  Icon name.
	 */
	private static function stat( string $label, string $value, string $sub, string $icon ): void {
		echo '<div class="wrr-stat">';
		echo '<div class="flex items-center justify-between">';
		echo '<span class="wrr-stat-label">' . esc_html( $label ) . '</span>';
		echo '<span class="wrr-stat-icon">' . Icons::get( $icon, 'h-4 w-4' ) . '</span>';
		echo '</div>';
		echo '<div class="wrr-stat-value">' . esc_html( $value ) . '</div>';
		echo '<div class="wrr-stat-sub">' . esc_html( $sub ) . '</div>';
		echo '</div>';
	}

	/**
	 * Product list card.
	 *
	 * @param string                   $title    Title.
	 * @param string                   $desc     Description.
	 * @param array<int, array<string, mixed>> $items Items.
	 * @param bool                     $no_reviews Show "No reviews" badge style.
	 */
	private static function product_list( string $title, string $desc, array $items, bool $no_reviews = false ): void {
		echo '<div class="wrr-card">';
		echo '<div class="wrr-card-header"><div><h2 class="wrr-card-title">' . esc_html( $title ) . '</h2>';
		echo '<p class="wrr-card-desc">' . esc_html( $desc ) . '</p></div></div>';
		echo '<div class="wrr-card-body">';

		if ( empty( $items ) ) {
			echo '<div class="wrr-empty">';
			echo '<div class="wrr-empty-title">' . ( $no_reviews
				? esc_html__( 'All products have reviews', 'woocommerce-review-reminder' )
				: esc_html__( 'No reviews yet', 'woocommerce-review-reminder' ) ) . '</div>';
			echo '<div class="wrr-empty-desc">' . ( $no_reviews
				? esc_html__( 'Every product has at least one approved review.', 'woocommerce-review-reminder' )
				: esc_html__( 'Reviews from your customers will show up here.', 'woocommerce-review-reminder' ) ) . '</div>';
			echo '</div>';
		} else {
			echo '<ul class="divide-y divide-gray-100">';
			foreach ( $items as $row ) {
				$product_id = (int) $row['product_id'];
				$name       = (string) ( $row['name'] ?? '' );
				echo '<li class="flex items-center justify-between gap-4 py-3">';
				echo '<div class="min-w-0"><p class="truncate text-sm font-medium text-gray-800">' . esc_html( $name ) . '</p>';
				echo '<p class="text-xs text-gray-500">' . esc_html( 'Product #' . $product_id ) . '</p></div>';
				if ( $no_reviews ) {
					echo '<span class="wrr-badge wrr-badge-amber">' . esc_html__( 'No reviews', 'woocommerce-review-reminder' ) . '</span>';
				} else {
					echo '<span class="wrr-badge wrr-badge-green">' . esc_html( (string) ( $row['review_count'] ?? 0 ) ) . ' ' . esc_html__( 'reviews', 'woocommerce-review-reminder' ) . '</span>';
				}
				echo '</li>';
			}
			echo '</ul>';
		}

		echo '</div></div>';
	}
}
