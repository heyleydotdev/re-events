<?php
/**
 * CSV import and export.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services;

use Generator;
use ReEvents\Data\Booking_Repository;
use ReEvents\Data\Event_Repository;
use ReEvents\Data\Meta;
use ReEvents\Data\Venue_Repository;
use ReEvents\Domain\Event;
use WP_Error;

/**
 * Streams export rows and imports event and venue CSV files.
 */
final class Csv {

	/**
	 * Header for an export type.
	 *
	 * @param string $type events, venues or bookings.
	 * @return string[]
	 */
	public static function header( string $type ): array {
		$headers = [
			'events'   => [ 'id', 'title', 'post_status', 'start_utc', 'end_utc', 'timezone', 'all_day', 'rrule', 'exdates', 'rdates', 'timeslots', 'venue_id', 'organizer_ids', 'capacity', 'online_urls', 'status', 'categories', 'tags', 'content', 'excerpt' ],
			'venues'   => [ 'id', 'title', 'post_status', 'description', 'address', 'lat', 'lng', 'website' ],
			'bookings' => [ 'id', 'reference', 'event_id', 'event_title', 'ticket_id', 'quantity', 'status', 'total_minor', 'currency', 'created_utc' ],
		];

		return $headers[ $type ] ?? [];
	}

	/**
	 * Stream export rows.
	 *
	 * @param string $type events, venues or bookings.
	 * @return Generator<int, string[]>
	 */
	public static function rows( string $type ): Generator {
		if ( 'events' === $type ) {
			yield from self::events();
		} elseif ( 'venues' === $type ) {
			yield from self::venues();
		} elseif ( 'bookings' === $type ) {
			yield from self::bookings();
		}
	}

	/**
	 * Import a CSV file.
	 *
	 * @param string $type events or venues.
	 * @param string $file File path.
	 * @return array{created:int,updated:int,errors:string[]}
	 */
	public static function import( string $type, string $file ): array {
		if ( 'events' === $type ) {
			return self::import_events( $file );
		}

		if ( 'venues' === $type ) {
			return self::import_venues( $file );
		}

		return [
			'created' => 0,
			'updated' => 0,
			'errors'  => [ __( 'This import type is not supported.', 're-events' ) ],
		];
	}

	/**
	 * Event rows.
	 *
	 * @return Generator<int, string[]>
	 */
	private static function events(): Generator {
		$page = 1;

		do {
			$ids = get_posts(
				[
					'post_type'      => 'reevents_event',
					'post_status'    => 'any',
					'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Batch export.
					'paged'          => $page,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
				]
			);

			$found = count( $ids );

			foreach ( $ids as $id ) {
				$row = self::event_row( (int) $id );

				if ( $row ) {
					yield $row;
				}
			}

			++$page;
		} while ( 500 === $found );
	}

	/**
	 * One event row.
	 *
	 * @param int $id Event ID.
	 * @return string[]
	 */
	private static function event_row( int $id ): array {
		$event = ( new Event_Repository() )->find( $id );
		$post  = get_post( $id );

		if ( ! $event || ! $post ) {
			return [];
		}

		$categories = wp_get_object_terms( $id, 'reevents_event_category', [ 'fields' => 'names' ] );
		$tags       = wp_get_object_terms( $id, 'reevents_event_tag', [ 'fields' => 'names' ] );

		return [
			(string) $id,
			$event->title,
			(string) $post->post_status,
			$event->start_utc,
			$event->end_utc,
			$event->timezone,
			$event->all_day ? '1' : '0',
			$event->rrule,
			(string) wp_json_encode( $event->exdates ),
			(string) wp_json_encode( $event->rdates ),
			(string) wp_json_encode( $event->timeslots ),
			(string) $event->venue_id,
			(string) wp_json_encode( $event->organizer_ids ),
			(string) $event->capacity,
			(string) wp_json_encode( $event->online_urls ),
			$event->status,
			implode( ', ', is_array( $categories ) ? $categories : [] ),
			implode( ', ', is_array( $tags ) ? $tags : [] ),
			(string) $post->post_content,
			(string) $post->post_excerpt,
		];
	}

