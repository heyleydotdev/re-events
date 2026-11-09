<?php
/**
 * Map template.
 *
 * @package ReEvents
 *
 * @var array{markers: array<int, array{lat:float,lng:float,title:string,url:string}>, height:int, args:array<string,mixed>} $args
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are scoped to the template include.

if ( empty( $args['markers'] ) ) {
	echo '<p class="reevents-empty">' . esc_html__( 'No mapped venues.', 're-events' ) . '</p>';
	return;
}

$map_id = wp_unique_id( 'reevents-map-' );

echo '<div class="reevents-map-wrapper ' . esc_attr( (string) ( $args['args']['wrapper_class'] ?? '' ) ) . '">';
echo '<div id="' . esc_attr( $map_id ) . '" class="reevents-map ' . esc_attr( (string) ( $args['args']['class'] ?? '' ) ) . '" data-markers="' . esc_attr( (string) wp_json_encode( $args['markers'] ) ) . '" data-height="' . esc_attr( (string) $args['height'] ) . '"></div>';
echo '</div>';
