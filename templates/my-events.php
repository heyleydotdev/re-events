<?php
/**
 * My events template.
 *
 * @package ReEvents
 *
 * @var array{events:array<int, array{id:int,title:string,status:string,url:string,edit:string,created:string}>,args:array<string,mixed>} $args
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are scoped to the template include.

if ( empty( $args['events'] ) ) {
	echo '<p class="reevents-empty">' . esc_html( (string) ( $args['args']['empty_label'] ?? __( 'You have not submitted any events yet.', 're-events' ) ) ) . '</p>';
	return;
}

echo '<div class="reevents-my-events-wrapper ' . esc_attr( (string) ( $args['args']['wrapper_class'] ?? '' ) ) . '">';
echo '<ul class="reevents-my-events ' . esc_attr( (string) ( $args['args']['class'] ?? '' ) ) . '">';

foreach ( $args['events'] as $event ) {
	echo '<li class="reevents-my-event">';
	echo '<a class="reevents-my-event-title" href="' . esc_url( $event['url'] ) . '">' . esc_html( $event['title'] ) . '</a>';
	echo ' <span class="reevents-my-event-status">' . esc_html( $event['status'] ) . '</span>';
	echo ' <a class="reevents-my-event-edit" href="' . esc_url( $event['edit'] ) . '">' . esc_html( (string) ( $args['args']['edit_label'] ?? __( 'Edit', 're-events' ) ) ) . '</a>';
	echo '</li>';
}

echo '</ul>';
echo '</div>';
