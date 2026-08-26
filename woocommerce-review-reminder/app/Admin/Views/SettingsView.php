<?php
/**
 * Settings admin view.
 *
 * @package WooCommerceReviewReminder\Admin\Views
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Admin\Views;

use WooCommerceReviewReminder\Core\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Class SettingsView
 */
final class SettingsView {

	/**
	 * Render the settings page.
	 */
	public static function render(): void {
		/** @var Config $config */
		$config = View::service( Config::class );
		$data   = array( 'settings' => $config->all() );

		View::open();
		echo '<div x-data="wrrSettings(' . View::json_attr( $data['settings'] ) . ')">';

		echo View::page_header(
			__( 'Settings', 'woocommerce-review-reminder' ),
			__( 'Global configuration for review reminders.', 'woocommerce-review-reminder' ),
			'<button type="button" class="wrr-btn wrr-btn-primary" x-on:click="saveSettings()">' . Icons::get( 'check' ) . esc_html__( 'Save settings', 'woocommerce-review-reminder' ) . '</button>'
		);

		echo '<div class="mb-4 flex flex-wrap items-center gap-2">';
		$tabs = array(
			'general'      => __( 'General', 'woocommerce-review-reminder' ),
			'automation'   => __( 'Automation', 'woocommerce-review-reminder' ),
			'reviews'      => __( 'Reviews', 'woocommerce-review-reminder' ),
			'privacy'      => __( 'Privacy', 'woocommerce-review-reminder' ),
			'suppressions' => __( 'Suppressions', 'woocommerce-review-reminder' ),
			'advanced'     => __( 'Advanced', 'woocommerce-review-reminder' ),
		);
		foreach ( $tabs as $key => $label ) {
			echo '<button type="button" class="wrr-btn wrr-btn-sm ' . ( 'general' === $key ? 'wrr-btn-primary' : 'wrr-btn-secondary' ) . '" '
				. 'x-on:click="setTab(\'' . esc_attr( $key ) . '\')" x-show="tab === \'' . esc_attr( $key ) . '\' ? false : true" x-cloak>'
				. esc_html( $label ) . '</button>';
		}
		echo '</div>';

		// General.
		self::panel(
			'general',
			__( 'General', 'woocommerce-review-reminder' ),
			static function () {
				self::field_toggle( 'settings.general.enabled', __( 'Enable review reminders', 'woocommerce-review-reminder' ), __( 'Master switch for sending review request emails.', 'woocommerce-review-reminder' ) );
				self::field_text( 'settings.email.from_name', __( 'From name', 'woocommerce-review-reminder' ), __( 'Leave empty to use the site title.', 'woocommerce-review-reminder' ) );
				self::field_text( 'settings.email.from_email', __( 'From email', 'woocommerce-review-reminder' ), __( 'Leave empty to use the WordPress admin email.', 'woocommerce-review-reminder' ) );
				self::field_text( 'settings.email.reply_to', __( 'Reply-to email', 'woocommerce-review-reminder' ), __( 'Optional. Defaults to the from email.', 'woocommerce-review-reminder' ) );
				echo '<div class="wrr-field">';
				echo '<label class="wrr-label">' . esc_html__( 'Mail provider', 'woocommerce-review-reminder' ) . '</label>';
				echo '<select class="wrr-select" x-model="settings.email.provider"><option value="wordpress">' . esc_html__( 'WordPress (wp_mail)', 'woocommerce-review-reminder' ) . '</option></select>';
				echo '<p class="wrr-hint">' . esc_html__( 'WordPress uses your configured SMTP plugin or hosting mailer.', 'woocommerce-review-reminder' ) . '</p>';
				echo '</div>';
			}
		);

		// Automation.
		self::panel(
			'automation',
			__( 'Automation', 'woocommerce-review-reminder' ),
			static function () {
				self::field_number( 'settings.automation.default_delay', __( 'Default delay', 'woocommerce-review-reminder' ), 0, 365 );
				echo '<div class="wrr-field">';
				echo '<label class="wrr-label">' . esc_html__( 'Default delay unit', 'woocommerce-review-reminder' ) . '</label>';
				echo '<select class="wrr-select" x-model="settings.automation.default_delay_unit">';
				foreach ( array(
					'hours' => __( 'Hours', 'woocommerce-review-reminder' ),
					'days'  => __( 'Days', 'woocommerce-review-reminder' ),
					'weeks' => __( 'Weeks', 'woocommerce-review-reminder' ),
				) as $value => $label ) {
					echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
				}
				echo '</select></div>';
				echo '<div class="wrr-field">';
				echo '<label class="wrr-label">' . esc_html__( 'Default send time', 'woocommerce-review-reminder' ) . '</label>';
				echo '<input type="time" class="wrr-input" x-model="settings.automation.default_send_time" />';
				echo '</div>';
				self::field_number( 'settings.automation.max_reminders', __( 'Max follow-ups', 'woocommerce-review-reminder' ), 0, 10, __( 'How many follow-up emails may be sent per request.', 'woocommerce-review-reminder' ) );
				self::field_number( 'settings.automation.retry_count', __( 'Send retries', 'woocommerce-review-reminder' ), 0, 10, __( 'Retries before marking an email as failed.', 'woocommerce-review-reminder' ) );
				self::field_number( 'settings.automation.retry_delay', __( 'Retry delay (seconds)', 'woocommerce-review-reminder' ), 0, 3600 );
			}
		);

		// Reviews.
		self::panel(
			'reviews',
			__( 'Reviews', 'woocommerce-review-reminder' ),
			static function () {
				echo '<div class="wrr-field">';
				echo '<label class="wrr-label">' . esc_html__( 'Default strategy', 'woocommerce-review-reminder' ) . '</label>';
				echo '<select class="wrr-select" x-model="settings.reviews.strategy">';
				echo '<option value="grouped">' . esc_html__( 'Group all products into one email', 'woocommerce-review-reminder' ) . '</option>';
				echo '<option value="per_product">' . esc_html__( 'One email per product', 'woocommerce-review-reminder' ) . '</option>';
				echo '</select></div>';
				echo '<div class="wrr-field">';
				echo '<label class="wrr-label">' . esc_html__( 'Review link mode', 'woocommerce-review-reminder' ) . '</label>';
				echo '<select class="wrr-select" x-model="settings.reviews.review_url_mode">';
				echo '<option value="reviews_section">' . esc_html__( 'Scroll to reviews section on product page', 'woocommerce-review-reminder' ) . '</option>';
				echo '</select></div>';
				self::field_toggle( 'settings.reviews.review_detection', __( 'Detect new reviews', 'woocommerce-review-reminder' ), __( 'Automatically mark requests as reviewed when a matching review appears.', 'woocommerce-review-reminder' ) );
			}
		);

		// Privacy.
		self::panel(
			'privacy',
			__( 'Privacy', 'woocommerce-review-reminder' ),
			static function () {
				self::field_toggle( 'settings.privacy.unsubscribe', __( 'Allow unsubscribing', 'woocommerce-review-reminder' ), __( 'Add an unsubscribe link to review emails.', 'woocommerce-review-reminder' ) );
				self::field_number( 'settings.privacy.retention_days', __( 'Data retention (days)', 'woocommerce-review-reminder' ), 1, 7300, __( 'How long to keep request data before cleanup.', 'woocommerce-review-reminder' ) );
				echo '<div class="wrr-field">';
				echo '<label class="wrr-label">' . esc_html__( 'Log level', 'woocommerce-review-reminder' ) . '</label>';
				echo '<select class="wrr-select" x-model="settings.privacy.log_level">';
				foreach ( array(
					'debug'   => __( 'Debug', 'woocommerce-review-reminder' ),
					'info'    => __( 'Info', 'woocommerce-review-reminder' ),
					'warning' => __( 'Warning', 'woocommerce-review-reminder' ),
					'error'   => __( 'Error', 'woocommerce-review-reminder' ),
				) as $value => $label ) {
					echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
				}
				echo '</select></div>';
			}
		);

		// Suppressions.
		self::panel(
			'suppressions',
			__( 'Suppressions', 'woocommerce-review-reminder' ),
			static function () {
				echo '<div class="flex items-center gap-2">';
				echo '<input type="email" class="wrr-input" x-model="newSuppression" placeholder="' . esc_attr__( 'customer@example.com', 'woocommerce-review-reminder' ) . '" x-on:keydown.enter="addSuppression()" />';
				echo '<button type="button" class="wrr-btn wrr-btn-secondary" x-on:click="addSuppression()">' . esc_html__( 'Add', 'woocommerce-review-reminder' ) . '</button>';
				echo '</div>';
				echo '<p class="wrr-hint mt-2">' . esc_html__( 'Suppressed emails never receive review requests.', 'woocommerce-review-reminder' ) . '</p>';
				echo '<div class="mt-4" x-init="loadSuppressions()">';
				echo '<p class="text-sm font-medium text-gray-700" x-show="suppressionsLoading" x-cloak>' . esc_html__( 'Loading…', 'woocommerce-review-reminder' ) . '</p>';
				echo '<ul class="divide-y divide-gray-100" x-show="!suppressionsLoading && suppressions.length" x-cloak>';
				echo '<template x-for="s in suppressions" :key="s.id">';
				echo '<li class="flex items-center justify-between py-2.5">';
				echo '<span class="text-sm text-gray-700" x-text="s.email"></span>';
				echo '<button type="button" class="wrr-btn wrr-btn-ghost wrr-btn-sm" x-on:click="removeSuppression(s.email)">' . Icons::get( 'trash' ) . '</button>';
				echo '</li>';
				echo '</template>';
				echo '</ul>';
				echo '<p class="text-sm text-gray-500" x-show="!suppressionsLoading && !suppressions.length" x-cloak>' . esc_html__( 'No suppressed emails yet.', 'woocommerce-review-reminder' ) . '</p>';
				echo '</div>';
			}
		);

		// Advanced.
		self::panel(
			'advanced',
			__( 'Advanced', 'woocommerce-review-reminder' ),
			static function () {
				self::field_toggle( 'settings.advanced.debug_logging', __( 'Debug logging', 'woocommerce-review-reminder' ), __( 'Log detailed activity to the plugin log.', 'woocommerce-review-reminder' ) );
				self::field_toggle( 'settings.advanced.dev_mode', __( 'Development mode', 'woocommerce-review-reminder' ), __( 'Skips sending in real time for testing.', 'woocommerce-review-reminder' ) );
				self::field_toggle( 'settings.advanced.delete_on_uninstall', __( 'Delete data on uninstall', 'woocommerce-review-reminder' ), __( 'Removes all plugin data when uninstalled.', 'woocommerce-review-reminder' ) );

				echo '<hr class="my-4 border-gray-100" />';
				echo '<h3 class="text-sm font-semibold text-gray-900">' . esc_html__( 'Test email', 'woocommerce-review-reminder' ) . '</h3>';
				echo '<p class="wrr-hint">' . esc_html__( 'Send a sample reminder to verify delivery and formatting.', 'woocommerce-review-reminder' ) . '</p>';
				echo '<div class="mt-3 grid gap-4 sm:grid-cols-3">';
				echo '<div class="wrr-field"><label class="wrr-label">' . esc_html__( 'Recipient', 'woocommerce-review-reminder' ) . '</label>';
				echo '<input type="email" class="wrr-input" x-model="testTo" placeholder="' . esc_attr__( 'you@example.com', 'woocommerce-review-reminder' ) . '" /></div>';
				echo '<div class="wrr-field"><label class="wrr-label">' . esc_html__( 'Subject', 'woocommerce-review-reminder' ) . '</label>';
				echo '<input type="text" class="wrr-input" x-model="testSubject" placeholder="' . esc_attr__( 'Optional subject override', 'woocommerce-review-reminder' ) . '" /></div>';
				echo '<div class="wrr-field"><label class="wrr-label">' . esc_html__( 'Body', 'woocommerce-review-reminder' ) . '</label>';
				echo '<input type="text" class="wrr-input" x-model="testBody" placeholder="' . esc_attr__( 'Optional body override', 'woocommerce-review-reminder' ) . '" /></div>';
				echo '</div>';
				echo '<button type="button" class="wrr-btn wrr-btn-secondary mt-2" x-on:click="sendTest()" x-bind:disabled="sendingTest">'
					. Icons::get( 'send' ) . ' <span x-show="!sendingTest">' . esc_html__( 'Send test email', 'woocommerce-review-reminder' ) . '</span>'
					. '<span x-show="sendingTest" x-cloak>' . esc_html__( 'Sending…', 'woocommerce-review-reminder' ) . '</span></button>';
			}
		);

		echo '</div>'; // root x-data
		View::close();
	}

