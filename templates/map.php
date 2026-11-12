<?php
/**
 * Map template.
 *
 * @package ReEvents
 *
 * @var array{markers: array<int, array{lat:float,lng:float,title:string,url:string,address:string,next:string}>, provider:string, height:int, center:string, zoom:int, fit:bool, args:array<string,mixed>} $args
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are scoped to the template include.

if ( empty( $args['markers'] ) ) {
	echo '<p class="reevents-empty">' . esc_html( (string) ( $args['args']['empty_label'] ?? __( 'No mapped venues.', 're-events' ) ) ) . '</p>';
	return;
}

$map_id = wp_unique_id( 'reevents-map-' );

echo '<div class="reevents-map-wrapper ' . esc_attr( (string) ( $args['args']['wrapper_class'] ?? '' ) ) . '">';
echo '<div id="' . esc_attr( $map_id ) . '" class="reevents-map ' . esc_attr( (string) ( $args['args']['class'] ?? '' ) ) . '" data-provider="' . esc_attr( (string) $args['provider'] ) . '" data-markers="' . esc_attr( (string) wp_json_encode( $args['markers'] ) ) . '" data-height="' . esc_attr( (string) $args['height'] ) . '" data-center="' . esc_attr( (string) $args['center'] ) . '" data-zoom="' . esc_attr( (string) $args['zoom'] ) . '" data-fit="' . ( $args['fit'] ? '1' : '0' ) . '" role="region" aria-label="' . esc_attr__( 'Venue map', 're-events' ) . '"></div>';
echo '<noscript><ul class="reevents-map-list">';

foreach ( $args['markers'] as $marker ) {
	echo '<li>';

	if ( '' !== (string) $marker['url'] ) {
		echo '<a href="' . esc_url( (string) $marker['url'] ) . '">' . esc_html( (string) $marker['title'] ) . '</a>';
	} else {
		echo esc_html( (string) $marker['title'] );
	}

	if ( '' !== (string) $marker['address'] ) {
		echo ' <span class="reevents-map-address">' . esc_html( (string) $marker['address'] ) . '</span>';
	}

	if ( '' !== (string) $marker['next'] ) {
		echo ' <span class="reevents-map-next">' . esc_html( (string) $marker['next'] ) . '</span>';
	}

	echo '</li>';
}

echo '</ul></noscript>';
echo '</div>';
