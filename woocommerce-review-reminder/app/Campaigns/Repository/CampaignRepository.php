<?php
/**
 * Campaign repository.
 *
 * @package WooCommerceReviewReminder\Campaigns\Repository
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Campaigns\Repository;

use WooCommerceReviewReminder\Campaigns\Campaign;
use WooCommerceReviewReminder\Campaigns\CampaignConfig;
use WooCommerceReviewReminder\Database\Repository;
use WooCommerceReviewReminder\Core\Logger;
use WooCommerceReviewReminder\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class CampaignRepository
 */
final class CampaignRepository extends Repository {

	/**
	 * Create a campaign.
	 *
	 * @param array<string, mixed> $data Campaign data. `config` may be array or JSON.
	 * @return int New campaign id.
	 */
	public function create( array $data ): int {
		$now    = current_time( 'mysql' );
		$config = $data['config'] ?? array();
		$config = is_string( $config ) ? $config : ( new CampaignConfig( is_array( $config ) ? $config : array() ) )->to_json();

		$result = $this->wpdb->insert(
			$this->table( 'campaigns' ),
			array(
				'name'        => sanitize_text_field( $data['name'] ?? '' ),
				'description' => sanitize_textarea_field( $data['description'] ?? '' ),
				'status'      => sanitize_key( $data['status'] ?? 'draft' ),
				'config'      => $config,
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			$this->logger->error( 'Failed to insert campaign.', array( 'db_error' => $this->wpdb->last_error ) );
			return 0;
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update a campaign.
	 *
	 * @param int                  $id   Campaign id.
	 * @param array<string, mixed> $data Fields to update.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		$fields = array();
		$format = array();

		if ( array_key_exists( 'name', $data ) ) {
			$fields['name'] = sanitize_text_field( $data['name'] );
			$format[]       = '%s';
		}
		if ( array_key_exists( 'description', $data ) ) {
			$fields['description'] = sanitize_textarea_field( (string) $data['description'] );
			$format[]              = '%s';
		}
		if ( array_key_exists( 'status', $data ) ) {
			$fields['status'] = sanitize_key( (string) $data['status'] );
			$format[]         = '%s';
		}
		if ( array_key_exists( 'config', $data ) ) {
			$config           = is_string( $data['config'] ) ? $data['config'] : ( new CampaignConfig( is_array( $data['config'] ) ? $data['config'] : array() ) )->to_json();
			$fields['config'] = $config;
			$format[]         = '%s';
		}
		if ( array_key_exists( 'stats', $data ) ) {
			$stats           = is_array( $data['stats'] ) ? wp_json_encode( $data['stats'] ) : (string) $data['stats'];
			$fields['stats'] = $stats;
			$format[]        = '%s';
		}

		if ( empty( $fields ) ) {
			return true;
		}

		$fields['updated_at'] = current_time( 'mysql' );
		$format[]             = '%s';

		$result = $this->wpdb->update(
			$this->table( 'campaigns' ),
			$fields,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);

		if ( false === $result ) {
			$this->logger->error(
				'Failed to update campaign.',
				array(
					'id'       => $id,
					'db_error' => $this->wpdb->last_error,
				)
			);
			return false;
		}

		return true;
	}

	/**
	 * Set campaign status.
	 *
	 * @param int    $id     Campaign id.
	 * @param string $status Status.
	 * @return bool
	 */
	public function set_status( int $id, string $status ): bool {
		return $this->update( $id, array( 'status' => sanitize_key( $status ) ) );
	}

	/**
	 * Find a campaign by id.
	 *
	 * @param int $id Campaign id.
	 * @return Campaign|null
	 */
	public function find( int $id ): ?Campaign {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d LIMIT 1',
				$this->table( 'campaigns' ),
				$id
			),
			ARRAY_A
		);

		return is_array( $row ) ? new Campaign( $row ) : null;
	}

