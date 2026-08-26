<?php
/**
 * Campaign editor admin view.
 *
 * @package WooCommerceReviewReminder\Admin\Views
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Admin\Views;

use WooCommerceReviewReminder\Admin\AdminPage;
use WooCommerceReviewReminder\Campaigns\Repository\CampaignRepository;
use WooCommerceReviewReminder\Emails\TemplateRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class CampaignEditorView
 */
final class CampaignEditorView {

	/**
	 * Render the campaign editor (new or edit).
	 */
	public static function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only id param.
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		/** @var CampaignRepository $repo */
		$repo = View::service( CampaignRepository::class );

		$name        = '';
		$description = '';
		$config      = self::default_config();

		if ( $id > 0 ) {
			$campaign = $repo->find( $id );
			if ( null === $campaign ) {
				self::not_found();
				return;
			}
			$name        = $campaign->name();
			$description = $campaign->description();
			$config      = self::merge_config( $config, $campaign->config()->to_array() );
		}

		/** @var TemplateRepository $templates */
		$templates = View::service( TemplateRepository::class );

		$order_statuses = array();
		if ( function_exists( 'wc_get_order_statuses' ) ) {
			foreach ( wc_get_order_statuses() as $key => $label ) {
				$order_statuses[ str_replace( 'wc-', '', $key ) ] = $label;
			}
		}

		$initial = array(
			'id'          => $id,
			'name'        => $name,
			'description' => $description,
			'config'      => $config,
		);

		View::open();
		echo View::page_header(
			$id > 0 ? $name : __( 'New campaign', 'woocommerce-review-reminder' ),
			$id > 0
				? __( 'Review and update your campaign.', 'woocommerce-review-reminder' )
				: __( 'Configure a new review-request automation.', 'woocommerce-review-reminder' ),
			'<a class="wrr-btn wrr-btn-secondary" href="' . esc_url( View::page_url( AdminPage::CAMPAIGNS ) ) . '">'
				. Icons::get( 'arrow-left' ) . esc_html__( 'Back', 'woocommerce-review-reminder' ) . '</a>'
			. '<button type="button" class="wrr-btn wrr-btn-secondary" x-on:click="save(false)">' . esc_html__( 'Save draft', 'woocommerce-review-reminder' ) . '</button>'
			. '<button type="button" class="wrr-btn wrr-btn-primary" x-on:click="save(true)">'
				. ( $id > 0 ? esc_html__( 'Save & activate', 'woocommerce-review-reminder' ) : esc_html__( 'Create & activate', 'woocommerce-review-reminder' ) ) . '</button>'
		);

		echo '<div class="grid gap-6" x-data="wrrCampaignEditor(' . View::json_attr( $initial ) . ')">';

		// Basics.
		echo '<div class="wrr-card">';
		echo '<div class="wrr-card-header"><div><h2 class="wrr-card-title">' . esc_html__( 'Basics', 'woocommerce-review-reminder' ) . '</h2>';
		echo '<p class="wrr-card-desc">' . esc_html__( 'Name your campaign so you can recognise it in reports.', 'woocommerce-review-reminder' ) . '</p></div></div>';
		echo '<div class="wrr-card-body grid gap-4">';
		echo '<div>';
		echo '<label class="wrr-label" for="wrr-campaign-name">' . esc_html__( 'Campaign name', 'woocommerce-review-reminder' ) . '</label>';
		echo '<input id="wrr-campaign-name" class="wrr-input" type="text" x-model="name" placeholder="' . esc_attr__( 'e.g. Post-purchase review request', 'woocommerce-review-reminder' ) . '" />';
		echo '</div>';
		echo '<div>';
		echo '<label class="wrr-label" for="wrr-campaign-desc">' . esc_html__( 'Description', 'woocommerce-review-reminder' ) . '</label>';
		echo '<textarea id="wrr-campaign-desc" class="wrr-textarea" rows="2" x-model="description" placeholder="' . esc_attr__( 'Internal note (optional)', 'woocommerce-review-reminder' ) . '"></textarea>';
		echo '</div>';
		echo '</div></div>';

