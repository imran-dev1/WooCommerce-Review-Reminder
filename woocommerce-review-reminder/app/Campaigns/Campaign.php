<?php
/**
 * Campaign entity.
 *
 * @package WooCommerceReviewReminder\Campaigns
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Campaigns;

defined( 'ABSPATH' ) || exit;

/**
 * Class Campaign
 */
final class Campaign {

	/**
	 * Status: draft|active|paused.
	 *
	 * @var string
	 */
	private string $status = 'draft';

	/**
	 * Row data.
	 *
	 * @var array<string, mixed>
	 */
	private array $data;

	/**
	 * Campaign constructor.
	 *
	 * @param array<string, mixed> $data Row data.
	 */
	public function __construct( array $data ) {
		$this->data   = $data;
		$this->status = (string) ( $data['status'] ?? 'draft' );
	}

	/**
	 * Create a new draft campaign entity.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return self
	 */
	public static function draft( array $data ): self {
		$data['status'] = 'draft';
		return new self( $data );
	}

	/**
	 * Row data.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return $this->data;
	}

	/**
	 * Campaign id.
	 *
	 * @return int
	 */
	public function id(): int {
		return (int) ( $this->data['id'] ?? 0 );
	}

	/**
	 * Campaign name.
	 *
	 * @return string
	 */
	public function name(): string {
		return (string) ( $this->data['name'] ?? '' );
	}

	/**
	 * Campaign description.
	 *
	 * @return string
	 */
	public function description(): string {
		return (string) ( $this->data['description'] ?? '' );
	}

	/**
	 * Campaign status.
	 *
	 * @return string
	 */
	public function status(): string {
		return $this->status;
	}

	/**
	 * Whether the campaign is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return 'active' === $this->status;
	}

	/**
	 * Campaign config object.
	 *
	 * @return CampaignConfig
	 */
	public function config(): CampaignConfig {
		return CampaignConfig::from_json( $this->data['config'] ?? array() );
	}

	/**
	 * Cached stats payload (may be empty).
	 *
	 * @return array<string, mixed>
	 */
	public function stats(): array {
		$stats = $this->data['stats'] ?? array();
		if ( is_string( $stats ) && '' !== $stats ) {
			$stats = json_decode( $stats, true );
		}
		return is_array( $stats ) ? $stats : array();
	}

	/**
	 * Created timestamp.
	 *
	 * @return string
	 */
	public function created_at(): string {
		return (string) ( $this->data['created_at'] ?? '' );
	}

	/**
	 * Updated timestamp.
	 *
	 * @return string
	 */
	public function updated_at(): string {
		return (string) ( $this->data['updated_at'] ?? '' );
	}
}
