<?php
/**
 * Coordinates email rendering and delivery.
 *
 * @package WooCommerceReviewReminder\Emails
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Emails;

use WooCommerceReviewReminder\Campaigns\Campaign;
use WooCommerceReviewReminder\Core\Config;
use WooCommerceReviewReminder\Core\Logger;
use WooCommerceReviewReminder\Privacy\UnsubscribeController;
use WooCommerceReviewReminder\Queue\ReviewRequest;
use WooCommerceReviewReminder\Reviews\ReviewUrl;
use WooCommerceReviewReminder\Tracking\Tracker;

defined( 'ABSPATH' ) || exit;

/**
 * Class EmailManager
 */
final class EmailManager {

	/**
	 * Renderer.
	 *
	 * @var EmailRenderer
	 */
	private EmailRenderer $renderer;

	/**
	 * Variables.
	 *
	 * @var Variables
	 */
	private Variables $variables;

	/**
	 * Config.
	 *
	 * @var Config
	 */
	private Config $config;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Tracker.
	 *
	 * @var Tracker
	 */
	private Tracker $tracker;

	/**
	 * Review URL builder.
	 *
	 * @var ReviewUrl
	 */
	private ReviewUrl $review_url;

	/**
	 * Unsubscribe controller.
	 *
	 * @var UnsubscribeController
	 */
	private UnsubscribeController $unsubscribe;

	/**
	 * EmailManager constructor.
	 *
	 * @param EmailRenderer          $renderer   Renderer.
	 * @param Variables              $variables  Variables.
	 * @param Config                 $config     Config.
	 * @param Logger                 $logger     Logger.
	 * @param Tracker                $tracker    Tracker.
	 * @param ReviewUrl              $review_url Review URL builder.
	 * @param UnsubscribeController  $unsubscribe Unsubscribe controller.
	 */
	public function __construct(
		EmailRenderer $renderer,
		Variables $variables,
		Config $config,
		Logger $logger,
		Tracker $tracker,
		ReviewUrl $review_url,
		UnsubscribeController $unsubscribe
	) {
		$this->renderer    = $renderer;
		$this->variables   = $variables;
		$this->config      = $config;
		$this->logger      = $logger;
		$this->tracker     = $tracker;
		$this->review_url  = $review_url;
		$this->unsubscribe = $unsubscribe;
	}

	/**
	 * Send a review request email.
	 *
	 * @param ReviewRequest $request  Request.
	 * @param Campaign      $campaign Campaign.
	 * @return SendResult
	 */
	public function send( ReviewRequest $request, Campaign $campaign ): SendResult {
		$context   = $this->build_context( $request, $campaign );
		$templates = $this->resolve_templates( $request, $campaign );

		$rendered = $this->renderer->render( $templates['subject'], $templates['body'], $context );

		// Inject the open-tracking pixel right before </body>.
		$pixel = $this->open_pixel( $request );
		$body  = $rendered['body'];
		$body  = str_replace( '</body>', $pixel . '</body>', $body );

		$mailer = $this->mailer();
		$result = $mailer->send(
			array(
				'to'      => $request->customer_email(),
				'subject' => $rendered['subject'],
				'body'    => $body,
			)
		);

		if ( ! $result->success ) {
			$this->logger->error(
				'Email send failed.',
				array(
					'request_id' => $request->id(),
					'error'      => $result->message,
				)
			);
		}

		/**
		 * Notify when a review request email is attempted.
		 *
		 * @param int         $request_id Request id.
		 * @param SendResult  $result     Result.
		 */
		do_action( 'wrr_email_attempted', $request->id(), $result );

		return $result;
	}

	/**
	 * Build the open-tracking pixel HTML for a request.
	 *
	 * @param ReviewRequest $request Request.
	 * @return string
	 */
	private function open_pixel( ReviewRequest $request ): string {
		$url = $this->tracker->open_url( $request );

		return '<img src="' . esc_url( $url ) . '" width="1" height="1" alt="" style="display:none;max-height:1px;overflow:hidden;" />';
	}

