<?php
/**
 * Events search template.
 *
 * @package ReEvents
 *
 * @var array{args: array<string, mixed>, filters: array{search:string,category:int,venue:int,from:string,to:string,upcoming:bool}, categories: array<int, array{id:int,name:string}>, venues: array<int, array{id:int,title:string}>, results: string, action: string} $args
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are scoped to the template include.

$widget_args = $args['args'];
$filters     = $args['filters'];
$categories  = $args['categories'];
$venues      = $args['venues'];
$results     = $args['results'];
$form_action = $args['action'];
$has_filters = '' !== $filters['search']
	|| $filters['category']
	|| $filters['venue']
	|| '' !== $filters['from']
	|| '' !== $filters['to']
	|| $filters['upcoming'];

echo '<form class="reevents-search" method="get" action="' . esc_url( $form_action ) . '">';

if ( ! empty( $widget_args['show_keyword'] ) ) {
	echo '<p class="reevents-search-field">';
	echo '<label for="reevents-search-keyword">' . esc_html__( 'Search', 're-events' ) . '</label>';
	echo '<input type="search" id="reevents-search-keyword" name="reevents_search" value="' . esc_attr( $filters['search'] ) . '" />';
	echo '</p>';
}

if ( ! empty( $widget_args['show_category'] ) && ! empty( $categories ) ) {
	echo '<p class="reevents-search-field">';
	echo '<label for="reevents-search-category">' . esc_html__( 'Category', 're-events' ) . '</label>';
	echo '<select id="reevents-search-category" name="reevents_category">';
	echo '<option value="0">' . esc_html__( 'All categories', 're-events' ) . '</option>';

	foreach ( $categories as $category ) {
		echo '<option value="' . esc_attr( (string) $category['id'] ) . '" ' . selected( $filters['category'], $category['id'], false ) . '>' . esc_html( $category['name'] ) . '</option>';
	}

	echo '</select>';
	echo '</p>';
}

if ( ! empty( $widget_args['show_venue'] ) && ! empty( $venues ) ) {
	echo '<p class="reevents-search-field">';
	echo '<label for="reevents-search-venue">' . esc_html__( 'Venue', 're-events' ) . '</label>';
	echo '<select id="reevents-search-venue" name="reevents_venue_filter">';
	echo '<option value="0">' . esc_html__( 'All venues', 're-events' ) . '</option>';

	foreach ( $venues as $venue ) {
		echo '<option value="' . esc_attr( (string) $venue['id'] ) . '" ' . selected( $filters['venue'], $venue['id'], false ) . '>' . esc_html( $venue['title'] ) . '</option>';
	}

	echo '</select>';
	echo '</p>';
}

if ( ! empty( $widget_args['show_dates'] ) ) {
	echo '<p class="reevents-search-field">';
	echo '<label for="reevents-search-from">' . esc_html__( 'From', 're-events' ) . '</label>';
	echo '<input type="date" id="reevents-search-from" name="reevents_from" value="' . esc_attr( $filters['from'] ) . '" />';
	echo '</p>';

	echo '<p class="reevents-search-field">';
	echo '<label for="reevents-search-to">' . esc_html__( 'To', 're-events' ) . '</label>';
	echo '<input type="date" id="reevents-search-to" name="reevents_to" value="' . esc_attr( $filters['to'] ) . '" />';
	echo '</p>';
}

if ( ! empty( $widget_args['show_upcoming'] ) ) {
	echo '<p class="reevents-search-field">';
	echo '<label><input type="checkbox" name="reevents_upcoming" value="1" ' . checked( $filters['upcoming'], true, false ) . ' /> ' . esc_html__( 'Only upcoming', 're-events' ) . '</label>';
	echo '</p>';
}

echo '<p class="reevents-search-actions">';
echo '<button type="submit" class="reevents-search-submit">' . esc_html__( 'Search', 're-events' ) . '</button>';

if ( $has_filters ) {
	echo '<a href="' . esc_url( $form_action ) . '" class="reevents-search-clear">' . esc_html__( 'Clear', 're-events' ) . '</a>';
}

echo '</p>';
echo '</form>';

if ( '' !== $results ) {
	echo '<div class="reevents-search-results">' . wp_kses_post( $results ) . '</div>';
}
