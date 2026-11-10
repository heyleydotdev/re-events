<?php
/**
 * Booking form template.
 *
 * @package ReEvents
 *
 * @var array{event_id:int,tickets:array<int, array<string, mixed>>,state:array{status:string,reference:string,message:string},action:string,endpoint:string,args:array<string,mixed>} $args
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are scoped to the template include.

$state       = $args['state'];
$widget_args = $args['args'];

if ( 'success' === $state['status'] ) {
	echo '<p class="reevents-booking-success" role="status">' . esc_html( (string) ( $widget_args['success_message'] ?? __( 'Booking confirmed.', 're-events' ) ) );

	if ( '' !== $state['reference'] ) {
		echo ' ' . esc_html( (string) ( $widget_args['reference_label'] ?? __( 'Reference:', 're-events' ) ) ) . ' <strong>' . esc_html( $state['reference'] ) . '</strong>';
	}

	echo '</p>';
}

if ( 'error' === $state['status'] && '' !== $state['message'] ) {
	echo '<p class="reevents-booking-error" role="alert">' . esc_html( $state['message'] ) . '</p>';
}

echo '<div class="reevents-booking-wrapper ' . esc_attr( (string) ( $widget_args['wrapper_class'] ?? '' ) ) . '">';
echo '<form class="reevents-booking-form ' . esc_attr( (string) ( $widget_args['class'] ?? '' ) ) . '" method="post" action="' . esc_url( $args['action'] ) . '" data-endpoint="' . esc_url( $args['endpoint'] ) . '">';
echo '<input type="hidden" name="action" value="reevents_book" />';
echo '<input type="hidden" name="event_id" value="' . esc_attr( (string) $args['event_id'] ) . '" />';
wp_nonce_field( 'reevents_book', 'reevents_book_nonce' );

echo '<p class="reevents-honeypot" hidden aria-hidden="true"><label>' . esc_html__( 'Website', 're-events' ) . '<input type="text" name="website" value="" tabindex="-1" autocomplete="off" /></label></p>';

echo '<p class="reevents-booking-field">';
echo '<label for="reevents-booking-ticket">' . esc_html( (string) ( $widget_args['ticket_label'] ?? __( 'Ticket', 're-events' ) ) ) . '</label>';
echo '<select id="reevents-booking-ticket" name="ticket_id" required>';

foreach ( $args['tickets'] as $ticket ) {
	$price = (int) $ticket['price_minor'];
	$label = (string) $ticket['name'];

	if ( $price > 0 ) {
		$label .= ' — ' . \ReEvents\Services\Currency::format( $price, (string) $ticket['currency'] );
	} else {
		$label .= ' — ' . (string) ( $widget_args['free_label'] ?? __( 'Free', 're-events' ) );
	}

	echo '<option value="' . esc_attr( (string) $ticket['id'] ) . '" data-min="' . esc_attr( (string) $ticket['min_per_booking'] ) . '" data-max="' . esc_attr( (string) $ticket['max_per_booking'] ) . '">' . esc_html( $label ) . '</option>';
}

echo '</select>';
echo '</p>';

echo '<p class="reevents-booking-field">';
echo '<label for="reevents-booking-quantity">' . esc_html( (string) ( $widget_args['quantity_label'] ?? __( 'Seats', 're-events' ) ) ) . '</label>';
echo '<input type="number" id="reevents-booking-quantity" name="quantity" value="1" min="1" required />';
echo '</p>';

echo '<p class="reevents-booking-field">';
echo '<label for="reevents-booking-name">' . esc_html( (string) ( $widget_args['name_label'] ?? __( 'Name', 're-events' ) ) ) . '</label>';
echo '<input type="text" id="reevents-booking-name" name="name" placeholder="' . esc_attr( (string) ( $widget_args['name_placeholder'] ?? '' ) ) . '" required />';
echo '</p>';

echo '<p class="reevents-booking-field">';
echo '<label for="reevents-booking-email">' . esc_html( (string) ( $widget_args['email_label'] ?? __( 'Email', 're-events' ) ) ) . '</label>';
echo '<input type="email" id="reevents-booking-email" name="email" placeholder="' . esc_attr( (string) ( $widget_args['email_placeholder'] ?? '' ) ) . '" required />';
echo '</p>';

foreach ( $args['fields'] as $field_key => $field ) {
	$field_id   = 'reevents-booking-field-' . $field_key;
	$field_name = 'reevents_fields[' . $field_key . ']';
	$required   = ! empty( $field['required'] ) ? ' required' : '';

	echo '<p class="reevents-booking-field">';
	echo '<label for="' . esc_attr( $field_id ) . '">' . esc_html( (string) $field['label'] ) . '</label>';

	if ( 'textarea' === $field['type'] ) {
		echo '<textarea id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" data-reevents-field="' . esc_attr( (string) $field_key ) . '"' . esc_attr( $required ) . '></textarea>';
	} else {
		echo '<input type="' . esc_attr( (string) $field['type'] ) . '" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" data-reevents-field="' . esc_attr( (string) $field_key ) . '"' . esc_attr( $required ) . ' />';
	}

	echo '</p>';
}

echo '<p class="reevents-booking-actions"><button type="submit">' . esc_html( (string) ( $widget_args['button_label'] ?? __( 'Book', 're-events' ) ) ) . '</button></p>';
echo '<p class="reevents-booking-message" aria-live="polite"></p>';
echo '</form>';
echo '</div>';
