<?php
/**
 * Campaign business logic.
 *
 * @package WooCommerceReviewReminder\Campaigns
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Campaigns;

use WooCommerceReviewReminder\Campaigns\Repository\CampaignRepository;
use WooCommerceReviewReminder\Core\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Class CampaignService
 */
final class CampaignService {

	/**
	 * Repository instance.
	 *
	 * @var CampaignRepository
	 */
	private CampaignRepository $repository;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * CampaignService constructor.
	 *
	 * @param CampaignRepository $repository Repository instance.
	 * @param Logger             $logger     Logger instance.
	 */
	public function __construct( CampaignRepository $repository, Logger $logger ) {
		$this->repository = $repository;
		$this->logger     = $logger;
	}

	/**
	 * Create a campaign.
	 *
	 * @param array<string, mixed> $data Campaign data.
	 * @return int
	 */
	public function create( array $data ): int {
		$id = $this->repository->create( $data );
		if ( $id > 0 ) {
			do_action( 'wrr_campaign_created', $id, $data );
			$this->logger->info( 'Campaign created.', array( 'campaign_id' => $id ) );
		}
		return $id;
	}

	/**
	 * Update a campaign.
	 *
	 * @param int                  $id   Campaign id.
	 * @param array<string, mixed> $data Campaign data.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		$updated = $this->repository->update( $id, $data );
		if ( $updated ) {
			do_action( 'wrr_campaign_updated', $id, $data );
			$this->logger->info( 'Campaign updated.', array( 'campaign_id' => $id ) );
		}
		return $updated;
	}

	/**
	 * Activate a campaign.
	 *
	 * @param int $id Campaign id.
	 * @return bool
	 */
	public function activate( int $id ): bool {
		$campaign = $this->repository->find( $id );
		if ( null === $campaign ) {
			return false;
		}
		$updated = $this->repository->set_status( $id, 'active' );
		if ( $updated ) {
			do_action( 'wrr_campaign_activated', $id );
			$this->logger->info( 'Campaign activated.', array( 'campaign_id' => $id ) );
		}
		return $updated;
	}

	/**
	 * Pause a campaign.
	 *
	 * @param int $id Campaign id.
	 * @return bool
	 */
	public function pause( int $id ): bool {
		$updated = $this->repository->set_status( $id, 'paused' );
		if ( $updated ) {
			do_action( 'wrr_campaign_paused', $id );
			$this->logger->info( 'Campaign paused.', array( 'campaign_id' => $id ) );
		}
		return $updated;
	}

	/**
	 * Duplicate a campaign.
	 *
	 * @param int $id Campaign id.
	 * @return int New campaign id or 0 on failure.
	 */
	public function duplicate( int $id ): int {
		$campaign = $this->repository->find( $id );
		if ( null === $campaign ) {
			return 0;
		}
		$data = $campaign->to_array();
		unset( $data['id'], $data['created_at'], $data['updated_at'], $data['stats'] );
		$data['name'] = sprintf(
			/* translators: %s: original campaign name. */
			__( '%s (Copy)', 'woocommerce-review-reminder' ),
			$campaign->name()
		);
		$data['status'] = 'draft';
		return $this->create( $data );
	}

	/**
	 * Delete a campaign. Also cancels its pending requests.
	 *
	 * @param int $id Campaign id.
	 */
	public function delete( int $id ): void {
		do_action( 'wrr_campaign_before_delete', $id );

		$queue = new \WooCommerceReviewReminder\Queue\QueueService(
			new \WooCommerceReviewReminder\Queue\RequestRepository(
				$this->repository->schema(),
				$this->logger
			),
			$this->logger
		);
		$queue->cancel_by_campaign( $id );

		$this->repository->delete( $id );
		do_action( 'wrr_campaign_deleted', $id );
		$this->logger->info( 'Campaign deleted.', array( 'campaign_id' => $id ) );
	}

	/**
	 * Find a campaign by id.
	 *
	 * @param int $id Campaign id.
	 * @return Campaign|null
	 */
	public function find( int $id ): ?Campaign {
		return $this->repository->find( $id );
	}
}
