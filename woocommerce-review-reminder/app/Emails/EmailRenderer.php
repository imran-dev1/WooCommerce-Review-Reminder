<?php
/**
 * Renders review-request emails from templates + variables.
 *
 * @package WooCommerceReviewReminder\Emails
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Class EmailRenderer
 */
final class EmailRenderer {

	/**
	 * Variables instance.
	 *
	 * @var Variables
	 */
	private Variables $variables;

	/**
	 * EmailRenderer constructor.
	 *
	 * @param Variables $variables Variables instance.
	 */
	public function __construct( Variables $variables ) {
		$this->variables = $variables;
	}

	/**
	 * Render subject and full HTML body.
	 *
	 * @param string               $subject_template Subject with variables.
	 * @param string               $body_template   Body HTML with variables.
	 * @param array<string, mixed> $context         Render context.
	 * @return array{subject: string, body: string}
	 */
	public function render( string $subject_template, string $body_template, array $context ): array {
		$subject = $this->variables->replace_all( $subject_template, $context );
		$subject = wp_strip_all_tags( $subject );

		$context['subject'] = $subject;

		$inner = $this->variables->replace_all( $body_template, $context );
		$body  = $this->wrap( $inner, $context );

		return array(
			'subject' => $subject,
			'body'    => $body,
		);
	}

	/**
	 * Available variables for the editor UI.
	 *
	 * @return array<int, array{name: string, label: string, description: string}>
	 */
	public function variable_catalog(): array {
		$catalog = array(
			array(
				'name'        => 'customer_first_name',
				'label'       => __( 'Customer first name', 'woocommerce-review-reminder' ),
				'description' => __( 'First name of the customer.', 'woocommerce-review-reminder' ),
			),
			array(
				'name'        => 'customer_last_name',
				'label'       => __( 'Customer last name', 'woocommerce-review-reminder' ),
				'description' => __( 'Last name of the customer.', 'woocommerce-review-reminder' ),
			),
			array(
				'name'        => 'customer_name',
				'label'       => __( 'Customer name', 'woocommerce-review-reminder' ),
				'description' => __( 'Full name of the customer.', 'woocommerce-review-reminder' ),
			),
			array(
				'name'        => 'customer_email',
				'label'       => __( 'Customer email', 'woocommerce-review-reminder' ),
				'description' => __( 'Email address of the customer.', 'woocommerce-review-reminder' ),
			),
			array(
				'name'        => 'order_number',
				'label'       => __( 'Order number', 'woocommerce-review-reminder' ),
				'description' => __( 'Number of the order.', 'woocommerce-review-reminder' ),
			),
			array(
				'name'        => 'order_date',
				'label'       => __( 'Order date', 'woocommerce-review-reminder' ),
				'description' => __( 'Date the order was placed.', 'woocommerce-review-reminder' ),
			),
			array(
				'name'        => 'product_name',
				'label'       => __( 'Product name', 'woocommerce-review-reminder' ),
				'description' => __( 'Name of the purchased product.', 'woocommerce-review-reminder' ),
			),
			array(
				'name'        => 'product_url',
				'label'       => __( 'Product URL', 'woocommerce-review-reminder' ),
				'description' => __( 'Link to the purchased product.', 'woocommerce-review-reminder' ),
			),
			array(
				'name'        => 'product_image',
				'label'       => __( 'Product image', 'woocommerce-review-reminder' ),
				'description' => __( 'Image of the purchased product.', 'woocommerce-review-reminder' ),
			),
			array(
				'name'        => 'review_url',
				'label'       => __( 'Review link', 'woocommerce-review-reminder' ),
				'description' => __( 'Link to leave a review.', 'woocommerce-review-reminder' ),
			),
			array(
				'name'        => 'store_name',
				'label'       => __( 'Store name', 'woocommerce-review-reminder' ),
				'description' => __( 'Name of your store.', 'woocommerce-review-reminder' ),
			),
			array(
				'name'        => 'store_url',
				'label'       => __( 'Store URL', 'woocommerce-review-reminder' ),
				'description' => __( 'Link to your store.', 'woocommerce-review-reminder' ),
			),
			array(
				'name'        => 'unsubscribe_url',
				'label'       => __( 'Unsubscribe link', 'woocommerce-review-reminder' ),
				'description' => __( 'Link to opt out of review emails.', 'woocommerce-review-reminder' ),
			),
		);

		/**
		 * Filter the variable catalog shown in the email editor.
		 *
		 * @param array<int, array{name: string, label: string, description: string}> $catalog Variable list.
		 */
		return apply_filters( 'wrr_email_variables', $catalog );
	}

	/**
	 * Wrap the rendered body in the email shell (header + footer).
	 *
	 * @param string               $inner   Rendered inner content.
	 * @param array<string, mixed> $context Render context.
	 * @return string
	 */
	private function wrap( string $inner, array $context ): string {
		$store_name      = esc_html( (string) ( $context['store_name'] ?? get_bloginfo( 'name' ) ) );
		$store_url       = esc_url( (string) ( $context['store_url'] ?? home_url( '/' ) ) );
		$unsubscribe_url = esc_url( (string) ( $context['unsubscribe_url'] ?? '' ) );
		$footer_text     = sprintf(
			/* translators: %s: store name. */
			__( 'You are receiving this email because you purchased from %s. Unsubscribe anytime.', 'woocommerce-review-reminder' ),
			$store_name
		);

		$unsubscribe = '';
		if ( '' !== $unsubscribe_url ) {
			$unsubscribe = sprintf(
				'<p style="margin:16px 0 0;font-size:12px;line-height:1.5;color:#8b8d98;">
					<a href="%1$s" style="color:#8b8d98;text-decoration:underline;">%2$s</a>
				</p>',
				$unsubscribe_url,
				esc_html__( "Don't want to receive review reminders? Unsubscribe", 'woocommerce-review-reminder' )
			);
		}

		/**
		 * Filter the sender label shown in the email header.
		 *
		 * @param string $label Label.
		 */
		$sender = apply_filters( 'wrr_email_sender_label', $store_name );

		return sprintf(
			'<!DOCTYPE html>
			<html lang="%s">
			<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>%s</title></head>
			<body style="margin:0;padding:0;background-color:#f6f7f9;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
				<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" style="background-color:#f6f7f9;padding:32px 16px;">
					<tr><td align="center">
						<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%%;background-color:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e7e8ee;">
							<tr>
								<td style="padding:28px 32px 8px;border-bottom:1px solid #f0f1f4;">
									<a href="%s" style="font-size:16px;font-weight:700;color:#18181b;text-decoration:none;letter-spacing:-0.01em;">%s</a>
								</td>
							</tr>
							<tr>
								<td style="padding:32px;font-size:15px;line-height:1.7;color:#3f3f46;">
									%s
								</td>
							</tr>
							<tr>
								<td style="padding:8px 32px 28px;border-top:1px solid #f0f1f4;color:#8b8d98;">
									<p style="margin:16px 0 0;font-size:12px;line-height:1.6;">%s</p>
									%s
								</td>
							</tr>
						</table>
					</td></tr>
				</table>
			</body>
			</html>',
			esc_attr( get_locale() ),
			esc_html( $context['subject'] ?? '' ),
			$store_url,
			$sender,
			$inner,
			esc_html( $footer_text ),
			$unsubscribe
		);
	}
}
