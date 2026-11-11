<?php
/**
 * Event query.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Query;

use DateTimeImmutable;
use DateTimeZone;
use ReEvents\Data\Meta;
use ReEvents\Data\Occurrence_Repository;
use WP_Query;

/**
 * Shared event query used by renderers and the REST API.
 */
final class Event_Query {

	/**
	 * Query events.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array{ids:int[],total:int,pages:int}
	 */
	public static function query( array $args = [] ): array {
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? $args['limit'] ?? 10 ) ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$from     = Meta::sanitize_date( (string) ( $args['from'] ?? '' ) );
		$to       = Meta::sanitize_date( (string) ( $args['to'] ?? '' ) );
		$upcoming = ! empty( $args['upcoming'] );
		$total    = null;

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_query, WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Event queries filter by date, venue and category.
		$query_args = [
			'post_type'      => 'reevents_event',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'meta_key'       => '_reevents_start_utc',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_type'      => 'DATETIME',
			'meta_query'     => [],
			'tax_query'      => [],
		];

		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = sanitize_text_field( (string) $args['search'] );
		}

		if ( ! empty( $args['category'] ) ) {
			$query_args['tax_query'][] = [
				'taxonomy' => 'reevents_event_category',
				'field'    => 'term_id',
				'terms'    => (int) $args['category'],
			];
		}

		if ( ! empty( $args['venue'] ) ) {
			$query_args['meta_query'][] = [
				'key'   => '_reevents_venue_id',
				'value' => (int) $args['venue'],
				'type'  => 'NUMERIC',
			];
		}

		if ( ! empty( $args['organizer'] ) ) {
			// shortcut: organizers are a serialized meta array, so this is an unindexed LIKE scan. Move to an index table when organizer pages get traffic.
			$query_args['meta_query'][] = [
				'key'     => '_reevents_organizer_ids',
				'value'   => 'i:' . (int) $args['organizer'] . ';',
				'compare' => 'LIKE',
			];
		}

		if ( '' !== $from || '' !== $to || $upcoming ) {
			$now   = current_time( 'mysql', true );
			$start = '' !== $from ? self::utc_bound( $from, '00:00:00' ) : $now;

			if ( $upcoming && $start < $now ) {
				$start = $now;
			}

			$end = '' !== $to ? self::utc_bound( $to, '23:59:59' ) : gmdate( 'Y-m-d H:i:s', strtotime( '+1 year', strtotime( $start ) ) );

			if ( $end < $start ) {
				return [
					'ids'   => [],
					'total' => 0,
					'pages' => 0,
				];
			}

			$occurrences = new Occurrence_Repository();
			$filtered    = ! empty( $args['search'] ) || ! empty( $args['category'] ) || ! empty( $args['venue'] ) || ! empty( $args['organizer'] );

			if ( $filtered ) {
				// Filters run inside WP_Query, so pagination and totals must come from it too.
				$ids = $occurrences->event_ids_between( $start, $end );

				if ( ! $ids ) {
					return [
						'ids'   => [],
						'total' => 0,
						'pages' => 0,
					];
				}

				$query_args['post__in'] = $ids;
			} else {
				$ids   = $occurrences->event_ids_between( $start, $end, $per_page, ( $page - 1 ) * $per_page );
				$total = $occurrences->count_events_between( $start, $end );

				if ( ! $ids ) {
					return [
						'ids'   => [],
						'total' => $total,
						'pages' => (int) ceil( $total / $per_page ),
					];
				}

				$query_args['post__in']       = $ids;
				$query_args['posts_per_page'] = count( $ids );
				$query_args['paged']          = 1;
				$query_args['no_found_rows']  = true;
			}

			$query_args['orderby'] = 'post__in';
			unset( $query_args['meta_key'], $query_args['meta_type'] );
		}

		$query = new WP_Query( $query_args );
		// phpcs:enable

		if ( null === $total ) {
			$total = (int) $query->found_posts;
		}

		$pages = (int) ceil( $total / $per_page );

		return [
			'ids'   => array_map( 'intval', (array) $query->posts ),
			'total' => $total,
			'pages' => $pages,
		];
	}

	/**
	 * Convert a site-local date and time to UTC.
	 *
	 * @param string $date Site-local date (Y-m-d).
	 * @param string $time Site-local time (H:i:s).
	 */
	private static function utc_bound( string $date, string $time ): string {
		$local = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $date . ' ' . $time, wp_timezone() );

		if ( ! $local instanceof DateTimeImmutable ) {
			return $date . ' ' . $time;
		}

		return $local->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}
}
