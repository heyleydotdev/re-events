<?php
/**
 * Booking form template.
 *
 * @package ReEvents
 *
 * @var array{event_id:int,tickets:array<int, array<string, mixed>>,state:array{status:string,reference:string,message:string},action:string,endpoint:string,args:array<string,mixed>} $args
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are scoped to the template include.

$state = $args['state'];

if ( 'success' === $state['status'] ) {
	echo '<p class="reevents-booking-success" role="status">' . esc_html__( 'Booking confirmed.', 're-events' );

	if ( '' !== $state['reference'] ) {
		echo ' ' . esc_html__( 'Reference:', 're-events' ) . ' <strong>' . esc_html( $state['reference'] ) . '</strong>';
	}

	echo '</p>';
}

if ( 'error' === $state['status'] && '' !== $state['message'] ) {
	echo '<p class="reevents-booking-error" role="alert">' . esc_html( $state['message'] ) . '</p>';
}

echo '<form class="reevents-booking-form" method="post" action="' . esc_url( $args['action'] ) . '" data-endpoint="' . esc_url( $args['endpoint'] ) . '">';
echo '<input type="hidden" name="action" value="reevents_book" />';
echo '<input type="hidden" name="event_id" value="' . esc_attr( (string) $args['event_id'] ) . '" />';
wp_nonce_field( 'reevents_book', 'reevents_book_nonce' );

echo '<p class="reevents-honeypot"><label>' . esc_html__( 'Website', 're-events' ) . '<input type="text" name="website" value="" tabindex="-1" autocomplete="off" /></label></p>';

echo '<p class="reevents-booking-field">';
echo '<label for="reevents-booking-ticket">' . esc_html__( 'Ticket', 're-events' ) . '</label>';
echo '<select id="reevents-booking-ticket" name="ticket_id" required>';

foreach ( $args['tickets'] as $ticket ) {
	$price = (int) $ticket['price_minor'];
	$label = (string) $ticket['name'];

	if ( $price > 0 ) {
		$label .= ' — ' . number_format_i18n( $price / 100, 2 ) . ' ' . (string) $ticket['currency'];
	} else {
		$label .= ' — ' . __( 'Free', 're-events' );
	}

	echo '<option value="' . esc_attr( (string) $ticket['id'] ) . '" data-min="' . esc_attr( (string) $ticket['min_per_booking'] ) . '" data-max="' . esc_attr( (string) $ticket['max_per_booking'] ) . '">' . esc_html( $label ) . '</option>';
}

echo '</select>';
echo '</p>';

echo '<p class="reevents-booking-field">';
echo '<label for="reevents-booking-quantity">' . esc_html__( 'Seats', 're-events' ) . '</label>';
echo '<input type="number" id="reevents-booking-quantity" name="quantity" value="1" min="1" required />';
echo '</p>';

echo '<p class="reevents-booking-field">';
echo '<label for="reevents-booking-name">' . esc_html__( 'Name', 're-events' ) . '</label>';
echo '<input type="text" id="reevents-booking-name" name="name" required />';
echo '</p>';

echo '<p class="reevents-booking-field">';
echo '<label for="reevents-booking-email">' . esc_html__( 'Email', 're-events' ) . '</label>';
echo '<input type="email" id="reevents-booking-email" name="email" required />';
echo '</p>';

echo '<p class="reevents-booking-actions"><button type="submit">' . esc_html__( 'Book', 're-events' ) . '</button></p>';
echo '<p class="reevents-booking-message" aria-live="polite"></p>';
echo '</form>';
