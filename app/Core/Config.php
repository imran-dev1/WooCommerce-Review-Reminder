<?php
/**
 * Plugin settings and option helpers with defaults.
 *
 * @package WooCommerceReviewReminder\Core
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Config
 */
final class Config {

	/**
	 * Option name that holds the whole settings array.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'wrr_settings';

	/**
	 * Option name that stores plugin data flags (e.g. delete data on uninstall).
	 *
	 * @var string
	 */
	public const DATA_OPTION_NAME = 'wrr_plugin_data';

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'general'    => array(
				'enabled'     => true,
				'timezone'    => '',
				'date_format' => '',
			),
			'email'      => array(
				'from_name'  => '',
				'from_email' => '',
				'reply_to'   => '',
				'provider'   => 'wordpress',
			),
			'automation' => array(
				'default_delay'      => 7,
				'default_delay_unit' => 'days',
				'default_send_time'  => '10:00',
				'max_reminders'      => 2,
				'retry_count'        => 3,
				'retry_delay'        => 60,
			),
			'reviews'    => array(
				'strategy'         => 'grouped',
				'review_detection' => true,
				'review_url_mode'  => 'reviews_section',
			),
			'privacy'    => array(
				'unsubscribe'    => true,
				'retention_days' => 730,
				'log_level'      => 'warning',
			),
			'advanced'   => array(
				'debug_logging'       => false,
				'delete_on_uninstall' => false,
				'dev_mode'            => false,
			),
		);
	}

	/**
	 * Retrieve the full settings array, merged over defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored   = get_option( self::OPTION_NAME, array() );
		$defaults = $this->defaults();

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return $this->array_merge_recursive_distinct( $defaults, $stored );
	}

	/**
	 * Get a settings value using dot notation.
	 *
	 * Example: `get( 'email.from_name' )`.
	 *
	 * @param string $key     Dot-notation key.
	 * @param mixed  $default Fallback when the key does not exist.
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		$value = $this->all();
		foreach ( explode( '.', $key ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $default;
			}
			$value = $value[ $segment ];
		}
		return $value;
	}

	/**
	 * Persist settings. Missing top-level groups keep their stored values.
	 *
	 * @param array<string, mixed> $settings Full or partial settings array.
	 */
	public function set( array $settings ): void {
		$merged = $this->array_merge_recursive_distinct( $this->all(), $settings );
		update_option( self::OPTION_NAME, $merged, false );
		wp_cache_flush_group( 'wrr_settings' );
	}

	/**
	 * Get a plugin data flag.
	 *
	 * @param string $key     Key name.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public function data( string $key, $default = null ) {
		$data = get_option( self::DATA_OPTION_NAME, array() );
		if ( ! is_array( $data ) ) {
			return $default;
		}
		return $data[ $key ] ?? $default;
	}

	/**
	 * Set a plugin data flag.
	 *
	 * @param string $key   Key name.
	 * @param mixed  $value Value.
	 */
	public function set_data( string $key, $value ): void {
		$data         = get_option( self::DATA_OPTION_NAME, array() );
		$data         = is_array( $data ) ? $data : array();
		$data[ $key ] = $value;
		update_option( self::DATA_OPTION_NAME, $data, false );
	}

	/**
	 * Merge arrays recursively; later values win, scalars are overwritten.
	 *
	 * @param array<mixed> $base Base array.
	 * @param array<mixed> $override Override array.
	 * @return array<mixed>
	 */
	private function array_merge_recursive_distinct( array $base, array $override ): array {
		$merged = $base;
		foreach ( $override as $key => $value ) {
			if ( is_array( $value ) && isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ) {
				$merged[ $key ] = $this->array_merge_recursive_distinct( $merged[ $key ], $value );
			} else {
				$merged[ $key ] = $value;
			}
		}
		return $merged;
	}
}
