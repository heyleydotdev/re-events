<?php
/**
 * Submission form template.
 *
 * @package ReEvents
 *
 * @var array{post_id:int,title:string,excerpt:string,content:string,start:string,end:string,timezone:string,all_day:bool,venue_id:int,capacity:int,categories:array<int,int>,tags:string,organizer_ids:array<int,int>,online_urls:array<int, array{label:string,url:string}>,categories_terms:array<int, array{id:int,name:string}>,organizers:array<int, array{id:int,title:string}>,venues:array<int, array{id:int,title:string}>,use_editor:bool,errors:array<string,string>,state:array{status:string,message:string,errors:array<string,string>,values:array<string,mixed>},action:string,nonce:string,args:array<string,mixed>} $args
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are scoped to the template include.

$widget_args  = $args['args'];
$field_errors = $args['errors'];

if ( 'success' === $args['state']['status'] ) {
	echo '<p class="reevents-submission-success" role="status">' . esc_html( $args['state']['message'] ) . '</p>';
} elseif ( 'error' === $args['state']['status'] && empty( $field_errors ) && '' !== $args['state']['message'] ) {
	echo '<p class="reevents-submission-error" role="alert">' . esc_html( $args['state']['message'] ) . '</p>';
}

if ( ! empty( $field_errors ) ) {
	echo '<div class="reevents-submission-errors" role="alert">';
	echo '<p>' . esc_html__( 'Please correct the following fields.', 're-events' ) . '</p>';
	echo '<ul>';

	foreach ( $field_errors as $message ) {
		echo '<li>' . esc_html( (string) $message ) . '</li>';
	}

	echo '</ul>';
	echo '</div>';
}

echo '<div class="reevents-submit-wrapper ' . esc_attr( (string) ( $widget_args['wrapper_class'] ?? '' ) ) . '">';
echo '<form class="reevents-submit-form ' . esc_attr( (string) ( $widget_args['class'] ?? '' ) ) . '" method="post" action="' . esc_url( $args['action'] ) . '">';
echo '<input type="hidden" name="action" value="reevents_submit" />';
echo '<input type="hidden" name="event_id" value="' . esc_attr( (string) $args['post_id'] ) . '" />';
echo '<input type="hidden" name="timezone" value="' . esc_attr( $args['timezone'] ) . '" />';
echo '<input type="hidden" name="reevents_submit_nonce" value="' . esc_attr( $args['nonce'] ) . '" />';
echo '<p class="reevents-honeypot" hidden aria-hidden="true"><label>' . esc_html__( 'Website', 're-events' ) . '<input type="text" name="website" value="" tabindex="-1" autocomplete="off" /></label></p>';

$title_error = (string) ( $field_errors['title'] ?? '' );
echo '<p class="reevents-submit-field">';
echo '<label for="reevents-submit-title">' . esc_html( (string) ( $widget_args['title_label'] ?? __( 'Event title', 're-events' ) ) ) . '</label>';
echo '<input type="text" id="reevents-submit-title" name="title" value="' . esc_attr( $args['title'] ) . '" ' . ( '' !== $title_error ? 'aria-invalid="true" aria-describedby="reevents-submit-title-error"' : '' ) . ' required />';
echo '<span id="reevents-submit-title-error" class="reevents-field-error" role="alert">' . esc_html( $title_error ) . '</span>';
echo '</p>';

$excerpt_error = (string) ( $field_errors['excerpt'] ?? '' );
echo '<p class="reevents-submit-field">';
echo '<label for="reevents-submit-excerpt">' . esc_html( (string) ( $widget_args['excerpt_label'] ?? __( 'Short summary', 're-events' ) ) ) . '</label>';
echo '<textarea id="reevents-submit-excerpt" name="excerpt" rows="3" ' . ( '' !== $excerpt_error ? 'aria-invalid="true" aria-describedby="reevents-submit-excerpt-error"' : '' ) . '>' . esc_textarea( $args['excerpt'] ) . '</textarea>';
echo '<span id="reevents-submit-excerpt-error" class="reevents-field-error" role="alert">' . esc_html( $excerpt_error ) . '</span>';
echo '</p>';

$content_error = (string) ( $field_errors['content'] ?? '' );
$content_for   = $args['use_editor'] ? 'reevents_submit_content' : 'reevents-submit-content';
echo '<div class="reevents-submit-field">';
echo '<label for="' . esc_attr( $content_for ) . '">' . esc_html( (string) ( $widget_args['content_label'] ?? __( 'Description', 're-events' ) ) ) . '</label>';

if ( $args['use_editor'] ) {
	wp_editor(
		$args['content'],
		'reevents_submit_content',
		[
			'textarea_name' => 'content',
			'textarea_rows' => 12,
			'media_buttons' => false,
			'quicktags'     => true,
			'tinymce'       => true,
		]
	);
} else {
	echo '<textarea id="reevents-submit-content" name="content" rows="10">' . esc_textarea( $args['content'] ) . '</textarea>';
}

echo '<span id="reevents-submit-content-error" class="reevents-field-error" role="alert">' . esc_html( $content_error ) . '</span>';
echo '</div>';

$start_error = (string) ( $field_errors['start'] ?? '' );
echo '<p class="reevents-submit-field">';
echo '<label for="reevents-submit-start">' . esc_html( (string) ( $widget_args['start_label'] ?? __( 'Starts', 're-events' ) ) ) . '</label>';
echo '<input type="datetime-local" id="reevents-submit-start" name="start" value="' . esc_attr( $args['start'] ) . '" ' . ( '' !== $start_error ? 'aria-invalid="true" aria-describedby="reevents-submit-start-error"' : '' ) . ' required />';
echo '<span id="reevents-submit-start-error" class="reevents-field-error" role="alert">' . esc_html( $start_error ) . '</span>';
echo '</p>';

$end_error = (string) ( $field_errors['end'] ?? '' );
echo '<p class="reevents-submit-field">';
echo '<label for="reevents-submit-end">' . esc_html( (string) ( $widget_args['end_label'] ?? __( 'Ends', 're-events' ) ) ) . '</label>';
echo '<input type="datetime-local" id="reevents-submit-end" name="end" value="' . esc_attr( $args['end'] ) . '" ' . ( '' !== $end_error ? 'aria-invalid="true" aria-describedby="reevents-submit-end-error"' : '' ) . ' />';
echo '<span id="reevents-submit-end-error" class="reevents-field-error" role="alert">' . esc_html( $end_error ) . '</span>';
echo '</p>';

echo '<p class="reevents-submit-field reevents-submit-checkbox">';
echo '<label><input type="checkbox" name="all_day" value="1" ' . checked( $args['all_day'], true, false ) . ' /> ' . esc_html( (string) ( $widget_args['all_day_label'] ?? __( 'All-day event', 're-events' ) ) ) . '</label>';
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

if ( ! empty( $args['categories_terms'] ) ) {
	echo '<p class="reevents-submit-field">';
	echo '<label for="reevents-submit-categories">' . esc_html( (string) ( $widget_args['categories_label'] ?? __( 'Categories', 're-events' ) ) ) . '</label>';
	echo '<select id="reevents-submit-categories" name="categories[]" multiple size="6">';

	foreach ( $args['categories_terms'] as $category_term ) {
		echo '<option value="' . esc_attr( (string) $category_term['id'] ) . '" ' . selected( in_array( $category_term['id'], $args['categories'], true ), true, false ) . '>' . esc_html( $category_term['name'] ) . '</option>';
	}

	echo '</select>';
	echo '</p>';
}

echo '<p class="reevents-submit-field">';
echo '<label for="reevents-submit-tags">' . esc_html( (string) ( $widget_args['tags_label'] ?? __( 'Tags', 're-events' ) ) ) . '</label>';
echo '<input type="text" id="reevents-submit-tags" name="tags" value="' . esc_attr( $args['tags'] ) . '" placeholder="' . esc_attr( (string) ( $widget_args['tags_placeholder'] ?? __( 'Comma-separated', 're-events' ) ) ) . '" />';
echo '</p>';

if ( ! empty( $args['organizers'] ) ) {
	echo '<p class="reevents-submit-field">';
	echo '<label for="reevents-submit-organizers">' . esc_html( (string) ( $widget_args['organizers_label'] ?? __( 'Organizers', 're-events' ) ) ) . '</label>';
	echo '<select id="reevents-submit-organizers" name="organizer_ids[]" multiple size="5">';

	foreach ( $args['organizers'] as $organizer ) {
		echo '<option value="' . esc_attr( (string) $organizer['id'] ) . '" ' . selected( in_array( $organizer['id'], $args['organizer_ids'], true ), true, false ) . '>' . esc_html( $organizer['title'] ) . '</option>';
	}

	echo '</select>';
	echo '</p>';
}

echo '<fieldset class="reevents-submit-field reevents-submit-links">';
echo '<legend>' . esc_html( (string) ( $widget_args['online_links_label'] ?? __( 'Online links', 're-events' ) ) ) . '</legend>';
$online_link_rows = max( 3, count( $args['online_urls'] ) + 1 );
$online_link_rows = min( 10, $online_link_rows );

for ( $i = 0; $i < $online_link_rows; $i++ ) {
	$online_link = $args['online_urls'][ $i ] ?? [
		'label' => '',
		'url'   => '',
	];

	echo '<p class="reevents-submit-link">';
	echo '<label for="reevents-submit-link-label-' . esc_attr( (string) $i ) . '">' . esc_html__( 'Label', 're-events' ) . '</label>';
	echo '<input type="text" id="reevents-submit-link-label-' . esc_attr( (string) $i ) . '" name="online_label[]" value="' . esc_attr( (string) $online_link['label'] ) . '" />';
	echo '<label for="reevents-submit-link-url-' . esc_attr( (string) $i ) . '">' . esc_html__( 'URL', 're-events' ) . '</label>';
	echo '<input type="url" id="reevents-submit-link-url-' . esc_attr( (string) $i ) . '" name="online_url[]" value="' . esc_attr( (string) $online_link['url'] ) . '" placeholder="https://" />';
	echo '</p>';
}

echo '</fieldset>';

if ( ! is_user_logged_in() ) {
	$name_error = (string) ( $field_errors['name'] ?? '' );
	echo '<p class="reevents-submit-field">';
	echo '<label for="reevents-submit-name">' . esc_html( (string) ( $widget_args['name_label'] ?? __( 'Your name', 're-events' ) ) ) . '</label>';
	echo '<input type="text" id="reevents-submit-name" name="name" value="' . esc_attr( $args['name'] ) . '" autocomplete="name" ' . ( '' !== $name_error ? 'aria-invalid="true" aria-describedby="reevents-submit-name-error"' : '' ) . ' required />';
	echo '<span id="reevents-submit-name-error" class="reevents-field-error" role="alert">' . esc_html( $name_error ) . '</span>';
	echo '</p>';

	$email_error = (string) ( $field_errors['email'] ?? '' );
	echo '<p class="reevents-submit-field">';
	echo '<label for="reevents-submit-email">' . esc_html( (string) ( $widget_args['email_label'] ?? __( 'Your email', 're-events' ) ) ) . '</label>';
	echo '<input type="email" id="reevents-submit-email" name="email" value="' . esc_attr( $args['email'] ?? '' ) . '" autocomplete="email" ' . ( '' !== $email_error ? 'aria-invalid="true" aria-describedby="reevents-submit-email-error"' : '' ) . ' required />';
	echo '<span id="reevents-submit-email-error" class="reevents-field-error" role="alert">' . esc_html( $email_error ) . '</span>';
	echo '</p>';
}

$button = 0 === $args['post_id'] ? (string) ( $widget_args['submit_label'] ?? __( 'Submit event', 're-events' ) ) : (string) ( $widget_args['save_label'] ?? __( 'Save changes', 're-events' ) );

echo '<p class="reevents-submit-actions"><button type="submit">' . esc_html( $button ) . '</button></p>';
echo '</form>';
echo '</div>';
