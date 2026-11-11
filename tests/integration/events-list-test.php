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
use ReEvents\Query\Event_Query;
use ReEvents\Render\Events_List;
use ReEvents\Services\Occurrence_Service;
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
	 * The current venue filters its own page.
	 */
	public function test_current_venue_filter(): void {
		$venue = wp_insert_post(
			[
				'post_type'   => 'reevents_venue',
				'post_status' => 'publish',
				'post_title'  => 'Filter venue',
			]
		);

		$inside  = Test_Factory::event(
			[
				'title' => 'Venue event ' . wp_generate_uuid4(),
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+3 days' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+3 days +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
					'_reevents_venue_id'  => $venue,
				],
			]
		);
		$outside = Test_Factory::event(
			[
				'title' => 'Other event ' . wp_generate_uuid4(),
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+3 days' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+3 days +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
				],
			]
		);

		$this->go_to( get_permalink( $venue ) );
		$html = Events_List::render(
			[
				'limit'    => 20,
				'venue'    => 'current',
				'upcoming' => false,
			]
		);

		self::assertStringContainsString( get_the_title( $inside ), $html );
		self::assertStringNotContainsString( get_the_title( $outside ), $html );
	}

	/**
	 * A current filter on the wrong page returns no events.
	 */
	public function test_current_filter_on_wrong_page(): void {
		$page = self::factory()->post->create( [ 'post_title' => 'Plain page' ] );

		$this->go_to( get_permalink( $page ) );

		self::assertStringContainsString(
			'No events found.',
			Events_List::render(
				[
					'limit'    => 5,
					'venue'    => 'current',
					'upcoming' => false,
				]
			)
		);
		self::assertStringContainsString(
			'No events found.',
			Events_List::render(
				[
					'limit'     => 5,
					'organizer' => 'current',
					'upcoming'  => false,
				]
			)
		);
	}

	/**
	 * Organizer filtering uses the stored organizer list.
	 */
	public function test_organizer_filter(): void {
		$first  = wp_insert_post(
			[
				'post_type'   => 'reevents_organizer',
				'post_status' => 'publish',
				'post_title'  => 'Org A',
			]
		);
		$second = wp_insert_post(
			[
				'post_type'   => 'reevents_organizer',
				'post_status' => 'publish',
				'post_title'  => 'Org B',
			]
		);

		$inside  = Test_Factory::event(
			[
				'title' => 'Org event ' . wp_generate_uuid4(),
				'meta'  => [
					'_reevents_start_utc'     => gmdate( 'Y-m-d H:i:s', strtotime( '+4 days' ) ),
					'_reevents_end_utc'       => gmdate( 'Y-m-d H:i:s', strtotime( '+4 days +1 hour' ) ),
					'_reevents_timezone'      => 'UTC',
					'_reevents_organizer_ids' => [ $first ],
				],
			]
		);
		$outside = Test_Factory::event(
			[
				'title' => 'Other org event ' . wp_generate_uuid4(),
				'meta'  => [
					'_reevents_start_utc'     => gmdate( 'Y-m-d H:i:s', strtotime( '+4 days' ) ),
					'_reevents_end_utc'       => gmdate( 'Y-m-d H:i:s', strtotime( '+4 days +1 hour' ) ),
					'_reevents_timezone'      => 'UTC',
					'_reevents_organizer_ids' => [ $second ],
				],
			]
		);

		$result = Event_Query::query(
			[
				'organizer' => $first,
				'upcoming'  => false,
				'per_page'  => 100,
			]
		);

		self::assertContains( $inside, $result['ids'] );
		self::assertNotContains( $outside, $result['ids'] );
	}

	/**
	 * A date range with a venue filter paginates the filtered set.
	 */
	public function test_filtered_date_range_paginates(): void {
		$venue = wp_insert_post(
			[
				'post_type'   => 'reevents_venue',
				'post_status' => 'publish',
				'post_title'  => 'Paged venue ' . wp_generate_uuid4(),
			]
		);

		$ids = [];

		for ( $i = 0; $i < 2; $i++ ) {
			$ids[] = Test_Factory::event(
				[
					'title' => 'Venue event ' . $i . ' ' . wp_generate_uuid4(),
					'meta'  => [
						'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days +' . $i . ' hours' ) ),
						'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days +' . ( $i + 1 ) . ' hours' ) ),
						'_reevents_timezone'  => 'UTC',
						'_reevents_status'    => 'scheduled',
						'_reevents_venue_id'  => $venue,
					],
				]
			);
		}

		$outside = Test_Factory::event(
			[
				'title' => 'No venue ' . wp_generate_uuid4(),
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
					'_reevents_status'    => 'scheduled',
				],
			]
		);

		foreach ( array_merge( $ids, [ $outside ] ) as $event_id ) {
			Occurrence_Service::sync( $event_id );
		}

		$args = [
			'venue'    => $venue,
			'from'     => gmdate( 'Y-m-d', strtotime( '+1 day' ) ),
			'to'       => gmdate( 'Y-m-d', strtotime( '+5 days' ) ),
			'per_page' => 1,
			'upcoming' => false,
		];

		$first = Event_Query::query( array_merge( $args, [ 'page' => 1 ] ) );
		$last  = Event_Query::query( array_merge( $args, [ 'page' => 2 ] ) );

		self::assertSame( 2, $first['total'] );
		self::assertSame( 2, $first['pages'] );
		self::assertCount( 1, $first['ids'] );
		self::assertCount( 1, $last['ids'] );
		self::assertNotSame( $first['ids'][0], $last['ids'][0] );
		self::assertSame( [ $ids[0], $ids[1] ], array_merge( $first['ids'], $last['ids'] ) );
	}

	/**
	 * Date bounds are site-local, not UTC.
	 */
	public function test_date_range_uses_site_timezone(): void {
		update_option( 'timezone_string', 'Pacific/Auckland' );

		$event = Test_Factory::event(
			[
				'title' => 'Auckland midnight ' . wp_generate_uuid4(),
				'meta'  => [
					'_reevents_start_utc' => '2026-11-30 12:00:00',
					'_reevents_end_utc'   => '2026-11-30 13:00:00',
					'_reevents_timezone'  => 'Pacific/Auckland',
					'_reevents_status'    => 'scheduled',
				],
			]
		);

		Occurrence_Service::sync( $event );

		$result = Event_Query::query(
			[
				'from'     => '2026-12-01',
				'to'       => '2026-12-01',
				'per_page' => 10,
				'upcoming' => false,
			]
		);

		self::assertContains( $event, $result['ids'] );

		update_option( 'timezone_string', '' );
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
