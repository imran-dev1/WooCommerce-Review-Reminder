<?php
/**
 * Base REST controller with shared permission + service helpers.
 *
 * @package WooCommerceReviewReminder\REST
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\REST;

use WooCommerceReviewReminder\Core\Plugin;
use WP_REST_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Class RestController
 */
abstract class RestController extends WP_REST_Controller {

	/**
	 * Permission check shared by all plugin routes.
	 *
	 * @return bool
	 */
	public function permission_callback(): bool {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Resolve a plugin service.
	 *
	 * @param string $id Service identifier.
	 * @return object
	 */
	protected function service( string $id ) {
		return Plugin::instance()->get( $id );
	}

	/**
	 * Read a sanitized string parameter from a request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param string           $key     Key.
	 * @param string           $default Default.
	 * @return string
	 */
	protected function param( $request, string $key, string $default = '' ): string {
		$value = $request->get_param( $key );
		if ( null === $value ) {
			return $default;
		}
		if ( is_array( $value ) ) {
			return $default;
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Read a sanitized int parameter from a request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param string           $key     Key.
	 * @param int              $default Default.
	 * @return int
	 */
	protected function int_param( $request, string $key, int $default = 0 ): int {
		$value = $request->get_param( $key );
		return null === $value ? $default : absint( $value );
	}

	/**
	 * Read a boolean parameter from a request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param string           $key     Key.
	 * @param bool             $default Default.
	 * @return bool
	 */
	protected function bool_param( $request, string $key, bool $default = false ): bool {
		$value = $request->get_param( $key );
		if ( null === $value ) {
			return $default;
		}
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}
}
