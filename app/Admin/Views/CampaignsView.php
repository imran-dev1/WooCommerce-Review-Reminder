<?php
/**
 * Campaigns list admin view.
 *
 * @package WooCommerceReviewReminder\Admin\Views
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Admin\Views;

use WooCommerceReviewReminder\Admin\AdminPage;
use WooCommerceReviewReminder\Analytics\AnalyticsRepository;
use WooCommerceReviewReminder\Campaigns\Repository\CampaignRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class CampaignsView
 */
final class CampaignsView {

	/**
	 * Render the campaigns list.
	 */
	public static function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status filter.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$repo   = self::repo();

		$campaigns = '' !== $status
			? $repo->find_by_status( $status, 200, 0 )
			: $repo->all( 200, 0 );

		$counts = array(
			'all'    => $repo->count(),
			'active' => $repo->count( 'active' ),
			'paused' => $repo->count( 'paused' ),
			'draft'  => $repo->count( 'draft' ),
		);

		$stats_map = array();
		/** @var AnalyticsRepository $analytics */
		$analytics = View::service( AnalyticsRepository::class );
		foreach ( $analytics->campaign_comparison() as $stat ) {
			$stats_map[ (int) $stat['id'] ] = $stat;
		}

		View::open();
		echo View::flash_notice();
		echo View::page_header(
			__( 'Campaigns', 'woocommerce-review-reminder' ),
			__( 'Automate review requests with triggers, timing and audience rules.', 'woocommerce-review-reminder' ),
			'<a class="wrr-btn wrr-btn-primary" href="' . esc_url( View::page_url( AdminPage::CAMPAIGN_EDIT ) ) . '">'
				. Icons::get( 'plus' ) . esc_html__( 'New campaign', 'woocommerce-review-reminder' ) . '</a>'
		);

		$tabs = array(
			''       => sprintf( /* translators: %d: count. */ __( 'All (%d)', 'woocommerce-review-reminder' ), $counts['all'] ),
			'active' => sprintf( /* translators: %d: count. */ __( 'Active (%d)', 'woocommerce-review-reminder' ), $counts['active'] ),
			'paused' => sprintf( /* translators: %d: count. */ __( 'Paused (%d)', 'woocommerce-review-reminder' ), $counts['paused'] ),
			'draft'  => sprintf( /* translators: %d: count. */ __( 'Drafts (%d)', 'woocommerce-review-reminder' ), $counts['draft'] ),
		);

