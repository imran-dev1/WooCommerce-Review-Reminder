<?php
/**
 * Dashboard / overview admin view.
 *
 * @package WooCommerceReviewReminder\Admin\Views
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Admin\Views;

use WooCommerceReviewReminder\Admin\AdminPage;
use WooCommerceReviewReminder\Analytics\AnalyticsRepository;
use WooCommerceReviewReminder\Analytics\EventRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class OverviewView
 */
final class OverviewView {

	/**
	 * Render the overview page.
	 */
	public static function render(): void {
		/** @var AnalyticsRepository $repo */
		$repo = View::service( AnalyticsRepository::class );
		$data = $repo->overview();

		/** @var EventRepository $events */
		$events = View::service( EventRepository::class );

		View::open();
		echo View::flash_notice();
		echo View::page_header(
			__( 'Dashboard', 'woocommerce-review-reminder' ),
			__( 'Your review automation at a glance.', 'woocommerce-review-reminder' ),
			'<a class="wrr-btn wrr-btn-primary" href="' . esc_url( View::page_url( AdminPage::CAMPAIGN_EDIT ) ) . '">'
				. Icons::get( 'plus' ) . esc_html__( 'New campaign', 'woocommerce-review-reminder' ) . '</a>'
		);

		$stats = array(
			array(
				'label' => __( 'Total requests', 'woocommerce-review-reminder' ),
				'value' => View::number( $data['requests_total'] ),
				'sub'   => sprintf( /* translators: %d: count. */ __( '%s scheduled', 'woocommerce-review-reminder' ), View::number( $data['scheduled'] ) ),
				'icon'  => 'inbox',
			),
			array(
				'label' => __( 'Emails sent', 'woocommerce-review-reminder' ),
				'value' => View::number( $data['sent'] ),
				'sub'   => sprintf( /* translators: %d: count. */ __( '%s opened', 'woocommerce-review-reminder' ), View::number( $data['opened'] ) ),
				'icon'  => 'mail',
			),
			array(
				'label' => __( 'Conversion rate', 'woocommerce-review-reminder' ),
				'value' => View::rate( $data['conversion_rate'] ),
				'sub'   => sprintf( /* translators: %d: count. */ __( '%s reviews received', 'woocommerce-review-reminder' ), View::number( $data['reviewed'] ) ),
				'icon'  => 'star',
			),
			array(
				'label' => __( 'Open rate', 'woocommerce-review-reminder' ),
				'value' => View::rate( $data['open_rate'] ),
				'sub'   => sprintf( /* translators: %s: rate. */ __( '%s click rate', 'woocommerce-review-reminder' ), View::rate( $data['click_rate'] ) ),
				'icon'  => 'pointer',
			),
		);

		echo '<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">';
		foreach ( $stats as $stat ) {
			echo '<div class="wrr-stat">';
			echo '<div class="flex items-center justify-between">';
			echo '<span class="wrr-stat-label">' . esc_html( $stat['label'] ) . '</span>';
			echo '<span class="wrr-stat-icon">' . Icons::get( $stat['icon'], 'h-4 w-4' ) . '</span>';
			echo '</div>';
			echo '<div class="wrr-stat-value">' . esc_html( $stat['value'] ) . '</div>';
			echo '<div class="wrr-stat-sub">' . esc_html( $stat['sub'] ) . '</div>';
			echo '</div>';
		}
		echo '</div>';

		echo '<div class="mt-6 grid gap-6 lg:grid-cols-5">';
		self::activity_feed( $events );
		self::getting_started();
		echo '</div>';

		View::close();
	}

