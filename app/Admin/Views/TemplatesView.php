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
			$arr       = $template->to_array();
			$body_text = trim( (string) wp_strip_all_tags( $arr['body'] ?? '' ) );
			$body_text = preg_replace( '/\s+/', ' ', $body_text );
			$items[]   = array(
				'id'          => (int) $arr['id'],
				'name'        => (string) $arr['name'],
				'description' => (string) ( $arr['description'] ?? '' ),
				'subject'     => (string) $arr['subject'],
				'body'        => (string) $arr['body'],
				'body_text'   => (string) $body_text,
				'is_builtin'  => (bool) $arr['is_builtin'],
			);
		}

		$data = array( 'items' => $items );

		View::open();
		echo '<div x-data="wrrTemplates(' . View::json_attr( $data ) . ')">';

		echo View::page_header(
			__( 'Email Templates', 'woocommerce-review-reminder' ),
			__( 'Reusable email templates for your review requests.', 'woocommerce-review-reminder' ),
			'<button type="button" class="wrr-btn wrr-btn-primary" x-on:click="openEdit(null)">' . Icons::get( 'plus' ) . esc_html__( 'New email template', 'woocommerce-review-reminder' ) . '</button>'
		);

		echo '<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" x-cloak>';

		echo '<template x-for="t in items" :key="t.id">';
		echo '<div class="wrr-card wrr-template-card">';
		echo '<div class="wrr-template-head">';
		echo '<span class="wrr-template-head-icon">' . Icons::get( 'mail', 'h-5 w-5' ) . '</span>';
		echo '<span class="wrr-badge wrr-badge-lavender" x-text="t.is_builtin ? \'Built-in\' : \'Custom\'"></span>';
		echo '</div>';
		echo '<div class="wrr-card-body">';
		echo '<h3 class="wrr-card-title" x-text="t.name"></h3>';
		echo '<p class="wrr-card-desc" x-show="t.description" x-cloak x-text="t.description"></p>';
		echo '<p class="wrr-card-label">' . esc_html__( 'Subject', 'woocommerce-review-reminder' ) . '</p>';
		echo '<p class="wrr-card-value truncate" x-text="t.subject"></p>';
		echo '<p class="wrr-card-label">' . esc_html__( 'Preview', 'woocommerce-review-reminder' ) . '</p>';
		echo '<div class="wrr-card-preview" x-text="t.body_text"></div>';
		echo '<div class="wrr-card-actions">';
		echo '<div class="flex items-center gap-2">';
		echo '<button type="button" class="wrr-icon-btn" x-on:click="openPreview(t)" aria-label="' . esc_attr__( 'Preview', 'woocommerce-review-reminder' ) . '" title="' . esc_attr__( 'Preview', 'woocommerce-review-reminder' ) . '">' . Icons::get( 'eye', 'h-4 w-4' ) . '</button>';
		echo '<button type="button" class="wrr-icon-btn" x-on:click="openTest(t)" aria-label="' . esc_attr__( 'Send test email', 'woocommerce-review-reminder' ) . '" title="' . esc_attr__( 'Send test email', 'woocommerce-review-reminder' ) . '">' . Icons::get( 'send', 'h-4 w-4' ) . '</button>';
		echo '<button type="button" class="wrr-icon-btn wrr-icon-btn-danger" x-show="!t.is_builtin" x-on:click="askDelete(t.id, t.name)" aria-label="' . esc_attr__( 'Delete', 'woocommerce-review-reminder' ) . '" title="' . esc_attr__( 'Delete', 'woocommerce-review-reminder' ) . '">' . Icons::get( 'trash', 'h-4 w-4' ) . '</button>';
		echo '</div>';
		echo '<button type="button" class="wrr-btn wrr-btn-purple" x-on:click="openEdit(t)">' . Icons::get( 'edit', 'h-4 w-4' ) . esc_html__( 'Edit', 'woocommerce-review-reminder' ) . '</button>';
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
		echo '<h3 class="text-lg font-semibold text-gray-900" x-text="form.id > 0 ? \'Edit email template\' : \'New email template\'"></h3>';
		echo '<button type="button" class="text-gray-400 hover:text-gray-600" x-on:click="closeForm()" aria-label="' . esc_attr__( 'Close', 'woocommerce-review-reminder' ) . '">' . Icons::get( 'x' ) . '</button>';
		echo '</div>';
		echo '<div class="wrr-modal-body">';
		echo '<div class="wrr-field">';
		echo '<label class="wrr-label">' . esc_html__( 'Name', 'woocommerce-review-reminder' ) . '</label>';
		echo '<input type="text" class="wrr-input" x-model="form.name" placeholder="' . esc_attr__( 'e.g. Thank you & review', 'woocommerce-review-reminder' ) . '" />';
		echo '</div>';
		echo '<div class="wrr-field">';
		echo '<label class="wrr-label">' . esc_html__( 'Description', 'woocommerce-review-reminder' ) . '</label>';
		echo '<input type="text" class="wrr-input" x-model="form.description" placeholder="' . esc_attr__( 'Short description shown on the template card.', 'woocommerce-review-reminder' ) . '" />';
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
		echo '<button type="button" class="wrr-btn wrr-btn-secondary" x-on:click="openTestFromForm()" x-bind:disabled="saving">' . Icons::get( 'send', 'h-4 w-4' ) . esc_html__( 'Send test', 'woocommerce-review-reminder' ) . '</button>';
		echo '<button type="button" class="wrr-btn wrr-btn-secondary" x-on:click="closeForm()">' . esc_html__( 'Cancel', 'woocommerce-review-reminder' ) . '</button>';
		echo '<button type="button" class="wrr-btn wrr-btn-primary" x-on:click="saveTemplate()" x-bind:disabled="saving">'
			. '<span x-show="!saving">' . esc_html__( 'Save email template', 'woocommerce-review-reminder' ) . '</span>'
			. '<span x-show="saving" x-cloak>' . esc_html__( 'Saving…', 'woocommerce-review-reminder' ) . '</span></button>';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		// Preview modal.
		echo '<div x-show="preview" x-cloak class="wrr-modal-overlay" x-transition.opacity>';
		echo '<div class="wrr-modal wrr-modal-wide" x-on:click.outside="closePreview()" x-trap="preview">';
		echo '<div class="wrr-modal-header">';
		echo '<h3 class="text-lg font-semibold text-gray-900" x-text="preview ? preview.name : \'\'"></h3>';
		echo '<button type="button" class="text-gray-400 hover:text-gray-600" x-on:click="closePreview()" aria-label="' . esc_attr__( 'Close', 'woocommerce-review-reminder' ) . '">' . Icons::get( 'x' ) . '</button>';
		echo '</div>';
		echo '<div class="wrr-modal-body">';
		echo '<p class="text-sm font-medium text-gray-700" x-text="previewHtml && previewHtml.subject ? \'Subject: \' + previewHtml.subject : \'\'"></p>';
		echo '<div class="mt-3" x-show="previewLoading" x-cloak><div class="rounded-lg border border-gray-200 bg-gray-50 p-6 text-sm text-gray-500">' . esc_html__( 'Rendering preview…', 'woocommerce-review-reminder' ) . '</div></div>';
		echo '<div class="mt-3 overflow-hidden rounded-lg border border-gray-200 bg-white" x-show="previewHtml && previewHtml.body" x-cloak>';
		echo '<iframe class="wrr-preview-frame" :srcdoc="previewHtml ? previewHtml.body : \'\'" sandbox="" title="' . esc_attr__( 'Email preview', 'woocommerce-review-reminder' ) . '"></iframe>';
		echo '</div>';
		echo '<p class="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-6 text-sm text-gray-500" x-show="!previewLoading && !(previewHtml && previewHtml.body)" x-cloak>' . esc_html__( 'No preview available.', 'woocommerce-review-reminder' ) . '</p>';
		echo '</div>';
		echo '<div class="wrr-modal-footer">';
		echo '<button type="button" class="wrr-btn wrr-btn-secondary" x-on:click="closePreview()">' . esc_html__( 'Close', 'woocommerce-review-reminder' ) . '</button>';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		// Send test email modal.
		echo '<div x-show="testOpen" x-cloak class="wrr-modal-overlay" x-transition.opacity>';
		echo '<div class="wrr-modal" x-on:click.outside="closeTest()" x-trap="testOpen">';
		echo '<div class="wrr-modal-header">';
		echo '<h3 class="text-lg font-semibold text-gray-900">' . esc_html__( 'Send test email', 'woocommerce-review-reminder' ) . '</h3>';
		echo '<button type="button" class="text-gray-400 hover:text-gray-600" x-on:click="closeTest()" aria-label="' . esc_attr__( 'Close', 'woocommerce-review-reminder' ) . '">' . Icons::get( 'x' ) . '</button>';
		echo '</div>';
		echo '<div class="wrr-modal-body">';
		echo '<p class="text-sm text-gray-600" x-show="testName" x-cloak x-text="\'Template: \' + testName"></p>';
		echo '<div class="wrr-field">';
		echo '<label class="wrr-label">' . esc_html__( 'Recipient email', 'woocommerce-review-reminder' ) . '</label>';
		echo '<input type="email" class="wrr-input" x-model="testTo" placeholder="' . esc_attr__( 'you@example.com', 'woocommerce-review-reminder' ) . '" />';
		echo '</div>';
		echo '<p class="text-xs text-gray-400">' . esc_html__( 'The template is rendered with sample data and sent to the address above.', 'woocommerce-review-reminder' ) . '</p>';
		echo '</div>';
		echo '<div class="wrr-modal-footer">';
		echo '<button type="button" class="wrr-btn wrr-btn-secondary" x-on:click="closeTest()">' . esc_html__( 'Cancel', 'woocommerce-review-reminder' ) . '</button>';
		echo '<button type="button" class="wrr-btn wrr-btn-primary" x-on:click="sendTest()" x-bind:disabled="sendingTest">'
			. '<span x-show="!sendingTest">' . esc_html__( 'Send test email', 'woocommerce-review-reminder' ) . '</span>'
			. '<span x-show="sendingTest" x-cloak>' . esc_html__( 'Sending…', 'woocommerce-review-reminder' ) . '</span></button>';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		echo '</div>'; // root x-data
		View::close();
	}
}
