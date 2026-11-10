<?php
/**
 * Add-to-calendar links.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use DateTimeImmutable;
use DateTimeZone;
use ReEvents\Admin\Settings;
use ReEvents\Domain\Event;

/**
 * Builds provider links for an event.
 */
final class Calendar_Links {

	/**
	 * Build the link list.
	 *
	 * @param Event                 $event  Event.
	 * @param array<string, string> $labels Link labels.
	 * @return array<int, array{label:string,url:string}>
	 */
	public static function links( Event $event, array $labels = [] ): array {
		if ( 'cancelled' === $event->status ) {
			return [];
		}

		return [
			[
				'label' => $labels['google'] ?? __( 'Google', 're-events' ),
				'url'   => self::google( $event ),
			],
			[
				'label' => $labels['outlook'] ?? __( 'Outlook', 're-events' ),
				'url'   => self::outlook( $event, 'https://outlook.live.com/calendar/0/deeplink/compose' ),
			],
			[
				'label' => $labels['office365'] ?? __( 'Office 365', 're-events' ),
				'url'   => self::outlook( $event, 'https://outlook.office.com/calendar/0/deeplink/compose' ),
			],
			[
				'label' => $labels['ical'] ?? __( 'Apple / iCal', 're-events' ),
				'url'   => self::ical( $event ),
			],
		];
	}

	/**
	 * Google Calendar link.
	 *
	 * @param Event $event Event.
	 */
	public static function google( Event $event ): string {
		return add_query_arg(
			[
				'action'   => 'TEMPLATE',
				'text'     => $event->title,
				'dates'    => self::google_dates( $event ),
				'details'  => self::description( $event ),
				'location' => self::location( $event ),
			],
			'https://calendar.google.com/calendar/render'
		);
	}

	/**
	 * Outlook and Office 365 link.
	 *
	 * @param Event  $event Event.
	 * @param string $base  Provider base URL.
	 */
	public static function outlook( Event $event, string $base ): string {
		$params = [
			'path'     => '/calendar/action/compose',
			'rru'      => 'addevent',
			'subject'  => $event->title,
			'body'     => self::description( $event ),
			'location' => self::location( $event ),
			'startdt'  => self::outlook_date( $event->start_utc, $event->all_day ),
			'enddt'    => self::outlook_date( '' !== $event->end_utc ? $event->end_utc : $event->start_utc, $event->all_day ),
		];

		if ( $event->all_day ) {
			$params['allday'] = 'true';
		}

		return add_query_arg( $params, $base );
	}

	/**
	 * Calendar download link.
	 *
	 * @param Event $event Event.
	 */
	public static function ical( Event $event ): string {
		$args = [ 'reevents_ical' => $event->id ];

		if ( 'publish' !== get_post_status( $event->id ) ) {
			$token = (string) Settings::get( 'feed_token', '' );

			if ( '' !== $token ) {
				$args['token'] = $token;
			}
		}

		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Google date range.
	 *
	 * @param Event $event Event.
	 */
	private static function google_dates( Event $event ): string {
		if ( $event->all_day ) {
			$start = self::date( $event->start_utc );
			$end   = self::date( '' !== $event->end_utc ? $event->end_utc : $event->start_utc );

			return $start->format( 'Ymd' ) . '/' . $end->modify( '+1 day' )->format( 'Ymd' );
		}

		return gmdate( 'Ymd\THis\Z', strtotime( $event->start_utc ) ) . '/' . gmdate( 'Ymd\THis\Z', strtotime( '' !== $event->end_utc ? $event->end_utc : $event->start_utc ) );
	}

	/**
	 * Outlook date.
	 *
	 * @param string $utc     UTC datetime.
	 * @param bool   $all_day All-day event.
	 */
	private static function outlook_date( string $utc, bool $all_day ): string {
		$date = self::date( $utc );

		return $all_day ? $date->format( 'Y-m-d' ) : $date->format( 'Y-m-d\TH:i:s\Z' );
	}

	/**
	 * Parse a UTC datetime.
	 *
	 * @param string $utc UTC datetime.
	 */
	private static function date( string $utc ): DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $utc, new DateTimeZone( 'UTC' ) );

		return $date instanceof DateTimeImmutable ? $date : new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Event description.
	 *
	 * @param Event $event Event.
	 */
	private static function description( Event $event ): string {
		$excerpt = wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $event->id ) );

		if ( '' !== $excerpt ) {
			return $excerpt;
		}

		return wp_trim_words( wp_strip_all_tags( (string) get_post_field( 'post_content', $event->id ) ), 40 );
	}

	/**
	 * Event location.
	 *
	 * @param Event $event Event.
	 */
	private static function location( Event $event ): string {
		if ( ! $event->venue_id ) {
			return '';
		}

		$name    = (string) get_the_title( $event->venue_id );
		$address = (string) get_post_meta( $event->venue_id, '_reevents_address', true );

		return trim( $name . ( '' !== $address ? ', ' . $address : '' ) );
	}
}