	/**
	 * Venue rows.
	 *
	 * @return Generator<int, string[]>
	 */
	private static function venues(): Generator {
		$page = 1;

		do {
			$ids = get_posts(
				[
					'post_type'      => 'reevents_venue',
					'post_status'    => 'any',
					'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Batch export.
					'paged'          => $page,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
				]
			);

			$found = count( $ids );

			foreach ( $ids as $id ) {
				$row = self::venue_row( (int) $id );

				if ( $row ) {
					yield $row;
				}
			}

			++$page;
		} while ( 500 === $found );
	}

	/**
	 * One venue row.
	 *
	 * @param int $id Venue ID.
	 * @return string[]
	 */
	private static function venue_row( int $id ): array {
		$venue = ( new Venue_Repository() )->find( $id );
		$post  = get_post( $id );

		if ( ! $venue || ! $post ) {
			return [];
		}

		return [
			(string) $id,
			$venue['title'],
			(string) $post->post_status,
			$venue['description'],
			$venue['address'],
			$venue['lat'],
			$venue['lng'],
			$venue['website'],
		];
	}

	/**
	 * Booking rows.
	 *
	 * @return Generator<int, string[]>
	 */
	private static function bookings(): Generator {
		$offset = 0;
		$repo   = new Booking_Repository();

		do {
			$result = $repo->query(
				[
					'limit'  => 500,
					'offset' => $offset,
				]
			);

			foreach ( $result['items'] as $booking ) {
				yield [
					(string) (int) $booking['id'],
					(string) $booking['reference'],
					(string) (int) $booking['event_id'],
					(string) ( $booking['event_title'] ?? '' ),
					(string) (int) $booking['ticket_id'],
					(string) (int) $booking['quantity'],
					(string) $booking['status'],
					(string) (int) $booking['total_minor'],
					(string) $booking['currency'],
					(string) $booking['created_utc'],
				];
			}

			$found   = count( $result['items'] );
			$offset += 500;
		} while ( 500 === $found );
	}

	/**
	 * Import events.
	 *
	 * @param string $file File path.
	 * @return array{created:int,updated:int,errors:string[]}
	 */
	private static function import_events( string $file ): array {
		$rows   = self::read( $file );
		$result = [
			'created' => 0,
			'updated' => 0,
			'errors'  => [],
		];

		if ( is_wp_error( $rows ) ) {
			$result['errors'][] = $rows->get_error_message();

			return $result;
		}

		foreach ( $rows as $line => $row ) {
			$id = absint( $row['id'] ?? 0 );

			if ( $id && 'reevents_event' !== get_post_type( $id ) ) {
				$result['errors'][] = sprintf(
					/* translators: %d: row number. */
					__( 'Row %d: the event ID does not exist.', 're-events' ),
					$line
				);
				continue;
			}

			$timezone = Meta::sanitize_timezone( (string) ( $row['timezone'] ?? 'UTC' ) );
			$event    = new Event(
				$id,
				sanitize_text_field( (string) ( $row['title'] ?? '' ) ),
				Meta::sanitize_datetime( (string) ( $row['start_utc'] ?? '' ) ),
				Meta::sanitize_datetime( (string) ( $row['end_utc'] ?? '' ) ),
				$timezone,
				! empty( $row['all_day'] ),
				sanitize_text_field( (string) ( $row['rrule'] ?? '' ) ),
				self::json_array( $row['exdates'] ?? '' ),
				self::json_array( $row['rdates'] ?? '' ),
				Meta::sanitize_timeslots( self::json_array( $row['timeslots'] ?? '' ) ),
				absint( $row['venue_id'] ?? 0 ),
				Meta::sanitize_id_array( self::json_array( $row['organizer_ids'] ?? '' ) ),
				absint( $row['capacity'] ?? 0 ),
				Meta::sanitize_urls( self::json_array( $row['online_urls'] ?? '' ) ),
				sanitize_key( (string) ( $row['status'] ?? 'scheduled' ) )
			);

			$status = sanitize_key( (string) ( $row['post_status'] ?? 'publish' ) );
			$status = in_array( $status, [ 'publish', 'pending', 'draft' ], true ) ? $status : 'publish';
			$saved  = Event_Service::save( $event, $status );

			if ( is_wp_error( $saved ) ) {
				$result['errors'][] = sprintf(
					/* translators: 1: row number, 2: error message. */
					__( 'Row %1$d: %2$s', 're-events' ),
					$line,
					$saved->get_error_message()
				);
				continue;
			}

			wp_update_post(
				[
					'ID'           => (int) $saved,
					'post_content' => wp_kses_post( (string) ( $row['content'] ?? '' ) ),
					'post_excerpt' => sanitize_textarea_field( (string) ( $row['excerpt'] ?? '' ) ),
				]
			);

			self::set_terms( (int) $saved, 'reevents_event_category', (string) ( $row['categories'] ?? '' ) );
			self::set_terms( (int) $saved, 'reevents_event_tag', (string) ( $row['tags'] ?? '' ) );

			if ( $id ) {
				++$result['updated'];
			} else {
				++$result['created'];
			}
		}

		return $result;
	}

