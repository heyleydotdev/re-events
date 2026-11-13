<?php
/**
 * Event details template.
 *
 * @package ReEvents
 *
 * @var array{event: \ReEvents\Domain\Event, content: string, venue: string, venue_url: string, venue_address: string, venue_map: string, venue_map_html: string, organizers: array<int, array{name:string,type:string,url:string}>, args: array<string, mixed>} $args
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are scoped to the template include.

$event    = $args['event'];
$settings = $args['args'];
$date_fmt = (string) ( $settings['date_format'] ?? '' );
$time_fmt = (string) ( $settings['time_format'] ?? '' );
$start    = \ReEvents\Render\Event_Format::start( $event, true, $date_fmt, $time_fmt );
$end      = \ReEvents\Render\Event_Format::end( $event, true, $date_fmt, $time_fmt );
$summary  = $args['summary'] ?? [];
$on       = static function ( string $key ) use ( $settings ): bool {
	return ! isset( $settings[ $key ] ) || ! empty( $settings[ $key ] );
};

echo '<div class="reevents-event-details-wrapper ' . esc_attr( (string) ( $settings['wrapper_class'] ?? '' ) ) . '">';
echo '<div class="reevents-event-details ' . esc_attr( (string) ( $settings['class'] ?? '' ) ) . '">';

if ( ! empty( $settings['show_image'] ) && has_post_thumbnail( $event->id ) ) {
	echo '<div class="reevents-event-image">' . get_the_post_thumbnail( $event->id, (string) ( $settings['image_size'] ?? 'large' ), [ 'class' => 'reevents-event-thumbnail' ] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core image markup.
}

if ( $on( 'show_title' ) ) {
	$title_tag = (string) ( $settings['title_tag'] ?? 'h2' );
	$title_tag = in_array( $title_tag, [ 'h1', 'h2', 'h3', 'div' ], true ) ? $title_tag : 'h2';

	echo '<' . esc_html( $title_tag ) . ' class="reevents-event-title">' . esc_html( $event->title ) . '</' . esc_html( $title_tag ) . '>';
}

if ( $on( 'show_status' ) ) {
	if ( 'cancelled' === $event->status ) {
		echo '<p class="reevents-event-status">' . esc_html( (string) ( $settings['cancelled_label'] ?? __( 'Cancelled', 're-events' ) ) ) . '</p>';
	} elseif ( \ReEvents\Render\Event_Format::is_ongoing( $event ) ) {
		echo '<p class="reevents-event-status">' . esc_html( (string) ( $settings['ongoing_label'] ?? __( 'Ongoing', 're-events' ) ) ) . '</p>';
	}
}

if ( '' !== $start ) {
	echo '<p class="reevents-event-when">' . esc_html( $start );

	if ( $on( 'show_end' ) && '' !== $end && $event->end_utc !== $event->start_utc ) {
		echo ' &ndash; ' . esc_html( $end );
	}

	echo '</p>';
}

if ( $on( 'show_venue' ) && '' !== $args['venue'] ) {
	$venue_name = esc_html( $args['venue'] );

	if ( '' !== (string) $args['venue_url'] ) {
		$venue_name = '<a href="' . esc_url( (string) $args['venue_url'] ) . '">' . $venue_name . '</a>';
	}

	echo '<p class="reevents-event-venue">' . esc_html( (string) ( $settings['venue_label'] ?? __( 'Venue:', 're-events' ) ) ) . ' ' . $venue_name . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
}

if ( ! empty( $settings['show_venue_address'] ) && '' !== (string) $args['venue_address'] ) {
	echo '<p class="reevents-event-venue-address">' . esc_html( (string) $args['venue_address'] ) . '</p>';
}

if ( ! empty( $settings['show_venue_map_link'] ) && '' !== (string) $args['venue_map'] ) {
	echo '<p class="reevents-event-venue-map"><a href="' . esc_url( (string) $args['venue_map'] ) . '">' . esc_html__( 'Map', 're-events' ) . '</a></p>';
}

if ( '' !== (string) $args['venue_map_html'] ) {
	echo $args['venue_map_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the map template.
}

if ( $on( 'show_organizers' ) && ! empty( $args['organizers'] ) ) {
	$organizers = [];

	foreach ( $args['organizers'] as $organizer ) {
		$name = esc_html( $organizer['name'] );

		if ( '' !== (string) $organizer['url'] ) {
			$name = '<a href="' . esc_url( (string) $organizer['url'] ) . '">' . $name . '</a>';
		}

		$organizers[] = $name . ( '' !== $organizer['type'] ? ' (' . esc_html( $organizer['type'] ) . ')' : '' );
	}

	echo '<p class="reevents-event-organizers">' . esc_html( (string) ( $settings['organizers_label'] ?? __( 'Organizers:', 're-events' ) ) ) . ' ' . implode( ', ', $organizers ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
}

if ( ! empty( $summary['sold_out'] ) ) {
	echo '<p class="reevents-event-sold-out">' . esc_html( (string) ( $settings['sold_out_label'] ?? __( 'Sold out', 're-events' ) ) ) . '</p>';
} elseif ( ! empty( $settings['show_price'] ) && null !== ( $summary['price_from'] ?? null ) ) {
	$price = ! empty( $summary['is_free'] )
		? (string) ( $settings['free_label'] ?? __( 'Free', 're-events' ) )
		: \ReEvents\Services\Currency::format( (int) $summary['price_from'], (string) $summary['currency'] );

	echo '<p class="reevents-event-price">' . esc_html( $price ) . '</p>';
}

if ( ! empty( $settings['show_seats_left'] ) && ! empty( $summary['seats_left'] ) && empty( $summary['sold_out'] ) ) {
	$seats = (int) $summary['seats_left'];

	/* translators: %d: number of remaining seats. */
	echo '<p class="reevents-event-seats-left">' . esc_html( sprintf( _n( '%d seat left', '%d seats left', $seats, 're-events' ), $seats ) ) . '</p>';
}

