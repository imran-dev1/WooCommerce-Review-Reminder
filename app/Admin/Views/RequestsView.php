<?php
/**
 * Review requests list admin view.
 *
 * @package WooCommerceReviewReminder\Admin\Views
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Admin\Views;

use WooCommerceReviewReminder\Admin\AdminPage;
use WooCommerceReviewReminder\Queue\RequestRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class RequestsView
 */
final class RequestsView {

	/**
	 * Render the requests list.
	 */
	public static function render(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter/pagination params.
		$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$campaign = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0;
		$search   = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		$page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = 20;
		// phpcs:enable

		/** @var RequestRepository $repo */
		$repo   = View::service( RequestRepository::class );
		$result = $repo->paginate(
			array(
				'status'      => $status,
				'campaign_id' => $campaign,
				'search'      => $search,
				'page'        => $page,
				'per_page'    => $per_page,
			)
		);

		$items      = $result['items'];
		$total      = (int) $result['total'];
		$pages      = max( 1, (int) $result['pages'] );
		$status_tab = $status;

		$counts = array();
		foreach ( array( 'scheduled', 'sent', 'reviewed', 'failed' ) as $s ) {
			$counts[ $s ] = $repo->count( $s );
		}

		View::open();
		echo View::page_header(
			__( 'Review requests', 'woocommerce-review-reminder' ),
			__( 'Every scheduled, sent and completed review request.', 'woocommerce-review-reminder' )
		);

		$tabs = array(
			''          => __( 'All', 'woocommerce-review-reminder' ),
			'scheduled' => sprintf( /* translators: %d: count. */ __( 'Scheduled (%d)', 'woocommerce-review-reminder' ), $counts['scheduled'] ),
			'sent'      => sprintf( /* translators: %d: count. */ __( 'Sent (%d)', 'woocommerce-review-reminder' ), $counts['sent'] ),
			'reviewed'  => sprintf( /* translators: %d: count. */ __( 'Reviewed (%d)', 'woocommerce-review-reminder' ), $counts['reviewed'] ),
			'failed'    => sprintf( /* translators: %d: count. */ __( 'Failed (%d)', 'woocommerce-review-reminder' ), $counts['failed'] ),
		);

		echo '<div class="mb-4 flex flex-wrap items-center justify-between gap-3">';
		echo '<div class="flex flex-wrap items-center gap-1">';
		foreach ( $tabs as $value => $label ) {
			$active = $value === $status_tab;
			$url    = View::page_url( AdminPage::REQUESTS, $value ? array( 'status' => $value ) : array() );
			printf(
				'<a href="%s" class="wrr-nav-tab%s" %s>%s</a>',
				esc_url( $url ),
				$active ? ' wrr-nav-tab-active' : '',
				$active ? 'aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo '</div>';

		echo '<form method="get" class="flex items-center gap-2">';
		echo '<input type="hidden" name="page" value="' . esc_attr( AdminPage::REQUESTS ) . '" />';
		echo '<input type="hidden" name="status" value="' . esc_attr( $status ) . '" />';
		echo '<input type="search" name="search" value="' . esc_attr( $search ) . '" class="wrr-input w-56" placeholder="' . esc_attr__( 'Search customer or email…', 'woocommerce-review-reminder' ) . '" />';
		echo '<button type="submit" class="wrr-btn wrr-btn-secondary">' . Icons::get( 'search' ) . '</button>';
		echo '</form>';
		echo '</div>';

		echo '<div class="wrr-card">';

		if ( empty( $items ) ) {
			echo '<div class="wrr-empty">';
			echo '<span class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-400">' . Icons::get( 'inbox', 'h-5 w-5' ) . '</span>';
			echo '<div class="wrr-empty-title">' . esc_html__( 'No requests here', 'woocommerce-review-reminder' ) . '</div>';
			echo '<div class="wrr-empty-desc">' . esc_html__( 'Review requests are created when an order matches an active campaign.', 'woocommerce-review-reminder' ) . '</div>';
			echo '</div>';
		} else {
			echo '<table class="wrr-table">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Customer', 'woocommerce-review-reminder' ) . '</th>';
			echo '<th>' . esc_html__( 'Product', 'woocommerce-review-reminder' ) . '</th>';
			echo '<th>' . esc_html__( 'Order', 'woocommerce-review-reminder' ) . '</th>';
			echo '<th>' . esc_html__( 'Status', 'woocommerce-review-reminder' ) . '</th>';
			echo '<th>' . esc_html__( 'Scheduled', 'woocommerce-review-reminder' ) . '</th>';
			echo '<th>' . esc_html__( 'Sent', 'woocommerce-review-reminder' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $items as $request ) {
				$data = $request->to_array();
				echo '<tr class="cursor-pointer" x-on:click="WRR.nav(\'' . esc_attr( AdminPage::REQUEST_DETAIL ) . '\', { id: ' . (int) $request->id() . ' })" data-href="' . esc_url( View::page_url( AdminPage::REQUEST_DETAIL, array( 'id' => $request->id() ) ) ) . '">';
				echo '<td>';
				echo '<div class="font-medium text-gray-900">' . esc_html( '' !== (string) $data['customer_name'] ? (string) $data['customer_name'] : __( 'Guest', 'woocommerce-review-reminder' ) ) . '</div>';
				echo '<div class="text-xs text-gray-500">' . esc_html( (string) $data['customer_email'] ) . '</div>';
				echo '</td>';
				echo '<td><div class="max-w-[200px] truncate text-gray-700">' . ( $request->product_id() > 0 ? esc_html( '#' . $request->product_id() ) : esc_html__( 'All products', 'woocommerce-review-reminder' ) ) . '</div></td>';
				echo '<td class="tabular-nums text-gray-700">#' . (int) $request->order_id() . '</td>';
				echo '<td>' . View::request_badge( (string) $data['status'] ) . '</td>';
				echo '<td class="text-xs text-gray-500">' . View::date( $data['scheduled_at'] ) . '</td>';
				echo '<td class="text-xs text-gray-500">' . View::date( $data['sent_at'] ) . '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';

			if ( $pages > 1 ) {
				echo '<div class="flex items-center justify-between px-6 py-4">';
				echo '<p class="text-xs text-gray-500">' . esc_html( sprintf( /* translators: 1: count, 2: total. */ __( '%1$s of %2$s requests', 'woocommerce-review-reminder' ), View::number( $total ), View::number( $total ) ) ) . '</p>';
				echo '<div class="flex items-center gap-2">';
				if ( $page > 1 ) {
					echo '<a class="wrr-btn wrr-btn-secondary wrr-btn-sm" href="' . esc_url( View::page_url( AdminPage::REQUESTS, array_merge( self::current_args(), array( 'paged' => $page - 1 ) ) ) ) . '">' . esc_html__( 'Previous', 'woocommerce-review-reminder' ) . '</a>';
				}
				if ( $page < $pages ) {
					echo '<a class="wrr-btn wrr-btn-secondary wrr-btn-sm" href="' . esc_url( View::page_url( AdminPage::REQUESTS, array_merge( self::current_args(), array( 'paged' => $page + 1 ) ) ) ) . '">' . esc_html__( 'Next', 'woocommerce-review-reminder' ) . '</a>';
				}
				echo '</div></div>';
			}
		}

		echo '</div>'; // card
		View::close();
	}

	/**
	 * Current filter args (without pagination).
	 *
	 * @return array<string, string|int>
	 */
	private static function current_args(): array {
		$args = array();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only.
		if ( isset( $_GET['status'] ) ) {
			$args['status'] = sanitize_key( wp_unslash( $_GET['status'] ) );
		}
		if ( isset( $_GET['search'] ) && '' !== $_GET['search'] ) {
			$args['search'] = sanitize_text_field( wp_unslash( $_GET['search'] ) );
		}
		// phpcs:enable
		return $args;
	}
}
