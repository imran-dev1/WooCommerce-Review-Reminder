<?php
/**
 * Shared helpers for admin views.
 *
 * @package WooCommerceReviewReminder\Admin\Views
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Admin\Views;

use WooCommerceReviewReminder\Admin\AdminPage;
use WooCommerceReviewReminder\Core\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Class View
 */
final class View {

	/**
	 * Resolve a plugin service.
	 *
	 * @param string $id Service identifier.
	 * @return object
	 */
	public static function service( string $id ) {
		return Plugin::instance()->get( $id );
	}

	/**
	 * URL to a plugin admin page.
	 *
	 * @param string               $slug Page slug (without prefix).
	 * @param array<string, mixed> $args Extra query arguments.
	 * @return string
	 */
	public static function page_url( string $slug, array $args = array() ): string {
		if ( 0 !== strpos( $slug, AdminPage::PAGE_PREFIX ) ) {
			$slug = AdminPage::PAGE_PREFIX . $slug;
		}
		$url = admin_url( 'admin.php' );
		$url = add_query_arg( 'page', $slug, $url );
		foreach ( $args as $key => $value ) {
			$url = add_query_arg( $key, $value, $url );
		}
		return $url;
	}

	/**
	 * Escape + echo a translated string.
	 *
	 * @param string $text Text.
	 */
	public static function e( string $text ): void {
		echo esc_html( $text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html applied.
	}

	/**
	 * Render a JSON value as an HTML single-quoted data attribute value.
	 *
	 * @param mixed $value Value to encode.
	 * @return string Escaped JSON suitable inside a single-quoted attribute.
	 */
	public static function json_attr( $value ): string {
		return esc_attr( (string) wp_json_encode( $value ) );
	}

	/**
	 * Format a number with thousands separators.
	 *
	 * @param int|float|null $n Number.
	 * @return string
	 */
	public static function number( $n ): string {
		return number_format_i18n( (float) ( $n ?? 0 ) );
	}

	/**
	 * Format a percentage (already 0-100).
	 *
	 * @param float|int|null $rate Rate.
	 * @return string
	 */
	public static function rate( $rate ): string {
		return sprintf( '%s%%', number_format_i18n( (float) ( $rate ?? 0 ), 1 ) );
	}

	/**
	 * Format a datetime for display.
	 *
	 * @param string|null $date    Date string (Y-m-d H:i:s or similar).
	 * @param string      $fallback Fallback when empty.
	 * @return string
	 */
	public static function date( ?string $date, string $fallback = '—' ): string {
		if ( ! $date ) {
			return $fallback;
		}
		$ts = strtotime( $date );
		if ( false === $ts ) {
			return esc_html( $date );
		}
		return esc_html( wp_date( get_option( 'date_format', 'M j, Y' ), $ts ) );
	}

	/**
	 * Human relative time (e.g. "3 hours ago").
	 *
	 * @param string|null $date Date string.
	 * @param string      $fallback Fallback when empty.
	 * @return string
	 */
	public static function human_time( ?string $date, string $fallback = '—' ): string {
		if ( ! $date ) {
			return $fallback;
		}
		$ts = strtotime( $date );
		if ( false === $ts ) {
			return esc_html( $date );
		}
		return esc_html( human_time_diff( $ts ) . ' ' . __( 'ago', 'woocommerce-review-reminder' ) );
	}

	/**
	 * Status badge with a colour variant.
	 *
	 * @param string $text     Label.
	 * @param string $variant gray|green|amber|red|blue|indigo.
	 * @return string HTML.
	 */
	public static function badge( string $text, string $variant = 'gray' ): string {
		$allowed = array( 'gray', 'green', 'amber', 'red', 'blue', 'indigo' );
		if ( ! in_array( $variant, $allowed, true ) ) {
			$variant = 'gray';
		}
		return sprintf(
			'<span class="wrr-badge wrr-badge-%s">%s</span>',
			esc_attr( $variant ),
			esc_html( $text )
		);
	}

	/**
	 * Campaign status badge.
	 *
	 * @param string $status Campaign status.
	 * @return string HTML.
	 */
	public static function campaign_badge( string $status ): string {
		$map = array(
			'active'   => 'green',
			'paused'   => 'amber',
			'draft'    => 'gray',
			'archived' => 'gray',
		);
		return self::badge( $status, $map[ $status ] ?? 'gray' );
	}

	/**
	 * Request status badge.
	 *
	 * @param string $status Request status.
	 * @return string HTML.
	 */
	public static function request_badge( string $status ): string {
		$map = array(
			'scheduled'  => 'blue',
			'processing' => 'indigo',
			'sent'       => 'indigo',
			'opened'     => 'blue',
			'clicked'    => 'green',
			'reviewed'   => 'green',
			'failed'     => 'red',
			'cancelled'  => 'gray',
			'suppressed' => 'gray',
		);
		return self::badge( $status, $map[ $status ] ?? 'gray' );
	}

	/**
	 * Flashed notice after a successful save (wrr_saved query arg).
	 *
	 * @return string HTML or empty.
	 */
	public static function flash_notice(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flash message.
		$flash = isset( $_GET['wrr_saved'] ) ? sanitize_key( wp_unslash( $_GET['wrr_saved'] ) ) : '';
		if ( '' === $flash ) {
			return '';
		}
		$messages = array(
			'created'   => __( 'Campaign created.', 'woocommerce-review-reminder' ),
			'draft'     => __( 'Campaign saved as draft.', 'woocommerce-review-reminder' ),
			'updated'   => __( 'Campaign updated.', 'woocommerce-review-reminder' ),
			'activated' => __( 'Campaign updated and activated.', 'woocommerce-review-reminder' ),
		);
		$message  = $messages[ $flash ] ?? '';
		if ( '' === $message ) {
			return '';
		}
		$dismiss = esc_url( remove_query_arg( 'wrr_saved' ) );
		return '<div class="wrr-alert mb-4">
			<span class="wrr-alert-icon" aria-hidden="true">✓</span>
			<div class="flex-1">' . esc_html( $message ) . '</div>
			<a href="' . $dismiss . '" class="wrr-btn-link" aria-label="' . esc_attr__( 'Dismiss', 'woocommerce-review-reminder' ) . '">✕</a>
		</div>';
	}

	/**
	 * Page header block.
	 *
	 * @param string      $title   Title.
	 * @param string      $desc    Description.
	 * @param string      $actions Right-aligned action HTML.
	 * @param string|null $back    Optional back-link URL.
	 * @return string HTML.
	 */
	public static function page_header( string $title, string $desc, string $actions = '', ?string $back = null ): string {
		$back_html = '';
		if ( $back ) {
			$back_html = '<a href="' . esc_url( $back ) . '" class="wrr-hero-back">&larr; ' . esc_html__( 'Back', 'woocommerce-review-reminder' ) . '</a>';
		}
		return '<div class="wrr-hero"><div class="wrr-hero-inner">
			' . $back_html . '
			<div class="wrr-hero-content">
				<div>
					<h1 class="wrr-hero-title">' . esc_html( $title ) . '</h1>
					' . ( $desc ? '<p class="wrr-hero-desc">' . esc_html( $desc ) . '</p>' : '' ) . '
				</div>
				' . ( $actions ? '<div class="wrr-hero-actions">' . $actions . '</div>' : '' ) . '
			</div>
		</div></div>';
	}

	/**
	 * Open the plugin admin wrapper (full-width content area inside the app shell).
	 */
	public static function open(): void {
		echo '<div class="wrr-app w-full px-6 py-8 lg:px-10">';
	}

	/**
	 * Close the plugin admin wrapper.
	 */
	public static function close(): void {
		echo '</div>';
	}

	/**
	 * Reusable Alpine confirm modal. Must be placed inside an element with
	 * `x-data="wrrConfirm()"` and `x-on:wrr-confirm-ask.window="ask($event.detail)"`.
	 *
	 * @return string HTML.
	 */
	public static function confirm_modal(): string {
		return <<<'HTML'
		<div class="wrr-modal-backdrop" x-show="state" x-cloak x-transition.opacity>
			<div class="wrr-modal max-w-md" x-on:click.outside="cancel()">
				<div class="p-6">
					<h3 class="text-base font-semibold text-gray-900" x-text="state?.title || ''"></h3>
					<p class="mt-2 text-sm text-gray-500" x-text="state?.message || ''"></p>
					<div class="mt-6 flex justify-end gap-2">
						<button type="button" class="wrr-btn wrr-btn-secondary" x-on:click="cancel()" x-text="state?.cancelLabel || 'Cancel'"></button>
						<button type="button" class="wrr-btn wrr-btn-danger" x-on:click="confirm()" x-bind:disabled="busy" x-text="state?.confirmLabel || 'Confirm'"></button>
					</div>
				</div>
			</div>
		</div>
		HTML;
	}
}
