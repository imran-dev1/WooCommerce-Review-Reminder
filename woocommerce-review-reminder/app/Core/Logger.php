<?php
/**
 * Structured logging using the WooCommerce logger when available.
 *
 * @package WooCommerceReviewReminder\Core
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Logger
 */
final class Logger {

	/**
	 * Log levels.
	 */
	public const LEVEL_DEBUG = 'debug';
	public const LEVEL_INFO  = 'info';
	public const LEVEL_WARN  = 'warning';
	public const LEVEL_ERROR = 'error';

	/**
	 * Config instance.
	 *
	 * @var Config
	 */
	private Config $config;

	/**
	 * Cached WC_Logger instance.
	 *
	 * @var object|null
	 */
	private $wc_logger = null;

	/**
	 * Logger constructor.
	 *
	 * @param Config $config Config instance.
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Whether debug logging is enabled.
	 *
	 * @return bool
	 */
	public function is_debug(): bool {
		return (bool) $this->config->get( 'advanced.debug_logging', false );
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message Message.
	 * @param array<mixed> $context Context.
	 */
	public function debug( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_DEBUG, $message, $context );
	}

	/**
	 * Log an info message.
	 *
	 * @param string $message Message.
	 * @param array<mixed> $context Context.
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_INFO, $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message Message.
	 * @param array<mixed> $context Context.
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_WARN, $message, $context );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message Message.
	 * @param array<mixed> $context Context.
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Write a log entry.
	 *
	 * @param string $level   Level.
	 * @param string $message Message.
	 * @param array<mixed> $context Context.
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		// Debug entries are only written when debug logging is enabled.
		if ( self::LEVEL_DEBUG === $level && ! $this->is_debug() ) {
			return;
		}

		$entry = $this->format_message( $message, $context );

		if ( function_exists( 'wc_get_logger' ) ) {
			if ( null === $this->wc_logger ) {
				$this->wc_logger = wc_get_logger();
			}
			$this->wc_logger->log( $level, $entry, array( 'source' => 'woocommerce-review-reminder' ) );
			return;
		}

		// Fallback to error_log when WooCommerce is unavailable.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[woocommerce-review-reminder] ' . strtoupper( $level ) . ' ' . $entry );
	}

	/**
	 * Format a message with a compact context summary.
	 *
	 * @param string $message Message.
	 * @param array<mixed> $context Context.
	 * @return string
	 */
	private function format_message( string $message, array $context ): string {
		if ( empty( $context ) ) {
			return $message;
		}

		$json = wp_json_encode( $context, JSON_UNESCAPED_SLASHES );
		return $message . ' ' . ( false !== $json ? $json : '' );
	}
}
