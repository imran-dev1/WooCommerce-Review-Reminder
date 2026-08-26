<?php
/**
 * Review request entity.
 *
 * @package WooCommerceReviewReminder\Queue
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Queue;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReviewRequest
 */
final class ReviewRequest {

	/**
	 * Request statuses.
	 */
	public const STATUS_SCHEDULED  = 'scheduled';
	public const STATUS_PROCESSING = 'processing';
	public const STATUS_SENT       = 'sent';
	public const STATUS_FAILED     = 'failed';
	public const STATUS_CANCELLED  = 'cancelled';
	public const STATUS_REVIEWED   = 'reviewed';

	/**
	 * Row data.
	 *
	 * @var array<string, mixed>
	 */
	private array $data;

	/**
	 * ReviewRequest constructor.
	 *
	 * @param array<string, mixed> $data Row data.
	 */
	public function __construct( array $data ) {
		$this->data = $data;
	}

	/**
	 * Row data.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return $this->data;
	}

	/**
	 * Request id.
	 *
	 * @return int
	 */
	public function id(): int {
		return (int) ( $this->data['id'] ?? 0 );
	}

	/**
	 * Campaign id.
	 *
	 * @return int
	 */
	public function campaign_id(): int {
		return (int) ( $this->data['campaign_id'] ?? 0 );
	}

	/**
	 * Order id.
	 *
	 * @return int
	 */
	public function order_id(): int {
		return (int) ( $this->data['order_id'] ?? 0 );
	}

	/**
	 * Product id, or 0 for grouped requests covering the whole order.
	 *
	 * @return int
	 */
	public function product_id(): int {
		return (int) ( $this->data['product_id'] ?? 0 );
	}

	/**
	 * Whether this request covers every eligible product in the order.
	 *
	 * @return bool
	 */
	public function is_grouped(): bool {
		return 0 === $this->product_id();
	}

	/**
	 * Customer user id, or 0 for guests.
	 *
	 * @return int
	 */
	public function customer_id(): int {
		return (int) ( $this->data['customer_id'] ?? 0 );
	}

	/**
	 * Customer email.
	 *
	 * @return string
	 */
	public function customer_email(): string {
		return (string) ( $this->data['customer_email'] ?? '' );
	}

	/**
	 * Customer display name.
	 *
	 * @return string
	 */
	public function customer_name(): string {
		return (string) ( $this->data['customer_name'] ?? '' );
	}

	/**
	 * Status.
	 *
	 * @return string
	 */
	public function status(): string {
		return (string) ( $this->data['status'] ?? self::STATUS_SCHEDULED );
	}

	/**
	 * Request type: initial|followup.
	 *
	 * @return string
	 */
	public function request_type(): string {
		return (string) ( $this->data['request_type'] ?? 'initial' );
	}

	/**
	 * Follow-up number (0 for the initial request).
	 *
	 * @return int
	 */
	public function followup_number(): int {
		return (int) ( $this->data['followup_number'] ?? 0 );
	}

	/**
	 * Scheduled timestamp.
	 *
	 * @return string
	 */
	public function scheduled_at(): string {
		return (string) ( $this->data['scheduled_at'] ?? '' );
	}

	/**
	 * Sent timestamp.
	 *
	 * @return string
	 */
	public function sent_at(): string {
		return (string) ( $this->data['sent_at'] ?? '' );
	}

	/**
	 * Opened timestamp.
	 *
	 * @return string
	 */
	public function opened_at(): string {
		return (string) ( $this->data['opened_at'] ?? '' );
	}

	/**
	 * Clicked timestamp.
	 *
	 * @return string
	 */
	public function clicked_at(): string {
		return (string) ( $this->data['clicked_at'] ?? '' );
	}

	/**
	 * Review submitted timestamp.
	 *
	 * @return string
	 */
	public function review_submitted_at(): string {
		return (string) ( $this->data['review_submitted_at'] ?? '' );
	}

	/**
	 * Attempt count.
	 *
	 * @return int
	 */
	public function attempts(): int {
		return (int) ( $this->data['attempts'] ?? 0 );
	}

	/**
	 * Max attempts.
	 *
	 * @return int
	 */
	public function max_attempts(): int {
		return (int) ( $this->data['max_attempts'] ?? 3 );
	}

	/**
	 * Last error message.
	 *
	 * @return string
	 */
	public function last_error(): string {
		return (string) ( $this->data['last_error'] ?? '' );
	}

	/**
	 * Email subject as sent.
	 *
	 * @return string
	 */
	public function email_subject(): string {
		return (string) ( $this->data['email_subject'] ?? '' );
	}

	/**
	 * Email body as sent.
	 *
	 * @return string
	 */
	public function email_body(): string {
		return (string) ( $this->data['email_body'] ?? '' );
	}

	/**
	 * Tracking token.
	 *
	 * @return string
	 */
	public function token(): string {
		return (string) ( $this->data['token'] ?? '' );
	}

	/**
	 * Whether the request is pending (scheduled or processing).
	 *
	 * @return bool
	 */
	public function is_pending(): bool {
		return in_array( $this->status(), array( self::STATUS_SCHEDULED, self::STATUS_PROCESSING ), true );
	}
}
