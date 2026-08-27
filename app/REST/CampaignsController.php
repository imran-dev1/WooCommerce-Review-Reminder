<?php
/**
 * Campaign REST endpoints.
 *
 * @package WooCommerceReviewReminder\REST
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\REST;

use WooCommerceReviewReminder\Campaigns\CampaignService;
use WooCommerceReviewReminder\Campaigns\Repository\CampaignRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class CampaignsController
 */
final class CampaignsController extends RestController {

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			RestRouter::NAMESPACE,
			'/campaigns',
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
			'/campaigns/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
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

		register_rest_route(
			RestRouter::NAMESPACE,
			'/campaigns/(?P<id>\d+)/activate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'activate' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/campaigns/(?P<id>\d+)/pause',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'pause' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/campaigns/(?P<id>\d+)/duplicate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'duplicate' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * List campaigns with stats.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function index( $request ) {
		$repo      = $this->service( CampaignRepository::class );
		$analytics = $this->service( \WooCommerceReviewReminder\Analytics\AnalyticsRepository::class );

		$status = $this->param( $request, 'status', '' );

		$campaigns = '' !== $status
			? $repo->find_by_status( $status, 200, 0 )
			: $repo->all( 200, 0 );

		$comparison = $analytics->campaign_comparison();
		$stats_map  = array();
		foreach ( $comparison as $stat ) {
			$stats_map[ $stat['id'] ] = $stat;
		}

		$items = array_map(
			function ( $campaign ) use ( $stats_map ): array {
				$stat = $stats_map[ $campaign->id() ] ?? array(
					'sent'            => 0,
					'reviews'         => 0,
					'conversion_rate' => 0.0,
				);
				return array_merge(
					$campaign->to_array(),
					array(
						'config'     => $campaign->config()->to_array(),
						'stats'      => array(
							'sent'            => $stat['sent'],
							'reviews'         => $stat['reviews'],
							'conversion_rate' => $stat['conversion_rate'],
						),
						'created_at' => $campaign->created_at(),
						'updated_at' => $campaign->updated_at(),
					)
				);
			},
			$campaigns
		);

		return rest_ensure_response(
			array(
				'items'  => $items,
				'total'  => $repo->count( '' !== $status ? $status : null ),
				'counts' => array(
					'all'    => $repo->count(),
					'active' => $repo->count( 'active' ),
					'paused' => $repo->count( 'paused' ),
					'draft'  => $repo->count( 'draft' ),
				),
			)
		);
	}

	/**
	 * Create a campaign.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function create( $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			return rest_ensure_response( new \WP_Error( 'invalid_data', __( 'Invalid request body.', 'woocommerce-review-reminder' ), array( 'status' => 400 ) ) );
		}

		$name = sanitize_text_field( $data['name'] ?? '' );
		if ( '' === $name ) {
			return rest_ensure_response( new \WP_Error( 'missing_name', __( 'Campaign name is required.', 'woocommerce-review-reminder' ), array( 'status' => 400 ) ) );
		}

		$status = sanitize_key( (string) ( $data['status'] ?? 'draft' ) );
		if ( ! in_array( $status, array( 'active', 'paused', 'draft', 'archived' ), true ) ) {
			$status = 'draft';
		}

		/** @var CampaignService $service */
		$service = $this->service( CampaignService::class );

		$id = $service->create(
			array(
				'name'        => $name,
				'description' => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
				'status'      => $status,
				'config'      => $data['config'] ?? array(),
			)
		);

		if ( $id <= 0 ) {
			return rest_ensure_response( new \WP_Error( 'create_failed', __( 'Could not create the campaign.', 'woocommerce-review-reminder' ), array( 'status' => 500 ) ) );
		}

		return rest_ensure_response(
			array(
				'id'   => $id,
				'item' => $this->serialize_campaign( $id ),
			)
		);
	}

