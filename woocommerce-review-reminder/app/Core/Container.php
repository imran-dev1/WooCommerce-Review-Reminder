<?php
/**
 * Lightweight dependency injection container.
 *
 * @package WooCommerceReviewReminder\Core
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Core;

use Closure;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Class Container
 */
final class Container {

	/**
	 * Service factories, keyed by identifier.
	 *
	 * @var array<string, Closure>
	 */
	private array $factories = array();

	/**
	 * Resolved shared instances, keyed by identifier.
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * Whether a service is shared (singleton) or built fresh each time.
	 *
	 * @var array<string, bool>
	 */
	private array $shared = array();

	/**
	 * Register a service factory.
	 *
	 * @param string   $id       Identifier (usually the class name).
	 * @param Closure  $factory  Factory that builds the service.
	 * @param bool     $shared   Cache the resolved instance.
	 * @return Container
	 */
	public function register( string $id, Closure $factory, bool $shared = true ): Container {
		$this->factories[ $id ] = $factory;
		$this->shared[ $id ]    = $shared;
		unset( $this->instances[ $id ] );
		return $this;
	}

	/**
	 * Resolve a service.
	 *
	 * @param string $id Service identifier.
	 * @return object
	 * @throws RuntimeException When the service is not registered.
	 */
	public function get( string $id ) {
		if ( ! isset( $this->factories[ $id ] ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are developer-facing, never echoed to HTML.
			throw new RuntimeException(
				sprintf(
					/* translators: %s: service identifier. */
					__( 'Service %s is not registered.', 'woocommerce-review-reminder' ),
					$id
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( $this->shared[ $id ] ) {
			if ( ! isset( $this->instances[ $id ] ) ) {
				$this->instances[ $id ] = $this->factories[ $id ]( $this );
			}
			return $this->instances[ $id ];
		}

		return $this->factories[ $id ]( $this );
	}

	/**
	 * Whether a service is registered.
	 *
	 * @param string $id Service identifier.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] );
	}

	/**
	 * Replace an existing service factory at runtime.
	 *
	 * Useful for tests and for other plugins to swap implementations.
	 *
	 * @param string  $id      Service identifier.
	 * @param Closure $factory Replacement factory.
	 */
	public function replace( string $id, Closure $factory ): void {
		if ( $this->has( $id ) ) {
			$this->register( $id, $factory, $this->shared[ $id ] );
		}
	}
}
