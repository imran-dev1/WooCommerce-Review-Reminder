<?php
/**
 * Admin page slug constants.
 *
 * @package WooCommerceReviewReminder\Admin
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class AdminPage
 */
final class AdminPage {

	/**
	 * Slug prefix for all plugin pages.
	 *
	 * @var string
	 */
	public const PAGE_PREFIX = 'wrr-';

	/**
	 * Sidebar pages.
	 *
	 * @var string
	 */
	public const DASHBOARD = 'wrr-dashboard';
	public const CAMPAIGNS = 'wrr-campaigns';
	public const REQUESTS  = 'wrr-requests';
	public const REVIEWS   = 'wrr-reviews';
	public const ANALYTICS = 'wrr-analytics';
	public const TEMPLATES = 'wrr-templates';
	public const SETTINGS  = 'wrr-settings';

	/**
	 * Hidden (non-menu) pages.
	 *
	 * @var string
	 */
	public const CAMPAIGN_EDIT  = 'wrr-campaign-edit';
	public const REQUEST_DETAIL = 'wrr-request-detail';
	public const TEMPLATE_EDIT  = 'wrr-template-edit';
}
