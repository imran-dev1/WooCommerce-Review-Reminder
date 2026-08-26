<?php
/**
 * Mailer that uses WordPress' wp_mail().
 *
 * @package WooCommerceReviewReminder\Emails
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Emails;

use WooCommerceReviewReminder\Core\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Class WordpressMailer
 */
final class WordpressMailer implements MailerInterface {

	/**
	 * Config instance.
	 *
	 * @var Config
	 */
	private Config $config;

	/**
	 * WordpressMailer constructor.
	 *
	 * @param Config $config Config instance.
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Send via wp_mail().
	 *
	 * @param array<string, mixed> $data Email data.
	 * @return SendResult
	 */
	public function send( array $data ): SendResult {
		$to      = sanitize_email( (string) $data['to'] );
		$subject = sanitize_text_field( (string) $data['subject'] );
		$body    = (string) $data['body'];

		if ( ! is_email( $to ) ) {
			return SendResult::fail(
				sprintf(
					/* translators: %s: email address. */
					__( 'Invalid recipient email: %s', 'woocommerce-review-reminder' ),
					$to
				)
			);
		}

		$from_name  = (string) $this->config->get( 'email.from_name', '' );
		$from_email = (string) $this->config->get( 'email.from_email', '' );
		$reply_to   = (string) $this->config->get( 'email.reply_to', '' );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		if ( '' !== $from_email && is_email( $from_email ) ) {
			$from      = $from_name ? sprintf( '%s <%s>', $from_name, $from_email ) : $from_email;
			$headers[] = 'From: ' . $from;
		}
		if ( '' !== $reply_to && is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		$sent = wp_mail( $to, $subject, $body, $headers );

		if ( false === $sent ) {
			return SendResult::fail( __( 'wp_mail() returned false.', 'woocommerce-review-reminder' ) );
		}

		return SendResult::ok();
	}
}
