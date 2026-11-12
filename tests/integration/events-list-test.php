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
use ReEvents\Render\Venues_List;
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
	 * A teaser list ignores the page argument used by the main list.
	 */
	public function test_sidebar_list_ignores_main_page(): void {
		$prefix = $this->events( 7 );

		$_GET['reevents_page'] = '2';

		$html = Events_List::render(
			[
				'limit'    => 5,
				'paginate' => 0,
				'upcoming' => false,
				'search'   => $prefix,
			]
		);

		self::assertStringContainsString( $prefix . ' 1', $html );
		self::assertStringNotContainsString( 'reevents-pagination', $html );
	}

	/**
	 * A custom pagination key reads and writes its own page argument.
	 */
	public function test_custom_pagination_key(): void {
		$prefix = $this->events( 7 );

		$_GET['reevents_page_main'] = '2';

		$html = Events_List::render(
			[
				'limit'          => 3,
				'pagination_key' => 'main',
				'upcoming'       => false,
				'search'         => $prefix,
			]
		);

		self::assertStringContainsString( $prefix . ' 4', $html );
		self::assertStringContainsString( 'reevents-pagination', $html );
	}

	/**
	 * Venue lists paginate and expose the venue page argument.
	 */
	public function test_venue_list_paginates(): void {
		$existing = get_posts(
			[
				'post_type'      => 'reevents_venue',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		foreach ( $existing as $venue_id ) {
			wp_delete_post( (int) $venue_id, true );
		}

		for ( $i = 1; $i <= 4; $i++ ) {
			wp_insert_post(
				[
					'post_type'   => 'reevents_venue',
					'post_status' => 'publish',
					'post_title'  => 'Paged venue ' . $i,
				]
			);
		}

		$html = Venues_List::render(
			[
				'limit' => 2,
				'page'  => 2,
			]
		);

		self::assertStringContainsString( 'Paged venue 3', $html );
		self::assertStringNotContainsString( 'Paged venue 1', $html );
		self::assertStringContainsString( 'reevents-pagination', $html );
	}

	/**
	 * The list can read the event search form values.
	 */
	public function test_list_uses_search_query(): void {
		Test_Factory::event(
			[
				'title' => 'Searchable alpha',
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
				],
			]
		);
		Test_Factory::event(
			[
				'title' => 'Searchable beta',
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
				],
			]
		);

		$_GET['reevents_search'] = 'alpha';

		$html = Events_List::render(
			[
				'use_query' => 1,
				'limit'     => 10,
				'upcoming'  => false,
			]
		);

		self::assertStringContainsString( 'Searchable alpha', $html );
		self::assertStringNotContainsString( 'Searchable beta', $html );
	}

	/**
	 * Use-query keeps attributes whose fields the form did not submit.
	 */
	public function test_use_query_keeps_unsubmitted_attributes(): void {
		$past   = Test_Factory::event(
			[
				'title' => 'Searchable alpha past',
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
					'_reevents_status'    => 'scheduled',
				],
			]
		);
		$future = Test_Factory::event(
			[
				'title' => 'Searchable alpha future',
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
					'_reevents_status'    => 'scheduled',
				],
			]
		);

		Occurrence_Service::sync( $past );
		Occurrence_Service::sync( $future );

		$_GET['reevents_search'] = 'alpha';

		$html = Events_List::render(
			[
				'use_query' => 1,
				'upcoming'  => true,
				'limit'     => 10,
			]
		);

		self::assertStringContainsString( 'Searchable alpha future', $html );
		self::assertStringNotContainsString( 'Searchable alpha past', $html );
	}

	/**
	 * Use-query applies submitted date filters.
	 */
	public function test_use_query_applies_dates(): void {
		$inside  = Test_Factory::event(
			[
				'title' => 'Date range inside',
				'meta'  => [
					'_reevents_start_utc' => '2027-06-01 10:00:00',
					'_reevents_end_utc'   => '2027-06-01 11:00:00',
					'_reevents_timezone'  => 'UTC',
					'_reevents_status'    => 'scheduled',
				],
			]
		);
		$outside = Test_Factory::event(
			[
				'title' => 'Date range outside',
				'meta'  => [
					'_reevents_start_utc' => '2026-06-01 10:00:00',
					'_reevents_end_utc'   => '2026-06-01 11:00:00',
					'_reevents_timezone'  => 'UTC',
					'_reevents_status'    => 'scheduled',
				],
			]
		);

		Occurrence_Service::sync( $inside );
		Occurrence_Service::sync( $outside );

		$_GET['reevents_from'] = '2027-01-01';
		$_GET['reevents_to']   = '2027-12-31';

		$html = Events_List::render(
			[
				'use_query' => 1,
				'upcoming'  => false,
				'limit'     => 10,
			]
		);

		self::assertStringContainsString( 'Date range inside', $html );
		self::assertStringNotContainsString( 'Date range outside', $html );
	}

	/**
	 * The list ignores search values unless explicitly asked.
	 */
	public function test_list_ignores_search_query_without_flag(): void {
		Test_Factory::event(
			[
				'title' => 'Searchable alpha',
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
				],
			]
		);
		Test_Factory::event(
			[
				'title' => 'Searchable beta',
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
				],
			]
		);

		$_GET['reevents_search'] = 'alpha';

		$html = Events_List::render(
			[
				'limit'    => 10,
				'upcoming' => false,
			]
		);

		self::assertStringContainsString( 'Searchable alpha', $html );
		self::assertStringContainsString( 'Searchable beta', $html );
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

	/**
	 * Clear pagination query arguments.
	 */
	public function tear_down(): void {
		unset(
			$_GET['reevents_page'],
			$_GET['reevents_page_main'],
			$_GET['reevents_venue_page'],
			$_GET['reevents_venue_page_featured'],
			$_GET['reevents_search'],
			$_GET['reevents_category'],
			$_GET['reevents_venue_filter'],
			$_GET['reevents_from'],
			$_GET['reevents_to'],
			$_GET['reevents_upcoming']
		);

		parent::tear_down();
	}
}