	/**
	 * Get a single campaign.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function show( $request ) {
		$repo     = $this->service( CampaignRepository::class );
		$id       = $this->int_param( $request, 'id' );
		$campaign = $repo->find( $id );

		if ( null === $campaign ) {
			return rest_ensure_response( new \WP_Error( 'not_found', __( 'Campaign not found.', 'woocommerce-review-reminder' ), array( 'status' => 404 ) ) );
		}

		return rest_ensure_response( $this->serialize_campaign( $id ) );
	}

	/**
	 * Update a campaign.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update( $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			return rest_ensure_response( new \WP_Error( 'invalid_data', __( 'Invalid request body.', 'woocommerce-review-reminder' ), array( 'status' => 400 ) ) );
		}

		$id      = $this->int_param( $request, 'id' );
		$service = $this->service( CampaignService::class );

		$campaign = $service->find( $id );
		if ( null === $campaign ) {
			return rest_ensure_response( new \WP_Error( 'not_found', __( 'Campaign not found.', 'woocommerce-review-reminder' ), array( 'status' => 404 ) ) );
		}

		$payload = array();
		if ( array_key_exists( 'name', $data ) ) {
			$payload['name'] = sanitize_text_field( (string) $data['name'] );
		}
		if ( array_key_exists( 'description', $data ) ) {
			$payload['description'] = sanitize_textarea_field( (string) $data['description'] );
		}
		if ( array_key_exists( 'config', $data ) ) {
			$payload['config'] = is_array( $data['config'] ) ? $data['config'] : array();
		}
		if ( array_key_exists( 'status', $data ) ) {
			$payload['status'] = sanitize_key( (string) $data['status'] );
		}

		$updated = $service->update( $id, $payload );

		if ( ! $updated ) {
			return rest_ensure_response( new \WP_Error( 'update_failed', __( 'Could not update the campaign.', 'woocommerce-review-reminder' ), array( 'status' => 500 ) ) );
		}

		return rest_ensure_response( $this->serialize_campaign( $id ) );
	}

	/**
	 * Delete a campaign.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete( $request ) {
		$id      = $this->int_param( $request, 'id' );
		$service = $this->service( CampaignService::class );

		if ( null === $service->find( $id ) ) {
			return rest_ensure_response( new \WP_Error( 'not_found', __( 'Campaign not found.', 'woocommerce-review-reminder' ), array( 'status' => 404 ) ) );
		}

		$service->delete( $id );

		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * Activate a campaign.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function activate( $request ) {
		$id      = $this->int_param( $request, 'id' );
		$service = $this->service( CampaignService::class );

		if ( ! $service->activate( $id ) ) {
			return rest_ensure_response( new \WP_Error( 'activate_failed', __( 'Could not activate the campaign.', 'woocommerce-review-reminder' ), array( 'status' => 500 ) ) );
		}

		return rest_ensure_response(
			array(
				'activated' => true,
				'item'      => $this->serialize_campaign( $id ),
			)
		);
	}

	/**
	 * Pause a campaign.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function pause( $request ) {
		$id      = $this->int_param( $request, 'id' );
		$service = $this->service( CampaignService::class );

		if ( ! $service->pause( $id ) ) {
			return rest_ensure_response( new \WP_Error( 'pause_failed', __( 'Could not pause the campaign.', 'woocommerce-review-reminder' ), array( 'status' => 500 ) ) );
		}

		return rest_ensure_response(
			array(
				'paused' => true,
				'item'   => $this->serialize_campaign( $id ),
			)
		);
	}

	/**
	 * Duplicate a campaign.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function duplicate( $request ) {
		$id      = $this->int_param( $request, 'id' );
		$service = $this->service( CampaignService::class );

		$new_id = $service->duplicate( $id );
		if ( $new_id <= 0 ) {
			return rest_ensure_response( new \WP_Error( 'duplicate_failed', __( 'Could not duplicate the campaign.', 'woocommerce-review-reminder' ), array( 'status' => 500 ) ) );
		}

		return rest_ensure_response(
			array(
				'id'   => $new_id,
				'item' => $this->serialize_campaign( $new_id ),
			)
		);
	}

	/**
	 * Serialize a campaign for the API.
	 *
	 * @param int $id Campaign id.
	 * @return array<string, mixed>
	 */
	private function serialize_campaign( int $id ): array {
		$repo     = $this->service( CampaignRepository::class );
		$campaign = $repo->find( $id );

		if ( null === $campaign ) {
			return array();
		}

		$analytics = $this->service( \WooCommerceReviewReminder\Analytics\AnalyticsRepository::class );
		$stat      = null;
		foreach ( $analytics->campaign_comparison() as $row ) {
			if ( (int) $row['id'] === $id ) {
				$stat = $row;
				break;
			}
		}

		return array_merge(
			$campaign->to_array(),
			array(
				'config' => $campaign->config()->to_array(),
				'stats'  => $stat ?? array(
					'sent'            => 0,
					'reviews'         => 0,
					'conversion_rate' => 0.0,
				),
			)
		);
	}
}
