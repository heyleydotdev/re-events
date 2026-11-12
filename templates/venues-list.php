<?php
/**
 * Venues list template.
 *
 * @package ReEvents
 *
 * @var array{venues: array<int, array{id:int,title:string,permalink:string,content:string,address:string,website:string,map_link:string,next_event:array{title:string,permalink:string,start:string}|null}>, args: array<string, mixed>, pagination: string} $args
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are scoped to the template include.

if ( empty( $args['venues'] ) ) {
	echo '<p class="reevents-empty">' . esc_html( (string) ( $args['args']['empty_label'] ?? __( 'No venues found.', 're-events' ) ) ) . '</p>';
	return;
}

$settings  = $args['args'];
$title_tag = (string) ( $settings['title_tag'] ?? 'a' );
$title_tag = in_array( $title_tag, [ 'a', 'h2', 'h3', 'div' ], true ) ? $title_tag : 'a';
$is_link   = ! isset( $settings['link'] ) || ! empty( $settings['link'] );
$length    = isset( $settings['excerpt_length'] ) ? (int) $settings['excerpt_length'] : 30;
$on        = static function ( string $key ) use ( $settings ): bool {
	return ! isset( $settings[ $key ] ) || ! empty( $settings[ $key ] );
};

echo '<div class="reevents-venues-list-wrapper ' . esc_attr( (string) ( $settings['wrapper_class'] ?? '' ) ) . '">';
echo '<ul class="reevents-venues-list ' . esc_attr( (string) ( $settings['class'] ?? '' ) ) . '">';

foreach ( $args['venues'] as $venue ) {
	echo '<li class="reevents-venue">';

	if ( ! empty( $settings['show_image'] ) && has_post_thumbnail( $venue['id'] ) ) {
		$image = get_the_post_thumbnail( $venue['id'], (string) ( $settings['image_size'] ?? 'thumbnail' ), [ 'class' => 'reevents-venue-thumbnail' ] );

		if ( $is_link ) {
			echo '<a class="reevents-venue-image" href="' . esc_url( $venue['permalink'] ) . '">' . $image . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core image markup.
		} else {
			echo '<span class="reevents-venue-image">' . $image . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core image markup.
		}
	}

	if ( 'a' === $title_tag ) {
		if ( $is_link ) {
			echo '<a class="reevents-venue-title" href="' . esc_url( $venue['permalink'] ) . '">' . esc_html( $venue['title'] ) . '</a>';
		} else {
			echo '<span class="reevents-venue-title">' . esc_html( $venue['title'] ) . '</span>';
		}
	} else {
		echo '<' . esc_html( $title_tag ) . ' class="reevents-venue-title">';

		if ( $is_link ) {
			echo '<a class="reevents-venue-title-link" href="' . esc_url( $venue['permalink'] ) . '">' . esc_html( $venue['title'] ) . '</a>';
		} else {
			echo esc_html( $venue['title'] );
		}

		echo '</' . esc_html( $title_tag ) . '>';
	}

	if ( $on( 'show_description' ) && '' !== $venue['content'] ) {
		$description = wp_strip_all_tags( $venue['content'] );

		if ( $length > 0 ) {
			$description = wp_trim_words( $description, $length );
		}

		if ( '' !== $description ) {
			echo '<p class="reevents-venue-description">' . esc_html( $description ) . '</p>';
		}
	}

	if ( ! empty( $settings['show_address'] ) && '' !== $venue['address'] ) {
		echo '<p class="reevents-venue-address">' . esc_html( $venue['address'] ) . '</p>';
	}

	if ( ! empty( $settings['show_website'] ) && '' !== $venue['website'] ) {
		echo '<p class="reevents-venue-website"><a href="' . esc_url( $venue['website'] ) . '">' . esc_html__( 'Website', 're-events' ) . '</a></p>';
	}

	if ( ! empty( $settings['show_map_link'] ) && '' !== $venue['map_link'] ) {
		echo '<p class="reevents-venue-map"><a href="' . esc_url( $venue['map_link'] ) . '">' . esc_html__( 'Map', 're-events' ) . '</a></p>';
	}

	if ( ! empty( $venue['next_event'] ) ) {
		echo '<p class="reevents-venue-next">';
		echo esc_html( (string) ( $settings['next_event_label'] ?? __( 'Next event:', 're-events' ) ) ) . ' ';
		echo '<a href="' . esc_url( $venue['next_event']['permalink'] ) . '">' . esc_html( $venue['next_event']['title'] ) . '</a>';

		if ( '' !== $venue['next_event']['start'] ) {
			echo ' <span class="reevents-venue-next-date">' . esc_html( $venue['next_event']['start'] ) . '</span>';
		}

		echo '</p>';
	}

	echo '</li>';
}

echo '</ul>';

if ( '' !== (string) ( $args['pagination'] ?? '' ) ) {
	echo $args['pagination']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by Pagination.
}

echo '</div>';
