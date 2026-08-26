<?php
/**
 * Mailer contract. Implementations deliver a rendered email.
 *
 * @package WooCommerceReviewReminder\Emails
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Interface MailerInterface
 */
interface MailerInterface {

	/**
	 * Send an email.
	 *
	 * @param array<string, mixed> $data {
	 *     @type string $to          Recipient.
	 *     @type string $subject     Subject.
	 *     @type string $body        Rendered HTML body.
	 *     @type string[] $headers   Extra headers.
	 * }
	 * @return SendResult
	 */
	public function send( array $data ): SendResult;
}
