<?php
/**
 * Bookings template.
 *
 * @package ReEvents
 *
 * @var array{heading: string, notice: array{type:string,message:string}|null, cards: array<int, array<string, mixed>>, lookup: array<string, string>|null, empty: string, pagination: string, args: array<string, mixed>} $args
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are scoped to the template include.

$settings = $args['args'];

echo '<div class="reevents-bookings-wrapper ' . esc_attr( (string) ( $settings['wrapper_class'] ?? '' ) ) . '">';
echo '<div class="reevents-bookings ' . esc_attr( (string) ( $settings['class'] ?? '' ) ) . '">';

if ( '' !== $args['heading'] ) {
	echo '<h2 class="reevents-bookings-heading">' . esc_html( $args['heading'] ) . '</h2>';
}

if ( $args['notice'] ) {
	echo '<p class="reevents-bookings-notice reevents-bookings-notice-' . esc_attr( $args['notice']['type'] ) . '" role="status">' . esc_html( $args['notice']['message'] ) . '</p>';
}

if ( ! empty( $args['cards'] ) ) {
	echo '<ul class="reevents-bookings-list">';

	foreach ( $args['cards'] as $card ) {
		$actions = $card['actions'];

		echo '<li class="reevents-booking reevents-booking-status-' . esc_attr( $card['status'] ) . '">';

		if ( '' !== $card['event_url'] ) {
			echo '<p class="reevents-booking-event"><a href="' . esc_url( $card['event_url'] ) . '">' . esc_html( $card['event_title'] ) . '</a></p>';
		} else {
			echo '<p class="reevents-booking-event">' . esc_html( $card['event_title'] ) . '</p>';
		}

		if ( '' !== $card['when'] ) {
			echo '<p class="reevents-booking-when">' . esc_html( $card['when'] ) . '</p>';
		}

		if ( '' !== $card['ticket'] ) {
			echo '<p class="reevents-booking-ticket">' . esc_html( $card['ticket'] ) . '</p>';
		}

		echo '<p class="reevents-booking-total">' . esc_html( $card['total'] ) . '</p>';
		echo '<p class="reevents-booking-status">' . esc_html( $card['status_text'] ) . '</p>';
		echo '<p class="reevents-booking-reference">' . esc_html__( 'Reference:', 're-events' ) . ' <code>' . esc_html( $card['reference'] ) . '</code></p>';

		if ( $actions['refund'] ) {
			echo '<p class="reevents-booking-refund">' . esc_html__( 'The payment is refunded when you cancel.', 're-events' ) . '</p>';
		}

		$forms = [];

		if ( $actions['can_cancel'] ) {
			$forms[] = [
				'action' => 'reevents_booking_cancel',
				'class'  => 'reevents-booking-cancel',
				'label'  => $actions['cancel_label'],
			];
		}

		if ( $actions['can_reactivate'] ) {
			$forms[] = [
				'action' => 'reevents_booking_reactivate',
				'class'  => 'reevents-booking-reactivate',
				'label'  => $actions['reactivate_label'],
			];
		}

		foreach ( $forms as $form ) {
			echo '<form class="reevents-booking-action" method="post" action="' . esc_url( $actions['url'] ) . '">';
			echo '<input type="hidden" name="action" value="' . esc_attr( $form['action'] ) . '" />';
			echo '<input type="hidden" name="booking_id" value="' . esc_attr( (string) $actions['booking'] ) . '" />';
			echo '<input type="hidden" name="redirect_to" value="' . esc_url( $actions['redirect'] ) . '" />';
			wp_nonce_field( 'reevents_booking_action_' . (int) $actions['booking'], 'reevents_booking_nonce' );

			foreach ( $actions['fields'] as $name => $value ) {
				if ( '' !== (string) $value ) {
					echo '<input type="hidden" name="' . esc_attr( (string) $name ) . '" value="' . esc_attr( (string) $value ) . '" />';
				}
			}

			echo '<button type="submit" class="' . esc_attr( $form['class'] ) . '">' . esc_html( $form['label'] ) . '</button>';
			echo '</form>';
		}

		if ( ! $actions['can_cancel'] && in_array( $card['status'], [ 'pending', 'confirmed' ], true ) && '' !== $actions['cancel_note'] ) {
			echo '<p class="reevents-booking-note">' . esc_html( $actions['cancel_note'] ) . '</p>';
		}

		if ( ! $actions['can_reactivate'] && 'cancelled' === $card['status'] && '' !== $actions['reactivate_note'] ) {
			echo '<p class="reevents-booking-note">' . esc_html( $actions['reactivate_note'] ) . '</p>';
		}

		echo '</li>';
	}

	echo '</ul>';

	if ( '' !== (string) $args['pagination'] ) {
		echo $args['pagination']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by Pagination.
	}
} elseif ( $args['lookup'] ) {
	$lookup = $args['lookup'];

	echo '<form class="reevents-bookings-lookup" method="post" action="' . esc_url( $lookup['action'] ) . '">';
	echo '<h3 class="reevents-bookings-lookup-heading">' . esc_html( $lookup['heading'] ) . '</h3>';
	echo '<input type="hidden" name="reevents_lookup" value="1" />';
	wp_nonce_field( 'reevents_lookup', 'reevents_lookup_nonce' );
	echo '<p class="reevents-bookings-field"><label for="reevents-lookup-reference">' . esc_html( $lookup['reference'] ) . '</label><input type="text" id="reevents-lookup-reference" name="reevents_reference" autocomplete="off" required /></p>';
	echo '<p class="reevents-bookings-field"><label for="reevents-lookup-email">' . esc_html( $lookup['email'] ) . '</label><input type="email" id="reevents-lookup-email" name="reevents_email" autocomplete="email" required /></p>';
	echo '<p class="reevents-bookings-submit"><button type="submit">' . esc_html( $lookup['button'] ) . '</button></p>';
	echo '</form>';
} else {
	echo '<p class="reevents-empty">' . esc_html( $args['empty'] ) . '</p>';
}

echo '</div>';
echo '</div>';
