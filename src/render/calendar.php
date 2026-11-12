<?php
/**
 * Calendar renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use DateTimeImmutable;
use DateTimeZone;
use ReEvents\Data\Occurrence_Repository;
use ReEvents\Query\Event_Query;
use WP_Query;

/**
 * Renders a month calendar from stored occurrences.
 */
final class Calendar {

	/**
	 * Render the calendar.
	 *
	 * @param array<string, mixed> $args Widget arguments.
	 */
	public static function render( array $args ): string {
		$display_tz     = 'visitor' === Time_Display::mode() ? Time_Display::timezone( '' ) : wp_timezone();
		$month          = self::month( (string) ( $args['month'] ?? '' ), $display_tz );
		$month_fmt      = (string) ( $args['month_format'] ?? 'F Y' );
		$time_fmt       = (string) ( $args['time_format'] ?? '' );
		$show_cancelled = ! empty( $args['show_cancelled'] );
		$show_today     = ! empty( $args['show_today'] );
		$week_start     = 'sun' === strtolower( (string) ( $args['week_start'] ?? 'mon' ) ) ? 'sun' : 'mon';
		$category       = $args['category'] ?? 0;
		$tag            = (string) ( $args['tag'] ?? '' );
		$venue          = $args['venue'] ?? 0;
		$organizer      = $args['organizer'] ?? 0;

		if ( 'current' === $category ) {
			$term     = is_tax( 'reevents_event_category' ) ? get_queried_object() : null;
			$category = $term instanceof \WP_Term ? (int) $term->term_id : -1;
		}

		if ( 'current' === $venue ) {
			$venue = 'reevents_venue' === get_post_type() ? (int) get_the_ID() : -1;
		}

		if ( 'current' === $organizer ) {
			$organizer = 'reevents_organizer' === get_post_type() ? (int) get_the_ID() : -1;
		}

		$month_start = new DateTimeImmutable( $month . '-01 00:00:00', $display_tz );
		$month_end   = $month_start->modify( 'last day of this month' )->setTime( 23, 59, 59 );
		$utc         = new DateTimeZone( 'UTC' );
		$repo        = new Occurrence_Repository();
		$from_utc    = $month_start->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
		$to_utc      = $month_end->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
		$rows        = [];
		$offset      = 0;

		do {
			$batch       = $repo->get_between( $from_utc, $to_utc, 2000, $offset, $show_cancelled );
			$batch_count = count( $batch );
			$rows        = array_merge( $rows, $batch );
			$offset     += 2000;
		} while ( 2000 === $batch_count );

		$event_ids = [];

		foreach ( $rows as $row ) {
			$event_id = (int) ( $row['event_id'] ?? 0 );

			if ( $event_id ) {
				$event_ids[ $event_id ] = $event_id;
			}
		}

		if ( $event_ids && ( $category || '' !== $tag || $venue || $organizer ) ) {
			$allowed   = self::filter_event_ids( array_values( $event_ids ), (int) $category, $tag, (int) $venue, (int) $organizer );
			$event_ids = array_intersect_key( $event_ids, array_flip( $allowed ) );
			$rows      = array_values(
				array_filter(
					$rows,
					static fn( array $row ): bool => isset( $event_ids[ (int) ( $row['event_id'] ?? 0 ) ] )
				)
			);
		}

		if ( $event_ids ) {
			_prime_post_caches( array_values( $event_ids ), false, true );
			update_meta_cache( 'post', array_values( $event_ids ) );
		}

		$base = is_post_type_archive( 'reevents_event' ) ? get_post_type_archive_link( 'reevents_event' ) : get_permalink();

		if ( ! $base ) {
			$base = get_post_type_archive_link( 'reevents_event' );
		}

		if ( ! $base ) {
			$base = home_url( '/' );
		}

		return Template::render(
			'calendar',
			[
				'month_label' => (string) wp_date( '' !== $month_fmt ? $month_fmt : 'F Y', $month_start->getTimestamp(), $display_tz ),
				'prev_url'    => add_query_arg( 'reevents_month', $month_start->modify( '-1 month' )->format( 'Y-m' ), $base ),
				'next_url'    => add_query_arg( 'reevents_month', $month_start->modify( '+1 month' )->format( 'Y-m' ), $base ),
				'days'        => self::build_days( $month_start, $rows, $display_tz, $time_fmt, $week_start, $show_today ),
				'args'        => $args,
			]
		);
	}

