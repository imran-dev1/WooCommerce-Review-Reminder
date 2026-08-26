<?php
/**
 * Admin menu registration and page dispatch.
 *
 * @package WooCommerceReviewReminder\Admin
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Admin;

use WooCommerceReviewReminder\Admin\Views\AnalyticsView;
use WooCommerceReviewReminder\Admin\Views\CampaignEditorView;
use WooCommerceReviewReminder\Admin\Views\CampaignsView;
use WooCommerceReviewReminder\Admin\Views\Icons;
use WooCommerceReviewReminder\Admin\Views\OverviewView;
use WooCommerceReviewReminder\Admin\Views\RequestDetailView;
use WooCommerceReviewReminder\Admin\Views\RequestsView;
use WooCommerceReviewReminder\Admin\Views\ReviewsView;
use WooCommerceReviewReminder\Admin\Views\SettingsView;
use WooCommerceReviewReminder\Admin\Views\TemplatesView;

defined( 'ABSPATH' ) || exit;

/**
 * Class AdminMenu
 */
final class AdminMenu {

	/**
	 * Parent page slug.
	 *
	 * @var string
	 */
	public const SLUG = 'wrr-dashboard';

	/**
	 * Public menu pages (slug => label).
	 *
	 * @var array<string, string>
	 */
	private const PAGES = array(
		AdminPage::DASHBOARD => 'Overview',
		AdminPage::CAMPAIGNS => 'Campaigns',
		AdminPage::REQUESTS  => 'Requests',
		AdminPage::REVIEWS   => 'Reviews',
		AdminPage::ANALYTICS => 'Analytics',
		AdminPage::TEMPLATES => 'Templates',
		AdminPage::SETTINGS  => 'Settings',
	);

	/**
	 * Hidden pages registered so routes resolve.
	 *
	 * @var array<string, string>
	 */
	private const HIDDEN_PAGES = array(
		AdminPage::CAMPAIGN_EDIT  => 'Campaign editor',
		AdminPage::REQUEST_DETAIL => 'Request detail',
	);

	/**
	 * Page slug => renderer callback.
	 *
	 * @var array<string, string>
	 */
	private const RENDERERS = array(
		AdminPage::DASHBOARD      => array( OverviewView::class, 'render' ),
		AdminPage::CAMPAIGNS      => array( CampaignsView::class, 'render' ),
		AdminPage::REQUESTS       => array( RequestsView::class, 'render' ),
		AdminPage::REVIEWS        => array( ReviewsView::class, 'render' ),
		AdminPage::ANALYTICS      => array( AnalyticsView::class, 'render' ),
		AdminPage::TEMPLATES      => array( TemplatesView::class, 'render' ),
		AdminPage::SETTINGS       => array( SettingsView::class, 'render' ),
		AdminPage::CAMPAIGN_EDIT  => array( CampaignEditorView::class, 'render' ),
		AdminPage::REQUEST_DETAIL => array( RequestDetailView::class, 'render' ),
	);

	/**
	 * All plugin page slugs (menu + hidden).
	 *
	 * @return string[]
	 */
	public static function slugs(): array {
		return array_merge( array_keys( self::PAGES ), array_keys( self::HIDDEN_PAGES ) );
	}

	/**
	 * Register the admin menu.
	 */
	public function register(): void {
		add_action(
			'admin_menu',
			function (): void {
				add_menu_page(
					__( 'Review Reminder', 'woocommerce-review-reminder' ),
					__( 'Review Reminder', 'woocommerce-review-reminder' ),
					'manage_woocommerce',
					AdminPage::DASHBOARD,
					array( $this, 'render_app' ),
					WRR_PLUGIN_URL . 'assets/img/product-starred.svg',
					58
				);

				foreach ( self::PAGES as $slug => $label ) {
					if ( AdminPage::DASHBOARD === $slug ) {
						continue;
					}
					add_submenu_page(
						AdminPage::DASHBOARD,
						__( 'Review Reminder', 'woocommerce-review-reminder' ),
						$this->menu_label( $slug ),
						'manage_woocommerce',
						$slug,
						array( $this, 'render_app' )
					);
				}

				foreach ( self::HIDDEN_PAGES as $slug => $label ) {
					add_submenu_page(
						'options.php',
						$this->menu_label( $slug ),
						$this->menu_label( $slug ),
						'manage_woocommerce',
						$slug,
						array( $this, 'render_app' )
					);
				}
			}
		);

		add_action( 'admin_head', array( $this, 'inline_menu_styles' ) );
		add_action( 'admin_head', array( $this, 'suppress_notices' ), PHP_INT_MAX );
	}

	/**
	 * Dispatch the current page to its view renderer.
	 */
	public function render_app(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page slug, sanitized.
		$slug     = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : AdminPage::DASHBOARD;
		$renderer = self::RENDERERS[ $slug ] ?? array( OverviewView::class, 'render' );

		if ( ! is_callable( $renderer ) ) {
			$renderer = array( OverviewView::class, 'render' );
		}

		$this->submenu_rail( $slug );
		call_user_func( $renderer );
	}

