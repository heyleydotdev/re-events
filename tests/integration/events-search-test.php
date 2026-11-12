<?php
/**
 * Events search rendering tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Render\Events_Search;
use WP_UnitTestCase;

/**
 * Covers the events search renderer.
 */
final class Events_Search_Test extends WP_UnitTestCase {

	/**
	 * Tag and organizer fields filter the results.
	 */
	public function test_tag_and_organizer(): void {
		$organizer = wp_insert_post(
			[
				'post_type'   => 'reevents_organizer',
				'post_status' => 'publish',
				'post_title'  => 'Search organizer',
			]
		);

		$tag = wp_insert_term( 'Search tag', 'reevents_event_tag' );

		if ( is_wp_error( $tag ) ) {
			self::fail( 'Tag could not be created.' );
		}

		$inside = Test_Factory::event(
			[
				'title' => 'Inside search event',
				'meta'  => [
					'_reevents_start_utc'     => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days' ) ),
					'_reevents_end_utc'       => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days +1 hour' ) ),
					'_reevents_timezone'      => 'UTC',
					'_reevents_organizer_ids' => [ $organizer ],
				],
			]
		);

		Test_Factory::event(
			[
				'title' => 'Outside search event',
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
				],
			]
		);

		wp_set_object_terms( $inside, [ (int) $tag['term_id'] ], 'reevents_event_tag' );

		$_GET['reevents_tag']       = (string) $tag['term_id'];
		$_GET['reevents_organizer'] = (string) $organizer;

		$html = Events_Search::render(
			[
				'limit'          => 10,
				'show_keyword'   => false,
				'show_category'  => false,
				'show_venue'     => false,
				'show_dates'     => false,
				'show_upcoming'  => false,
				'show_tag'       => true,
				'show_organizer' => true,
				'show_results'   => true,
			]
		);

		self::assertStringContainsString( 'name="reevents_tag"', $html );
		self::assertStringContainsString( 'name="reevents_organizer"', $html );
		self::assertStringContainsString( 'Inside search event', $html );
		self::assertStringNotContainsString( 'Outside search event', $html );
	}

	/**
	 * Order and result pagination apply to the results list.
	 */
	public function test_order_and_pagination(): void {
		$prefix = 'search' . str_replace( '-', '', wp_generate_uuid4() );

		for ( $i = 1; $i <= 3; $i++ ) {
			Test_Factory::event(
				[
					'title' => $prefix . ' ' . $i,
					'meta'  => [
						'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+' . $i . ' day' ) ),
						'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+' . $i . ' day +1 hour' ) ),
						'_reevents_timezone'  => 'UTC',
						'_reevents_status'    => 'scheduled',
					],
				]
			);
		}

		$_GET['reevents_search'] = $prefix;
		$_GET['reevents_order']  = 'desc';
		$_GET['reevents_page']   = '2';

		$html = Events_Search::render(
			[
				'limit'         => 1,
				'show_keyword'  => false,
				'show_category' => false,
				'show_venue'    => false,
				'show_dates'    => false,
				'show_upcoming' => false,
				'show_order'    => true,
				'paginate'      => true,
				'show_results'  => true,
			]
		);

		self::assertStringContainsString( 'name="reevents_order"', $html );
		self::assertStringContainsString( $prefix . ' 2', $html );
		self::assertStringNotContainsString( $prefix . ' 3', $html );
		self::assertStringContainsString( 'reevents-pagination', $html );
	}

	/**
	 * Result card options reach the event list.
	 */
	public function test_result_card_options(): void {
		$id = Test_Factory::event(
			[
				'title' => 'Search card ' . wp_generate_uuid4(),
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days' ) ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', strtotime( '+2 days +1 hour' ) ),
					'_reevents_timezone'  => 'UTC',
				],
			]
		);

		wp_update_post(
			[
				'ID'           => $id,
				'post_excerpt' => 'Search teaser',
			]
		);
		wp_set_object_terms( $id, [ 'Search category' ], 'reevents_event_category' );
		Test_Factory::ticket( $id, [ 'price_minor' => 1500 ] );

		$_GET['reevents_search'] = (string) get_the_title( $id );

		$html = Events_Search::render(
			[
				'limit'           => 10,
				'show_keyword'    => false,
				'show_category'   => false,
				'show_venue'      => false,
				'show_dates'      => false,
				'show_upcoming'   => false,
				'show_results'    => true,
				'show_price'      => true,
				'show_excerpt'    => true,
				'show_categories' => true,
			]
		);

		self::assertStringContainsString( '15.00 USD', $html );
		self::assertStringContainsString( 'Search teaser', $html );
		self::assertStringContainsString( 'Search category', $html );
	}

	/**
	 * Clear search query arguments.
	 */
	public function tear_down(): void {
		unset(
			$_GET['reevents_search'],
			$_GET['reevents_tag'],
			$_GET['reevents_organizer'],
			$_GET['reevents_order'],
			$_GET['reevents_page']
		);

		parent::tear_down();
	}
}