	/**
	 * Import venues.
	 *
	 * @param string $file File path.
	 * @return array{created:int,updated:int,errors:string[]}
	 */
	private static function import_venues( string $file ): array {
		$rows   = self::read( $file );
		$result = [
			'created' => 0,
			'updated' => 0,
			'errors'  => [],
		];

		if ( is_wp_error( $rows ) ) {
			$result['errors'][] = $rows->get_error_message();

			return $result;
		}

		$repo = new Venue_Repository();

		foreach ( $rows as $line => $row ) {
			$id = absint( $row['id'] ?? 0 );

			if ( $id && 'reevents_venue' !== get_post_type( $id ) ) {
				$result['errors'][] = sprintf(
					/* translators: %d: row number. */
					__( 'Row %d: the venue ID does not exist.', 're-events' ),
					$line
				);
				continue;
			}

			$saved = $repo->save(
				[
					'id'          => $id,
					'title'       => sanitize_text_field( (string) ( $row['title'] ?? '' ) ),
					'description' => sanitize_textarea_field( (string) ( $row['description'] ?? '' ) ),
					'address'     => sanitize_textarea_field( (string) ( $row['address'] ?? '' ) ),
					'lat'         => (string) ( $row['lat'] ?? '' ),
					'lng'         => (string) ( $row['lng'] ?? '' ),
					'website'     => esc_url_raw( (string) ( $row['website'] ?? '' ) ),
				]
			);

			if ( is_wp_error( $saved ) ) {
				$result['errors'][] = sprintf(
					/* translators: 1: row number, 2: error message. */
					__( 'Row %1$d: %2$s', 're-events' ),
					$line,
					$saved->get_error_message()
				);
				continue;
			}

			if ( $id ) {
				++$result['updated'];
			} else {
				++$result['created'];
			}
		}

		return $result;
	}

	/**
	 * Read a CSV file.
	 *
	 * @param string $file File path.
	 * @return array<int, array<string, string>>|WP_Error
	 */
	private static function read( string $file ) {
		if ( ! is_readable( $file ) ) {
			return new WP_Error( 'reevents_csv_unreadable', __( 'The CSV file is not readable.', 're-events' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Local import file.
		$handle = fopen( $file, 'r' );

		if ( false === $handle ) {
			return new WP_Error( 'reevents_csv_unreadable', __( 'The CSV file could not be opened.', 're-events' ) );
		}

		$header = fgetcsv( $handle );
		$header = is_array( $header ) ? array_map( 'trim', $header ) : [];
		$rows   = [];
		$line   = 1;

		$data = fgetcsv( $handle );

		while ( false !== $data ) {
			++$line;

			$row = [];

			foreach ( $header as $index => $key ) {
				$row[ strtolower( $key ) ] = (string) ( $data[ $index ] ?? '' );
			}

			$rows[ $line ] = $row;
			$data          = fgetcsv( $handle );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Local import file.
		fclose( $handle );

		return $rows;
	}

	/**
	 * Decode a JSON array.
	 *
	 * @param mixed $value JSON value.
	 * @return array<int, mixed>
	 */
	private static function json_array( $value ): array {
		$data = json_decode( (string) $value, true );

		return is_array( $data ) ? $data : [];
	}

	/**
	 * Set terms from a comma-separated list.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @param string $value    Names.
	 */
	private static function set_terms( int $post_id, string $taxonomy, string $value ): void {
		$names = array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );

		if ( $names ) {
			wp_set_object_terms( $post_id, $names, $taxonomy );
		}
	}
}
