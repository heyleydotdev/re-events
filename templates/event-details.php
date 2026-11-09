<?php
/**
 * Event details template.
 *
 * @package ReEvents
 *
 * @var array{event: \ReEvents\Domain\Event, content: string, venue: string, organizers: array<int, array{name:string,type:string}>, permalink: string, args: array<string, mixed>} $args
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are scoped to the template include.

$event = $args['event'];
$start = \ReEvents\Render\Event_Format::start( $event );
$end   = \ReEvents\Render\Event_Format::end( $event );

echo '<div class="reevents-event-details">';
echo '<h2 class="reevents-event-title">' . esc_html( $event->title ) . '</h2>';

if ( 'cancelled' === $event->status ) {
	echo '<p class="reevents-event-status">' . esc_html__( 'Cancelled', 're-events' ) . '</p>';
}

if ( '' !== $start ) {
	echo '<p class="reevents-event-when">' . esc_html( $start );

	if ( '' !== $end && $end !== $start ) {
		echo ' &ndash; ' . esc_html( $end );
	}

	echo '</p>';
}

if ( ! empty( $args['args']['show_venue'] ) && '' !== $args['venue'] ) {
	echo '<p class="reevents-event-venue">' . esc_html__( 'Venue:', 're-events' ) . ' ' . esc_html( $args['venue'] ) . '</p>';
}

if ( ! empty( $args['args']['show_organizers'] ) && ! empty( $args['organizers'] ) ) {
	$organizers = [];

	foreach ( $args['organizers'] as $organizer ) {
		$organizers[] = esc_html( $organizer['name'] ) . ( '' !== $organizer['type'] ? ' (' . esc_html( $organizer['type'] ) . ')' : '' );
	}

	echo '<p class="reevents-event-organizers">' . esc_html__( 'Organizers:', 're-events' ) . ' ' . implode( ', ', $organizers ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
}

if ( ! empty( $args['args']['show_online_links'] ) && ! empty( $event->online_urls ) ) {
	echo '<ul class="reevents-event-links">';

	foreach ( $event->online_urls as $online_link ) {
		$label = '' !== $online_link['label'] ? $online_link['label'] : $online_link['url'];
		echo '<li><a href="' . esc_url( $online_link['url'] ) . '">' . esc_html( $label ) . '</a></li>';
	}

	echo '</ul>';
}

if ( ! empty( $args['args']['show_content'] ) && '' !== $args['content'] ) {
	echo '<div class="reevents-event-content">' . wp_kses_post( $args['content'] ) . '</div>';
}

echo '</div>';