	/**
	 * Find campaigns by status.
	 *
	 * @param string   $status Status filter.
	 * @param int      $limit  Limit.
	 * @param int      $offset Offset.
	 * @return Campaign[]
	 */
	public function find_by_status( string $status, int $limit = 100, int $offset = 0 ): array {
		return $this->query(
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d',
				$this->table( 'campaigns' ),
				sanitize_key( $status ),
				$limit,
				$offset
			)
		);
	}

	/**
	 * All campaigns, newest first.
	 *
	 * @param int $limit  Limit.
	 * @param int $offset Offset.
	 * @return Campaign[]
	 */
	public function all( int $limit = 100, int $offset = 0 ): array {
		return $this->query(
			$this->wpdb->prepare(
				'SELECT * FROM %i ORDER BY created_at DESC LIMIT %d OFFSET %d',
				$this->table( 'campaigns' ),
				$limit,
				$offset
			)
		);
	}

	/**
	 * Active campaigns.
	 *
	 * @return Campaign[]
	 */
	public function active(): array {
		return $this->find_by_status( 'active', 200, 0 );
	}

	/**
	 * Count campaigns, optionally by status.
	 *
	 * @param string|null $status Status filter or null for all.
	 * @return int
	 */
	public function count( ?string $status = null ): int {
		if ( null === $status ) {
			return (int) $this->wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table( 'campaigns' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE status = %s',
				$this->table( 'campaigns' ),
				sanitize_key( $status )
			)
		);
	}

	/**
	 * Delete a campaign.
	 *
	 * @param int $id Campaign id.
	 */
	public function delete( int $id ): void {
		$this->wpdb->delete( $this->table( 'campaigns' ), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Query campaigns as entities.
	 *
	 * @param string $sql SQL statement.
	 * @return Campaign[]
	 */
	private function query( string $sql ): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		return array_map(
			static function ( array $row ): Campaign {
				return new Campaign( $row );
			},
			$rows
		);
	}

	/**
	 * Seed a sensible default campaign on first activation.
	 *
	 * @return int
	 */
	public function seed_default_campaign(): int {
		$config = new CampaignConfig(
			array(
				'trigger'    => array(
					'type'           => 'order_status',
					'order_statuses' => array( 'completed' ),
				),
				'timing'     => array(
					'delay'      => 7,
					'delay_unit' => 'days',
					'send_time'  => '',
				),
				'audience'   => array(
					'customer_type'       => 'all',
					'customer_roles'      => array(),
					'min_order_value'     => null,
					'max_order_value'     => null,
					'payment_methods'     => array(),
					'shipping_methods'    => array(),
					'customer_history'    => 'any',
					'min_previous_orders' => 1,
				),
				'products'   => array(
					'include'              => 'all',
					'product_ids'          => array(),
					'category_ids'         => array(),
					'tag_ids'              => array(),
					'exclude_product_ids'  => array(),
					'exclude_category_ids' => array(),
				),
				'email'      => array(
					'template_id' => 0,
					'subject'     => __( 'How are you enjoying your {{product_name}}?', 'woocommerce-review-reminder' ),
					'body'        => '',
					'button_text' => __( 'Leave a Review', 'woocommerce-review-reminder' ),
				),
				'followup'   => array(
					'enabled'       => false,
					'delay'         => 7,
					'delay_unit'    => 'days',
					'max_reminders' => 2,
					'subject'       => '',
					'body'          => '',
				),
				'strategy'   => array(
					'request' => 'grouped',
				),
				'exclusions' => array(
					'skip_reviewed'   => true,
					'skip_suppressed' => true,
					'skip_refunded'   => true,
					'skip_cancelled'  => true,
					'max_per_order'   => null,
				),
			)
		);

		return $this->create(
			array(
				'name'        => __( 'Post-Purchase Review Request', 'woocommerce-review-reminder' ),
				'description' => __( 'Automatically ask customers for a product review 7 days after their order is completed.', 'woocommerce-review-reminder' ),
				'status'      => 'draft',
				'config'      => $config->to_json(),
			)
		);
	}
}
