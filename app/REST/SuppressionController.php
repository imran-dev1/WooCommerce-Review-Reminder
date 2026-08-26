<?php
/**
 * Suppression list REST endpoints.
 *
 * @package WooCommerceReviewReminder\REST
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\REST;

use WooCommerceReviewReminder\Privacy\SuppressionRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class SuppressionController
 */
final class SuppressionController extends RestController {

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			RestRouter::NAMESPACE,
			'/suppressions',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'add' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/suppressions/(?P<email>[^/]+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'remove' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * Paginated suppression list.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function index( $request ) {
		$repo = $this->service( SuppressionRepository::class );

		$result = $repo->paginate(
			$this->int_param( $request, 'per_page', 20 ),
			$this->int_param( $request, 'page', 1 )
		);

		return rest_ensure_response(
			array(
				'items' => $result['items'],
				'total' => $result['total'],
				'pages' => $result['pages'],
			)
		);
	}

	/**
	 * Suppress an email manually.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function add( $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			return rest_ensure_response( new \WP_Error( 'invalid_data', __( 'Invalid request body.', 'woocommerce-review-reminder' ), array( 'status' => 400 ) ) );
		}

		$email = sanitize_email( (string) ( $data['email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			return rest_ensure_response( new \WP_Error( 'invalid_email', __( 'Please provide a valid email address.', 'woocommerce-review-reminder' ), array( 'status' => 400 ) ) );
		}

		$repo  = $this->service( SuppressionRepository::class );
		$added = $repo->add( $email, 'manual' );

		return rest_ensure_response( array( 'suppressed' => $added || $repo->is_suppressed( $email ) ) );
	}

	/**
	 * Remove an email from the suppression list.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function remove( $request ) {
		$email = sanitize_email( rawurldecode( (string) $request->get_param( 'email' ) ) );
		if ( ! is_email( $email ) ) {
			return rest_ensure_response( new \WP_Error( 'invalid_email', __( 'Please provide a valid email address.', 'woocommerce-review-reminder' ), array( 'status' => 400 ) ) );
		}

		$repo = $this->service( SuppressionRepository::class );
		$repo->remove( $email );

		return rest_ensure_response( array( 'removed' => true ) );
	}
}
