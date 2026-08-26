<?php
/**
 * Main plugin bootstrap and service wiring.
 *
 * @package WooCommerceReviewReminder\Core
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 */
final class Plugin {

	/**
	 * Plugin main file path.
	 *
	 * @var string
	 */
	private string $file;

	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Whether boot has run.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Get or create the shared plugin instance.
	 *
	 * @param string $file Plugin main file path (first call only).
	 * @return Plugin
	 */
	public static function instance( string $file = '' ): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self( $file );
		}
		return self::$instance;
	}

	/**
	 * Plugin constructor.
	 *
	 * @param string $file Plugin main file path.
	 */
	private function __construct( string $file ) {
		$this->file      = $file;
		$this->container = new Container();
	}

	/**
	 * Boot the plugin. Safe to call multiple times.
	 *
	 * @return Plugin
	 */
	public function boot(): Plugin {
		if ( $this->booted ) {
			return $this;
		}
		$this->booted = true;

		$this->register_core_services();
		$this->register_lifecycle_hooks();

		// WooCommerce may not be loaded yet when this file is included during a
		// normal page load (WooCommerce registers on plugins_loaded priority 20).
		// Defer the WooCommerce-dependent module wiring until it is guaranteed
		// to be available.
		add_action(
			'plugins_loaded',
			function (): void {
				$compatibility = $this->container->get( Compatibility::class );
				$compatibility->check();

				// Bail wiring for WooCommerce-bound modules when WooCommerce is unavailable.
				if ( ! $compatibility->is_woocommerce_active() ) {
					$this->container->get( \WooCommerceReviewReminder\Admin\AdminNotice::class )->register();
					return;
				}

				$this->wire_modules();
			},
			30
		);

		return $this;
	}

	/**
	 * Register core infrastructure services.
	 */
	private function register_core_services(): void {
		$this->container->register( Config::class, fn( Container $c ) => new Config() );
		$this->container->register( Logger::class, fn( Container $c ) => new Logger( $c->get( Config::class ) ) );
		$this->container->register( Compatibility::class, fn( Container $c ) => new Compatibility( $c->get( Logger::class ) ) );
		$this->container->register( \WooCommerceReviewReminder\Database\Schema::class, fn() => new \WooCommerceReviewReminder\Database\Schema() );
		$this->container->register( \WooCommerceReviewReminder\Database\Installer::class, fn( Container $c ) => new \WooCommerceReviewReminder\Database\Installer( $c->get( \WooCommerceReviewReminder\Database\Schema::class ), $c->get( Logger::class ) ) );
		$this->container->register( \WooCommerceReviewReminder\Admin\AdminNotice::class, fn() => new \WooCommerceReviewReminder\Admin\AdminNotice() );
	}

	/**
	 * Register activation/deactivation hooks.
	 */
	private function register_lifecycle_hooks(): void {
		register_activation_hook(
			$this->file,
			function (): void {
				$installer = $this->container->get( \WooCommerceReviewReminder\Database\Installer::class );
				$installer->install();
			}
		);

		register_deactivation_hook(
			$this->file,
			function (): void {
				$this->container->get( \WooCommerceReviewReminder\Database\Installer::class )->deactivate();
			}
		);
	}

	/**
	 * Wire WooCommerce-bound modules: their providers register services and hooks.
	 *
	 * Service registration runs first (phase 1), then hook wiring (phase 2) so
	 * that modules can safely resolve each other's services during wiring.
	 */
	private function wire_modules(): void {
		// Phase 1: register services.
		\WooCommerceReviewReminder\Campaigns\CampaignProvider::register( $this->container );
		\WooCommerceReviewReminder\Analytics\AnalyticsProvider::register( $this->container );
		\WooCommerceReviewReminder\Reviews\ReviewProvider::register( $this->container );
		\WooCommerceReviewReminder\Privacy\PrivacyProvider::register( $this->container );
		\WooCommerceReviewReminder\Tracking\TrackingProvider::register( $this->container );
		\WooCommerceReviewReminder\Emails\EmailProvider::register( $this->container );
		\WooCommerceReviewReminder\Queue\QueueProvider::register( $this->container );
		\WooCommerceReviewReminder\Cron\CronProvider::register( $this->container );
		\WooCommerceReviewReminder\Orders\OrderProvider::register( $this->container );
		\WooCommerceReviewReminder\REST\RestProvider::register( $this->container );
		\WooCommerceReviewReminder\Admin\AdminProvider::register( $this->container );

		// Phase 2: wire hooks.
		\WooCommerceReviewReminder\Reviews\ReviewProvider::hooks( $this->container );
		\WooCommerceReviewReminder\Privacy\PrivacyProvider::hooks( $this->container );
		\WooCommerceReviewReminder\Tracking\TrackingProvider::hooks( $this->container );
		\WooCommerceReviewReminder\Emails\EmailProvider::hooks( $this->container );
		\WooCommerceReviewReminder\Queue\QueueProvider::hooks( $this->container );
		\WooCommerceReviewReminder\Cron\CronProvider::hooks( $this->container );
		\WooCommerceReviewReminder\Orders\OrderProvider::hooks( $this->container );
		\WooCommerceReviewReminder\REST\RestProvider::hooks( $this->container );
		\WooCommerceReviewReminder\Admin\AdminProvider::hooks( $this->container );
	}

	/**
	 * Access the container.
	 *
	 * @return Container
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Resolve a service from the container.
	 *
	 * @param string $id Service identifier.
	 * @return object
	 */
	public function get( string $id ) {
		return $this->container->get( $id );
	}

	/**
	 * Plugin main file path.
	 *
	 * @return string
	 */
	public function file(): string {
		return $this->file;
	}

	/**
	 * Plugin directory path (trailing slash).
	 *
	 * @return string
	 */
	public function dir(): string {
		return WRR_PLUGIN_DIR;
	}

	/**
	 * Plugin directory URL (trailing slash).
	 *
	 * @return string
	 */
	public function url(): string {
		return WRR_PLUGIN_URL;
	}
}
