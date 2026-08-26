<?php
/**
 * Typed access to a campaign's JSON configuration.
 *
 * @package WooCommerceReviewReminder\Campaigns
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Campaigns;

defined( 'ABSPATH' ) || exit;

/**
 * Class CampaignConfig
 */
final class CampaignConfig {

	/**
	 * Raw config array.
	 *
	 * @var array<string, mixed>
	 */
	private array $raw;

	/**
	 * CampaignConfig constructor.
	 *
	 * @param array<string, mixed> $raw Raw config array.
	 */
	public function __construct( array $raw = array() ) {
		$this->raw = $raw;
	}

	/**
	 * Build from a decoded JSON payload.
	 *
	 * @param mixed $json Decoded JSON (array, object or string).
	 * @return self
	 */
	public static function from_json( $json ): self {
		if ( is_string( $json ) && '' !== $json ) {
			$json = json_decode( $json, true );
		}
		return new self( is_array( $json ) ? $json : array() );
	}

	/**
	 * Raw config array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return $this->raw;
	}

	/**
	 * Encode to JSON string.
	 *
	 * @return string
	 */
	public function to_json(): string {
		return (string) wp_json_encode( $this->raw );
	}

	/**
	 * Read a nested key with fallback.
	 *
	 * @param string $path    Dot notation path.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	private function get_path( string $path, $default = null ) {
		$value = $this->raw;
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $default;
			}
			$value = $value[ $segment ];
		}
		return $value;
	}

	/**
	 * Trigger type.
	 *
	 * @return string
	 */
	public function trigger_type(): string {
		return (string) $this->get_path( 'trigger.type', 'order_status' );
	}

	/**
	 * Order statuses that trigger the campaign.
	 *
	 * @return string[]
	 */
	public function trigger_order_statuses(): array {
		$value = $this->get_path( 'trigger.order_statuses', array() );
		return is_array( $value ) ? array_map( 'strval', $value ) : array( 'completed' );
	}

	/**
	 * Delay amount.
	 *
	 * @return int
	 */
	public function delay(): int {
		return max( 0, (int) $this->get_path( 'timing.delay', 7 ) );
	}

	/**
	 * Delay unit: minutes|hours|days|weeks.
	 *
	 * @return string
	 */
	public function delay_unit(): string {
		$unit = (string) $this->get_path( 'timing.delay_unit', 'days' );
		$unit = in_array( $unit, array( 'minutes', 'hours', 'days', 'weeks' ), true ) ? $unit : 'days';
		return $unit;
	}

	/**
	 * Optional preferred daily send time (H:i, site timezone), or empty.
	 *
	 * @return string
	 */
	public function send_time(): string {
		return (string) $this->get_path( 'timing.send_time', '' );
	}

	/**
	 * Audience: customer type (all|guest|registered).
	 *
	 * @return string
	 */
	public function customer_type(): string {
		$type = (string) $this->get_path( 'audience.customer_type', 'all' );
		return in_array( $type, array( 'all', 'guest', 'registered' ), true ) ? $type : 'all';
	}

	/**
	 * Allowed customer roles (empty = any role).
	 *
	 * @return string[]
	 */
	public function customer_roles(): array {
		$value = $this->get_path( 'audience.customer_roles', array() );
		return is_array( $value ) ? array_map( 'strval', $value ) : array();
	}

	/**
	 * Minimum order value (null = none).
	 *
	 * @return float|null
	 */
	public function min_order_value() {
		$value = $this->get_path( 'audience.min_order_value', null );
		return ( null === $value || '' === $value ) ? null : (float) $value;
	}

	/**
	 * Maximum order value (null = none).
	 *
	 * @return float|null
	 */
	public function max_order_value() {
		$value = $this->get_path( 'audience.max_order_value', null );
		return ( null === $value || '' === $value ) ? null : (float) $value;
	}

	/**
	 * Allowed payment method ids (empty = any).
	 *
	 * @return string[]
	 */
	public function payment_methods(): array {
		$value = $this->get_path( 'audience.payment_methods', array() );
		return is_array( $value ) ? array_map( 'strval', $value ) : array();
	}

	/**
	 * Allowed shipping method ids (empty = any).
	 *
	 * @return string[]
	 */
	public function shipping_methods(): array {
		$value = $this->get_path( 'audience.shipping_methods', array() );
		return is_array( $value ) ? array_map( 'strval', $value ) : array();
	}

	/**
	 * Customer history filter: any|first_time|returning.
	 *
	 * @return string
	 */
	public function customer_history(): string {
		$value = (string) $this->get_path( 'audience.customer_history', 'any' );
		return in_array( $value, array( 'any', 'first_time', 'returning' ), true ) ? $value : 'any';
	}

	/**
	 * Minimum number of previous orders (for returning customers).
	 *
	 * @return int
	 */
	public function min_previous_orders(): int {
		return max( 0, (int) $this->get_path( 'audience.min_previous_orders', 1 ) );
	}