	/**
	 * Recent activity feed card.
	 *
	 * @param EventRepository $events Events repo.
	 */
	private static function activity_feed( EventRepository $events ): void {
		$rows = $events->recent( 10 );

		echo '<div class="wrr-card lg:col-span-3">';
		echo '<div class="wrr-card-header"><div><h2 class="wrr-card-title">' . esc_html__( 'Recent activity', 'woocommerce-review-reminder' ) . '</h2>';
		echo '<p class="wrr-card-desc">' . esc_html__( 'Latest review-request events across all campaigns.', 'woocommerce-review-reminder' ) . '</p></div></div>';
		echo '<div class="wrr-card-body">';

		if ( empty( $rows ) ) {
			echo '<div class="wrr-empty">';
			echo '<span class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-400">' . Icons::get( 'clock', 'h-5 w-5' ) . '</span>';
			echo '<div class="wrr-empty-title">' . esc_html__( 'No activity yet', 'woocommerce-review-reminder' ) . '</div>';
			echo '<div class="wrr-empty-desc">' . esc_html__( 'Review-request activity will appear here once your campaigns start sending emails.', 'woocommerce-review-reminder' ) . '</div>';
			echo '</div>';
		} else {
			echo '<ul class="divide-y divide-gray-100">';
			foreach ( $rows as $row ) {
				$type    = (string) $row['event_type'];
				$label   = ucwords( str_replace( '_', ' ', $type ) );
				$icon    = in_array( $type, array( 'reviewed', 'clicked' ), true ) ? $type : 'mail';
				$context = array();
				if ( ! empty( $row['campaign_id'] ) ) {
					$context[] = 'Campaign #' . (int) $row['campaign_id'];
				}
				if ( ! empty( $row['order_id'] ) ) {
					$context[] = 'Order #' . (int) $row['order_id'];
				}

				echo '<li class="flex items-center gap-3 py-3">';
				echo '<span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500">' . Icons::get( $icon, 'h-3.5 w-3.5' ) . '</span>';
				echo '<div class="min-w-0 flex-1">';
				echo '<p class="truncate text-sm"><span class="font-medium capitalize">' . esc_html( $label ) . '</span>';
				foreach ( $context as $bit ) {
					echo '<span class="text-gray-500"> · ' . esc_html( $bit ) . '</span>';
				}
				echo '</p>';
				if ( ! empty( $row['customer_email'] ) ) {
					echo '<p class="truncate text-xs text-gray-500">' . esc_html( (string) $row['customer_email'] ) . '</p>';
				}
				echo '</div>';
				echo '<span class="shrink-0 text-xs text-gray-500">' . View::human_time( $row['created_at'] ?? null ) . '</span>';
				echo '</li>';
			}
			echo '</ul>';
		}

		echo '</div></div>';
	}

	/**
	 * Getting started quick links card.
	 */
	private static function getting_started(): void {
		$links = array(
			array(
				'url'   => View::page_url( AdminPage::CAMPAIGN_EDIT ),
				'label' => __( 'Create a campaign', 'woocommerce-review-reminder' ),
			),
			array(
				'url'   => View::page_url( AdminPage::TEMPLATES ),
				'label' => __( 'Customize email templates', 'woocommerce-review-reminder' ),
			),
			array(
				'url'   => View::page_url( AdminPage::SETTINGS ),
				'label' => __( 'Configure email & privacy', 'woocommerce-review-reminder' ),
			),
		);

		echo '<div class="wrr-card lg:col-span-2">';
		echo '<div class="wrr-card-header"><div><h2 class="wrr-card-title">' . esc_html__( 'Getting started', 'woocommerce-review-reminder' ) . '</h2>';
		echo '<p class="wrr-card-desc">' . esc_html__( 'Set up your first review automation in minutes.', 'woocommerce-review-reminder' ) . '</p></div></div>';
		echo '<div class="wrr-card-body space-y-2">';
		foreach ( $links as $link ) {
			echo '<a href="' . esc_url( $link['url'] ) . '" class="flex items-center justify-between rounded-lg border border-gray-200 p-3 text-sm transition-colors hover:bg-gray-50">';
			echo '<span class="font-medium text-gray-800">' . esc_html( $link['label'] ) . '</span>';
			echo '<span class="text-gray-400">' . Icons::get( 'arrow-right', 'h-4 w-4' ) . '</span>';
			echo '</a>';
		}
		echo '</div></div>';
	}
}
