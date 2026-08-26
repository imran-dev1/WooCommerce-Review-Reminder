<?php
/**
 * Database installer and uninstaller.
 *
 * @package WooCommerceReviewReminder\Database
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Database;

use WooCommerceReviewReminder\Core\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Class Installer
 */
final class Installer {

	/**
	 * Schema instance.
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Installer constructor.
	 *
	 * @param Schema $schema Schema instance.
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Schema $schema, Logger $logger ) {
		$this->schema = $schema;
		$this->logger = $logger;
	}

	/**
	 * Run on plugin activation. Creates tables and seeds defaults.
	 */
	public function install(): void {
		$this->create_tables();
		$this->seed_defaults();
		update_option( Schema::VERSION_OPTION, Schema::VERSION, false );
		$this->logger->info( 'Plugin activated and database installed.' );
	}

	/**
	 * Run schema upgrades when the stored version is older than the current one.
	 */
	public function maybe_upgrade(): void {
		$installed = get_option( Schema::VERSION_OPTION, '' );
		if ( version_compare( (string) $installed, Schema::VERSION, '<' ) ) {
			$this->install();
		}
	}

	/**
	 * Run on plugin deactivation. Clears scheduled cron events.
	 */
	public function deactivate(): void {
		$cron = new \WooCommerceReviewReminder\Cron\CronManager( $this->schema, $this->logger );
		$cron->clear_all();
		$this->logger->info( 'Plugin deactivated, cron events cleared.' );
	}

	/**
	 * Remove all plugin data. Only called from uninstall.php and only when the
	 * "delete data on uninstall" option is enabled.
	 */
	public function uninstall(): void {
		global $wpdb;

		foreach ( $this->schema->tables() as $table ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . esc_sql( $table ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		delete_option( 'wrr_settings' );
		delete_option( 'wrr_plugin_data' );
		delete_option( Schema::VERSION_OPTION );
		delete_option( 'wrr_onboarding_complete' );
		delete_option( 'wrr_installed_at' );
		delete_option( 'wrr_db_version' );
	}

	/**
	 * Create plugin tables with dbDelta.
	 */
	private function create_tables(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( $this->schema->create_statements() as $table => $statement ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			dbDelta( $statement );
		}
	}

	/**
	 * Seed default data (default email templates and a starter campaign).
	 */
	private function seed_defaults(): void {
		$template_repo = new \WooCommerceReviewReminder\Emails\TemplateRepository( $this->schema, $this->logger );
		$template_repo->seed_default_templates();
		$template_repo->backfill_default_descriptions();

		// Only seed a starter campaign when the campaigns table is empty.
		$campaign_repo = new \WooCommerceReviewReminder\Campaigns\Repository\CampaignRepository( $this->schema, $this->logger );
		if ( 0 === $campaign_repo->count() ) {
			$campaign_repo->seed_default_campaign();
		}
	}
}
