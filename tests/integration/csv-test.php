<?php
/**
 * CSV import and export tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Data\Venue_Repository;
use ReEvents\Services\Csv;
use WP_UnitTestCase;

/**
 * Covers CSV headers and round-trips.
 */
final class Csv_Test extends WP_UnitTestCase {

	/**
	 * Write a temporary CSV file.
	 *
	 * @param string[] $lines CSV lines.
	 */
	private function file( array $lines ): string {
		$file = tempnam( sys_get_temp_dir(), 'reevents-csv' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		file_put_contents( (string) $file, implode( "\n", $lines ) );

		return (string) $file;
	}

	/**
	 * Every export type has a header.
	 */
	public function test_headers(): void {
		foreach ( [ 'events', 'venues', 'bookings' ] as $type ) {
			self::assertNotEmpty( Csv::header( $type ) );
		}

		self::assertSame( [], Csv::header( 'unknown' ) );
	}

	/**
	 * An exported event row contains the saved values.
	 */
	public function test_export_events(): void {
		$id = Test_Factory::event(
			[
				'title' => 'CSV export event',
				'meta'  => [
					'_reevents_start_utc' => '2027-01-02 10:00:00',
					'_reevents_end_utc'   => '2027-01-02 11:00:00',
					'_reevents_timezone'  => 'UTC',
					'_reevents_status'    => 'scheduled',
				],
			]
		);

		$rows = iterator_to_array( Csv::rows( 'events' ) );
		$row  = array_values( array_filter( $rows, static fn( array $row ): bool => (int) $row[0] === $id ) );

		self::assertNotEmpty( $row );
		self::assertSame( 'CSV export event', $row[0][1] );
		self::assertSame( '2027-01-02 10:00:00', $row[0][3] );
	}

	/**
	 * Importing an empty tag cell clears the old tags.
	 */
	public function test_import_events_clears_tags(): void {
		$event = Test_Factory::event(
			[
				'title' => 'Tagged event',
				'meta'  => [
					'_reevents_start_utc' => '2027-04-01 10:00:00',
					'_reevents_end_utc'   => '2027-04-01 11:00:00',
					'_reevents_timezone'  => 'UTC',
				],
			]
		);
		wp_set_object_terms( $event, [ 'Old tag' ], 'reevents_event_tag' );
		wp_update_post(
			[
				'ID'           => $event,
				'post_content' => 'Keep this content',
			]
		);

		$file = $this->file(
			[
				'id,title,start_utc,end_utc,timezone,tags',
				$event . ',"Tagged event",2027-04-01 10:00:00,2027-04-01 11:00:00,UTC,',
			]
		);

		$result = Csv::import( 'events', $file );

		self::assertSame( 1, $result['updated'] );
		self::assertSame( [], wp_get_object_terms( $event, 'reevents_event_tag', [ 'fields' => 'ids' ] ) );
		self::assertSame( 'Keep this content', get_post_field( 'post_content', $event ) );
	}

	/**
	 * A CSV without tag columns keeps existing tags.
	 */
	public function test_import_events_keeps_tags_when_column_is_absent(): void {
		$event = Test_Factory::event(
			[
				'title' => 'Keep tags event',
				'meta'  => [
					'_reevents_start_utc' => '2027-04-02 10:00:00',
					'_reevents_end_utc'   => '2027-04-02 11:00:00',
					'_reevents_timezone'  => 'UTC',
				],
			]
		);
		wp_set_object_terms( $event, [ 'Keep tag' ], 'reevents_event_tag' );

		$file = $this->file(
			[
				'id,title,start_utc,end_utc,timezone',
				$event . ',"Keep tags event",2027-04-02 10:00:00,2027-04-02 11:00:00,UTC',
			]
		);

		Csv::import( 'events', $file );

		self::assertSame( [ 'Keep tag' ], wp_get_object_terms( $event, 'reevents_event_tag', [ 'fields' => 'names' ] ) );
	}

	/**
	 * Imported venues keep the requested post status.
	 */
	public function test_import_venues_status(): void {
		$file = $this->file(
			[
				'title,address,post_status',
				'"Pending venue","1 Main Street",pending',
			]
		);

		$result = Csv::import( 'venues', $file );

		self::assertSame( 1, $result['created'] );

		$posts = get_posts(
			[
				'post_type'   => 'reevents_venue',
				'title'       => 'Pending venue',
				'post_status' => 'pending',
			]
		);

		self::assertCount( 1, $posts );
	}

	/**
	 * An imported event is created with its dates and status.
	 */
	public function test_import_events(): void {
		$file = $this->file(
			[
				'title,start_utc,end_utc,timezone,status,post_status',
				'"Imported event",2027-02-03 09:00:00,2027-02-03 10:00:00,UTC,scheduled,publish',
			]
		);

		$result = Csv::import( 'events', $file );

		self::assertSame( 1, $result['created'] );
		self::assertSame( [], $result['errors'] );

		$posts = get_posts(
			[
				'post_type'   => 'reevents_event',
				'title'       => 'Imported event',
				'post_status' => 'publish',
			]
		);

		self::assertCount( 1, $posts );
		self::assertSame( '2027-02-03 09:00:00', get_post_meta( $posts[0]->ID, '_reevents_start_utc', true ) );
	}

	/**
	 * An imported venue is created with its address.
	 */
	public function test_import_venues(): void {
		$file = $this->file(
			[
				'title,description,address,lat,lng,website',
				'"Imported venue",Description,"1 Main Street",1.5,2.5,https://example.com',
			]
		);

		$result = Csv::import( 'venues', $file );

		self::assertSame( 1, $result['created'] );
		self::assertSame( [], $result['errors'] );

		$venues = ( new Venue_Repository() )->query( [ 'per_page' => 10 ] );

		self::assertNotEmpty( $venues['items'] );
		self::assertSame( 'Imported venue', $venues['items'][0]['title'] );
	}

	/**
	 * An unknown import type returns an error.
	 */
	public function test_import_unknown_type(): void {
		$result = Csv::import( 'unknown', '' );

		self::assertSame( 0, $result['created'] );
		self::assertNotEmpty( $result['errors'] );
	}
}