if ( ! empty( $args['categories'] ) ) {
	echo '<p class="reevents-event-categories">' . implode( ', ', array_map( 'esc_html', $args['categories'] ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
}

if ( ! empty( $args['tags'] ) ) {
	echo '<p class="reevents-event-tags">' . implode( ', ', array_map( 'esc_html', $args['tags'] ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
}

if ( ! empty( $settings['show_excerpt'] ) && '' !== (string) $args['excerpt'] ) {
	echo '<p class="reevents-event-excerpt">' . wp_kses_post( (string) $args['excerpt'] ) . '</p>';
}

if ( $on( 'show_online_links' ) && ! empty( $event->online_urls ) ) {
	echo '<ul class="reevents-event-links">';

	foreach ( $event->online_urls as $online_link ) {
		$label = '' !== $online_link['label'] ? $online_link['label'] : $online_link['url'];
		echo '<li><a href="' . esc_url( $online_link['url'] ) . '">' . esc_html( $label ) . '</a></li>';
	}

	echo '</ul>';
}

if ( ! empty( $args['calendar_links'] ) ) {
	echo '<div class="reevents-event-calendar-links">';
	echo '<strong>' . esc_html( (string) ( $settings['calendar_label'] ?? __( 'Add to calendar', 're-events' ) ) ) . '</strong>';
	echo '<ul>';

	foreach ( $args['calendar_links'] as $calendar_link ) {
		echo '<li><a href="' . esc_url( $calendar_link['url'] ) . '">' . esc_html( $calendar_link['label'] ) . '</a></li>';
	}

	echo '</ul>';
	echo '</div>';
}

if ( $on( 'show_content' ) && '' !== $args['content'] ) {
	echo '<div class="reevents-event-content">' . wp_kses_post( $args['content'] ) . '</div>';
}

echo '</div>';
echo '</div>';