	/**
	 * Remove all admin notice hooks so no third-party notifications render
	 * inside the plugin's admin pages. Hooked on admin_head at the maximum
	 * priority: it runs after every other admin_head callback but still before
	 * admin-header.php fires admin_notices/all_admin_notices.
	 */
	public function suppress_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'wrr-' ) ) {
			return;
		}
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
	}

	/**
	 * Render the plugin's full-height submenu rail, placed just right of the
	 * WP admin sidebar.
	 *
	 * @param string $current Current page slug.
	 */
	private function submenu_rail( string $current ): void {
		$icons = array(
			AdminPage::DASHBOARD => 'chart',
			AdminPage::CAMPAIGNS => 'megaphone',
			AdminPage::REQUESTS  => 'mail',
			AdminPage::REVIEWS   => 'star',
			AdminPage::ANALYTICS => 'trend',
			AdminPage::TEMPLATES => 'file',
			AdminPage::SETTINGS  => 'settings',
		);

		echo '<aside class="wrr-submenu-rail">';

		echo '<div class="wrr-submenu-rail-header">';
		echo '<span class="wrr-submenu-rail-logo"><img src="' . esc_url( WRR_PLUGIN_URL . 'assets/img/product-starred.svg' ) . '" alt="' . esc_attr__( 'Review Reminder', 'woocommerce-review-reminder' ) . '"></span>';
		echo '<div class="wrr-submenu-rail-brand">';
		echo '<span>' . esc_html__( 'Review Reminder', 'woocommerce-review-reminder' ) . '</span>';
		echo '<div class="wrr-submenu-rail-sub">' . esc_html__( 'WooCommerce', 'woocommerce-review-reminder' ) . '</div>';
		echo '</div>';
		echo '</div>';

		echo '<nav class="wrr-submenu-rail-nav">';
		foreach ( self::PAGES as $slug => $label ) {
			$active = $slug === $current;
			$icon   = $icons[ $slug ] ?? 'inbox';
			echo '<a class="wrr-submenu-rail-link' . ( $active ? ' is-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">';
			echo '<span class="wrr-submenu-rail-icon">' . Icons::get( $icon, 'h-4 w-4' ) . '</span>';
			echo '<span>' . esc_html( $this->menu_label( $slug ) ) . '</span>';
			echo '</a>';
		}
		echo '</nav>';

		echo '<div class="wrr-submenu-rail-footer">' . esc_html__( 'Version ', 'woocommerce-review-reminder' ) . esc_html( WRR_VERSION ) . '</div>';

		echo '</aside>';
	}

	/**
	 * Translated menu label for a page slug.
	 *
	 * @param string $slug Page slug.
	 * @return string
	 */
	private function menu_label( string $slug ): string {
		$labels = array(
			AdminPage::DASHBOARD      => __( 'Overview', 'woocommerce-review-reminder' ),
			AdminPage::CAMPAIGNS      => __( 'Campaigns', 'woocommerce-review-reminder' ),
			AdminPage::REQUESTS       => __( 'Requests', 'woocommerce-review-reminder' ),
			AdminPage::REVIEWS        => __( 'Reviews', 'woocommerce-review-reminder' ),
			AdminPage::ANALYTICS      => __( 'Analytics', 'woocommerce-review-reminder' ),
			AdminPage::TEMPLATES      => __( 'Templates', 'woocommerce-review-reminder' ),
			AdminPage::SETTINGS       => __( 'Settings', 'woocommerce-review-reminder' ),
			AdminPage::CAMPAIGN_EDIT  => __( 'Campaign editor', 'woocommerce-review-reminder' ),
			AdminPage::REQUEST_DETAIL => __( 'Request detail', 'woocommerce-review-reminder' ),
		);
		return $labels[ $slug ] ?? __( 'Overview', 'woocommerce-review-reminder' );
	}

	/**
	 * Style the plugin's section inside the WP admin sidebar.
	 */
	public function inline_menu_styles(): void {
		$css = '
		#adminmenu #toplevel_page_wrr-dashboard .wp-menu-image img {
			width: 20px;
			height: 20px;
			padding-top: 7px;
		}
		#adminmenu #toplevel_page_wrr-dashboard.wp-has-current-submenu > a.menu-top,
		#adminmenu #toplevel_page_wrr-dashboard.wp-has-current-submenu > a.menu-top:hover {
			background: linear-gradient(90deg, rgba(99,102,241,.34), rgba(139,92,246,.16));
			border-left: 3px solid #a78bfa;
			color: #fff;
		}
		#adminmenu #toplevel_page_wrr-dashboard.wp-has-current-submenu .wp-menu-image:before,
		#adminmenu #toplevel_page_wrr-dashboard.wp-menu-open .wp-menu-image:before {
			color: #c4b5fd !important;
		}
		#adminmenu #toplevel_page_wrr-dashboard.wp-has-current-submenu .wp-submenu {
			background: #151a22;
			border: 1px solid rgba(255,255,255,.06);
			border-radius: 10px;
			margin: 4px 10px 8px;
			padding: 6px;
			box-shadow: 0 8px 24px -12px rgba(0,0,0,.7);
		}
		#adminmenu #toplevel_page_wrr-dashboard .wp-submenu a {
			border-radius: 7px;
			padding: 9px 12px 9px 14px;
			color: #b6c2d0;
			font-weight: 500;
			transition: color .12s ease, background-color .12s ease;
		}
		#adminmenu #toplevel_page_wrr-dashboard .wp-submenu a:hover {
			color: #fff;
			background: rgba(255,255,255,.07);
		}
		#adminmenu #toplevel_page_wrr-dashboard .wp-submenu li.current a {
			background: linear-gradient(90deg, #6366f1, #8b5cf6);
			color: #fff;
			box-shadow: 0 4px 12px -4px rgba(99,102,241,.55);
		}
		';
		echo '<style>' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