	/**
	 * Send a test email.
	 *
	 * @param string               $to       Recipient.
	 * @param string               $subject  Subject template.
	 * @param string               $body     Body template.
	 * @return SendResult
	 */
	public function send_test( string $to, string $subject, string $body ): SendResult {
		if ( ! is_email( $to ) ) {
			return SendResult::fail( __( 'Please enter a valid email address.', 'woocommerce-review-reminder' ) );
		}

		$context  = SampleContext::build();
		$rendered = $this->renderer->render( $subject, $body, $context );

		$result = $this->mailer()->send(
			array(
				'to'      => $to,
				'subject' => $rendered['subject'],
				'body'    => $rendered['body'],
			)
		);

		if ( $result->success ) {
			$this->logger->info( 'Test email sent.', array( 'to' => $to ) );
		}

		return $result;
	}

	/**
	 * Preview rendered subject + body for a set of templates.
	 *
	 * @param string $subject Subject template.
	 * @param string $body    Body template.
	 * @return array{subject: string, body: string}
	 */
	public function preview( string $subject, string $body ): array {
		return $this->renderer->render( $subject, $body, SampleContext::build() );
	}

	/**
	 * Build the render context for a request.
	 *
	 * @param ReviewRequest $request  Request.
	 * @param Campaign      $campaign Campaign.
	 * @return array<string, mixed>
	 */
	public function build_context( ReviewRequest $request, Campaign $campaign ): array {
		$order    = wc_get_order( $request->order_id() );
		$products = $this->product_context( $request, $order );
		$customer = $this->customer_context( $request, $order );
		$primary  = $products[0] ?? array();

		$review_target = $this->review_url->for_request( $request );
		$review_url    = $this->tracker->click_url( $request, $review_target );
		$unsub_url     = $this->unsubscribe->unsubscribe_url( $request );

		$context = array(
			'customer'        => $customer,
			'order'           => $this->order_context( $request, $order ),
			'product'         => $primary,
			'products'        => $products,
			'review_url'      => $review_url,
			'unsubscribe_url' => $unsub_url,
			'store_name'      => get_bloginfo( 'name' ),
			'store_url'       => home_url( '/' ),
			'campaign'        => array(
				'id'   => $campaign->id(),
				'name' => $campaign->name(),
			),
		);

		/**
		 * Filter the render context for review request emails.
		 *
		 * @param array<string, mixed> $context  Context.
		 * @param ReviewRequest        $request  Request.
		 * @param Campaign             $campaign Campaign.
		 */
		return apply_filters( 'wrr_email_context', $context, $request, $campaign );
	}

	/**
	 * Resolve subject/body templates for a request.
	 *
	 * @param ReviewRequest $request  Request.
	 * @param Campaign      $campaign Campaign.
	 * @return array{subject: string, body: string}
	 */
	private function resolve_templates( ReviewRequest $request, Campaign $campaign ): array {
		$config      = $campaign->config();
		$is_followup = 'followup' === $request->request_type() && $config->followup_enabled();

		$subject = $is_followup && '' !== $config->followup_subject()
			? $config->followup_subject()
			: $config->email_subject();

		$body = $is_followup && '' !== $config->followup_body()
			? $config->followup_body()
			: $config->email_body();

		// Pull from a saved template when no inline body is set.
		$template_id = $config->email_template_id();
		if ( '' === trim( $body ) && $template_id ) {
			$repo     = new TemplateRepository( $this->db_schema(), $this->logger );
			$template = $repo->find( $template_id );
			if ( $template ) {
				$subject = $subject ? $subject : $template->subject();
				$body    = $template->body();
			}
		}

		if ( '' === trim( $body ) ) {
			$body = $this->default_body();
		}

		return array(
			'subject' => $subject,
			'body'    => $body,
		);
	}

	/**
	 * Resolve the configured mailer.
	 *
	 * @return MailerInterface
	 */
	private function mailer(): MailerInterface {
		$provider = (string) $this->config->get( 'email.provider', 'WordPress' );

		if ( 'woocommerce' === $provider && function_exists( 'wc_mail' ) ) {
			return new WooCommerceMailer( $this->config );
		}

		return new WordpressMailer( $this->config );
	}

