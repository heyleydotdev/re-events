<?php
/**
 * Event field tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Render\Field;
use WP_UnitTestCase;

/**
 * Covers the [reevents_field] renderer.
 */
final class Field_Test extends WP_UnitTestCase {

	/**
	 * Administrator with the plugin capabilities.
	 *
	 * @var int
	 */
	private int $admin = 0;

	/**
	 * Create an administrator.
	 */
	public function set_up(): void {
		parent::set_up();

		\ReEvents\Capabilities::add();
		delete_option( 'reevents_settings' );

		$this->admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin );
	}

	/**
	 * Create an event.
	 *
	 * @param array<string, mixed> $meta Extra meta.
	 */
	private function event( array $meta = [] ): int {
		return Test_Factory::event(
			[
				'title' => 'Field event & more',
				'meta'  => array_merge(
					[
						'_reevents_start_utc' => '2027-03-04 18:30:00',
						'_reevents_end_utc'   => '2027-03-04 20:00:00',
						'_reevents_timezone'  => 'UTC',
					],
					$meta
				),
			]
		);
	}

	/**
	 * Text fields are escaped and wrapped.
	 */
	public function test_text_fields(): void {
		$id    = $this->event();
		$title = Field::render(
			[
				'field' => 'title',
				'event' => $id,
			]
		);

		self::assertStringContainsString( 'Field event &amp; more', $title );
		self::assertStringContainsString( 'Field event', Field::render( [ 'event' => $id ] ) );
		self::assertStringContainsString( '<span class="reevents-field reevents-field-title">', $title );
		self::assertStringContainsString(
			'2027',
			Field::render(
				[
					'field' => 'start',
					'event' => $id,
				]
			)
		);
		self::assertSame(
			'',
			Field::render(
				[
					'field' => 'unknown',
					'event' => $id,
				]
			)
		);
	}

	/**
	 * Empty fields honor empty_label.
	 */
	public function test_empty_label(): void {
		$id = $this->event();

		self::assertSame(
			'',
			Field::render(
				[
					'field' => 'venue',
					'event' => $id,
				]
			)
		);
		self::assertStringContainsString(
			'No venue',
			Field::render(
				[
					'field'       => 'venue',
					'event'       => $id,
					'empty_label' => 'No venue',
				]
			)
		);
	}

	/**
	 * Labels, links and tags are applied.
	 */
	public function test_label_link_and_tag(): void {
		$id   = $this->event();
		$html = Field::render(
			[
				'field' => 'title',
				'event' => $id,
				'link'  => true,
				'label' => 'Event:',
				'tag'   => 'p',
			]
		);

		self::assertStringContainsString( '<p class="reevents-field reevents-field-title">', $html );
		self::assertStringContainsString( '<span class="reevents-field-label">Event:</span>', $html );
		self::assertStringContainsString( '<a href="', $html );
	}

	/**
	 * Drafts stay hidden from anonymous callers.
	 */
	public function test_draft_is_hidden(): void {
		$id = $this->event();

		wp_update_post(
			[
				'ID'          => $id,
				'post_status' => 'draft',
			]
		);

		self::assertNotSame(
			'',
			Field::render(
				[
					'field' => 'title',
					'event' => $id,
				]
			)
		);

		wp_set_current_user( 0 );

		self::assertSame(
			'',
			Field::render(
				[
					'field' => 'title',
					'event' => $id,
				]
			)
		);
	}

	/**
	 * Terms and online links are joined.
	 */
	public function test_terms_and_links(): void {
		$id = $this->event(
			[
				'_reevents_online_urls' => [
					[
						'url'   => 'https://example.com/live',
						'label' => 'Live stream',
					],
				],
			]
		);

		wp_set_object_terms( $id, [ 'Conference' ], 'reevents_event_category' );

		self::assertStringContainsString(
			'Conference',
			Field::render(
				[
					'field' => 'categories',
					'event' => $id,
				]
			)
		);
		self::assertStringContainsString(
			'Live stream',
			Field::render(
				[
					'field' => 'online_links',
					'event' => $id,
				]
			)
		);
	}

	/**
	 * Password-protected event content stays hidden.
	 */
	public function test_protected_content_is_hidden(): void {
		$id = $this->event();

		wp_update_post(
			[
				'ID'            => $id,
				'post_password' => 'letmein',
				'post_content'  => 'TOP SECRET CONTENT',
			]
		);

		wp_set_current_user( 0 );

		self::assertSame(
			'',
			Field::render(
				[
					'field' => 'content',
					'event' => $id,
				]
			)
		);
		self::assertStringNotContainsString( 'TOP SECRET', \ReEvents\Render\Event_Details::render( [ 'id' => $id ] ) );
	}

	/**
	 * Several classes survive as separate classes.
	 */
	public function test_multiple_classes_are_kept(): void {
		$id = $this->event();

		self::assertStringContainsString(
			'class="reevents-field reevents-field-title text-lg mt-4"',
			Field::render(
				[
					'field' => 'title',
					'event' => $id,
					'class' => 'text-lg mt-4',
				]
			)
		);
	}

	/**
	 * The shortcode is registered.
	 */
	public function test_shortcode(): void {
		$id = $this->event();

		self::assertStringContainsString(
			'Field event',
			do_shortcode( '[reevents_field field="title" event="' . $id . '"]' )
		);
	}
}
