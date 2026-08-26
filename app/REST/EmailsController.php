<?php
/**
 * Email test/preview REST endpoints.
 *
 * @package WooCommerceReviewReminder\REST
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\REST;

use WooCommerceReviewReminder\Emails\EmailManager;
use WooCommerceReviewReminder\Emails\EmailRenderer;

defined( 'ABSPATH' ) || exit;

/**
 * Class EmailsController
 */
final class EmailsController extends RestController {

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			RestRouter::NAMESPACE,
			'/emails/variables',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'variables' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/emails/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/emails/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'test' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * Variable catalog for the email editor.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function variables( $request ) {
		$renderer = $this->service( EmailRenderer::class );
		return rest_ensure_response( array( 'items' => $renderer->variable_catalog() ) );
	}

	/**
	 * Render a live preview.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function preview( $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			return rest_ensure_response( new \WP_Error( 'invalid_data', __( 'Invalid request body.', 'woocommerce-review-reminder' ), array( 'status' => 400 ) ) );
		}

		$subject = (string) ( $data['subject'] ?? '' );
		$body    = (string) ( $data['body'] ?? '' );

		$manager = $this->service( EmailManager::class );
		$result  = $manager->preview( $subject, $body );

		return rest_ensure_response(
			array(
				'subject' => $result['subject'],
				'body'    => $result['body'],
			)
		);
	}

	/**
	 * Send a test email.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function test( $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			return rest_ensure_response( new \WP_Error( 'invalid_data', __( 'Invalid request body.', 'woocommerce-review-reminder' ), array( 'status' => 400 ) ) );
		}

		$to      = sanitize_email( (string) ( $data['to'] ?? '' ) );
		$subject = (string) ( $data['subject'] ?? '' );
		$body    = (string) ( $data['body'] ?? '' );

		$manager = $this->service( EmailManager::class );
		$result  = $manager->send_test( $to, $subject, $body );

		if ( ! $result->success ) {
			return rest_ensure_response(
				new \WP_Error(
					'test_failed',
					$result->message,
					array( 'status' => 400 )
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => sprintf(
					/* translators: %s: email address. */
					__( 'Test email sent to %s.', 'woocommerce-review-reminder' ),
					$to
				),
			)
		);
	}
}
