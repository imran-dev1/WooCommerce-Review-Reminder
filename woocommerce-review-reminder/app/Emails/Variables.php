<?php
/**
 * Dynamic email variables.
 *
 * @package WooCommerceReviewReminder\Emails
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Class Variables
 */
final class Variables {

	/**
	 * Registered resolvers: variable name (without braces) => callable.
	 *
	 * @var array<string, callable>
	 */
	private array $resolvers = array();

	/**
	 * Variables constructor. Registers the built-in variable set.
	 */
	public function __construct() {
		$this->register_builtins();
	}

	/**
	 * Register a variable resolver.
	 *
	 * @param string   $name     Variable name.
	 * @param callable $resolver Callable accepting the render context array.
	 */
	public function register( string $name, callable $resolver ): void {
		$this->resolvers[ $name ] = $resolver;
	}

	/**
	 * All variable names.
	 *
	 * @return string[]
	 */
	public function names(): array {
		return array_keys( $this->resolvers );
	}

	/**
	 * Resolve a single variable.
	 *
	 * @param string               $name    Variable name.
	 * @param array<string, mixed> $context Render context.
	 * @return string
	 */
	public function resolve( string $name, array $context ): string {
		$name = trim( $name, '{}' );

		if ( isset( $this->resolvers[ $name ] ) ) {
			$value = ( $this->resolvers[ $name ] )( $context );
			return is_scalar( $value ) ? (string) $value : '';
		}

		// Fall back to context values (e.g. custom data passed by extensions).
		return isset( $context[ $name ] ) && is_scalar( $context[ $name ] ) ? (string) $context[ $name ] : '';
	}

	/**
	 * Replace every {{variable}} in a string.
	 *
	 * @param string               $content Content containing variables.
	 * @param array<string, mixed> $context Render context.
	 * @return string
	 */
	public function replace_all( string $content, array $context ): string {
		return preg_replace_callback(
			'/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
			function ( array $matches ) use ( $context ): string {
				return $this->resolve( $matches[1], $context );
			},
			$content
		) ?? $content;
	}

	/**
	 * Register the built-in variables.
	 */
	private function register_builtins(): void {
		$customer = static function ( array $ctx, string $key, string $fallback = '' ): string {
			if ( isset( $ctx['customer'][ $key ] ) ) {
				return (string) $ctx['customer'][ $key ];
			}
			if ( isset( $ctx[ $key ] ) ) {
				return (string) $ctx[ $key ];
			}
			return $fallback;
		};

		$order = static function ( array $ctx, string $key ): string {
			return isset( $ctx['order'][ $key ] ) ? (string) $ctx['order'][ $key ] : '';
		};

		$product = static function ( array $ctx, string $key ): string {
			if ( ! empty( $ctx['product'] ) ) {
				return (string) ( $ctx['product'][ $key ] ?? '' );
			}
			// Grouped emails use the first product as the primary subject.
			if ( ! empty( $ctx['products'] ) ) {
				return (string) ( $ctx['products'][0][ $key ] ?? '' );
			}
			return '';
		};

		$this->register(
			'customer_first_name',
			static fn( array $ctx ): string => $customer( $ctx, 'first_name', $customer( $ctx, 'name', '' ) )
		);
		$this->register( 'customer_last_name', static fn( array $ctx ): string => $customer( $ctx, 'last_name', '' ) );
		$this->register(
			'customer_name',
			static fn( array $ctx ): string => $customer( $ctx, 'name', $customer( $ctx, 'email', '' ) )
		);
		$this->register( 'customer_email', static fn( array $ctx ): string => $customer( $ctx, 'email', '' ) );

		$this->register( 'order_number', static fn( array $ctx ): string => $order( $ctx, 'number' ) );
		$this->register( 'order_date', static fn( array $ctx ): string => $order( $ctx, 'date' ) );

		$this->register( 'product_name', static fn( array $ctx ): string => $product( $ctx, 'name' ) );
		$this->register( 'product_url', static fn( array $ctx ): string => $product( $ctx, 'url' ) );
		$this->register(
			'product_image',
			static function ( array $ctx ) use ( $product ): string {
				$url = $product( $ctx, 'image' );
				if ( '' === $url ) {
					return '';
				}
				return sprintf(
					'<img src="%s" alt="%s" style="display:block;max-width:180px;height:auto;margin:0 auto 8px;border-radius:8px;" />',
					esc_url( $url ),
					esc_attr( $product( $ctx, 'name' ) )
				);
			}
		);
		$this->register( 'review_url', static fn( array $ctx ): string => (string) ( $ctx['review_url'] ?? '' ) );

		$this->register( 'store_name', static fn( array $ctx ): string => (string) ( $ctx['store_name'] ?? get_bloginfo( 'name' ) ) );
		$this->register( 'store_url', static fn( array $ctx ): string => (string) ( $ctx['store_url'] ?? home_url( '/' ) ) );
		$this->register(
			'unsubscribe_url',
			static fn( array $ctx ): string => (string) ( $ctx['unsubscribe_url'] ?? '' )
		);
	}
}
