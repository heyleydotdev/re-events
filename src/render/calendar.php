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
		$display_tz  = 'visitor' === Time_Display::mode() ? Time_Display::timezone( '' ) : wp_timezone();
		$month       = self::month( (string) ( $args['month'] ?? '' ), $display_tz );
		$month_start = new DateTimeImmutable( $month . '-01 00:00:00', $display_tz );
		$month_end   = $month_start->modify( 'last day of this month' )->setTime( 23, 59, 59 );
		$utc         = new DateTimeZone( 'UTC' );
		$repo        = new Occurrence_Repository();
		$from_utc    = $month_start->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
		$to_utc      = $month_end->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
		$rows        = [];
		$offset      = 0;

		do {
			$batch       = $repo->get_between( $from_utc, $to_utc, 2000, $offset );
			$batch_count = count( $batch );
			$rows        = array_merge( $rows, $batch );
			$offset     += 2000;
		} while ( 2000 === $batch_count );
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
				'month_label' => (string) wp_date( 'F Y', $month_start->getTimestamp(), $display_tz ),
				'prev_url'    => add_query_arg( 'reevents_month', $month_start->modify( '-1 month' )->format( 'Y-m' ), $base ),
				'next_url'    => add_query_arg( 'reevents_month', $month_start->modify( '+1 month' )->format( 'Y-m' ), $base ),
				'days'        => self::build_days( $month_start, $rows, $display_tz ),
				'args'        => $args,
			]
		);
	}

	/**
	 * Build the 42-day grid.
	 *
	 * @param DateTimeImmutable                $month_start First day of the month.
	 * @param array<int, array<string, mixed>> $rows      Occurrence rows.
	 * @param DateTimeZone                     $display_tz     Site timezone.
	 * @return array<int, array{date:string,label:int,in_month:bool,events:array<int, array{title:string,url:string,time:string,cancelled:bool}>}>
	 */
	private static function build_days( DateTimeImmutable $month_start, array $rows, DateTimeZone $display_tz ): array {
		$settings    = get_option( 'reevents_settings', [] );
		$time_format = is_array( $settings ) && ! empty( $settings['time_format'] ) ? (string) $settings['time_format'] : (string) get_option( 'time_format' );
		$utc         = new DateTimeZone( 'UTC' );
		$by_date     = [];
		$event_cache = [];
		$grid_start  = $month_start->modify( '-' . ( (int) $month_start->format( 'N' ) - 1 ) . ' days' );
		$grid_end    = $grid_start->modify( '+41 days' );

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
				'events'   => $by_date[ $key ] ?? [],
			];
		}

		return $days;
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