		echo '<div class="mb-4 flex flex-wrap items-center gap-1">';
		foreach ( $tabs as $value => $label ) {
			$active = $value === $status;
			$url    = View::page_url( AdminPage::CAMPAIGNS, $value ? array( 'status' => $value ) : array() );
			printf(
				'<a href="%s" class="wrr-nav-tab%s" %s>%s</a>',
				esc_url( $url ),
				$active ? ' wrr-nav-tab-active' : '',
				$active ? 'aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo '</div>';

		echo '<div x-data="wrrCampaigns()">';
		echo '<div class="wrr-card">';

		if ( empty( $campaigns ) ) {
			echo '<div class="wrr-empty">';
			echo '<span class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-400">' . Icons::get( 'megaphone', 'h-5 w-5' ) . '</span>';
			echo '<div class="wrr-empty-title">' . esc_html__( 'No campaigns here', 'woocommerce-review-reminder' ) . '</div>';
			echo '<div class="wrr-empty-desc">' . esc_html__( 'Create your first review-request campaign to start collecting reviews automatically.', 'woocommerce-review-reminder' ) . '</div>';
			echo '<a class="wrr-btn wrr-btn-primary mt-3" href="' . esc_url( View::page_url( AdminPage::CAMPAIGN_EDIT ) ) . '">'
				. Icons::get( 'plus' ) . esc_html__( 'Create campaign', 'woocommerce-review-reminder' ) . '</a>';
			echo '</div>';
		} else {
			echo '<table class="wrr-table">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Campaign', 'woocommerce-review-reminder' ) . '</th>';
			echo '<th>' . esc_html__( 'Status', 'woocommerce-review-reminder' ) . '</th>';
			echo '<th class="text-right">' . esc_html__( 'Sent', 'woocommerce-review-reminder' ) . '</th>';
			echo '<th class="text-right">' . esc_html__( 'Reviewed', 'woocommerce-review-reminder' ) . '</th>';
			echo '<th class="text-right">' . esc_html__( 'Conv.', 'woocommerce-review-reminder' ) . '</th>';
			echo '<th class="text-right">' . esc_html__( 'Actions', 'woocommerce-review-reminder' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $campaigns as $campaign ) {
				$id    = $campaign->id();
				$stat  = $stats_map[ $id ] ?? array(
					'sent'            => 0,
					'reviews'         => 0,
					'conversion_rate' => 0.0,
				);
				$delay = $campaign->config()->delay();
				$unit  = $campaign->config()->delay_unit();

				echo '<tr data-cid="' . (int) $id . '" data-name="' . esc_attr( $campaign->name() ) . '">';
				echo '<td>';
				echo '<div class="font-medium text-gray-900"><a href="' . esc_url( View::page_url( AdminPage::CAMPAIGN_EDIT, array( 'id' => $id ) ) ) . '" class="hover:text-indigo-600">' . esc_html( $campaign->name() ) . '</a></div>';
				echo '<div class="text-xs text-gray-500">' . View::date( $campaign->updated_at() )
					. ' · ' . esc_html( (string) $delay . ' ' . $unit ) . ' ' . esc_html__( 'delay', 'woocommerce-review-reminder' ) . '</div>';
				echo '</td>';
				echo '<td>' . View::campaign_badge( $campaign->status() ) . '</td>';
				echo '<td class="text-right tabular-nums text-gray-700">' . View::number( $stat['sent'] ) . '</td>';
				echo '<td class="text-right tabular-nums text-gray-700">' . View::number( $stat['reviews'] ) . '</td>';
				echo '<td class="text-right tabular-nums text-gray-700">' . View::rate( $stat['conversion_rate'] ) . '</td>';

				echo '<td class="text-right whitespace-nowrap">';
				echo '<div class="inline-flex items-center gap-1">';
				echo '<a class="wrr-btn wrr-btn-ghost wrr-btn-sm" title="' . esc_attr__( 'Edit', 'woocommerce-review-reminder' ) . '" href="' . esc_url( View::page_url( AdminPage::CAMPAIGN_EDIT, array( 'id' => $id ) ) ) . '">' . Icons::get( 'edit' ) . '</a>';

				if ( 'active' === $campaign->status() ) {
					echo '<button type="button" class="wrr-btn wrr-btn-ghost wrr-btn-sm" title="' . esc_attr__( 'Pause', 'woocommerce-review-reminder' ) . '" x-on:click="runAction(' . (int) $id . ', \'pause\')">' . Icons::get( 'pause' ) . '</button>';
				} elseif ( 'archived' !== $campaign->status() ) {
					echo '<button type="button" class="wrr-btn wrr-btn-ghost wrr-btn-sm" title="' . esc_attr__( 'Activate', 'woocommerce-review-reminder' ) . '" x-on:click="runAction(' . (int) $id . ', \'activate\')">' . Icons::get( 'play' ) . '</button>';
				}

				echo '<button type="button" class="wrr-btn wrr-btn-ghost wrr-btn-sm" title="' . esc_attr__( 'Duplicate', 'woocommerce-review-reminder' ) . '" x-on:click="runAction(' . (int) $id . ', \'duplicate\')">' . Icons::get( 'copy' ) . '</button>';
				echo '<button type="button" class="wrr-btn wrr-btn-ghost wrr-btn-sm" title="' . esc_attr__( 'Delete', 'woocommerce-review-reminder' ) . '" x-on:click="askDelete($el.closest(\'tr\').dataset.cid, $el.closest(\'tr\').dataset.name)">' . Icons::get( 'trash' ) . '</button>';
				echo '</div></td>';

				echo '</tr>';
			}

			echo '</tbody></table>';
		}

		echo '</div>'; // card

		echo '<div x-data="wrrConfirm()" x-on:wrr-confirm-ask.window="ask($event.detail)">' . View::confirm_modal() . '</div>';
		echo '</div>'; // campaigns alpine root

		View::close();
	}

	/**
	 * Campaign repository.
	 *
	 * @return CampaignRepository
	 */
	private static function repo(): CampaignRepository {
		/** @var CampaignRepository $repo */
		$repo = View::service( CampaignRepository::class );
		return $repo;
	}
}
