<?php
/**
 * Events list rendering tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Data\Ticket_Repository;
use ReEvents\Render\Events_List;
use WP_UnitTestCase;

/**
 * Covers the events list renderer.
 */
final class Events_List_Test extends WP_UnitTestCase {

	/**
	 * Create events starting one day apart.
	 *
	 * @param int $count Number of events.
	 */
	private function events( int $count ): string {
		$prefix = 'pgn' . str_replace( '-', '', wp_generate_uuid4() );

		for ( $i = 1; $i <= $count; $i++ ) {
			Test_Factory::event(
				[
					'title' => $prefix . ' ' . $i,
					'meta'  => [
						'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+' . $i . ' day' ) ),
						'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+' . $i . ' day +1 hour' ) ),
						'_reevents_timezone'  => 'UTC',
					],
				]
			);
		}

		return $prefix;
	}

	/**
	 * Card arguments print image, price, excerpt and categories.
	 */
	public function test_card_data(): void {
		$id     = Test_Factory::event(
			[
				'title' => 'Card event ' . wp_generate_uuid4(),
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
				],
			]
		);
		$ticket = Test_Factory::ticket(
			$id,
			[
				'capacity'    => 1,
				'price_minor' => 2500,
			]
		);

		wp_update_post(
			[
				'ID'           => $id,
				'post_excerpt' => 'Short teaser',
			]
		);
		wp_set_object_terms( $id, [ 'Conference' ], 'reevents_event_category' );

		$attachment = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $id );
		set_post_thumbnail( $id, $attachment );

		$html = Events_List::render(
			[
				'limit'           => 5,
				'upcoming'        => false,
				'search'          => (string) get_the_title( $id ),
				'show_image'      => true,
				'show_price'      => true,
				'show_excerpt'    => true,
				'show_categories' => true,
			]
		);

		self::assertStringContainsString( '<img', $html );
		self::assertStringContainsString( '25.00 USD', $html );
		self::assertStringContainsString( 'Short teaser', $html );
		self::assertStringContainsString( 'Conference', $html );

		( new Ticket_Repository() )->reserve( $ticket, 1 );

		self::assertStringContainsString(
			'Sold out',
			Events_List::render(
				[
					'limit'      => 5,
					'upcoming'   => false,
					'search'     => (string) get_the_title( $id ),
					'show_price' => true,
				]
			)
		);
	}

	/**
	 * The page argument returns one page and pager links.
	 */
	public function test_page_argument(): void {
		$prefix = $this->events( 7 );

		$html = Events_List::render(
			[
				'limit'    => 3,
				'page'     => 2,
				'upcoming' => false,
				'search'   => $prefix,
			]
		);

		self::assertSame( 3, substr_count( $html, '<li class="reevents-event">' ) );
		self::assertStringContainsString( $prefix . ' 4', $html );
		self::assertStringNotContainsString( $prefix . ' 1<', $html );
		self::assertStringContainsString( 'reevents-pagination', $html );
		self::assertStringContainsString( 'reevents_page=3', $html );
	}

	/**
	 * The public page argument beats a fixed page attribute.
	 */
	public function test_query_argument_beats_page_attribute(): void {
		$prefix = $this->events( 4 );

		$_GET['reevents_page'] = '2';

		$html = Events_List::render(
			[
				'limit'    => 3,
				'page'     => 1,
				'upcoming' => false,
				'search'   => $prefix,
			]
		);

		unset( $_GET['reevents_page'] );

		self::assertSame( 1, substr_count( $html, '<li class="reevents-event">' ) );
		self::assertStringContainsString( $prefix . ' 4', $html );
	}

	/**
	 * The public page query argument is used when no argument is passed.
	 */
	public function test_query_argument(): void {
		$prefix = $this->events( 4 );

		$_GET['reevents_page'] = '2';

		$html = Events_List::render(
			[
				'limit'    => 3,
				'upcoming' => false,
				'search'   => $prefix,
			]
		);

		unset( $_GET['reevents_page'] );

		self::assertSame( 1, substr_count( $html, '<li class="reevents-event">' ) );
		self::assertStringContainsString( $prefix . ' 4', $html );
	}
}
