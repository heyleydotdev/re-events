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
		$from     = self::date( (string) ( $args['from'] ?? '' ) );
		$to       = self::date( (string) ( $args['to'] ?? '' ) );
		$upcoming = ! empty( $args['upcoming'] );

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

		if ( '' !== $from || '' !== $to || $upcoming ) {
			$now   = current_time( 'mysql', true );
			$start = '' !== $from ? $from . ' 00:00:00' : $now;

			if ( $upcoming && $start < $now ) {
				$start = $now;
			}

			$end = '' !== $to ? $to . ' 23:59:59' : gmdate( 'Y-m-d H:i:s', strtotime( '+1 year', strtotime( $start ) ) );

			if ( $end < $start ) {
				return [
					'ids'   => [],
					'total' => 0,
					'pages' => 0,
				];
			}

			$ids = ( new Occurrence_Repository() )->event_ids_between( $start, $end );

			if ( ! $ids ) {
				return [
					'ids'   => [],
					'total' => 0,
					'pages' => 0,
				];
			}

			$query_args['post__in'] = $ids;
			$query_args['orderby']  = 'post__in';
			unset( $query_args['meta_key'], $query_args['meta_type'] );
		}

		$query = new WP_Query( $query_args );
		// phpcs:enable

		return [
			'ids'   => array_map( 'intval', (array) $query->posts ),
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		];
	}

	/**
	 * Validate a Y-m-d date.
	 *
	 * @param string $value Raw date.
	 */
	private static function date( string $value ): string {
		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new DateTimeZone( 'UTC' ) );

		return $date instanceof DateTimeImmutable ? $date->format( 'Y-m-d' ) : '';
	}
}
