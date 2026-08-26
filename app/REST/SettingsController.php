<?php
/**
 * Settings REST endpoints.
 *
 * @package WooCommerceReviewReminder\REST
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\REST;

use WooCommerceReviewReminder\Core\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Class SettingsController
 */
final class SettingsController extends RestController {

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			RestRouter::NAMESPACE,
			'/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_settings' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/settings/onboarding',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'complete_onboarding' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/settings/cron',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'cron_status' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * Return the full settings payload.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_settings( $request ) {
		$config = $this->service( Config::class );

		return rest_ensure_response(
			array(
				'settings'        => $config->all(),
				'onboarding_done' => (bool) get_option( 'wrr_onboarding_complete', false ),
				'store_timezone'  => wp_timezone_string(),
			)
		);
	}

	/**
	 * Save settings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_settings( $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			return rest_ensure_response( new \WP_Error( 'invalid_data', __( 'Invalid request body.', 'woocommerce-review-reminder' ), array( 'status' => 400 ) ) );
		}

		$config    = $this->service( Config::class );
		$sanitized = $this->sanitize_settings( $data );

		$config->set( $sanitized );

		do_action( 'wrr_settings_saved', $sanitized );

		return rest_ensure_response(
			array(
				'saved'    => true,
				'settings' => $config->all(),
			)
		);
	}

	/**
	 * Mark onboarding as complete.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function complete_onboarding( $request ) {
		update_option( 'wrr_onboarding_complete', true, false );
		return rest_ensure_response( array( 'onboarding_done' => true ) );
	}

	/**
	 * Cron status for the Advanced settings tab.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function cron_status( $request ) {
		$manager = $this->service( \WooCommerceReviewReminder\Cron\CronManager::class );
		return rest_ensure_response( $manager->status() );
	}

	/**
	 * Sanitize a settings payload against the known schema.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	private function sanitize_settings( array $data ): array {
		$config   = $this->service( Config::class );
		$defaults = $config->defaults();

		$clean = array();

		foreach ( $defaults as $group => $fields ) {
			if ( ! isset( $data[ $group ] ) || ! is_array( $data[ $group ] ) ) {
				continue;
			}
			foreach ( $fields as $key => $default ) {
				if ( ! array_key_exists( $key, $data[ $group ] ) ) {
					continue;
				}
				$value                   = $data[ $group ][ $key ];
				$clean[ $group ][ $key ] = $this->sanitize_value( $value, $default );
			}
		}

		return $clean;
	}

	/**
	 * Sanitize a single setting value based on the default type.
	 *
	 * @param mixed $value Value.
	 * @param mixed $default Default.
	 * @return mixed
	 */
	private function sanitize_value( $value, $default ) {
		if ( is_bool( $default ) ) {
			return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
		}
		if ( is_int( $default ) ) {
			return absint( $value );
		}
		if ( is_float( $default ) ) {
			return (float) $value;
		}
		if ( is_array( $default ) ) {
			return array_map( 'sanitize_text_field', (array) $value );
		}
		return sanitize_text_field( (string) $value );
	}
}
