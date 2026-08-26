<?php
/**
 * Email templates admin view.
 *
 * @package WooCommerceReviewReminder\Admin\Views
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Admin\Views;

use WooCommerceReviewReminder\Emails\TemplateRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class TemplatesView
 */
final class TemplatesView {

	/**
	 * Render the templates page.
	 */
	public static function render(): void {
		/** @var TemplateRepository $repo */
		$repo      = View::service( TemplateRepository::class );
		$templates = $repo->all();

		$items = array();
		foreach ( $templates as $template ) {
			$arr     = $template->to_array();
			$items[] = array(
				'id'         => (int) $arr['id'],
				'name'       => (string) $arr['name'],
				'subject'    => (string) $arr['subject'],
				'body'       => (string) $arr['body'],
				'is_builtin' => (bool) $arr['is_builtin'],
			);
		}

		$data = array( 'items' => $items );

		View::open();
		echo '<div x-data="wrrTemplates(' . View::json_attr( $data ) . ')">';

		echo View::page_header(
			__( 'Templates', 'woocommerce-review-reminder' ),
			__( 'Reusable email templates for your review requests.', 'woocommerce-review-reminder' ),
			'<button type="button" class="wrr-btn wrr-btn-primary" x-on:click="openEdit(null)">' . Icons::get( 'plus' ) . esc_html__( 'New template', 'woocommerce-review-reminder' ) . '</button>'
		);

		echo '<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" x-cloak>';

		echo '<template x-for="t in items" :key="t.id">';
		echo '<div class="wrr-card">';
		echo '<div class="wrr-card-header">';
		echo '<div><h2 class="wrr-card-title" x-text="t.name"></h2>';
		echo '<p class="wrr-card-desc" x-text="t.is_builtin ? \'Built-in\' : \'Custom\'"></p></div>';
		echo '<span class="wrr-badge wrr-badge-gray" x-text="t.is_builtin ? \'Built-in\' : \'Custom\'"></span>';
		echo '</div>';
		echo '<div class="wrr-card-body">';
		echo '<p class="text-xs text-gray-500">' . esc_html__( 'Subject', 'woocommerce-review-reminder' ) . '</p>';
		echo '<p class="mt-0.5 truncate text-sm font-medium text-gray-800" x-text="t.subject"></p>';
		echo '<p class="mt-3 text-xs text-gray-500">' . esc_html__( 'Body', 'woocommerce-review-reminder' ) . '</p>';
		echo '<p class="mt-0.5 line-clamp-3 text-sm text-gray-600" x-text="t.body"></p>';
		echo '<div class="mt-4 flex items-center gap-2">';
		echo '<button type="button" class="wrr-btn wrr-btn-secondary wrr-btn-sm" x-on:click="openPreview(t)">' . Icons::get( 'external' ) . esc_html__( 'Preview', 'woocommerce-review-reminder' ) . '</button>';
		echo '<button type="button" class="wrr-btn wrr-btn-secondary wrr-btn-sm" x-on:click="openEdit(t)">' . Icons::get( 'edit' ) . esc_html__( 'Edit', 'woocommerce-review-reminder' ) . '</button>';
		echo '<button type="button" class="wrr-btn wrr-btn-danger wrr-btn-sm" x-on:click="askDelete(t.id, t.name)" x-show="!t.is_builtin">' . Icons::get( 'trash' ) . esc_html__( 'Delete', 'woocommerce-review-reminder' ) . '</button>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</template>';

		echo '</div>';

		// Confirm delete modal.
		echo '<div x-data="wrrConfirm()" x-on:wrr-confirm-ask.window="ask($event.detail)">' . View::confirm_modal() . '</div>';

		// Edit modal.
		echo '<div x-show="formOpen" x-cloak class="wrr-modal-overlay" x-transition.opacity>';
		echo '<div class="wrr-modal" x-on:click.outside="closeForm()" x-trap="formOpen">';
		echo '<div class="wrr-modal-header">';
		echo '<h3 class="text-lg font-semibold text-gray-900" x-text="form.id > 0 ? \'Edit template\' : \'New template\'"></h3>';
		echo '<button type="button" class="text-gray-400 hover:text-gray-600" x-on:click="closeForm()" aria-label="' . esc_attr__( 'Close', 'woocommerce-review-reminder' ) . '">' . Icons::get( 'x' ) . '</button>';
		echo '</div>';
		echo '<div class="wrr-modal-body">';
		echo '<div class="wrr-field">';
		echo '<label class="wrr-label">' . esc_html__( 'Name', 'woocommerce-review-reminder' ) . '</label>';
		echo '<input type="text" class="wrr-input" x-model="form.name" placeholder="' . esc_attr__( 'e.g. Thank you & review', 'woocommerce-review-reminder' ) . '" />';
		echo '</div>';
		echo '<div class="wrr-field">';
		echo '<label class="wrr-label">' . esc_html__( 'Subject', 'woocommerce-review-reminder' ) . '</label>';
		echo '<input type="text" class="wrr-input" x-model="form.subject" placeholder="' . esc_attr__( 'e.g. {{customer_name}}, how did we do?', 'woocommerce-review-reminder' ) . '" />';
		echo '</div>';
		echo '<div class="wrr-field">';
		echo '<label class="wrr-label">' . esc_html__( 'Body', 'woocommerce-review-reminder' ) . '</label>';
		echo '<textarea class="wrr-textarea" x-model="form.body" rows="8" placeholder="' . esc_attr__( 'Plain-text body. Available variables: {{customer_name}}, {{product_name}}, {{review_url}}, {{store_name}}.', 'woocommerce-review-reminder' ) . '"></textarea>';
		echo '</div>';
		echo '</div>';
		echo '<div class="wrr-modal-footer">';
		echo '<button type="button" class="wrr-btn wrr-btn-secondary" x-on:click="closeForm()">' . esc_html__( 'Cancel', 'woocommerce-review-reminder' ) . '</button>';
		echo '<button type="button" class="wrr-btn wrr-btn-primary" x-on:click="saveTemplate()" x-bind:disabled="saving">'
			. '<span x-show="!saving">' . esc_html__( 'Save template', 'woocommerce-review-reminder' ) . '</span>'
			. '<span x-show="saving" x-cloak>' . esc_html__( 'Saving…', 'woocommerce-review-reminder' ) . '</span></button>';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		// Preview modal.
		echo '<div x-show="preview" x-cloak class="wrr-modal-overlay" x-transition.opacity>';
		echo '<div class="wrr-modal" x-on:click.outside="closePreview()" x-trap="preview">';
		echo '<div class="wrr-modal-header">';
		echo '<h3 class="text-lg font-semibold text-gray-900" x-text="preview ? preview.name : \'\'"></h3>';
		echo '<button type="button" class="text-gray-400 hover:text-gray-600" x-on:click="closePreview()" aria-label="' . esc_attr__( 'Close', 'woocommerce-review-reminder' ) . '">' . Icons::get( 'x' ) . '</button>';
		echo '</div>';
		echo '<div class="wrr-modal-body">';
		echo '<p class="text-sm font-medium text-gray-700" x-text="preview ? \'Subject: \' + preview.subject : \'\'"></p>';
		echo '<div class="mt-3 whitespace-pre-wrap rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700" x-text="preview ? preview.body : \'\'"></div>';
		echo '</div>';
		echo '<div class="wrr-modal-footer">';
		echo '<button type="button" class="wrr-btn wrr-btn-secondary" x-on:click="closePreview()">' . esc_html__( 'Close', 'woocommerce-review-reminder' ) . '</button>';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		echo '</div>'; // root x-data
		View::close();
	}
}
