<?php
/**
 * Events list template.
 *
 * @package ReEvents
 *
 * @var array{events: array<int, \ReEvents\Domain\Event>, summaries: array<int, array<string, mixed>>, args: array<string, mixed>, page: int, pages: int, total: int, base_url: string} $args
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
	$summary    = $args['summaries'][ $event->id ] ?? [];

	echo '<li class="reevents-event">';

	if ( ! empty( $args['args']['show_image'] ) && has_post_thumbnail( $event->id ) ) {
		echo '<a class="reevents-event-image" href="' . esc_url( get_permalink( $event->id ) ) . '">';
		echo get_the_post_thumbnail( $event->id, (string) ( $args['args']['image_size'] ?? 'thumbnail' ), [ 'class' => 'reevents-event-thumbnail' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core image markup.
		echo '</a>';
	}

	echo '<a class="reevents-event-title" href="' . esc_url( get_permalink( $event->id ) ) . '">' . esc_html( $event->title ) . '</a>';

	if ( $start_text ) {
		echo '<span class="reevents-event-start">' . esc_html( (string) $start_text ) . '</span>';
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

	if ( ! empty( $args['args']['show_venue'] ) && $venue ) {
		echo '<span class="reevents-event-venue">' . esc_html( $venue ) . '</span>';
	}

	if ( ! empty( $args['args']['show_excerpt'] ) ) {
		$excerpt = (string) get_the_excerpt( $event->id );

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

	echo '</li>';
}

echo '</ul>';

$page_count = (int) ( $args['pages'] ?? 0 );

if ( $page_count > 1 ) {
	$links = paginate_links(
		[
			'base'      => add_query_arg( 'reevents_page', '%_%', (string) $args['base_url'] ),
			'format'    => '%#%',
			'current'   => max( 1, (int) $args['page'] ),
			'total'     => $page_count,
			'type'      => 'list',
			'prev_text' => __( 'Previous', 're-events' ),
			'next_text' => __( 'Next', 're-events' ),
		]
	);

	if ( is_string( $links ) ) {
		echo '<nav class="reevents-pagination" aria-label="' . esc_attr__( 'Event pages', 're-events' ) . '">' . $links . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core pagination markup.
	}
}

echo '</div>';
