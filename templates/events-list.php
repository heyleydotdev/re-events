<?php
/**
 * Events list template.
 *
 * @package ReEvents
 *
 * @var array{events: array<int, \ReEvents\Domain\Event>, summaries: array<int, array<string, mixed>>, args: array<string, mixed>, pagination: string} $args
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are scoped to the template include.

if ( empty( $args['events'] ) ) {
	echo '<p class="reevents-empty">' . esc_html( (string) ( $args['args']['empty_label'] ?? __( 'No events found.', 're-events' ) ) ) . '</p>';
	return;
}

echo '<div class="reevents-events-list-wrapper ' . esc_attr( (string) ( $args['args']['wrapper_class'] ?? '' ) ) . '">';
echo '<ul class="reevents-events-list ' . esc_attr( (string) ( $args['args']['class'] ?? '' ) ) . '">';

$title_tag = (string) ( $args['args']['title_tag'] ?? 'a' );
$title_tag = in_array( $title_tag, [ 'a', 'h2', 'h3', 'div' ], true ) ? $title_tag : 'a';
$is_link   = ! isset( $args['args']['link'] ) || ! empty( $args['args']['link'] );
$date_fmt  = (string) ( $args['args']['date_format'] ?? '' );
$time_fmt  = (string) ( $args['args']['time_format'] ?? '' );

foreach ( $args['events'] as $event ) {
	$start_text = \ReEvents\Render\Event_Format::start( $event, true, $date_fmt, $time_fmt );
	$venue      = $event->venue_id ? get_the_title( $event->venue_id ) : '';
	$summary    = $args['summaries'][ $event->id ] ?? [];
	$permalink  = get_permalink( $event->id );

	echo '<li class="reevents-event">';

	if ( ! empty( $args['args']['show_image'] ) && has_post_thumbnail( $event->id ) ) {
		$image = get_the_post_thumbnail( $event->id, (string) ( $args['args']['image_size'] ?? 'thumbnail' ), [ 'class' => 'reevents-event-thumbnail' ] );

		if ( $is_link ) {
			echo '<a class="reevents-event-image" href="' . esc_url( (string) $permalink ) . '">' . $image . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core image markup.
		} else {
			echo '<span class="reevents-event-image">' . $image . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core image markup.
		}
	}

	if ( 'a' === $title_tag ) {
		if ( $is_link ) {
			echo '<a class="reevents-event-title" href="' . esc_url( (string) $permalink ) . '">' . esc_html( $event->title ) . '</a>';
		} else {
			echo '<span class="reevents-event-title">' . esc_html( $event->title ) . '</span>';
		}
	} else {
		echo '<' . esc_html( $title_tag ) . ' class="reevents-event-title">';

		if ( $is_link ) {
			echo '<a class="reevents-event-title-link" href="' . esc_url( (string) $permalink ) . '">' . esc_html( $event->title ) . '</a>';
		} else {
			echo esc_html( $event->title );
		}

		echo '</' . esc_html( $title_tag ) . '>';
	}

	if ( 'cancelled' === $event->status ) {
		echo '<span class="reevents-event-cancelled">' . esc_html( (string) ( $args['args']['cancelled_label'] ?? __( 'Cancelled', 're-events' ) ) ) . '</span>';
	}

	if ( $start_text ) {
		echo '<span class="reevents-event-start">' . esc_html( (string) $start_text ) . '</span>';
	}

	if ( ! empty( $args['args']['show_end'] ) && '' !== $event->end_utc && $event->end_utc !== $event->start_utc ) {
		$end_text = \ReEvents\Render\Event_Format::end( $event, true, $date_fmt, $time_fmt );

		if ( '' !== $end_text ) {
			echo '<span class="reevents-event-end">' . esc_html( (string) $end_text ) . '</span>';
		}
	}

	if ( \ReEvents\Render\Event_Format::is_ongoing( $event ) ) {
		echo '<span class="reevents-event-ongoing">' . esc_html( (string) ( $args['args']['ongoing_label'] ?? __( 'Ongoing', 're-events' ) ) ) . '</span>';
	}

	if ( ! empty( $summary['sold_out'] ) ) {
		echo '<span class="reevents-event-sold-out">' . esc_html( (string) ( $args['args']['sold_out_label'] ?? __( 'Sold out', 're-events' ) ) ) . '</span>';
	} elseif ( ! empty( $args['args']['show_price'] ) && null !== ( $summary['price_from'] ?? null ) ) {
		$price = ! empty( $summary['is_free'] )
			? (string) ( $args['args']['free_label'] ?? __( 'Free', 're-events' ) )
			: \ReEvents\Services\Currency::format( (int) $summary['price_from'], (string) $summary['currency'] );

		echo '<span class="reevents-event-price">' . esc_html( $price ) . '</span>';
	}

	if ( ! empty( $args['args']['show_seats_left'] ) && ! empty( $summary['seats_left'] ) && empty( $summary['sold_out'] ) ) {
		$seats = (int) $summary['seats_left'];

		/* translators: %d: number of remaining seats. */
		echo '<span class="reevents-event-seats-left">' . esc_html( sprintf( _n( '%d seat left', '%d seats left', $seats, 're-events' ), $seats ) ) . '</span>';
	}

	if ( ! empty( $args['args']['show_venue'] ) && $venue ) {
		echo '<span class="reevents-event-venue">' . esc_html( $venue ) . '</span>';
	}

	if ( ! empty( $args['args']['show_organizers'] ) && $event->organizer_ids ) {
		$organizer_names = [];

		foreach ( $event->organizer_ids as $organizer_id ) {
			$organizer_name = (string) get_the_title( (int) $organizer_id );

			if ( '' !== $organizer_name ) {
				$organizer_names[] = esc_html( $organizer_name );
			}
		}

		if ( $organizer_names ) {
			echo '<span class="reevents-event-organizers">' . implode( ', ', $organizer_names ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		}
	}

	if ( ! empty( $args['args']['show_excerpt'] ) ) {
		$excerpt = (string) get_the_excerpt( $event->id );
		$length  = (int) ( $args['args']['excerpt_length'] ?? 0 );

		if ( $length > 0 ) {
			$excerpt = wp_trim_words( $excerpt, $length, '…' );
		}

		if ( '' !== $excerpt ) {
			echo '<p class="reevents-event-excerpt">' . wp_kses_post( $excerpt ) . '</p>';
		}
	}

	if ( ! empty( $args['args']['show_categories'] ) ) {
		$event_terms = get_the_terms( $event->id, 'reevents_event_category' );
		$term_names  = [];

		foreach ( is_array( $event_terms ) ? $event_terms : [] as $event_term ) {
			$term_names[] = esc_html( $event_term->name );
		}

		if ( $term_names ) {
			echo '<span class="reevents-event-categories">' . implode( ', ', $term_names ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		}
	}

	if ( ! empty( $args['args']['show_tags'] ) ) {
		$event_terms = get_the_terms( $event->id, 'reevents_event_tag' );
		$term_names  = [];

		foreach ( is_array( $event_terms ) ? $event_terms : [] as $event_term ) {
			$term_names[] = esc_html( $event_term->name );
		}

		if ( $term_names ) {
			echo '<span class="reevents-event-tags">' . implode( ', ', $term_names ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		}
	}

	echo '</li>';
}

echo '</ul>';

if ( '' !== (string) ( $args['pagination'] ?? '' ) ) {
	echo $args['pagination']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by Pagination.
}

echo '</div>';
