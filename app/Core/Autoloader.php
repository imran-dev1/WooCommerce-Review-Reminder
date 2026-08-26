<?php
/**
 * PSR-4 style autoloader for the plugin namespace.
 *
 * Maps the `WooCommerceReviewReminder\` namespace to the `app/` directory.
 *
 * @package WooCommerceReviewReminder\Core
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Autoloader
 */
final class Autoloader {

	/**
	 * Root namespace prefix.
	 *
	 * @var string
	 */
	private string $prefix = 'WooCommerceReviewReminder\\';

	/**
	 * Base directory for the namespace.
	 *
	 * @var string
	 */
	private string $base_dir;

	/**
	 * Autoloader constructor.
	 *
	 * @param string $base_dir Absolute path to the directory containing the namespace root.
	 */
	public function __construct( string $base_dir ) {
		$this->base_dir = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR;
	}

	/**
	 * Register the autoloader with SPL.
	 */
	public function register(): void {
		spl_autoload_register( array( $this, 'autoload' ) );
	}

	/**
	 * Autoload a class file.
	 *
	 * @param string $class_name Fully qualified class name.
	 */
	public function autoload( string $class_name ): void {
		if ( strpos( $class_name, $this->prefix ) !== 0 ) {
			return;
		}

		$relative = substr( $class_name, strlen( $this->prefix ) );
		$path     = $this->base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require $path;
		}
	}
}
