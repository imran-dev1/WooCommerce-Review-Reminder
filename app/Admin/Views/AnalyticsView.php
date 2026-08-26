<?php
/**
 * Analytics admin view.
 *
 * @package WooCommerceReviewReminder\Admin\Views
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Admin\Views;

use WooCommerceReviewReminder\Analytics\AnalyticsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class AnalyticsView
 */
final class AnalyticsView {

	/**
	 * Render the analytics page.
	 */
	public static function render(): void {
		/** @var AnalyticsRepository $repo */
		$repo = View::service( AnalyticsRepository::class );
		$data = $repo->overview();

		View::open();
		echo View::page_header(
			__( 'Analytics', 'woocommerce-review-reminder' ),
			__( 'Track the performance of your review requests.', 'woocommerce-review-reminder' )
		);

		echo '<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">';
		$stats = array(
			array( __( 'Total requests', 'woocommerce-review-reminder' ), View::number( $data['requests_total'] ), __( 'all time', 'woocommerce-review-reminder' ), 'inbox' ),
			array( __( 'Emails sent', 'woocommerce-review-reminder' ), View::number( $data['sent'] ), __( 'all time', 'woocommerce-review-reminder' ), 'mail' ),
			array( __( 'Conversion rate', 'woocommerce-review-reminder' ), View::rate( $data['conversion_rate'] ), __( 'emails → reviews', 'woocommerce-review-reminder' ), 'star' ),
			array( __( 'Avg. time to review', 'woocommerce-review-reminder' ), View::number( $data['avg_time_to_review'] ), __( 'days', 'woocommerce-review-reminder' ), 'clock' ),
		);
		foreach ( $stats as $stat ) {
			echo '<div class="wrr-stat">';
			echo '<div class="flex items-center justify-between"><span class="wrr-stat-label">' . esc_html( $stat[0] ) . '</span>';
			echo '<span class="wrr-stat-icon">' . Icons::get( $stat[3], 'h-4 w-4' ) . '</span></div>';
			echo '<div class="wrr-stat-value">' . esc_html( $stat[1] ) . '</div>';
			echo '<div class="wrr-stat-sub">' . esc_html( $stat[2] ) . '</div>';
			echo '</div>';
		}
		echo '</div>';

		$metrics = array(
			'scheduled' => __( 'Scheduled', 'woocommerce-review-reminder' ),
			'sent'      => __( 'Sent', 'woocommerce-review-reminder' ),
			'opened'    => __( 'Opened', 'woocommerce-review-reminder' ),
			'clicked'   => __( 'Clicked', 'woocommerce-review-reminder' ),
			'reviewed'  => __( 'Reviewed', 'woocommerce-review-reminder' ),
		);
		$ranges  = array(
			'7'  => __( '7 days', 'woocommerce-review-reminder' ),
			'30' => __( '30 days', 'woocommerce-review-reminder' ),
			'90' => __( '90 days', 'woocommerce-review-reminder' ),
		);

		echo '<div class="mt-6 wrr-card" x-data="wrrAnalytics({ metric: \'sent\', range: \'30\' })">';
		echo '<div class="wrr-card-header">';
		echo '<div><h2 class="wrr-card-title">' . esc_html__( 'Performance over time', 'woocommerce-review-reminder' ) . '</h2>';
		echo '<p class="wrr-card-desc">' . esc_html__( 'Daily events across all campaigns.', 'woocommerce-review-reminder' ) . '</p></div>';
		echo '<div class="flex flex-wrap items-center gap-2">';

		echo '<div class="wrr-nav-tabs" role="tablist">';
		foreach ( $metrics as $value => $label ) {
			echo '<button type="button" class="wrr-nav-tab" role="tab" x-on:click="setMetric(\'' . esc_attr( $value ) . '\')" x-bind:aria-selected="metric === \'' . esc_attr( $value ) . '\'">' . esc_html( $label ) . '</button>';
		}
		echo '</div>';

		echo '<div class="wrr-nav-tabs" role="tablist">';
		foreach ( $ranges as $value => $label ) {
			echo '<button type="button" class="wrr-nav-tab" role="tab" x-on:click="setRange(\'' . esc_attr( $value ) . '\')" x-bind:aria-selected="range === \'' . esc_attr( $value ) . '\'">' . esc_html( $label ) . '</button>';
		}
		echo '</div>';

		echo '</div></div>'; // header

		echo '<div class="wrr-card-body">';
		echo '<div class="relative h-72 w-full">';
		echo '<canvas x-ref="chart" class="h-full w-full"></canvas>';
		echo '<div x-show="loading" class="absolute inset-0 flex items-center justify-center bg-white/60 text-sm text-gray-500" x-cloak>'
			. esc_html__( 'Loading…', 'woocommerce-review-reminder' ) . '</div>';
		echo '</div></div></div>';

		// Campaign comparison.
		$comparison = $repo->campaign_comparison();

		echo '<div class="mt-6 wrr-card">';
		echo '<div class="wrr-card-header"><div><h2 class="wrr-card-title">' . esc_html__( 'Campaign comparison', 'woocommerce-review-reminder' ) . '</h2>';
		echo '<p class="wrr-card-desc">' . esc_html__( 'Performance per campaign.', 'woocommerce-review-reminder' ) . '</p></div></div>';

		if ( empty( $comparison ) ) {
			echo '<div class="wrr-card-body"><div class="wrr-empty">';
			echo '<div class="wrr-empty-title">' . esc_html__( 'No campaign data', 'woocommerce-review-reminder' ) . '</div>';
			echo '<div class="wrr-empty-desc">' . esc_html__( 'Create and run a campaign to see comparisons.', 'woocommerce-review-reminder' ) . '</div>';
			echo '</div></div>';
		} else {
			echo '<table class="wrr-table"><thead><tr>';
			echo '<th>' . esc_html__( 'Campaign', 'woocommerce-review-reminder' ) . '</th>';
			echo '<th class="text-right">' . esc_html__( 'Sent', 'woocommerce-review-reminder' ) . '</th>';
			echo '<th class="text-right">' . esc_html__( 'Reviewed', 'woocommerce-review-reminder' ) . '</th>';
			echo '<th class="text-right">' . esc_html__( 'Conv.', 'woocommerce-review-reminder' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $comparison as $row ) {
				echo '<tr>';
				echo '<td class="font-medium text-gray-900">' . esc_html( (string) $row['name'] ) . '</td>';
				echo '<td class="text-right tabular-nums text-gray-700">' . View::number( $row['sent'] ) . '</td>';
				echo '<td class="text-right tabular-nums text-gray-700">' . View::number( $row['reviews'] ) . '</td>';
				echo '<td class="text-right tabular-nums text-gray-700">' . View::rate( $row['conversion_rate'] ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		echo '</div>';

		View::close();
	}
}
