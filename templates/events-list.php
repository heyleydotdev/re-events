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
	echo '<p class="reevents-empty">' . esc_html( (string) ( $args['args']['empty_label'] ?? __( 'No events found.', 're-events' ) ) ) . '</p>';
	return;
}

echo '<div class="reevents-events-list-wrapper ' . esc_attr( (string) ( $args['args']['wrapper_class'] ?? '' ) ) . '">';
echo '<ul class="reevents-events-list ' . esc_attr( (string) ( $args['args']['class'] ?? '' ) ) . '">';

foreach ( $args['events'] as $event ) {
	$start_text = \ReEvents\Render\Event_Format::start( $event );
	$venue      = $event->venue_id ? get_the_title( $event->venue_id ) : '';

	echo '<li class="reevents-event">';
	echo '<a class="reevents-event-title" href="' . esc_url( get_permalink( $event->id ) ) . '">' . esc_html( $event->title ) . '</a>';

	if ( $start_text ) {
		echo '<span class="reevents-event-start">' . esc_html( (string) $start_text ) . '</span>';
	}

	if ( \ReEvents\Render\Event_Format::is_ongoing( $event ) ) {
		echo '<span class="reevents-event-ongoing">' . esc_html( (string) ( $args['args']['ongoing_label'] ?? __( 'Ongoing', 're-events' ) ) ) . '</span>';
	}

	if ( ! empty( $args['args']['show_venue'] ) && $venue ) {
		echo '<span class="reevents-event-venue">' . esc_html( $venue ) . '</span>';
	}

	echo '</li>';
}

echo '</ul>';
echo '</div>';
