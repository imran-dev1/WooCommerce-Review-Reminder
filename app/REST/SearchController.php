<?php
/**
 * Search endpoints for products, categories, tags and customers.
 *
 * @package WooCommerceReviewReminder\REST
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\REST;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchController
 */
final class SearchController extends RestController {

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			RestRouter::NAMESPACE,
			'/search/products',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'products' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/search/categories',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'categories' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/search/tags',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'tags' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/search/customers',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'customers' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/search/roles',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'roles' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/search/order-statuses',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'order_statuses' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/search/payment-methods',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'payment_methods' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);

		register_rest_route(
			RestRouter::NAMESPACE,
			'/search/shipping-methods',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'shipping_methods' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	/**
	 * Search products.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function products( $request ) {
		$term = $this->param( $request, 'search', '' );
		$page = $this->int_param( $request, 'page', 1 );

		$args = array(
			'post_type'      => array( 'product', 'product_variation' ),
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( '' !== $term ) {
			$args['s'] = $term;
		}

		$query = new \WP_Query( $args );

		$items = array_map(
			static function ( $post ): array {
				$product = wc_get_product( $post );
				return array(
					'id'   => $post->ID,
					'name' => $product ? $product->get_name() : $post->post_title,
				);
			},
			$query->posts
		);

		return rest_ensure_response(
			array(
				'items' => $items,
				'total' => (int) $query->found_posts,
			)
		);
	}

	/**
	 * Search product categories.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function categories( $request ) {
		$term  = $this->param( $request, 'search', '' );
		$items = $this->taxonomy_terms( 'product_cat', $term );
		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * Search product tags.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function tags( $request ) {
		$term  = $this->param( $request, 'search', '' );
		$items = $this->taxonomy_terms( 'product_tag', $term );
		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * Search customers.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function customers( $request ) {
		$term = $this->param( $request, 'search', '' );

		$args = array(
			'role__in' => array( 'customer', 'subscriber' ),
			'number'   => 20,
			'orderby'  => 'display_name',
			'order'    => 'ASC',
		);

		if ( '' !== $term ) {
			$args['search']         = '*' . $term . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'user_nicename', 'display_name' );
		}

		$users = get_users( $args );

		$items = array_map(
			static function ( \WP_User $user ): array {
				return array(
					'id'    => $user->ID,
					'name'  => $user->display_name,
					'email' => $user->user_email,
				);
			},
			$users
		);

		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * List customer roles.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function roles( $request ) {
		$items = array();
		foreach ( wp_roles()->roles as $key => $role ) {
			$items[] = array(
				'id'   => $key,
				'name' => $role['name'],
			);
		}
		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * List WooCommerce order statuses.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function order_statuses( $request ) {
		$items = array();
		if ( function_exists( 'wc_get_order_statuses' ) ) {
			foreach ( wc_get_order_statuses() as $key => $label ) {
				$items[] = array(
					'id'   => str_replace( 'wc-', '', $key ),
					'name' => $label,
				);
			}
		}
		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * List payment gateways.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function payment_methods( $request ) {
		$items = array();
		if ( function_exists( 'WC' ) && WC()->payment_gateways ) {
			foreach ( WC()->payment_gateways->payment_gateways() as $gateway ) {
				if ( 'yes' !== $gateway->enabled ) {
					continue;
				}
				$items[] = array(
					'id'   => $gateway->id,
					'name' => $gateway->get_title(),
				);
			}
		}
		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * List shipping methods.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function shipping_methods( $request ) {
		$items = array();
		if ( function_exists( 'WC' ) && WC()->shipping ) {
			foreach ( WC()->shipping()->get_shipping_methods() as $method ) {
				if ( 'yes' !== $method->enabled ) {
					continue;
				}
				$items[] = array(
					'id'   => $method->id,
					'name' => $method->get_method_title(),
				);
			}
		}
		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * Fetch terms for a taxonomy with a name filter.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $term     Search term.
	 * @return array<int, array{id: int, name: string}>
	 */
	private function taxonomy_terms( string $taxonomy, string $term ): array {
		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => 20,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);

		if ( '' !== $term ) {
			$args['name__like'] = $term;
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return array_map(
			static function ( $term ): array {
				return array(
					'id'   => (int) $term->term_id,
					'name' => $term->name,
				);
			},
			$terms
		);
	}
}
