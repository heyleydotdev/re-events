<?php
/**
 * Schema.org Event data.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Frontend;

use DateTimeImmutable;
use DateTimeZone;
use ReEvents\Data\Event_Repository;
use ReEvents\Data\Ticket_Repository;
use ReEvents\Domain\Event;
use ReEvents\Services\Currency;

/**
 * Outputs JSON-LD for published events.
 */
final class Structured_Data {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'wp_head', [ self::class, 'output' ], 20 );
	}

	/**
	 * Output the JSON-LD script.
	 */
	public static function output(): void {
		if ( ! is_singular( 'reevents_event' ) || ! apply_filters( 'reevents_output_structured_data', true ) ) {
			return;
		}

		$data = self::data( get_queried_object_id() );

		if ( ! $data ) {
			return;
		}

		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( is_string( $json ) ) {
			echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD.
		}
	}

	/**
	 * Build the Event data.
	 *
	 * @param int $event_id Event ID.
	 * @return array<string, mixed>
	 */
	public static function data( int $event_id ): array {
		$event = ( new Event_Repository() )->find( $event_id );

		if ( ! $event || 'publish' !== get_post_status( $event_id ) ) {
			return [];
		}

		$data = [
			'@context'            => 'https://schema.org',
			'@type'               => 'Event',
			'name'                => $event->title,
			'startDate'           => self::iso( $event->start_utc, $event->timezone ),
			'eventStatus'         => 'cancelled' === $event->status ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
			'eventAttendanceMode' => self::attendance_mode( $event ),
			'url'                 => (string) get_permalink( $event_id ),
			'description'         => self::description( $event_id ),
		];

		if ( '' !== $event->end_utc ) {
			$data['endDate'] = self::iso( $event->end_utc, $event->timezone );
		}

		$locations = [];

		if ( $event->venue_id ) {
			$locations[] = [
				'@type'   => 'Place',
				'name'    => (string) get_the_title( $event->venue_id ),
				'address' => (string) get_post_meta( $event->venue_id, '_reevents_address', true ),
			];
		}

		foreach ( $event->online_urls as $link ) {
			$locations[] = [
				'@type' => 'VirtualLocation',
				'url'   => (string) $link['url'],
			];
		}

		if ( $locations ) {
			$data['location'] = 1 === count( $locations ) ? $locations[0] : $locations;
		}

		$organizer = self::organizer( $event );

		if ( $organizer ) {
			$data['organizer'] = $organizer;
		}

		$image = get_the_post_thumbnail_url( $event_id, 'large' );

		if ( is_string( $image ) && '' !== $image ) {
			$data['image'] = $image;
		}

		$offers = self::offers( $event_id );

		if ( $offers ) {
			$data['offers'] = $offers;
		}

		return $data;
	}

	/**
	 * Build offers from tickets.
	 *
	 * @param int $event_id Event ID.
	 * @return array<int, array<string, mixed>>
	 */
	private static function offers( int $event_id ): array {
		$offers = [];

		foreach ( ( new Ticket_Repository() )->for_event( $event_id, 'active' ) as $ticket ) {
			$capacity = (int) $ticket['capacity'];
			$sold     = (int) $ticket['sold'];
			$offers[] = [
				'@type'         => 'Offer',
				'url'           => (string) get_permalink( $event_id ),
				'price'         => Currency::amount( (int) $ticket['price_minor'], (string) $ticket['currency'] ),
				'priceCurrency' => (string) $ticket['currency'],
				'availability'  => $capacity > 0 && $sold >= $capacity
					? 'https://schema.org/SoldOut'
					: 'https://schema.org/InStock',
			];
		}

		return $offers;
	}

	/**
	 * Build the organizer entry.
	 *
	 * @param Event $event Event.
	 * @return array<string, string>
	 */
	private static function organizer( Event $event ): array {
		foreach ( $event->organizer_ids as $organizer_id ) {
			$name = (string) get_the_title( $organizer_id );

			if ( '' !== $name ) {
				return [
					'@type' => 'Organization',
					'name'  => $name,
					'url'   => (string) get_permalink( $organizer_id ),
				];
			}
		}

		return [];
	}

	/**
	 * Event attendance mode.
	 *
	 * @param Event $event Event.
	 */
	private static function attendance_mode( Event $event ): string {
		if ( $event->venue_id && $event->online_urls ) {
			return 'https://schema.org/MixedEventAttendanceMode';
		}

		if ( $event->online_urls ) {
			return 'https://schema.org/OnlineEventAttendanceMode';
		}

		return 'https://schema.org/OfflineEventAttendanceMode';
	}

	/**
	 * Convert UTC to ISO 8601 in the event timezone.
	 *
	 * @param string $utc      UTC datetime.
	 * @param string $timezone IANA timezone.
	 */
	private static function iso( string $utc, string $timezone ): string {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $utc, new DateTimeZone( 'UTC' ) );

		if ( ! $date instanceof DateTimeImmutable ) {
			return '';
		}

		try {
			$zone = new DateTimeZone( $timezone );
		} catch ( \Exception ) {
			$zone = new DateTimeZone( 'UTC' );
		}

		return $date->setTimezone( $zone )->format( 'c' );
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

		return wp_trim_words( wp_strip_all_tags( (string) get_post_field( 'post_content', $event_id ) ), 60 );
	}
}