		// Trigger & timing.
		echo '<div class="wrr-card">';
		echo '<div class="wrr-card-header"><div><h2 class="wrr-card-title">' . esc_html__( 'Trigger & timing', 'woocommerce-review-reminder' ) . '</h2>';
		echo '<p class="wrr-card-desc">' . esc_html__( 'When should review requests be scheduled?', 'woocommerce-review-reminder' ) . '</p></div></div>';
		echo '<div class="wrr-card-body grid gap-4 sm:grid-cols-2 lg:grid-cols-4">';

		echo '<div class="sm:col-span-2"><label class="wrr-label">' . esc_html__( 'Trigger order status', 'woocommerce-review-reminder' ) . '</label>';
		echo '<select class="wrr-select" x-model="config.trigger.order_statuses[0]">';
		foreach ( $order_statuses as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></div>';

		echo '<div><label class="wrr-label">' . esc_html__( 'Delay', 'woocommerce-review-reminder' ) . '</label>';
		echo '<input class="wrr-input" type="number" min="0" x-model.number="config.timing.delay" /></div>';

		echo '<div><label class="wrr-label">' . esc_html__( 'Delay unit', 'woocommerce-review-reminder' ) . '</label>';
		echo '<select class="wrr-select" x-model="config.timing.delay_unit">';
		foreach ( array(
			'hours' => __( 'Hours', 'woocommerce-review-reminder' ),
			'days'  => __( 'Days', 'woocommerce-review-reminder' ),
			'weeks' => __( 'Weeks', 'woocommerce-review-reminder' ),
		) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></div>';

		echo '<div class="sm:col-span-2"><label class="wrr-label">' . esc_html__( 'Request strategy', 'woocommerce-review-reminder' ) . '</label>';
		echo '<select class="wrr-select" x-model="config.strategy.request">';
		echo '<option value="grouped">' . esc_html__( 'Group all products into one email', 'woocommerce-review-reminder' ) . '</option>';
		echo '<option value="per_product">' . esc_html__( 'One email per product', 'woocommerce-review-reminder' ) . '</option>';
		echo '</select></div>';

		echo '<div class="sm:col-span-2 flex items-center gap-3 rounded-lg border border-gray-200 p-3">';
		echo '<input type="checkbox" class="h-4 w-4 accent-indigo-600" x-model="config.followup.enabled" id="wrr-followup" />';
		echo '<div class="flex-1"><label class="block text-sm font-medium text-gray-800" for="wrr-followup">' . esc_html__( 'Enable follow-up email', 'woocommerce-review-reminder' ) . '</label>';
		echo '<p class="text-xs text-gray-500">' . esc_html__( 'Send a gentle nudge if the first email is ignored.', 'woocommerce-review-reminder' ) . '</p></div>';
		echo '</div>';

		echo '<template x-if="config.followup.enabled">';
		echo '<div class="sm:col-span-2 grid gap-4 sm:grid-cols-3">';
		echo '<div><label class="wrr-label">' . esc_html__( 'Follow-up after', 'woocommerce-review-reminder' ) . '</label>';
		echo '<input class="wrr-input" type="number" min="1" x-model.number="config.followup.delay" /></div>';
		echo '<div><label class="wrr-label">' . esc_html__( 'Follow-up unit', 'woocommerce-review-reminder' ) . '</label>';
		echo '<select class="wrr-select" x-model="config.followup.delay_unit">';
		foreach ( array(
			'days'  => __( 'Days', 'woocommerce-review-reminder' ),
			'weeks' => __( 'Weeks', 'woocommerce-review-reminder' ),
		) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></div>';
		echo '<div><label class="wrr-label">' . esc_html__( 'Max reminders', 'woocommerce-review-reminder' ) . '</label>';
		echo '<input class="wrr-input" type="number" min="1" x-model.number="config.followup.max_reminders" /></div>';
		echo '</div></template>';

		echo '</div></div>';

		// Audience.
		echo '<div class="wrr-card">';
		echo '<div class="wrr-card-header"><div><h2 class="wrr-card-title">' . esc_html__( 'Audience', 'woocommerce-review-reminder' ) . '</h2>';
		echo '<p class="wrr-card-desc">' . esc_html__( 'Which customers should this campaign target?', 'woocommerce-review-reminder' ) . '</p></div></div>';
		echo '<div class="wrr-card-body grid gap-4 sm:grid-cols-2">';

		echo '<div><label class="wrr-label">' . esc_html__( 'Customer type', 'woocommerce-review-reminder' ) . '</label>';
		echo '<select class="wrr-select" x-model="config.audience.customer_type">';
		$customer_types = array(
			'all'        => __( 'All customers', 'woocommerce-review-reminder' ),
			'guest'      => __( 'Guests only', 'woocommerce-review-reminder' ),
			'registered' => __( 'Registered customers', 'woocommerce-review-reminder' ),
		);
		foreach ( $customer_types as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></div>';

		echo '<div><label class="wrr-label">' . esc_html__( 'Customer history', 'woocommerce-review-reminder' ) . '</label>';
		echo '<select class="wrr-select" x-model="config.audience.customer_history">';
		$history = array(
			'any'        => __( 'All customers', 'woocommerce-review-reminder' ),
			'first_time' => __( 'First-time buyers', 'woocommerce-review-reminder' ),
			'returning'  => __( 'Repeat customers', 'woocommerce-review-reminder' ),
		);
		foreach ( $history as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></div>';

		echo '<div><label class="wrr-label">' . esc_html__( 'Min order value ($)', 'woocommerce-review-reminder' ) . '</label>';
		echo '<input class="wrr-input" type="number" min="0" step="0.01" x-model.number="config.audience.min_order_value" /></div>';

		echo '<div><label class="wrr-label">' . esc_html__( 'Max order value ($)', 'woocommerce-review-reminder' ) . '</label>';
		echo '<input class="wrr-input" type="number" min="0" step="0.01" x-model.number="config.audience.max_order_value" /></div>';

		echo '</div></div>';

		// Products.
		echo '<div class="wrr-card">';
		echo '<div class="wrr-card-header"><div><h2 class="wrr-card-title">' . esc_html__( 'Products', 'woocommerce-review-reminder' ) . '</h2>';
		echo '<p class="wrr-card-desc">' . esc_html__( 'Limit which products are eligible for review requests.', 'woocommerce-review-reminder' ) . '</p></div></div>';
		echo '<div class="wrr-card-body grid gap-4 sm:grid-cols-2">';

		echo '<div class="sm:col-span-2"><label class="wrr-label">' . esc_html__( 'Product targeting', 'woocommerce-review-reminder' ) . '</label>';
		echo '<select class="wrr-select" x-model="config.products.include">';
		$include = array(
			'all'        => __( 'All products', 'woocommerce-review-reminder' ),
			'specific'   => __( 'Only specific products', 'woocommerce-review-reminder' ),
			'categories' => __( 'Products in specific categories', 'woocommerce-review-reminder' ),
			'tags'       => __( 'Products with specific tags', 'woocommerce-review-reminder' ),
		);
		foreach ( $include as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></div>';

		echo '<div x-show="config.products.include === \'specific\'" x-cloak>';
		echo '<label class="wrr-label">' . esc_html__( 'Include product IDs', 'woocommerce-review-reminder' ) . '</label>';
		echo '<input class="wrr-input" type="text" x-model="productIdsText" placeholder="12, 34, 56" />';
		echo '<p class="wrr-hint">' . esc_html__( 'Comma separated. Only these products generate requests.', 'woocommerce-review-reminder' ) . '</p></div>';

		echo '<div x-show="config.products.include === \'categories\'" x-cloak>';
		echo '<label class="wrr-label">' . esc_html__( 'Include category IDs', 'woocommerce-review-reminder' ) . '</label>';
		echo '<input class="wrr-input" type="text" x-model="categoryIdsText" placeholder="4, 7" />';
		echo '<p class="wrr-hint">' . esc_html__( 'Comma separated category ids.', 'woocommerce-review-reminder' ) . '</p></div>';

		echo '<div x-show="config.products.include === \'tags\'" x-cloak>';
		echo '<label class="wrr-label">' . esc_html__( 'Include tag IDs', 'woocommerce-review-reminder' ) . '</label>';
		echo '<input class="wrr-input" type="text" x-model="tagIdsText" placeholder="9, 12" />';
		echo '<p class="wrr-hint">' . esc_html__( 'Comma separated product tag ids.', 'woocommerce-review-reminder' ) . '</p></div>';

		echo '<div><label class="wrr-label">' . esc_html__( 'Exclude product IDs', 'woocommerce-review-reminder' ) . '</label>';
		echo '<input class="wrr-input" type="text" x-model="excludeProductIdsText" placeholder="78, 90" />';
		echo '<p class="wrr-hint">' . esc_html__( 'These products will never generate requests.', 'woocommerce-review-reminder' ) . '</p></div>';

		echo '<div><label class="wrr-label">' . esc_html__( 'Exclude category IDs', 'woocommerce-review-reminder' ) . '</label>';
		echo '<input class="wrr-input" type="text" x-model="excludeCategoryIdsText" placeholder="11" />';
		echo '<p class="wrr-hint">' . esc_html__( 'Products in these categories are skipped.', 'woocommerce-review-reminder' ) . '</p></div>';

		echo '</div></div>';

		// Email content.
		echo '<div class="wrr-card">';
		echo '<div class="wrr-card-header"><div><h2 class="wrr-card-title">' . esc_html__( 'Email content', 'woocommerce-review-reminder' ) . '</h2>';
		echo '<p class="wrr-card-desc">' . esc_html__( 'Use variables like {{customer_first_name}}, {{product_name}} and {{review_url}}.', 'woocommerce-review-reminder' ) . '</p></div></div>';
		echo '<div class="wrr-card-body grid gap-4">';

		echo '<div class="sm:col-span-2"><label class="wrr-label">' . esc_html__( 'Email template', 'woocommerce-review-reminder' ) . '</label>';
		echo '<select class="wrr-select" x-model="config.email.template_id">';
		echo '<option :value="null">' . esc_html__( 'Use inline content below', 'woocommerce-review-reminder' ) . '</option>';
		foreach ( $templates->all() as $template ) {
			echo '<option :value="' . (int) $template->id() . '">' . esc_html( $template->name() ) . '</option>';
		}
		echo '</select></div>';

		echo '<div><label class="wrr-label">' . esc_html__( 'Subject line', 'woocommerce-review-reminder' ) . '</label>';
		echo '<input class="wrr-input" type="text" x-model="config.email.subject" placeholder="' . esc_attr__( 'How are you enjoying your {{product_name}}?', 'woocommerce-review-reminder' ) . '" /></div>';

		echo '<div><label class="wrr-label">' . esc_html__( 'Review button text', 'woocommerce-review-reminder' ) . '</label>';
		echo '<input class="wrr-input" type="text" x-model="config.email.button_text" placeholder="' . esc_attr__( 'Leave a Review', 'woocommerce-review-reminder' ) . '" /></div>';

		echo '<div class="sm:col-span-2"><label class="wrr-label">' . esc_html__( 'Email body (HTML)', 'woocommerce-review-reminder' ) . '</label>';
		echo '<textarea class="wrr-textarea" rows="8" x-model="config.email.body"></textarea></div>';

		echo '<template x-if="config.followup.enabled">';
		echo '<div class="sm:col-span-2 grid gap-4">';
		echo '<div><label class="wrr-label">' . esc_html__( 'Follow-up subject', 'woocommerce-review-reminder' ) . '</label>';
		echo '<input class="wrr-input" type="text" x-model="config.followup.subject" placeholder="' . esc_attr__( 'We miss you!', 'woocommerce-review-reminder' ) . '" /></div>';
		echo '<div><label class="wrr-label">' . esc_html__( 'Follow-up body (HTML)', 'woocommerce-review-reminder' ) . '</label>';
		echo '<textarea class="wrr-textarea" rows="5" x-model="config.followup.body"></textarea></div>';
		echo '</div></template>';

		echo '</div></div>';

		// Exclusions.
		echo '<div class="wrr-card">';
		echo '<div class="wrr-card-header"><div><h2 class="wrr-card-title">' . esc_html__( 'Exclusions', 'woocommerce-review-reminder' ) . '</h2>';
		echo '<p class="wrr-card-desc">' . esc_html__( 'Automatically skip requests when these conditions are met.', 'woocommerce-review-reminder' ) . '</p></div></div>';
		echo '<div class="wrr-card-body grid gap-3">';

		$exclusions = array(
			'skip_reviewed'   => __( 'Skip customers who already reviewed', 'woocommerce-review-reminder' ),
			'skip_suppressed' => __( 'Skip suppressed customers', 'woocommerce-review-reminder' ),
			'skip_refunded'   => __( 'Skip refunded orders', 'woocommerce-review-reminder' ),
			'skip_cancelled'  => __( 'Skip cancelled orders', 'woocommerce-review-reminder' ),
		);
		foreach ( $exclusions as $key => $label ) {
			echo '<div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 p-3">';
			echo '<label class="text-sm font-medium text-gray-800" for="wrr-excl-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
			echo '<input type="checkbox" id="wrr-excl-' . esc_attr( $key ) . '" class="h-4 w-4 accent-indigo-600" x-model="config.exclusions.' . esc_attr( $key ) . '" />';
			echo '</div>';
		}

		echo '</div></div>';

		echo '<div class="wrr-alert">';
		echo '<span>' . Icons::get( 'alert', 'h-4 w-4 shrink-0 mt-0.5' ) . '</span>';
		echo '<div><strong>' . esc_html__( 'How this works', 'woocommerce-review-reminder' ) . '</strong><br />'
			. esc_html__( 'When an order matches the trigger, the plugin schedules a request and sends the email after the delay. Customers can unsubscribe from any email, and requests are automatically cancelled when the order is refunded or cancelled, or when the customer reviews the product.', 'woocommerce-review-reminder' ) . '</div>';
		echo '</div>';

		echo '</div>'; // editor alpine root

		View::close();
	}

	/**
	 * Render a not-found notice.
	 */
	private static function not_found(): void {
		View::open();
		echo View::page_header(
			__( 'Campaign not found', 'woocommerce-review-reminder' ),
			__( 'The requested campaign does not exist or was deleted.', 'woocommerce-review-reminder' ),
			'<a class="wrr-btn wrr-btn-secondary" href="' . esc_url( View::page_url( AdminPage::CAMPAIGNS ) ) . '">'
				. Icons::get( 'arrow-left' ) . esc_html__( 'Back to campaigns', 'woocommerce-review-reminder' ) . '</a>'
		);
		View::close();
	}

	/**
	 * Default campaign config.
	 *
	 * @return array<string, mixed>
	 */
	private static function default_config(): array {
		return array(
			'trigger'    => array(
				'type'           => 'order_status',
				'order_statuses' => array( 'completed' ),
			),
			'timing'     => array(
				'delay'      => 7,
				'delay_unit' => 'days',
				'send_time'  => '',
			),
			'audience'   => array(
				'customer_type'       => 'all',
				'customer_roles'      => array(),
				'min_order_value'     => null,
				'max_order_value'     => null,
				'payment_methods'     => array(),
				'shipping_methods'    => array(),
				'customer_history'    => 'any',
				'min_previous_orders' => 0,
			),
			'products'   => array(
				'include'              => 'all',
				'product_ids'          => array(),
				'category_ids'         => array(),
				'tag_ids'              => array(),
				'exclude_product_ids'  => array(),
				'exclude_category_ids' => array(),
			),
			'email'      => array(
				'template_id' => null,
				'subject'     => 'How are you enjoying your {{product_name}}?',
				'body'        => '<p>Hi {{customer_first_name}},</p><p>Thanks for your recent order of <strong>{{product_name}}</strong>!</p><p>We would love to hear what you think.</p><p><a href="{{review_url}}">Leave a review</a></p>',
				'button_text' => 'Leave a Review',
			),
			'followup'   => array(
				'enabled'       => false,
				'delay'         => 3,
				'delay_unit'    => 'days',
				'max_reminders' => 2,
				'subject'       => '',
				'body'          => '',
			),
			'strategy'   => array(
				'request' => 'grouped',
			),
			'exclusions' => array(
				'skip_reviewed'   => true,
				'skip_suppressed' => true,
				'skip_refunded'   => true,
				'skip_cancelled'  => true,
				'max_per_order'   => null,
			),
		);
	}

	/**
	 * Merge a stored config over defaults (numeric lists are replaced).
	 *
	 * @param array<string, mixed> $defaults Defaults.
	 * @param array<string, mixed> $stored   Stored config.
	 * @return array<string, mixed>
	 */
	private static function merge_config( array $defaults, array $stored ): array {
		return array_replace_recursive( $defaults, $stored );
	}
}
