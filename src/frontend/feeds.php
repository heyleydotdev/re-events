<?php
/**
 * Calendar and RSS feeds.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Frontend;

use ReEvents\Admin\Settings;
use ReEvents\Data\Event_Repository;
use ReEvents\Data\Occurrence_Repository;
use ReEvents\Domain\Event;
use ReEvents\Query\Event_Query;

/**
 * Serves calendar and RSS feeds from query arguments.
 */
final class Feeds {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'template_redirect', [ self::class, 'output' ], 0 );
		add_action( 'wp_head', [ self::class, 'links' ] );
	}

	/**
	 * Output a feed when requested.
	 */
	public static function output(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only feed.
		$ical = isset( $_GET['reevents_ical'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['reevents_ical'] ) ) : '';

		if ( '' !== $ical ) {
			self::ical( $ical );
			exit;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only feed.
		$rss = isset( $_GET['reevents_rss'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['reevents_rss'] ) ) : '';

		if ( '' !== $rss ) {
			self::rss( $rss );
			exit;
		}
	}

	/**
	 * Add feed discovery links.
	 */
	public static function links(): void {
		$ical = self::feed_url( 'reevents_ical' );
		$rss  = self::feed_url( 'reevents_rss' );

		echo '<link rel="alternate" type="text/calendar" title="' . esc_attr( get_bloginfo( 'name' ) ) . '" href="' . esc_url( $ical ) . '" />' . "\n";
		echo '<link rel="alternate" type="application/rss+xml" title="' . esc_attr( get_bloginfo( 'name' ) ) . '" href="' . esc_url( $rss ) . '" />' . "\n";
	}

	/**
	 * Output an iCal feed.
	 *
	 * @param string $target `all` or an event ID.
	 */
	public static function ical( string $target ): void {
		$body = self::calendar( $target );

		if ( null === $body ) {
			status_header( 404 );
			exit;
		}

		header( 'Content-Type: text/calendar; charset=UTF-8' );
		header( 'Content-Disposition: inline; filename="re-events.ics"' );

		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped per field.
	}

	/**
	 * Build an iCal feed.
	 *
	 * @param string $target `all` or an event ID.
	 */
	public static function calendar( string $target ): ?string {
		$items = self::items( $target );

		if ( null === $items ) {
			return null;
		}

		$lines = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//ReEvents//ReEvents//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . self::text( get_bloginfo( 'name' ) ),
			'X-WR-TIMEZONE:UTC',
			'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
			'X-PUBLISHED-TTL:PT1H',
		];

		foreach ( $items as $item ) {
			$event   = $item['event'];
			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:' . self::text( $item['uid'] );
			$lines[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );
			$lines[] = 'DTSTART:' . self::ical_date( $item['start'] );
			$lines[] = 'DTEND:' . self::ical_date( '' !== $item['end'] ? $item['end'] : $item['start'] );
			$lines[] = 'SUMMARY:' . self::text( $event->title );
			$lines[] = 'DESCRIPTION:' . self::text( self::description( $event->id ) );
			$lines[] = 'LOCATION:' . self::text( self::location( $event ) );
			$lines[] = 'URL:' . esc_url_raw( (string) get_permalink( $event->id ) );
			$lines[] = 'STATUS:' . ( 'cancelled' === $event->status ? 'CANCELLED' : 'CONFIRMED' );
			$lines[] = 'END:VEVENT';
		}

		$lines[] = 'END:VCALENDAR';

		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Output an RSS feed.
	 *
	 * @param string $target `all` or an event ID.
	 */
	public static function rss( string $target ): void {
		$events = self::rss_events( $target );

		if ( null === $events ) {
			status_header( 404 );
			exit;
		}

		header( 'Content-Type: application/rss+xml; charset=UTF-8' );

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<rss version="2.0">' . "\n";
		echo '<channel>' . "\n";
		echo '<title>' . esc_html( get_bloginfo( 'name' ) ) . '</title>' . "\n";
		echo '<link>' . esc_url( home_url( '/' ) ) . '</link>' . "\n";
		echo '<description>' . esc_html( get_bloginfo( 'description' ) ) . '</description>' . "\n";
		echo '<language>' . esc_html( get_bloginfo( 'language' ) ) . '</language>' . "\n";

		foreach ( $events as $event ) {
			$link = (string) get_permalink( $event->id );

			echo '<item>' . "\n";
			echo '<title>' . esc_html( $event->title ) . '</title>' . "\n";
			echo '<link>' . esc_url( $link ) . '</link>' . "\n";
			echo '<guid isPermaLink="true">' . esc_url( $link ) . '</guid>' . "\n";
			echo '<pubDate>' . esc_html( self::rss_date( $event->start_utc ) ) . '</pubDate>' . "\n";
			echo '<description>' . esc_html( self::description( $event->id ) ) . '</description>' . "\n";
			echo '</item>' . "\n";
		}

		echo '</channel>' . "\n";
		echo '</rss>' . "\n";
	}

	/**
	 * Feed items for iCal.
	 *
	 * @param string $target `all` or an event ID.
	 * @return array<int, array{event:Event,start:string,end:string,uid:string}>|null
	 */
	private static function items( string $target ): ?array {
		$repo = new Event_Repository();

		if ( 'all' !== $target ) {
			$event_id = absint( $target );
			$event    = $repo->find( $event_id );

			if ( ! $event || ! self::can_view( $event_id ) ) {
				return null;
			}

			$rows  = ( new Occurrence_Repository() )->get_for_event( $event_id, 500 );
			$items = [];

			if ( $rows ) {
				foreach ( $rows as $row ) {
					if ( 'scheduled' !== (string) $row['status'] ) {
						continue;
					}

					$items[] = [
						'event' => $event,
						'start' => (string) $row['start_utc'],
						'end'   => (string) $row['end_utc'],
						'uid'   => 'event-' . $event_id . '-' . (int) $row['id'] . '@' . self::host(),
					];
				}

				return $items;
			}

			return [
				[
					'event' => $event,
					'start' => $event->start_utc,
					'end'   => $event->end_utc,
					'uid'   => 'event-' . $event_id . '@' . self::host(),
				],
			];
		}

		$from  = gmdate( 'Y-m-d H:i:s', strtotime( '-1 year' ) );
		$to    = gmdate( 'Y-m-d H:i:s', strtotime( '+1 year' ) );
		$rows  = ( new Occurrence_Repository() )->get_between( $from, $to, 2000 );
		$ids   = array_values( array_unique( array_map( static fn( array $row ): int => (int) $row['event_id'], $rows ) ) );
		$found = $repo->find_many( $ids );
		$items = [];

		foreach ( $rows as $row ) {
			$event = $found[ (int) $row['event_id'] ] ?? null;

			if ( ! $event ) {
				continue;
			}

			$items[] = [
				'event' => $event,
				'start' => (string) $row['start_utc'],
				'end'   => (string) $row['end_utc'],
				'uid'   => 'event-' . $event->id . '-' . (int) $row['id'] . '@' . self::host(),
			];
		}

		return $items;
	}

	/**
	 * Events for RSS.
	 *
	 * @param string $target `all` or an event ID.
	 * @return array<int, Event>|null
	 */
	private static function rss_events( string $target ): ?array {
		$repo = new Event_Repository();

		if ( 'all' !== $target ) {
			$event_id = absint( $target );
			$event    = $repo->find( $event_id );

			return $event && self::can_view( $event_id ) ? [ $event ] : null;
		}

		$result = Event_Query::query(
			[
				'upcoming' => true,
				'per_page' => 50,
			]
		);

		return array_values( $repo->find_many( $result['ids'] ) );
	}

	/**
	 * Whether the current request may view the event.
	 *
	 * @param int $event_id Event ID.
	 */
	private static function can_view( int $event_id ): bool {
		if ( 'publish' === get_post_status( $event_id ) ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Feed token.
		$token  = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['token'] ) ) : '';
		$secret = (string) Settings::get( 'feed_token', '' );

		return '' !== $secret && '' !== $token && hash_equals( $secret, $token );
	}

	/**
	 * Feed URL.
	 *
	 * @param string $key Query argument.
	 */
	private static function feed_url( string $key ): string {
		return add_query_arg( $key, 'all', home_url( '/' ) );
	}

	/**
	 * Host for UIDs.
	 */
	private static function host(): string {
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		return is_string( $host ) && '' !== $host ? $host : 'localhost';
	}

	/**
	 * Format a UTC datetime for iCal.
	 *
	 * @param string $utc UTC datetime.
	 */
	private static function ical_date( string $utc ): string {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $utc, new \DateTimeZone( 'UTC' ) );

		return $date instanceof \DateTimeImmutable ? $date->format( 'Ymd\THis\Z' ) : gmdate( 'Ymd\THis\Z' );
	}

	/**
	 * Escape iCal text.
	 *
	 * @param string $value Text.
	 */
	private static function text( string $value ): string {
		return str_replace(
			[ '\\', ';', ',', "\r\n", "\n", "\r" ],
			[ '\\\\', '\\;', '\\,', '\\n', '\\n', '\\n' ],
			$value
		);
	}

	/**
	 * Event description.
	 *
	 * @param int $event_id Event ID.
	 */
	private static function description( int $event_id ): string {
		$excerpt = wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $event_id ) );

		if ( '' !== $excerpt ) {
			return $excerpt;
		}

		return wp_trim_words( wp_strip_all_tags( (string) get_post_field( 'post_content', $event_id ) ), 40 );
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

	/**
	 * Format a UTC date for RSS.
	 *
	 * @param string $utc UTC datetime.
	 */
	private static function rss_date( string $utc ): string {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $utc, new \DateTimeZone( 'UTC' ) );

		return $date instanceof \DateTimeImmutable ? $date->format( DATE_RSS ) : '';
	}
}