	/**
	 * Build the 42-day grid.
	 *
	 * @param DateTimeImmutable                $month_start First day of the month.
	 * @param array<int, array<string, mixed>> $rows      Occurrence rows.
	 * @param DateTimeZone                     $display_tz      Site timezone.
	 * @param string                           $time_format     Time format.
	 * @param string                           $week_start      First weekday.
	 * @param bool                             $show_today      Mark today.
	 * @return array<int, array{date:string,label:int,in_month:bool,is_today:bool,events:array<int, array{title:string,url:string,time:string,cancelled:bool}>}>
	 */
	private static function build_days( DateTimeImmutable $month_start, array $rows, DateTimeZone $display_tz, string $time_format = '', string $week_start = 'mon', bool $show_today = false ): array {
		$time_format = '' !== $time_format ? $time_format : Event_Format::time_format();
		$utc         = new DateTimeZone( 'UTC' );
		$by_date     = [];
		$event_cache = [];
		$grid_start  = 'sun' === $week_start
			? $month_start->modify( '-' . (int) $month_start->format( 'w' ) . ' days' )
			: $month_start->modify( '-' . ( (int) $month_start->format( 'N' ) - 1 ) . ' days' );
		$grid_end    = $grid_start->modify( '+41 days' );
		$today       = $show_today ? ( new DateTimeImmutable( 'now', $display_tz ) )->format( 'Y-m-d' ) : '';

		foreach ( $rows as $row ) {
			$start = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', (string) ( $row['start_utc'] ?? '' ), $utc );

			if ( ! $start instanceof DateTimeImmutable ) {
				continue;
			}

			$end = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', (string) ( $row['end_utc'] ?? '' ), $utc );
			$end = $end instanceof DateTimeImmutable ? $end : $start;

			if ( $end < $start ) {
				$end = $start;
			}

			$local_start = $start->setTimezone( $display_tz );
			$local_end   = $end->setTimezone( $display_tz );
			$first_date  = $local_start->format( 'Y-m-d' );
			$event_id    = (int) ( $row['event_id'] ?? 0 );

			if ( ! $event_id ) {
				continue;
			}

			if ( ! isset( $event_cache[ $event_id ] ) ) {
				$event_cache[ $event_id ] = [
					'title'   => (string) get_the_title( $event_id ),
					'url'     => (string) get_permalink( $event_id ),
					'all_day' => (bool) get_post_meta( $event_id, '_reevents_all_day', true ),
				];
			}

			$event    = $event_cache[ $event_id ];
			$cursor   = $local_start->setTime( 0, 0 );
			$last_day = $local_end->setTime( 0, 0 );

			if ( $cursor < $grid_start ) {
				$cursor = $grid_start;
			}

			if ( $last_day > $grid_end ) {
				$last_day = $grid_end;
			}

			$cursor_date = $cursor->format( 'Y-m-d' );

			for ( $day = 0; $day < 42 && $cursor <= $last_day; $day++ ) {
				$day_key = $cursor_date;
				$ongoing = $day_key !== $first_date;

				$by_date[ $day_key ][] = [
					'title'     => $event['title'],
					'url'       => $event['url'],
					'time'      => $ongoing || $event['all_day'] ? '' : (string) wp_date( $time_format, $local_start->getTimestamp(), $display_tz ),
					'ongoing'   => $ongoing,
					'cancelled' => 'cancelled' === (string) ( $row['status'] ?? '' ),
				];

				$cursor      = $cursor->modify( '+1 day' );
				$cursor_date = $cursor->format( 'Y-m-d' );
			}
		}

		$days = [];

		for ( $i = 0; $i < 42; $i++ ) {
			$date = $grid_start->modify( '+' . $i . ' days' );
			$key  = $date->format( 'Y-m-d' );

			$days[] = [
				'date'     => $key,
				'label'    => (int) $date->format( 'j' ),
				'in_month' => $date->format( 'm' ) === $month_start->format( 'm' ),
				'is_today' => '' !== $today && $key === $today,
				'events'   => $by_date[ $key ] ?? [],
			];
		}

		return $days;
	}

	/**
	 * Filter the month event IDs.
	 *
	 * @param int[]  $ids       Event IDs.
	 * @param int    $category  Category ID.
	 * @param string $tag       Tag ID or slug.
	 * @param int    $venue     Venue ID.
	 * @param int    $organizer Organizer ID.
	 * @return int[]
	 */
	private static function filter_event_ids( array $ids, int $category, string $tag, int $venue, int $organizer ): array {
		[ $tax_query, $meta_query ] = Event_Query::filter_queries( $category, $tag, $venue, $organizer );

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_tax_query, WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Calendar filters are indexed taxonomy and meta filters.
		$query = new WP_Query(
			[
				'post_type'      => 'reevents_event',
				'post_status'    => 'publish',
				'post__in'       => $ids,
				'posts_per_page' => count( $ids ),
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => $tax_query,
				'meta_query'     => $meta_query,
			]
		);
		// phpcs:enable

		return array_map( 'intval', (array) $query->posts );
	}

	/**
	 * Resolve and validate the requested month.
	 *
	 * @param string       $month   Requested month (Y-m).
	 * @param DateTimeZone $display_tz Site timezone.
	 */
	private static function month( string $month, DateTimeZone $display_tz ): string {
		if ( '' === $month ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only month navigation.
			$month = isset( $_GET['reevents_month'] ) ? sanitize_text_field( wp_unslash( $_GET['reevents_month'] ) ) : '';
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m', $month, $display_tz );

		if ( ! $date instanceof DateTimeImmutable || $date->format( 'Y-m' ) !== $month ) {
			$date = new DateTimeImmutable( 'first day of this month 00:00:00', $display_tz );
		}

		return $date->format( 'Y-m' );
	}
}
