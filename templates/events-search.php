<?php
/**
 * Events search template.
 *
 * @package ReEvents
 *
 * @var array{args: array<string, mixed>, filters: array{search:string,category:int,tag:string,venue:int,organizer:int,from:string,to:string,order:string,upcoming:bool}, categories: array<int, array{id:int,name:string}>, venues: array<int, array{id:int,title:string}>, tags: array<int, array{id:int,name:string}>, organizers: array<int, array{id:int,title:string}>, results: string, action: string} $args
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are scoped to the template include.

$widget_args = $args['args'];
$filters     = $args['filters'];
$categories  = $args['categories'];
$venues      = $args['venues'];
$tags        = $args['tags'];
$organizers  = $args['organizers'];
$results     = $args['results'];
$form_action = $args['action'];
$has_filters = '' !== $filters['search']
	|| $filters['category']
	|| '' !== $filters['tag']
	|| $filters['venue']
	|| $filters['organizer']
	|| '' !== $filters['from']
	|| '' !== $filters['to']
	|| $filters['upcoming'];

echo '<div class="reevents-search-wrapper ' . esc_attr( (string) ( $widget_args['wrapper_class'] ?? '' ) ) . '">';
echo '<form class="reevents-search ' . esc_attr( (string) ( $widget_args['class'] ?? '' ) ) . '" method="get" action="' . esc_url( $form_action ) . '">';

if ( ! empty( $widget_args['show_keyword'] ) ) {
	echo '<p class="reevents-search-field">';
	echo '<label for="reevents-search-keyword">' . esc_html( (string) ( $widget_args['search_label'] ?? __( 'Search', 're-events' ) ) ) . '</label>';
	echo '<input type="search" id="reevents-search-keyword" name="reevents_search" value="' . esc_attr( $filters['search'] ) . '" placeholder="' . esc_attr( (string) ( $widget_args['search_placeholder'] ?? '' ) ) . '" />';
	echo '</p>';
}

if ( ! empty( $widget_args['show_category'] ) && ! empty( $categories ) ) {
	echo '<p class="reevents-search-field">';
	echo '<label for="reevents-search-category">' . esc_html( (string) ( $widget_args['category_label'] ?? __( 'Category', 're-events' ) ) ) . '</label>';
	echo '<select id="reevents-search-category" name="reevents_category">';
	echo '<option value="0">' . esc_html( (string) ( $widget_args['all_categories_label'] ?? __( 'All categories', 're-events' ) ) ) . '</option>';

	foreach ( $categories as $category ) {
		echo '<option value="' . esc_attr( (string) $category['id'] ) . '" ' . selected( $filters['category'], $category['id'], false ) . '>' . esc_html( $category['name'] ) . '</option>';
	}

	echo '</select>';
	echo '</p>';
}

if ( ! empty( $widget_args['show_venue'] ) && ! empty( $venues ) ) {
	echo '<p class="reevents-search-field">';
	echo '<label for="reevents-search-venue">' . esc_html( (string) ( $widget_args['venue_label'] ?? __( 'Venue', 're-events' ) ) ) . '</label>';
	echo '<select id="reevents-search-venue" name="reevents_venue_filter">';
	echo '<option value="0">' . esc_html( (string) ( $widget_args['all_venues_label'] ?? __( 'All venues', 're-events' ) ) ) . '</option>';

	foreach ( $venues as $venue ) {
		echo '<option value="' . esc_attr( (string) $venue['id'] ) . '" ' . selected( $filters['venue'], $venue['id'], false ) . '>' . esc_html( $venue['title'] ) . '</option>';
	}

	echo '</select>';
	echo '</p>';
}

if ( ! empty( $widget_args['show_tag'] ) && ! empty( $tags ) ) {
	echo '<p class="reevents-search-field">';
	echo '<label for="reevents-search-tag">' . esc_html( (string) ( $widget_args['tag_label'] ?? __( 'Tag', 're-events' ) ) ) . '</label>';
	echo '<select id="reevents-search-tag" name="reevents_tag">';
	echo '<option value="">' . esc_html( (string) ( $widget_args['all_tags_label'] ?? __( 'All tags', 're-events' ) ) ) . '</option>';

	foreach ( $tags as $tag_term ) {
		echo '<option value="' . esc_attr( (string) $tag_term['id'] ) . '" ' . selected( $filters['tag'], (string) $tag_term['id'], false ) . '>' . esc_html( $tag_term['name'] ) . '</option>';
	}

	echo '</select>';
	echo '</p>';
}

if ( ! empty( $widget_args['show_organizer'] ) && ! empty( $organizers ) ) {
	echo '<p class="reevents-search-field">';
	echo '<label for="reevents-search-organizer">' . esc_html( (string) ( $widget_args['organizer_label'] ?? __( 'Organizer', 're-events' ) ) ) . '</label>';
	echo '<select id="reevents-search-organizer" name="reevents_organizer">';
	echo '<option value="0">' . esc_html( (string) ( $widget_args['all_organizers_label'] ?? __( 'All organizers', 're-events' ) ) ) . '</option>';

	foreach ( $organizers as $organizer ) {
		echo '<option value="' . esc_attr( (string) $organizer['id'] ) . '" ' . selected( $filters['organizer'], $organizer['id'], false ) . '>' . esc_html( $organizer['title'] ) . '</option>';
	}

	echo '</select>';
	echo '</p>';
}

if ( ! empty( $widget_args['show_order'] ) ) {
	echo '<p class="reevents-search-field">';
	echo '<label for="reevents-search-order">' . esc_html( (string) ( $widget_args['order_label'] ?? __( 'Order', 're-events' ) ) ) . '</label>';
	echo '<select id="reevents-search-order" name="reevents_order">';
	echo '<option value="asc" ' . selected( $filters['order'], 'asc', false ) . '>' . esc_html__( 'Soonest first', 're-events' ) . '</option>';
	echo '<option value="desc" ' . selected( $filters['order'], 'desc', false ) . '>' . esc_html__( 'Latest first', 're-events' ) . '</option>';
	echo '</select>';
	echo '</p>';
}

if ( ! empty( $widget_args['show_dates'] ) ) {
	echo '<p class="reevents-search-field">';
	echo '<label for="reevents-search-from">' . esc_html( (string) ( $widget_args['from_label'] ?? __( 'From', 're-events' ) ) ) . '</label>';
	echo '<input type="date" id="reevents-search-from" name="reevents_from" value="' . esc_attr( $filters['from'] ) . '" />';
	echo '</p>';

	echo '<p class="reevents-search-field">';
	echo '<label for="reevents-search-to">' . esc_html( (string) ( $widget_args['to_label'] ?? __( 'To', 're-events' ) ) ) . '</label>';
	echo '<input type="date" id="reevents-search-to" name="reevents_to" value="' . esc_attr( $filters['to'] ) . '" />';
	echo '</p>';
}

if ( ! empty( $widget_args['show_upcoming'] ) ) {
	echo '<p class="reevents-search-field">';
	echo '<input type="hidden" name="reevents_upcoming" value="0" />';
	echo '<label><input type="checkbox" name="reevents_upcoming" value="1" ' . checked( $filters['upcoming'], true, false ) . ' /> ' . esc_html( (string) ( $widget_args['upcoming_label'] ?? __( 'Only upcoming', 're-events' ) ) ) . '</label>';
	echo '</p>';
}

echo '<p class="reevents-search-actions">';
echo '<button type="submit" class="reevents-search-submit">' . esc_html( (string) ( $widget_args['submit_label'] ?? __( 'Search', 're-events' ) ) ) . '</button>';

if ( $has_filters ) {
	echo '<a href="' . esc_url( $form_action ) . '" class="reevents-search-clear">' . esc_html( (string) ( $widget_args['clear_label'] ?? __( 'Clear', 're-events' ) ) ) . '</a>';
}

echo '</p>';
echo '</form>';
echo '</div>';

if ( '' !== $results ) {
	echo '<div class="reevents-search-results">' . wp_kses_post( $results ) . '</div>';
}
