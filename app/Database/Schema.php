<?php
/**
 * Database table schema definitions.
 *
 * @package WooCommerceReviewReminder\Database
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Class Schema
 */
final class Schema {

	/**
	 * Schema version (bump to trigger migration).
	 *
	 * @var string
	 */
	public const VERSION = '1.2.0';

	/**
	 * Option name that stores the installed schema version.
	 *
	 * @var string
	 */
	public const VERSION_OPTION = 'wrr_db_version';

	/**
	 * All table names registered by the plugin.
	 *
	 * @var array<string, string>
	 */
	private const TABLES = array(
		'campaigns'    => 'wrr_campaigns',
		'requests'     => 'wrr_requests',
		'events'       => 'wrr_events',
		'suppressions' => 'wrr_suppressions',
		'templates'    => 'wrr_templates',
		'metrics'      => 'wrr_metrics',
	);

	/**
	 * Return the prefixed table name for a logical table.
	 *
	 * @param string $key Logical table key.
	 * @return string
	 */
	public function table( string $key ): string {
		global $wpdb;

		if ( ! isset( self::TABLES[ $key ] ) ) {
			return '';
		}
		return $wpdb->prefix . self::TABLES[ $key ];
	}

	/**
	 * All prefixed table names.
	 *
	 * @return array<string, string>
	 */
	public function tables(): array {
		global $wpdb;

		$tables = array();
		foreach ( self::TABLES as $key => $name ) {
			$tables[ $key ] = $wpdb->prefix . $name;
		}
		return $tables;
	}

	/**
	 * Full CREATE TABLE statements for every table.
	 *
	 * @return array<string, string>
	 */
	public function create_statements(): array {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		return array(
			$this->table( 'campaigns' )    => sprintf(
				"CREATE TABLE %s (
					id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					name varchar(255) NOT NULL,
					description text NULL,
					status varchar(20) NOT NULL DEFAULT 'draft',
					config longtext NOT NULL,
					stats text NULL,
					created_at datetime NOT NULL,
					updated_at datetime NOT NULL,
					PRIMARY KEY  (id),
					KEY status (status),
					KEY created_at (created_at)
				) %s;",
				$this->table( 'campaigns' ),
				$charset_collate
			),
			$this->table( 'requests' )     => sprintf(
				"CREATE TABLE %s (
					id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					campaign_id bigint(20) unsigned NULL,
					order_id bigint(20) unsigned NOT NULL,
					product_id bigint(20) unsigned NULL,
					customer_id bigint(20) unsigned NULL,
					customer_email varchar(190) NOT NULL,
					customer_name varchar(255) NULL,
					status varchar(20) NOT NULL DEFAULT 'scheduled',
					request_type varchar(20) NOT NULL DEFAULT 'initial',
					followup_number smallint(5) unsigned NOT NULL DEFAULT 0,
					scheduled_at datetime NOT NULL,
					sent_at datetime NULL,
					opened_at datetime NULL,
					clicked_at datetime NULL,
					review_submitted_at datetime NULL,
					attempts smallint(5) unsigned NOT NULL DEFAULT 0,
					max_attempts smallint(5) unsigned NOT NULL DEFAULT 3,
					last_error varchar(500) NULL,
					email_subject varchar(255) NULL,
					email_body longtext NULL,
					token varchar(64) NULL,
					source varchar(30) NOT NULL DEFAULT 'order',
					created_at datetime NOT NULL,
					updated_at datetime NOT NULL,
					PRIMARY KEY  (id),
					KEY status_scheduled (status, scheduled_at),
					KEY order_id (order_id),
					KEY product_id (product_id),
					KEY customer_email (customer_email),
					KEY campaign_id (campaign_id),
					KEY token (token),
					UNIQUE KEY order_product_campaign (order_id, product_id, campaign_id, request_type, followup_number)
				) %s;",
				$this->table( 'requests' ),
				$charset_collate
			),
			$this->table( 'events' )       => sprintf(
				'CREATE TABLE %s (
					id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					request_id bigint(20) unsigned NULL,
					campaign_id bigint(20) unsigned NULL,
					order_id bigint(20) unsigned NULL,
					customer_email varchar(190) NULL,
					event_type varchar(30) NOT NULL,
					meta longtext NULL,
					created_at datetime NOT NULL,
					PRIMARY KEY  (id),
					KEY event_type_created (event_type, created_at),
					KEY request_id (request_id),
					KEY campaign_created (campaign_id, created_at),
					KEY order_id (order_id)
				) %s;',
				$this->table( 'events' ),
				$charset_collate
			),
			$this->table( 'suppressions' ) => sprintf(
				"CREATE TABLE %s (
					id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					email varchar(190) NOT NULL,
					reason varchar(30) NOT NULL DEFAULT 'unsubscribed',
					created_at datetime NOT NULL,
					PRIMARY KEY  (id),
					UNIQUE KEY email (email)
				) %s;",
				$this->table( 'suppressions' ),
				$charset_collate
			),
			$this->table( 'templates' )    => sprintf(
				'CREATE TABLE %s (
					id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					name varchar(255) NOT NULL,
					slug varchar(255) NOT NULL,
					description text NULL,
					is_builtin tinyint(1) unsigned NOT NULL DEFAULT 0,
					subject varchar(255) NOT NULL,
					body longtext NOT NULL,
					created_at datetime NOT NULL,
					updated_at datetime NOT NULL,
					PRIMARY KEY  (id),
					UNIQUE KEY slug (slug)
				) %s;',
				$this->table( 'templates' ),
				$charset_collate
			),
			$this->table( 'metrics' )      => sprintf(
				'CREATE TABLE %s (
					id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					metric_date date NOT NULL,
					metric_type varchar(30) NOT NULL,
					campaign_id bigint(20) unsigned NULL,
					count int(11) unsigned NOT NULL DEFAULT 0,
					PRIMARY KEY  (id),
					UNIQUE KEY date_type_campaign (metric_date, metric_type, campaign_id)
				) %s;',
				$this->table( 'metrics' ),
				$charset_collate
			),
		);
	}
}