	/**
	 * Product include mode: all|specific|categories|tags.
	 *
	 * @return string
	 */
	public function product_include(): string {
		$mode = (string) $this->get_path( 'products.include', 'all' );
		return in_array( $mode, array( 'all', 'specific', 'categories', 'tags' ), true ) ? $mode : 'all';
	}

	/**
	 * Included product ids.
	 *
	 * @return int[]
	 */
	public function include_product_ids(): array {
		return array_map( 'absint', (array) $this->get_path( 'products.product_ids', array() ) );
	}

	/**
	 * Included category ids.
	 *
	 * @return int[]
	 */
	public function include_category_ids(): array {
		return array_map( 'absint', (array) $this->get_path( 'products.category_ids', array() ) );
	}

	/**
	 * Included tag ids.
	 *
	 * @return int[]
	 */
	public function include_tag_ids(): array {
		return array_map( 'absint', (array) $this->get_path( 'products.tag_ids', array() ) );
	}

	/**
	 * Excluded product ids.
	 *
	 * @return int[]
	 */
	public function exclude_product_ids(): array {
		return array_map( 'absint', (array) $this->get_path( 'products.exclude_product_ids', array() ) );
	}

	/**
	 * Excluded category ids.
	 *
	 * @return int[]
	 */
	public function exclude_category_ids(): array {
		return array_map( 'absint', (array) $this->get_path( 'products.exclude_category_ids', array() ) );
	}

	/**
	 * Review request strategy: grouped|per_product.
	 *
	 * @return string
	 */
	public function request_strategy(): string {
		$strategy = (string) $this->get_path( 'strategy.request', 'grouped' );
		return in_array( $strategy, array( 'grouped', 'per_product' ), true ) ? $strategy : 'grouped';
	}

	/**
	 * Whether follow-up reminders are enabled.
	 *
	 * @return bool
	 */
	public function followup_enabled(): bool {
		return (bool) $this->get_path( 'followup.enabled', false );
	}

	/**
	 * Follow-up delay amount.
	 *
	 * @return int
	 */
	public function followup_delay(): int {
		return max( 0, (int) $this->get_path( 'followup.delay', 7 ) );
	}

	/**
	 * Follow-up delay unit.
	 *
	 * @return string
	 */
	public function followup_delay_unit(): string {
		$unit = (string) $this->get_path( 'followup.delay_unit', 'days' );
		$unit = in_array( $unit, array( 'minutes', 'hours', 'days', 'weeks' ), true ) ? $unit : 'days';
		return $unit;
	}

	/**
	 * Maximum number of reminders (including the initial request).
	 *
	 * @return int
	 */
	public function max_reminders(): int {
		$max = (int) $this->get_path( 'followup.max_reminders', 2 );
		return max( 1, $max );
	}

	/**
	 * Email subject (with template variables).
	 *
	 * @return string
	 */
	public function email_subject(): string {
		return (string) $this->get_path( 'email.subject', '' );
	}

	/**
	 * Email body HTML (with template variables).
	 *
	 * @return string
	 */
	public function email_body(): string {
		return (string) $this->get_path( 'email.body', '' );
	}

	/**
	 * CTA button text.
	 *
	 * @return string
	 */
	public function button_text(): string {
		return (string) $this->get_path( 'email.button_text', __( 'Leave a Review', 'woocommerce-review-reminder' ) );
	}

	/**
	 * Referenced email template id, or null for inline content.
	 *
	 * @return int|null
	 */
	public function email_template_id() {
		$id = (int) $this->get_path( 'email.template_id', 0 );
		return $id > 0 ? $id : null;
	}

	/**
	 * Follow-up email subject override.
	 *
	 * @return string
	 */
	public function followup_subject(): string {
		return (string) $this->get_path( 'followup.subject', '' );
	}

	/**
	 * Follow-up email body override.
	 *
	 * @return string
	 */
	public function followup_body(): string {
		return (string) $this->get_path( 'followup.body', '' );
	}

	/**
	 * Exclusion: skip when the product was already reviewed.
	 *
	 * @return bool
	 */
	public function skip_reviewed(): bool {
		return (bool) $this->get_path( 'exclusions.skip_reviewed', true );
	}

	/**
	 * Exclusion: skip when the customer is suppressed.
	 *
	 * @return bool
	 */
	public function skip_suppressed(): bool {
		return (bool) $this->get_path( 'exclusions.skip_suppressed', true );
	}

	/**
	 * Exclusion: skip when the order is refunded.
	 *
	 * @return bool
	 */
	public function skip_refunded(): bool {
		return (bool) $this->get_path( 'exclusions.skip_refunded', true );
	}

	/**
	 * Exclusion: skip when the order is cancelled.
	 *
	 * @return bool
	 */
	public function skip_cancelled(): bool {
		return (bool) $this->get_path( 'exclusions.skip_cancelled', true );
	}

	/**
	 * Maximum number of requests per order (null = unlimited).
	 *
	 * @return int|null
	 */
	public function max_per_order() {
		$value = $this->get_path( 'exclusions.max_per_order', null );
		return ( null === $value || '' === $value ) ? null : max( 1, (int) $value );
	}
}
