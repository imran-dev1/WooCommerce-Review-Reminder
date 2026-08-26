<?php
/**
 * Single review request detail view.
 *
 * @package WooCommerceReviewReminder\Admin\Views
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Admin\Views;

use WooCommerceReviewReminder\Admin\AdminPage;
use WooCommerceReviewReminder\Analytics\EventRepository;
use WooCommerceReviewReminder\Queue\RequestRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class RequestDetailView
 */
final class RequestDetailView {

	/**
	 * Event type => label map.
	 *
	 * @return array<string, string>
	 */
	private static function event_labels(): array {
		return array(
			'scheduled'    => __( 'Request scheduled', 'woocommerce-review-reminder' ),
			'processing'   => __( 'Email send started', 'woocommerce-review-reminder' ),
			'sent'         => __( 'Email sent', 'woocommerce-review-reminder' ),
			'failed'       => __( 'Email failed', 'woocommerce-review-reminder' ),
			'opened'       => __( 'Email opened', 'woocommerce-review-reminder' ),
			'clicked'      => __( 'Review link clicked', 'woocommerce-review-reminder' ),
			'reviewed'     => __( 'Review submitted', 'woocommerce-review-reminder' ),
			'unsubscribed' => __( 'Customer unsubscribed', 'woocommerce-review-reminder' ),
			'cancelled'    => __( 'Request cancelled', 'woocommerce-review-reminder' ),
			'suppressed'   => __( 'Request suppressed', 'woocommerce-review-reminder' ),
		);
	}

	/**
	 * Render request detail.
	 */
	public static function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only id param.
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		/** @var RequestRepository $repo */
		$repo = View::service( RequestRepository::class );
		$item = $id > 0 ? $repo->find( $id ) : null;

		if ( null === $item ) {
			self::not_found();
			return;
		}

		$data = $item->to_array();

		$order   = function_exists( 'wc_get_order' ) ? wc_get_order( $item->order_id() ) : false;
		$product = $item->product_id() > 0 && function_exists( 'wc_get_product' ) ? wc_get_product( $item->product_id() ) : null;

		View::open();

		$actions = '';
		if ( $item->is_pending() ) {
			$actions .= '<div x-data="wrrRequests()" class="inline-flex items-center gap-2">'
				. '<button type="button" class="wrr-btn wrr-btn-danger" x-on:click="askCancel(' . (int) $id . ')">' . Icons::get( 'x' ) . esc_html__( 'Cancel request', 'woocommerce-review-reminder' ) . '</button>'
				. '<div x-data="wrrConfirm()" x-on:wrr-confirm-ask.window="ask($event.detail)">' . View::confirm_modal() . '</div>'
				. '</div>';
		}

		echo View::page_header(
			sprintf( /* translators: %d: request id. */ __( 'Request #%d', 'woocommerce-review-reminder' ), $id ),
			sprintf( /* translators: 1: email, 2: order id. */ __( 'For %1$s · Order #%2$d', 'woocommerce-review-reminder' ), esc_html( (string) $data['customer_email'] ), $item->order_id() ),
			$actions . '<a class="wrr-btn wrr-btn-secondary" href="' . esc_url( View::page_url( AdminPage::REQUESTS ) ) . '">'
				. Icons::get( 'arrow-left' ) . esc_html__( 'Back', 'woocommerce-review-reminder' ) . '</a>',
			null
		);

		echo '<div class="grid gap-6 lg:grid-cols-2">';

		// Details card.
		echo '<div class="wrr-card">';
		echo '<div class="wrr-card-header"><div><h2 class="wrr-card-title">' . esc_html__( 'Details', 'woocommerce-review-reminder' ) . '</h2>';
		echo '<p class="wrr-card-desc">' . esc_html__( 'Request metadata.', 'woocommerce-review-reminder' ) . '</p></div>'
			. View::request_badge( (string) $data['status'] ) . '</div>';
		echo '<div class="wrr-card-body">';

