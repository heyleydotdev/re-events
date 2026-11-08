<?php
/**
 * Events list renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use DateTimeImmutable;
use DateTimeZone;
use ReEvents\Data\Event_Repository;
use WP_Query;

/**
 * Renders a list of upcoming events.
 */
final class Events_List {

	/**
	 * Render the events list.
	 *
	 * @param array<string, mixed> $args Widget arguments.
	 */
	public static function render( array $args ): string {
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_query, WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Event queries filter by date, venue and category.
		$query_args = [
			'post_type'      => 'reevents_event',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) ( $args['limit'] ?? 5 ) ),
			'no_found_rows'  => true,
			'meta_key'       => '_reevents_start_utc',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_type'      => 'DATETIME',
			'meta_query'     => [],
			'tax_query'      => [],
		];

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

		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = sanitize_text_field( (string) $args['search'] );
		}

		$from = self::date( (string) ( $args['from'] ?? '' ) );
		$to   = self::date( (string) ( $args['to'] ?? '' ) );

		if ( '' !== $from ) {
			$query_args['meta_query'][] = [
				'key'     => '_reevents_start_utc',
				'value'   => $from . ' 00:00:00',
				'compare' => '>=',
				'type'    => 'DATETIME',
			];
		}

		if ( '' !== $to ) {
			$query_args['meta_query'][] = [
				'key'     => '_reevents_start_utc',
				'value'   => $to . ' 23:59:59',
				'compare' => '<=',
				'type'    => 'DATETIME',
			];
		}

		if ( ! empty( $args['upcoming'] ) ) {
			$query_args['meta_query'][] = [
				'key'     => '_reevents_start_utc',
				'value'   => current_time( 'mysql', true ),
				'compare' => '>=',
				'type'    => 'DATETIME',
			];
		}

		$query  = new WP_Query( $query_args );
		$repo   = new Event_Repository();
		$events = [];

		foreach ( $query->posts as $post ) {
			$event = $repo->find( (int) $post->ID );

			if ( $event ) {
				$events[] = $event;
			}
		}

		// phpcs:enable

		return Template::render(
			'events-list',
			[
				'events' => $events,
				'args'   => $args,
			]
		);
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
