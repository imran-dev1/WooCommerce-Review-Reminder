<?php
/**
 * Admin asset loading. Assets load only on the plugin's admin pages.
 *
 * @package WooCommerceReviewReminder\Admin
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Admin;

use WooCommerceReviewReminder\Core\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Class Assets
 */
final class Assets {

	/**
	 * Config instance.
	 *
	 * @var Config
	 */
	private Config $config;

	/**
	 * Assets constructor.
	 *
	 * @param Config $config Config instance.
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Register enqueue hooks.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Enqueue plugin assets on plugin pages only.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function enqueue( string $hook ): void {
		$is_plugin_page = false;
		foreach ( AdminMenu::slugs() as $slug ) {
			if ( strpos( $hook, $slug ) !== false ) {
				$is_plugin_page = true;
				break;
			}
		}

		if ( ! $is_plugin_page ) {
			return;
		}

		$css = WRR_PLUGIN_DIR . 'assets/css/admin.css';
		$js  = WRR_PLUGIN_DIR . 'assets/js/admin.js';

		if ( file_exists( $css ) ) {
			wp_enqueue_style(
				'wrr-admin',
				WRR_PLUGIN_URL . 'assets/css/admin.css',
				array(),
				(string) filemtime( $css )
			);
		}

		if ( file_exists( $js ) ) {
			wp_enqueue_script(
				'wrr-admin',
				WRR_PLUGIN_URL . 'assets/js/admin.js',
				array(),
				(string) filemtime( $js ),
				true
			);
			wp_localize_script(
				'wrr-admin',
				'WRR_CONFIG',
				$this->config_payload()
			);
		}
	}

	/**
	 * Add a stable body class so themes/plugins can scope custom styles.
	 *
	 * @param string $classes Body classes.
	 * @return string
	 */
	public function body_class( string $classes ): string {
		$screen = get_current_screen();
		if ( $screen && false !== strpos( (string) $screen->id, 'wrr-' ) ) {
			$classes .= ' wrr-admin';
		}
		return $classes;
	}

	/**
	 * Client-side configuration.
	 *
	 * @return array<string, mixed>
	 */
	private function config_payload(): array {
		return array(
			'restUrl'       => esc_url_raw( rest_url( 'woocommerce-review-reminder/v1/' ) ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'adminUrl'      => esc_url_raw( admin_url( 'admin.php' ) ),
			'pluginUrl'     => esc_url_raw( WRR_PLUGIN_URL ),
			'version'       => WRR_VERSION,
			'wcVersion'     => defined( 'WC_VERSION' ) ? WC_VERSION : '',
			'wpVersion'     => get_bloginfo( 'version' ),
			'siteName'      => get_bloginfo( 'name' ),
			'siteUrl'       => esc_url_raw( home_url( '/' ) ),
			'timezone'      => wp_timezone_string(),
			'userEmail'     => wp_get_current_user()->user_email,
			'userCanManage' => current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ),
		);
	}
}
