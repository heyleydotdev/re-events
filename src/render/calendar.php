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
		$site_tz     = wp_timezone();
		$month       = self::month( (string) ( $args['month'] ?? '' ), $site_tz );
		$month_start = new DateTimeImmutable( $month . '-01 00:00:00', $site_tz );
		$month_end   = $month_start->modify( 'last day of this month' )->setTime( 23, 59, 59 );
		$utc         = new DateTimeZone( 'UTC' );
		$rows        = ( new Occurrence_Repository() )->get_between(
			$month_start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
			$month_end->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
			500
		);
		$base        = get_permalink();

		if ( ! $base ) {
			$base = get_post_type_archive_link( 'reevents_event' );
		}

		if ( ! $base ) {
			$base = home_url( '/' );
		}

		return Template::render(
			'calendar',
			[
				'month_label' => (string) wp_date( 'F Y', $month_start->getTimestamp(), $site_tz ),
				'prev_url'    => add_query_arg( 'reevents_month', $month_start->modify( '-1 month' )->format( 'Y-m' ), $base ),
				'next_url'    => add_query_arg( 'reevents_month', $month_start->modify( '+1 month' )->format( 'Y-m' ), $base ),
				'days'        => self::build_days( $month_start, $rows, $site_tz ),
				'args'        => $args,
			]
		);
	}

	/**
	 * Build the 42-day grid.
	 *
	 * @param DateTimeImmutable                $month_start First day of the month.
	 * @param array<int, array<string, mixed>> $rows      Occurrence rows.
	 * @param DateTimeZone                     $site_tz     Site timezone.
	 * @return array<int, array{date:string,label:int,in_month:bool,events:array<int, array{title:string,url:string,time:string,cancelled:bool}>}>
	 */
	private static function build_days( DateTimeImmutable $month_start, array $rows, DateTimeZone $site_tz ): array {
		$settings    = get_option( 'reevents_settings', [] );
		$time_format = is_array( $settings ) && ! empty( $settings['time_format'] ) ? (string) $settings['time_format'] : (string) get_option( 'time_format' );
		$utc         = new DateTimeZone( 'UTC' );
		$by_date     = [];

		foreach ( $rows as $row ) {
			$start = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', (string) ( $row['start_utc'] ?? '' ), $utc );

			if ( ! $start instanceof DateTimeImmutable ) {
				continue;
			}

			$local    = $start->setTimezone( $site_tz );
			$event_id = (int) ( $row['event_id'] ?? 0 );

			if ( ! $event_id ) {
				continue;
			}

			$by_date[ $local->format( 'Y-m-d' ) ][] = [
				'title'     => (string) get_the_title( $event_id ),
				'url'       => (string) get_permalink( $event_id ),
				'time'      => get_post_meta( $event_id, '_reevents_all_day', true ) ? '' : (string) wp_date( $time_format, $local->getTimestamp(), $site_tz ),
				'cancelled' => 'cancelled' === (string) ( $row['status'] ?? '' ),
			];
		}

		$grid_start = $month_start->modify( '-' . ( (int) $month_start->format( 'N' ) - 1 ) . ' days' );
		$days       = [];

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
	 * @param DateTimeZone $site_tz Site timezone.
	 */
	private static function month( string $month, DateTimeZone $site_tz ): string {
		if ( '' === $month ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only month navigation.
			$month = isset( $_GET['reevents_month'] ) ? sanitize_text_field( wp_unslash( $_GET['reevents_month'] ) ) : '';
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m', $month, $site_tz );

		if ( ! $date instanceof DateTimeImmutable || $date->format( 'Y-m' ) !== $month ) {
			$date = new DateTimeImmutable( 'first day of this month 00:00:00', $site_tz );
		}

		return $date->format( 'Y-m' );
	}
}
