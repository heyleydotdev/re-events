<?php
/**
 * Venues list template.
 *
 * @package ReEvents
 *
 * @var array{venues: array<int, array{id: int, title: string, permalink: string, content: string}>, args: array<string, mixed>} $args
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are scoped to the template include.

if ( empty( $args['venues'] ) ) {
	echo '<p class="reevents-empty">' . esc_html__( 'No venues found.', 're-events' ) . '</p>';
	return;
}

echo '<ul class="reevents-venues-list">';

foreach ( $args['venues'] as $venue ) {
	echo '<li class="reevents-venue">';
	echo '<a class="reevents-venue-title" href="' . esc_url( $venue['permalink'] ) . '">' . esc_html( $venue['title'] ) . '</a>';

	if ( ! empty( $args['args']['show_description'] ) && '' !== $venue['content'] ) {
		echo '<p class="reevents-venue-description">' . esc_html( wp_trim_words( wp_strip_all_tags( $venue['content'] ), 30 ) ) . '</p>';
	}

	echo '</li>';
}

echo '</ul>';
