<?php
/**
 * Base repository with shared database helpers.
 *
 * @package WooCommerceReviewReminder\Database
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Database;

use WooCommerceReviewReminder\Core\Logger;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Class Repository
 */
abstract class Repository {

	/**
	 * Schema instance.
	 *
	 * @var Schema
	 */
	protected Schema $schema;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	protected Logger $logger;

	/**
	 * wpdb instance.
	 *
	 * @var wpdb
	 */
	protected $wpdb;

	/**
	 * Repository constructor.
	 *
	 * @param Schema $schema Schema instance.
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Schema $schema, Logger $logger ) {
		global $wpdb;

		$this->schema = $schema;
		$this->logger = $logger;
		$this->wpdb   = $wpdb;
	}

	/**
	 * Prefixed table name for a logical table.
	 *
	 * @param string $key Logical table key.
	 * @return string
	 */
	protected function table( string $key ): string {
		return $this->schema->table( $key );
	}

	/**
	 * Access the schema instance.
	 *
	 * @return Schema
	 */
	public function schema(): Schema {
		return $this->schema;
	}
}
