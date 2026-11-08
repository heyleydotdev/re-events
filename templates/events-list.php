<?php
/**
 * Events list template.
 *
 * @package ReEvents
 *
 * @var array{events: array<int, \ReEvents\Domain\Event>, args: array<string, mixed>} $args
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are scoped to the template include.

if ( empty( $args['events'] ) ) {
	echo '<p class="reevents-empty">' . esc_html__( 'No events found.', 're-events' ) . '</p>';
	return;
}

$settings    = get_option( 'reevents_settings', [] );
$date_format = is_array( $settings ) && ! empty( $settings['date_format'] ) ? (string) $settings['date_format'] : (string) get_option( 'date_format' );
$time_format = is_array( $settings ) && ! empty( $settings['time_format'] ) ? (string) $settings['time_format'] : (string) get_option( 'time_format' );

echo '<ul class="reevents-events-list">';

foreach ( $args['events'] as $event ) {
	$timezone = $event->timezone ? $event->timezone : 'UTC';
	$start    = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $event->start_utc, new DateTimeZone( 'UTC' ) );
	$format   = $date_format;

	if ( ! $event->all_day ) {
		$format .= ' ' . $time_format;
	}

	$start_text = $start instanceof DateTimeImmutable ? wp_date( $format, $start->getTimestamp(), new DateTimeZone( $timezone ) ) : '';
	$venue      = $event->venue_id ? get_the_title( $event->venue_id ) : '';

	echo '<li class="reevents-event">';
	echo '<a class="reevents-event-title" href="' . esc_url( get_permalink( $event->id ) ) . '">' . esc_html( $event->title ) . '</a>';

	if ( $start_text ) {
		echo '<span class="reevents-event-start">' . esc_html( (string) $start_text ) . '</span>';
	}

	if ( ! empty( $args['args']['show_venue'] ) && $venue ) {
		echo '<span class="reevents-event-venue">' . esc_html( $venue ) . '</span>';
	}

	echo '</li>';
}

echo '</ul>';
