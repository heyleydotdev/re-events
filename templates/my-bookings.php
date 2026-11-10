<?php
/**
 * My bookings template.
 *
 * @package ReEvents
 *
 * @var array{bookings:array<int, array{id:int,reference:string,event:string,url:string,status:string,quantity:int,total:string,created:string}>,args:array<string,mixed>} $args
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are scoped to the template include.

if ( empty( $args['bookings'] ) ) {
	echo '<p class="reevents-empty">' . esc_html( (string) ( $args['args']['empty_label'] ?? __( 'You have no bookings yet.', 're-events' ) ) ) . '</p>';
	return;
}

echo '<div class="reevents-my-bookings-wrapper ' . esc_attr( (string) ( $args['args']['wrapper_class'] ?? '' ) ) . '">';
echo '<ul class="reevents-my-bookings ' . esc_attr( (string) ( $args['args']['class'] ?? '' ) ) . '">';

foreach ( $args['bookings'] as $booking ) {
	echo '<li class="reevents-my-booking">';
	echo '<a class="reevents-my-booking-event" href="' . esc_url( $booking['url'] ) . '">' . esc_html( $booking['event'] ) . '</a>';
	echo ' <span class="reevents-my-booking-reference">' . esc_html( $booking['reference'] ) . '</span>';
	echo ' <span class="reevents-my-booking-status">' . esc_html( $booking['status'] ) . '</span>';
	echo ' <span class="reevents-my-booking-quantity">' . esc_html( (string) $booking['quantity'] ) . '</span>';
	echo ' <span class="reevents-my-booking-total">' . esc_html( $booking['total'] ) . '</span>';
	echo '</li>';
}

echo '</ul>';
echo '</div>';
