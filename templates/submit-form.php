<?php
/**
 * Submission form template.
 *
 * @package ReEvents
 *
 * @var array{post_id:int,title:string,start:string,end:string,timezone:string,venue_id:int,capacity:int,content:string,name:string,email:string,venues:array<int, array{id:int,title:string}>,action:string,nonce:string,state:array{status:string,message:string},args:array<string,mixed>} $args
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are scoped to the template include.

$widget_args = $args['args'];

if ( 'success' === $args['state']['status'] ) {
	echo '<p class="reevents-submission-success" role="status">' . esc_html( $args['state']['message'] ) . '</p>';
} elseif ( 'error' === $args['state']['status'] && '' !== $args['state']['message'] ) {
	echo '<p class="reevents-submission-error" role="alert">' . esc_html( $args['state']['message'] ) . '</p>';
}

echo '<div class="reevents-submit-wrapper ' . esc_attr( (string) ( $widget_args['wrapper_class'] ?? '' ) ) . '">';
echo '<form class="reevents-submit-form ' . esc_attr( (string) ( $widget_args['class'] ?? '' ) ) . '" method="post" action="' . esc_url( $args['action'] ) . '">';
echo '<input type="hidden" name="action" value="reevents_submit" />';
echo '<input type="hidden" name="event_id" value="' . esc_attr( (string) $args['post_id'] ) . '" />';
echo '<input type="hidden" name="timezone" value="' . esc_attr( $args['timezone'] ) . '" />';
echo '<input type="hidden" name="reevents_submit_nonce" value="' . esc_attr( $args['nonce'] ) . '" />';
echo '<p class="reevents-honeypot" hidden aria-hidden="true"><label>' . esc_html__( 'Website', 're-events' ) . '<input type="text" name="website" value="" tabindex="-1" autocomplete="off" /></label></p>';

echo '<p class="reevents-submit-field">';
echo '<label for="reevents-submit-title">' . esc_html( (string) ( $widget_args['title_label'] ?? __( 'Event title', 're-events' ) ) ) . '</label>';
echo '<input type="text" id="reevents-submit-title" name="title" value="' . esc_attr( $args['title'] ) . '" required />';
echo '</p>';

echo '<p class="reevents-submit-field">';
echo '<label for="reevents-submit-start">' . esc_html( (string) ( $widget_args['start_label'] ?? __( 'Starts', 're-events' ) ) ) . '</label>';
echo '<input type="datetime-local" id="reevents-submit-start" name="start" value="' . esc_attr( $args['start'] ) . '" required />';
echo '</p>';

echo '<p class="reevents-submit-field">';
echo '<label for="reevents-submit-end">' . esc_html( (string) ( $widget_args['end_label'] ?? __( 'Ends', 're-events' ) ) ) . '</label>';
echo '<input type="datetime-local" id="reevents-submit-end" name="end" value="' . esc_attr( $args['end'] ) . '" />';
echo '</p>';

if ( ! empty( $args['venues'] ) ) {
	echo '<p class="reevents-submit-field">';
	echo '<label for="reevents-submit-venue">' . esc_html( (string) ( $widget_args['venue_label'] ?? __( 'Venue', 're-events' ) ) ) . '</label>';
	echo '<select id="reevents-submit-venue" name="venue_id">';
	echo '<option value="0">' . esc_html__( 'No venue', 're-events' ) . '</option>';

	foreach ( $args['venues'] as $venue ) {
		echo '<option value="' . esc_attr( (string) $venue['id'] ) . '" ' . selected( $args['venue_id'], $venue['id'], false ) . '>' . esc_html( $venue['title'] ) . '</option>';
	}

	echo '</select>';
	echo '</p>';
}

echo '<p class="reevents-submit-field">';
echo '<label for="reevents-submit-capacity">' . esc_html( (string) ( $widget_args['capacity_label'] ?? __( 'Capacity', 're-events' ) ) ) . '</label>';
echo '<input type="number" id="reevents-submit-capacity" name="capacity" value="' . esc_attr( (string) $args['capacity'] ) . '" min="0" />';
echo '</p>';

echo '<p class="reevents-submit-field">';
echo '<label for="reevents-submit-content">' . esc_html( (string) ( $widget_args['content_label'] ?? __( 'Description', 're-events' ) ) ) . '</label>';
echo '<textarea id="reevents-submit-content" name="content" rows="8">' . esc_textarea( $args['content'] ) . '</textarea>';
echo '</p>';

if ( ! is_user_logged_in() ) {
	echo '<p class="reevents-submit-field">';
	echo '<label for="reevents-submit-name">' . esc_html( (string) ( $widget_args['name_label'] ?? __( 'Your name', 're-events' ) ) ) . '</label>';
	echo '<input type="text" id="reevents-submit-name" name="name" value="' . esc_attr( $args['name'] ) . '" required />';
	echo '</p>';

	echo '<p class="reevents-submit-field">';
	echo '<label for="reevents-submit-email">' . esc_html( (string) ( $widget_args['email_label'] ?? __( 'Your email', 're-events' ) ) ) . '</label>';
	echo '<input type="email" id="reevents-submit-email" name="email" value="' . esc_attr( $args['email'] ) . '" required />';
	echo '</p>';
}

$button = 0 === $args['post_id'] ? (string) ( $widget_args['submit_label'] ?? __( 'Submit event', 're-events' ) ) : (string) ( $widget_args['save_label'] ?? __( 'Save changes', 're-events' ) );

echo '<p class="reevents-submit-actions"><button type="submit">' . esc_html( $button ) . '</button></p>';
echo '</form>';
echo '</div>';
