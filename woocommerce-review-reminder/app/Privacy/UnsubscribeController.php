<?php
/**
 * Unsubscribe page and link handling.
 *
 * @package WooCommerceReviewReminder\Privacy
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Privacy;

use WooCommerceReviewReminder\Analytics\EventRepository;
use WooCommerceReviewReminder\Core\Config;
use WooCommerceReviewReminder\Core\Logger;
use WooCommerceReviewReminder\Queue\RequestRepository;
use WooCommerceReviewReminder\Queue\ReviewRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Class UnsubscribeController
 */
final class UnsubscribeController {

	/**
	 * Suppression repository.
	 *
	 * @var SuppressionRepository
	 */
	private SuppressionRepository $suppressions;

	/**
	 * Request repository.
	 *
	 * @var RequestRepository
	 */
	private RequestRepository $requests;

	/**
	 * Event repository.
	 *
	 * @var EventRepository
	 */
	private EventRepository $events;

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
	 * UnsubscribeController constructor.
	 *
	 * @param SuppressionRepository $suppressions Suppression repository.
	 * @param RequestRepository     $requests     Request repository.
	 * @param EventRepository       $events       Event repository.
	 * @param Config                $config       Config.
	 * @param Logger                $logger       Logger.
	 */
	public function __construct(
		SuppressionRepository $suppressions,
		RequestRepository $requests,
		EventRepository $events,
		Config $config,
		Logger $logger
	) {
		$this->suppressions = $suppressions;
		$this->requests     = $requests;
		$this->events       = $events;
		$this->config       = $config;
		$this->logger       = $logger;
	}

	/**
	 * Register the front-end query var handler.
	 */
	public function register(): void {
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'handle' ) );
	}

	/**
	 * Declare the unsubscribe query variable.
	 *
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public function add_query_var( array $vars ): array {
		$vars[] = 'wrr_unsub';
		return $vars;
	}

	/**
	 * Build the unsubscribe URL for a request.
	 *
	 * @param ReviewRequest $request Request.
	 * @return string
	 */
	public function unsubscribe_url( ReviewRequest $request ): string {
		return add_query_arg( 'wrr_unsub', rawurlencode( $request->token() ), home_url( '/' ) );
	}

	/**
	 * Handle unsubscribe requests.
	 */
	public function handle(): void {
		$token = (string) get_query_var( 'wrr_unsub' );
		if ( '' === $token ) {
			return;
		}

		$request = $this->requests->find_by_token( $token );

		$email = $request ? $request->customer_email() : '';

		// Only suppress when we can tie the token to a real request.
		if ( '' !== $email ) {
			$this->suppressions->add( $email, 'unsubscribed' );
			$this->requests->update(
				$request->id(),
				array( 'status' => ReviewRequest::STATUS_CANCELLED )
			);
			$this->events->record(
				'unsubscribed',
				array(
					'request_id'     => $request->id(),
					'campaign_id'    => $request->campaign_id(),
					'order_id'       => $request->order_id(),
					'customer_email' => $email,
				)
			);
			do_action( 'wrr_customer_unsubscribed', $email, $request->id() );
		}

		$this->render_confirmation( '' !== $email );
		exit;
	}

	/**
	 * Render the unsubscribe confirmation page.
	 *
	 * @param bool $success Whether the email was suppressed.
	 */
	private function render_confirmation( bool $success ): void {
		$title   = $success
			? __( 'You are unsubscribed', 'woocommerce-review-reminder' )
			: __( 'Unsubscribe', 'woocommerce-review-reminder' );
		$message = $success
			? __( 'You will no longer receive review request emails from this store.', 'woocommerce-review-reminder' )
			: __( 'We could not find a review request for this link. It may already have been processed.', 'woocommerce-review-reminder' );

		$html = '<!DOCTYPE html><html lang="' . esc_attr( get_locale() ) . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . esc_html( $title ) . '</title></head>'
			. '<body style="margin:0;padding:0;background:#f6f7f9;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Arial,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;">'
			. '<div style="background:#fff;border:1px solid #e7e8ee;border-radius:12px;padding:48px;max-width:420px;margin:24px;text-align:center;">'
			. '<h1 style="font-size:20px;margin:0 0 12px;color:#18181b;">' . esc_html( $title ) . '</h1>'
			. '<p style="font-size:15px;line-height:1.6;color:#52525b;margin:0 0 24px;">' . esc_html( $message ) . '</p>'
			. '<a href="' . esc_url( home_url( '/' ) ) . '" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;font-weight:600;padding:12px 28px;border-radius:8px;font-size:14px;">' . esc_html__( 'Back to store', 'woocommerce-review-reminder' ) . '</a>'
			. '</div></body></html>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
	}
}
