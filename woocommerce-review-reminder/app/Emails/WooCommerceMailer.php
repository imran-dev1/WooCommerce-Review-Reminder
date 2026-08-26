<?php
/**
 * Mailer that routes through the WooCommerce email system.
 *
 * @package WooCommerceReviewReminder\Emails
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Emails;

use WooCommerceReviewReminder\Core\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Class WooCommerceMailer
 */
final class WooCommerceMailer implements MailerInterface {

	/**
	 * Config instance.
	 *
	 * @var Config
	 */
	private Config $config;

	/**
	 * WooCommerceMailer constructor.
	 *
	 * @param Config $config Config instance.
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Send through WooCommerce's email sender.
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

		if ( ! function_exists( 'wc_mail' ) ) {
			return SendResult::fail( __( 'WooCommerce mail function is unavailable.', 'woocommerce-review-reminder' ) );
		}

		$from_name  = (string) $this->config->get( 'email.from_name', '' );
		$from_email = (string) $this->config->get( 'email.from_email', '' );
		$reply_to   = (string) $this->config->get( 'email.reply_to', '' );

		if ( '' !== $from_name && '' !== $from_email ) {
			add_filter(
				'woocommerce_email_from_name',
				static fn() => $from_name,
				10
			);
			add_filter(
				'woocommerce_email_from_address',
				static fn() => $from_email,
				10
			);
		}

		if ( '' !== $reply_to && is_email( $reply_to ) ) {
			add_filter(
				'woocommerce_email_headers',
				static function ( $headers ) use ( $reply_to ) {
					$headers   = is_array( $headers ) ? $headers : ( '' !== $headers ? explode( "\n", $headers ) : array() );
					$headers[] = 'Reply-To: ' . $reply_to;
					return $headers;
				},
				10,
				1
			);
		}

		$sent = wc_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );

		if ( false === $sent ) {
			return SendResult::fail( __( 'WooCommerce mail returned false.', 'woocommerce-review-reminder' ) );
		}

		return SendResult::ok();
	}
}
