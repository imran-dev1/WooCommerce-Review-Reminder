<?php
/**
 * Email template repository with default template seeding.
 *
 * @package WooCommerceReviewReminder\Emails
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Emails;

use WooCommerceReviewReminder\Database\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Class TemplateRepository
 */
final class TemplateRepository extends Repository {

	/**
	 * Find a template by id.
	 *
	 * @param int $id Template id.
	 * @return Template|null
	 */
	public function find( int $id ): ?Template {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d LIMIT 1',
				$this->table( 'templates' ),
				$id
			),
			ARRAY_A
		);
		return is_array( $row ) ? new Template( $row ) : null;
	}

	/**
	 * All templates.
	 *
	 * @return Template[]
	 */
	public function all(): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results(
			'SELECT * FROM ' . $this->table( 'templates' ) . ' ORDER BY is_builtin DESC, id ASC',
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}
		return array_map(
			static function ( array $row ): Template {
				return new Template( $row );
			},
			$rows
		);
	}

	/**
	 * Create a template.
	 *
	 * @param array<string, mixed> $data Template data.
	 * @return int
	 */
	public function create( array $data ): int {
		$now = current_time( 'mysql' );
		$this->wpdb->insert(
			$this->table( 'templates' ),
			array(
				'name'       => sanitize_text_field( $data['name'] ?? '' ),
				'slug'       => sanitize_title( $data['slug'] ?? '' ),
				'is_builtin' => absint( $data['is_builtin'] ?? 0 ),
				'subject'    => sanitize_text_field( $data['subject'] ?? '' ),
				'body'       => wp_kses_post( $data['body'] ?? '' ),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update a template.
	 *
	 * @param int                  $id   Template id.
	 * @param array<string, mixed> $data Data.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		$fields = array();
		$format = array();

		if ( array_key_exists( 'name', $data ) ) {
			$fields['name'] = sanitize_text_field( $data['name'] );
			$format[]       = '%s';
		}
		if ( array_key_exists( 'subject', $data ) ) {
			$fields['subject'] = sanitize_text_field( $data['subject'] );
			$format[]          = '%s';
		}
		if ( array_key_exists( 'body', $data ) ) {
			$fields['body'] = wp_kses_post( $data['body'] );
			$format[]       = '%s';
		}
		if ( empty( $fields ) ) {
			return true;
		}

		$fields['updated_at'] = current_time( 'mysql' );
		$format[]             = '%s';

		return false !== $this->wpdb->update(
			$this->table( 'templates' ),
			$fields,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);
	}

	/**
	 * Delete a template.
	 *
	 * @param int $id Template id.
	 */
	public function delete( int $id ): void {
		$this->wpdb->delete( $this->table( 'templates' ), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Seed the built-in templates when the table is empty.
	 */
	public function seed_default_templates(): void {
		$count = (int) $this->wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table( 'templates' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $count > 0 ) {
			return;
		}

		foreach ( $this->default_templates() as $template ) {
			$this->wpdb->insert(
				$this->table( 'templates' ),
				array(
					'name'       => $template['name'],
					'slug'       => $template['slug'],
					'is_builtin' => 1,
					'subject'    => $template['subject'],
					'body'       => $template['body'],
					'created_at' => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Built-in template definitions.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function default_templates(): array {
		return array(
			array(
				'name'    => __( 'Simple Review Request', 'woocommerce-review-reminder' ),
				'slug'    => 'simple-review-request',
				'subject' => __( 'How are you enjoying your {{product_name}}?', 'woocommerce-review-reminder' ),
				'body'    => '<p>' . __( 'Hi {{customer_first_name}},', 'woocommerce-review-reminder' ) . '</p>'
					. '<p>' . __( 'Thank you for your recent purchase!', 'woocommerce-review-reminder' ) . '</p>'
					. '<p>' . __( "We'd love to hear what you think about {{product_name}}.", 'woocommerce-review-reminder' ) . '</p>'
					. '<div style="margin:28px 0;text-align:center;"><a href="{{review_url}}" style="display:inline-block;background-color:#2563eb;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;padding:14px 32px;border-radius:8px;">' . __( 'Leave a Review', 'woocommerce-review-reminder' ) . '</a></div>'
					. '<p style="margin-top:24px;">' . __( 'Thank you,', 'woocommerce-review-reminder' ) . '<br>{{store_name}}</p>',
			),
			array(
				'name'    => __( 'Product Feedback', 'woocommerce-review-reminder' ),
				'slug'    => 'product-feedback',
				'subject' => __( 'Share your feedback on {{product_name}}', 'woocommerce-review-reminder' ),
				'body'    => '<p>' . __( 'Hi {{customer_first_name}},', 'woocommerce-review-reminder' ) . '</p>'
					. '<p>' . __( 'We hope you are enjoying your new product!', 'woocommerce-review-reminder' ) . '</p>'
					. '<div style="margin:24px 0;padding:20px;background-color:#f8fafc;border-radius:8px;text-align:center;">'
					. '{{product_image}}'
					. '<p style="font-size:16px;font-weight:600;margin:8px 0 4px;">{{product_name}}</p>'
					. '<p style="font-size:13px;color:#71717a;margin:0 0 16px;">' . __( 'Order #{{order_number}}', 'woocommerce-review-reminder' ) . '</p>'
					. '<a href="{{review_url}}" style="display:inline-block;background-color:#2563eb;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;padding:14px 32px;border-radius:8px;">' . __( 'Write a Review', 'woocommerce-review-reminder' ) . '</a>'
					. '</div>'
					. '<p>' . __( 'Your feedback helps us improve.', 'woocommerce-review-reminder' ) . '</p>'
					. '<p style="margin-top:24px;">' . __( 'Thank you,', 'woocommerce-review-reminder' ) . '<br>{{store_name}}</p>',
			),
			array(
				'name'    => __( 'Friendly Follow-up', 'woocommerce-review-reminder' ),
				'slug'    => 'friendly-followup',
				'subject' => __( 'How was your experience with {{product_name}}?', 'woocommerce-review-reminder' ),
				'body'    => '<p>' . __( 'Hi {{customer_first_name}},', 'woocommerce-review-reminder' ) . '</p>'
					. '<p>' . __( 'Just checking in! We noticed you recently ordered {{product_name}}.', 'woocommerce-review-reminder' ) . '</p>'
					. '<p>' . __( 'If you have a moment, we would love to hear how it went.', 'woocommerce-review-reminder' ) . '</p>'
					. '<div style="margin:28px 0;text-align:center;"><a href="{{review_url}}" style="display:inline-block;background-color:#2563eb;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;padding:14px 32px;border-radius:8px;">' . __( 'Share Your Thoughts', 'woocommerce-review-reminder' ) . '</a></div>'
					. '<p style="margin-top:24px;">' . __( 'Warm regards,', 'woocommerce-review-reminder' ) . '<br>{{store_name}}</p>',
			),
			array(
				'name'    => __( 'Thank You + Review', 'woocommerce-review-reminder' ),
				'slug'    => 'thank-you-review',
				'subject' => __( 'Thank you for your order, {{customer_first_name}}!', 'woocommerce-review-reminder' ),
				'body'    => '<p>' . __( 'Hi {{customer_first_name}},', 'woocommerce-review-reminder' ) . '</p>'
					. '<p>' . __( 'Thank you so much for your order. We truly appreciate your support.', 'woocommerce-review-reminder' ) . '</p>'
					. '<p>' . __( 'Your opinion matters to us and to other customers. Could you spare a minute to review {{product_name}}?', 'woocommerce-review-reminder' ) . '</p>'
					. '<div style="margin:28px 0;text-align:center;"><a href="{{review_url}}" style="display:inline-block;background-color:#2563eb;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;padding:14px 32px;border-radius:8px;">' . __( 'Leave a Review', 'woocommerce-review-reminder' ) . '</a></div>'
					. '<p style="margin-top:24px;">' . __( 'With gratitude,', 'woocommerce-review-reminder' ) . '<br>{{store_name}}</p>',
			),
		);
	}
}