	/**
	 * Render one settings panel.
	 *
	 * @param string   $key Tab key.
	 * @param string   $title Panel title.
	 * @param callable $render Renders the panel body.
	 */
	private static function panel( string $key, string $title, callable $render ): void {
		echo '<div x-show="tab === \'' . esc_attr( $key ) . '\'" x-cloak>';
		echo '<div class="wrr-card">';
		echo '<div class="wrr-card-header"><div><h2 class="wrr-card-title">' . esc_html( $title ) . '</h2></div></div>';
		echo '<div class="wrr-card-body max-w-2xl">';
		call_user_func( $render );
		echo '</div></div></div>';
	}

	/**
	 * Text field.
	 *
	 * @param string $model  x-model expression.
	 * @param string $label  Label.
	 * @param string $hint   Hint text.
	 */
	private static function field_text( string $model, string $label, string $hint = '' ): void {
		echo '<div class="wrr-field">';
		echo '<label class="wrr-label">' . esc_html( $label ) . '</label>';
		echo '<input type="text" class="wrr-input" x-model="' . esc_attr( $model ) . '" />';
		if ( $hint ) {
			echo '<p class="wrr-hint">' . esc_html( $hint ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Number field.
	 *
	 * @param string $model  x-model expression.
	 * @param string $label  Label.
	 * @param int    $min    Min.
	 * @param int    $max    Max.
	 * @param string $hint   Hint text.
	 */
	private static function field_number( string $model, string $label, int $min, int $max, string $hint = '' ): void {
		echo '<div class="wrr-field">';
		echo '<label class="wrr-label">' . esc_html( $label ) . '</label>';
		echo '<input type="number" class="wrr-input" x-model="' . esc_attr( $model ) . '" x-bind:min="' . (int) $min . '" x-bind:max="' . (int) $max . '" />';
		if ( $hint ) {
			echo '<p class="wrr-hint">' . esc_html( $hint ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Toggle field.
	 *
	 * @param string $model  x-model expression.
	 * @param string $label  Label.
	 * @param string $hint   Hint text.
	 */
	private static function field_toggle( string $model, string $label, string $hint = '' ): void {
		echo '<label class="flex items-start justify-between gap-4 rounded-lg border border-gray-100 p-3">';
		echo '<span><span class="text-sm font-medium text-gray-800">' . esc_html( $label ) . '</span>';
		if ( $hint ) {
			echo '<span class="block text-xs text-gray-500">' . esc_html( $hint ) . '</span>';
		}
		echo '</span>';
		echo '<input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" x-model="' . esc_attr( $model ) . '" />';
		echo '</label>';
	}
}
