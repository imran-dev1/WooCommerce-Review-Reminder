<?php
/**
 * Result of an email send attempt.
 *
 * @package WooCommerceReviewReminder\Emails
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Class SendResult
 */
final class SendResult {

	/**
	 * Whether the send succeeded.
	 *
	 * @var bool
	 */
	public bool $success;

	/**
	 * Human-readable result message.
	 *
	 * @var string
	 */
	public string $message;

	/**
	 * SendResult constructor.
	 *
	 * @param bool   $success Success flag.
	 * @param string $message Message.
	 */
	public function __construct( bool $success, string $message = '' ) {
		$this->success = $success;
		$this->message = $message;
	}

	/**
	 * Factory for a successful result.
	 *
	 * @param string $message Message.
	 * @return self
	 */
	public static function ok( string $message = '' ): self {
		return new self( true, $message );
	}

	/**
	 * Factory for a failed result.
	 *
	 * @param string $message Message.
	 * @return self
	 */
	public static function fail( string $message ): self {
		return new self( false, $message );
	}
}