	/**
	 * Product context for the email.
	 *
	 * @param ReviewRequest $request Request.
	 * @param \WC_Order|false $order Order or false.
	 * @return array<int, array<string, string>>
	 */
	private function product_context( ReviewRequest $request, $order ): array {
		$products = array();

		if ( $request->product_id() > 0 ) {
			$products[] = $this->single_product_context( $request->product_id() );
			return $products;
		}

		if ( $order ) {
			foreach ( $order->get_items() as $item ) {
				$product_id = (int) $item->get_product_id();
				if ( $product_id > 0 ) {
					$products[] = $this->single_product_context( $product_id );
				}
			}
		}

		return $products;
	}

	/**
	 * Context for one product.
	 *
	 * @param int $product_id Product id.
	 * @return array<string, string>
	 */
	private function single_product_context( int $product_id ): array {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array(
				'name'  => sprintf( '#%d', $product_id ),
				'url'   => '',
				'image' => '',
			);
		}

		$image    = '';
		$image_id = $product->get_image_id();
		if ( $image_id ) {
			$image = wp_get_attachment_image_url( $image_id, 'medium' );
			$image = false !== $image ? $image : '';
		}

		$permalink = get_permalink( $product_id );
		$url       = $permalink ? $permalink : '';

		return array(
			'name'  => wp_strip_all_tags( $product->get_name() ),
			'url'   => $url,
			'image' => $image,
		);
	}

	/**
	 * Customer context.
	 *
	 * @param ReviewRequest $request Request.
	 * @param \WC_Order|false $order Order or false.
	 * @return array<string, string>
	 */
	private function customer_context( ReviewRequest $request, $order ): array {
		$first = '';
		$last  = '';

		if ( $order ) {
			$first = (string) $order->get_billing_first_name();
			$last  = (string) $order->get_billing_last_name();
		}

		if ( '' === $first && '' === $last ) {
			$name  = $request->customer_name();
			$parts = explode( ' ', $name, 2 );
			$first = $parts[0] ?? '';
			$last  = $parts[1] ?? '';
		}

		$full = trim( $first . ' ' . $last );
		if ( '' === $full ) {
			$full = $request->customer_name();
		}

		return array(
			'first_name' => $first,
			'last_name'  => $last,
			'name'       => $full,
			'email'      => $request->customer_email(),
		);
	}

	/**
	 * Order context.
	 *
	 * @param ReviewRequest $request Request.
	 * @param \WC_Order|false $order Order or false.
	 * @return array<string, string>
	 */
	private function order_context( ReviewRequest $request, $order ): array {
		if ( $order ) {
			return array(
				'number' => $order->get_order_number() ? (string) $order->get_order_number() : (string) $order->get_id(),
				'date'   => wc_format_datetime( $order->get_date_created() ),
			);
		}

		return array(
			'number' => (string) $request->order_id(),
			'date'   => '',
		);
	}

	/**
	 * Fallback body used when a campaign has no body configured.
	 *
	 * @return string
	 */
	public function default_body(): string {
		return wp_kses_post(
			'<p>' . __( 'Hi {{customer_first_name}},', 'woocommerce-review-reminder' ) . '</p>'
			. '<p>' . __( 'Thank you for your recent purchase!', 'woocommerce-review-reminder' ) . '</p>'
			. '<p>' . __( "We'd love to hear what you think about {{product_name}}.", 'woocommerce-review-reminder' ) . '</p>'
			. '<p>' . __( 'Your feedback helps other customers make better purchasing decisions.', 'woocommerce-review-reminder' ) . '</p>'
			. '<div style="margin:28px 0;text-align:center;">'
			. '<a href="{{review_url}}" style="display:inline-block;background-color:#2563eb;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;padding:14px 32px;border-radius:8px;">' . esc_html__( 'Leave a Review', 'woocommerce-review-reminder' ) . '</a>'
			. '</div>'
			. '<p style="margin-top:24px;">' . __( 'Thank you,', 'woocommerce-review-reminder' ) . '<br>{{store_name}}</p>'
		);
	}

	/**
	 * Schema accessor helper used when resolving templates.
	 *
	 * @return \WooCommerceReviewReminder\Database\Schema
	 */
	private function db_schema() {
		return new \WooCommerceReviewReminder\Database\Schema();
	}
}
