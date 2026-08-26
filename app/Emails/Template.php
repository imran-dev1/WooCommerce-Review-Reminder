<?php
/**
 * Email template entity.
 *
 * @package WooCommerceReviewReminder\Emails
 */

declare( strict_types=1 );

namespace WooCommerceReviewReminder\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Class Template
 */
final class Template {

	/**
	 * Row data.
	 *
	 * @var array<string, mixed>
	 */
	private array $data;

	/**
	 * Template constructor.
	 *
	 * @param array<string, mixed> $data Row data.
	 */
	public function __construct( array $data ) {
		$this->data = $data;
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
	 * Template id.
	 *
	 * @return int
	 */
	public function id(): int {
		return (int) ( $this->data['id'] ?? 0 );
	}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name(): string {
		return (string) ( $this->data['name'] ?? '' );
	}

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return (string) ( $this->data['slug'] ?? '' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description(): string {
		return (string) ( $this->data['description'] ?? '' );
	}

	/**
	 * Whether this is a built-in template.
	 *
	 * @return bool
	 */
	public function is_builtin(): bool {
		return (bool) ( $this->data['is_builtin'] ?? 0 );
	}

	/**
	 * Subject with variables.
	 *
	 * @return string
	 */
	public function subject(): string {
		return (string) ( $this->data['subject'] ?? '' );
	}

	/**
	 * Body HTML with variables.
	 *
	 * @return string
	 */
	public function body(): string {
		return (string) ( $this->data['body'] ?? '' );
	}
}
