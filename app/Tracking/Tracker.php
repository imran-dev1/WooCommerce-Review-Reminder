<?php
/**
 * Email open/click tracking endpoints.
 *
 * @package WooCommerceReviewReminder\Tracking
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Tracking;

use WooCommerceReviewReminder\Analytics\EventRepository;
use WooCommerceReviewReminder\Core\Logger;
use WooCommerceReviewReminder\Queue\RequestRepository;
use WooCommerceReviewReminder\Queue\ReviewRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Class Tracker
 */
final class Tracker {

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
	 * Logger.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Tracker constructor.
	 *
	 * @param RequestRepository $requests Requests repository.
	 * @param EventRepository   $events   Events repository.
	 * @param Logger            $logger   Logger.
	 */
	public function __construct( RequestRepository $requests, EventRepository $events, Logger $logger ) {
		$this->requests = $requests;
		$this->events   = $events;
		$this->logger   = $logger;
	}

	/**
	 * Register query var handling on init.
	 */
	public function register(): void {
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle' ) );
	}

	/**
	 * Declare the plugin's query variables.
	 *
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'wrr_open';
		$vars[] = 'wrr_click';
		$vars[] = 'wrr_token';
		return $vars;
	}

	/**
	 * Handle tracking requests.
	 */
	public function handle(): void {
		if ( get_query_var( 'wrr_open' ) ) {
			$this->handle_open( (string) get_query_var( 'wrr_open' ) );
		}

		if ( get_query_var( 'wrr_click' ) ) {
			$this->handle_click( (string) get_query_var( 'wrr_click' ), (string) get_query_var( 'wrr_token' ) );
		}
	}

	/**
	 * Build the open-tracking pixel URL for a request.
	 *
	 * @param ReviewRequest $request Request.
	 * @return string
	 */
	public function open_url( ReviewRequest $request ): string {
		return add_query_arg( 'wrr_open', rawurlencode( $request->token() ), home_url( '/' ) );
	}

	/**
	 * Build the click-tracking URL for a request.
	 *
	 * @param ReviewRequest $request Request.
	 * @param string        $target  Destination URL.
	 * @return string
	 */
	public function click_url( ReviewRequest $request, string $target ): string {
		return add_query_arg(
			array(
				'wrr_click' => rawurlencode( $request->token() ),
				'wrr_token' => rawurlencode( base64_encode( $target ) ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Serve a transparent GIF and record an open.
	 *
	 * @param string $token Request token.
	 */
	private function handle_open( string $token ): void {
		nocache_headers();

		$this->record_once( $token, 'opened', 'opened_at' );

		// 1x1 transparent GIF.
		header( 'Content-Type: image/gif' );
		echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Record a click and redirect.
	 *
	 * @param string $token  Request token.
	 * @param string $target Base64-encoded destination.
	 */
	private function handle_click( string $token, string $target ): void {
		nocache_headers();

		$decoded = base64_decode( $target, true );

		$this->record_once( $token, 'clicked', 'clicked_at' );

		if ( $decoded && wp_http_validate_url( $decoded ) ) {
			wp_safe_redirect( $decoded );
			exit;
		}

		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	/**
	 * Record a tracking event only once per request.
	 *
	 * @param string $token    Request token.
	 * @param string $event    Event type.
	 * @param string $column   Column to update.
	 */
	private function record_once( string $token, string $event, string $column ): void {
		$request = $this->requests->find_by_token( $token );
		if ( null === $request ) {
			return;
		}

		if ( '' !== $request->{$column}() ) {
			return; // Already tracked.
		}

		$this->requests->update( $request->id(), array( $column => current_time( 'mysql' ) ) );

		$this->events->record(
			$event,
			array(
				'request_id'     => $request->id(),
				'campaign_id'    => $request->campaign_id(),
				'order_id'       => $request->order_id(),
				'customer_email' => $request->customer_email(),
			)
		);

		do_action( 'wrr_email_' . $event, $request->id() );
	}
}
