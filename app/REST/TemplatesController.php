<?php
/**
 * Email template REST endpoints.
 *
 * @package WooCommerceReviewReminder\REST
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\REST;

use WooCommerceReviewReminder\Emails\TemplateRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class TemplatesController
 */
final class TemplatesController extends RestController {

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			RestRouter::NAMESPACE,
			'/templates',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/templates/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);
	}

	/**
	 * List templates.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function index( $request ) {
		$repo = $this->service( TemplateRepository::class );

		$items = array_map(
			static function ( $template ): array {
				return $template->to_array();
			},
			$repo->all()
		);

		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * Create a template.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function create( $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			return rest_ensure_response( new \WP_Error( 'invalid_data', __( 'Invalid request body.', 'woocommerce-review-reminder' ), array( 'status' => 400 ) ) );
		}

		$repo = $this->service( TemplateRepository::class );
		$id   = $repo->create(
			array(
				'name'    => (string) ( $data['name'] ?? '' ),
				'slug'    => sanitize_title( (string) ( $data['name'] ?? 'template' ) ),
				'subject' => (string) ( $data['subject'] ?? '' ),
				'body'    => (string) ( $data['body'] ?? '' ),
			)
		);

		if ( $id <= 0 ) {
			return rest_ensure_response( new \WP_Error( 'create_failed', __( 'Could not create the template.', 'woocommerce-review-reminder' ), array( 'status' => 500 ) ) );
		}

		return rest_ensure_response(
			array(
				'id'   => $id,
				'item' => $repo->find( $id )->to_array(),
			)
		);
	}

	/**
	 * Update a template.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update( $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			return rest_ensure_response( new \WP_Error( 'invalid_data', __( 'Invalid request body.', 'woocommerce-review-reminder' ), array( 'status' => 400 ) ) );
		}

		$id   = $this->int_param( $request, 'id' );
		$repo = $this->service( TemplateRepository::class );

		if ( null === $repo->find( $id ) ) {
			return rest_ensure_response( new \WP_Error( 'not_found', __( 'Template not found.', 'woocommerce-review-reminder' ), array( 'status' => 404 ) ) );
		}

		$payload = array();
		if ( array_key_exists( 'name', $data ) ) {
			$payload['name'] = (string) $data['name'];
		}
		if ( array_key_exists( 'subject', $data ) ) {
			$payload['subject'] = (string) $data['subject'];
		}
		if ( array_key_exists( 'body', $data ) ) {
			$payload['body'] = (string) $data['body'];
		}

		$repo->update( $id, $payload );

		return rest_ensure_response( array( 'item' => $repo->find( $id )->to_array() ) );
	}

	/**
	 * Delete a template.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete( $request ) {
		$id   = $this->int_param( $request, 'id' );
		$repo = $this->service( TemplateRepository::class );

		if ( null === $repo->find( $id ) ) {
			return rest_ensure_response( new \WP_Error( 'not_found', __( 'Template not found.', 'woocommerce-review-reminder' ), array( 'status' => 404 ) ) );
		}

		$repo->delete( $id );

		return rest_ensure_response( array( 'deleted' => true ) );
	}
}