		$rows = array(
			__( 'Customer', 'woocommerce-review-reminder' ) => '' !== (string) $data['customer_name']
				? (string) $data['customer_name']
				: __( 'Guest', 'woocommerce-review-reminder' ),
			__( 'Email', 'woocommerce-review-reminder' )   => (string) $data['customer_email'],
			__( 'Product', 'woocommerce-review-reminder' ) => $item->product_id() > 0
				? '#' . $item->product_id() . ( $product ? ' — ' . $product->get_name() : '' )
				: __( 'All products in order', 'woocommerce-review-reminder' ),
			__( 'Order status', 'woocommerce-review-reminder' ) => $order ? $order->get_status() : '',
			__( 'Order total', 'woocommerce-review-reminder' ) => $order ? wc_price( $order->get_total() ) : '',
			__( 'Campaign', 'woocommerce-review-reminder' ) => $item->campaign_id() > 0 ? '#' . $item->campaign_id() : '—',
			__( 'Type', 'woocommerce-review-reminder' )    => (string) $data['request_type'],
			__( 'Follow-up', 'woocommerce-review-reminder' ) => $item->followup_number() > 0 ? '#' . $item->followup_number() : __( 'Initial', 'woocommerce-review-reminder' ),
			__( 'Scheduled', 'woocommerce-review-reminder' ) => View::date( $data['scheduled_at'] ),
			__( 'Sent', 'woocommerce-review-reminder' )    => View::date( $data['sent_at'] ),
			__( 'Opened', 'woocommerce-review-reminder' )  => View::date( $data['opened_at'] ),
			__( 'Clicked', 'woocommerce-review-reminder' ) => View::date( $data['clicked_at'] ),
			__( 'Reviewed', 'woocommerce-review-reminder' ) => View::date( $data['review_submitted_at'] ),
			__( 'Attempts', 'woocommerce-review-reminder' ) => $data['attempts'] . ' / ' . $data['max_attempts'],
		);

		foreach ( $rows as $label => $value ) {
			$is_price = strpos( (string) $label, __( 'Order total', 'woocommerce-review-reminder' ) ) !== false;
			echo '<div class="flex items-start justify-between gap-4 border-b border-gray-100 py-2.5 last:border-0">';
			echo '<span class="text-sm text-gray-500">' . esc_html( $label ) . '</span>';
			echo '<span class="text-right text-sm font-medium text-gray-800">';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price returns escaped HTML.
			echo $is_price ? $value : esc_html( (string) $value );
			echo '</span></div>';
		}

		if ( ! empty( $data['last_error'] ) ) {
			echo '<div class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700">' . esc_html( (string) $data['last_error'] ) . '</div>';
		}

		echo '</div></div>';

		// Timeline card.
		echo '<div class="wrr-card">';
		echo '<div class="wrr-card-header"><div><h2 class="wrr-card-title">' . esc_html__( 'Timeline', 'woocommerce-review-reminder' ) . '</h2>';
		echo '<p class="wrr-card-desc">' . esc_html__( 'Events for this request.', 'woocommerce-review-reminder' ) . '</p></div></div>';
		echo '<div class="wrr-card-body">';

		/** @var EventRepository $events */
		$events   = View::service( EventRepository::class );
		$timeline = $events->for_request( $id );
		$labels   = self::event_labels();

		if ( empty( $timeline ) ) {
			echo '<p class="text-sm text-gray-500">' . esc_html__( 'No events recorded yet.', 'woocommerce-review-reminder' ) . '</p>';
		} else {
			$timeline = array_reverse( $timeline );
			echo '<ol class="relative space-y-4 border-l border-gray-200 pl-4">';
			foreach ( $timeline as $event ) {
				$type = (string) $event['event_type'];
				echo '<li class="relative">';
				echo '<span class="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full bg-indigo-500"></span>';
				echo '<div class="text-sm font-medium text-gray-800">' . esc_html( $labels[ $type ] ?? $type ) . '</div>';
				echo '<div class="text-xs text-gray-500">' . View::human_time( $event['created_at'] ) . '</div>';
				echo '</li>';
			}
			echo '</ol>';
		}

		echo '</div></div>';

		echo '</div>'; // grid
		View::close();
	}

	/**
	 * Render a not-found notice.
	 */
	private static function not_found(): void {
		View::open();
		echo View::page_header(
			__( 'Request not found', 'woocommerce-review-reminder' ),
			__( 'The requested review request does not exist.', 'woocommerce-review-reminder' ),
			'<a class="wrr-btn wrr-btn-secondary" href="' . esc_url( View::page_url( AdminPage::REQUESTS ) ) . '">'
				. Icons::get( 'arrow-left' ) . esc_html__( 'Back to requests', 'woocommerce-review-reminder' ) . '</a>'
		);
		View::close();
	}
}
