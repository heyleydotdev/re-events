<?php
/**
 * Calendar template.
 *
 * @package ReEvents
 *
 * @var array{month_label:string,prev_url:string,next_url:string,days:array<int, array{date:string,label:int,in_month:bool,events:array<int, array{title:string,url:string,time:string,ongoing:bool,cancelled:bool}>}>,args:array<string,mixed>} $args
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variables are scoped to the template include.

$weekdays = array_map( 'trim', explode( ',', (string) ( $args['args']['weekdays'] ?? '' ) ) );

if ( 7 !== count( $weekdays ) ) {
	$weekdays = [
		__( 'Mon', 're-events' ),
		__( 'Tue', 're-events' ),
		__( 'Wed', 're-events' ),
		__( 'Thu', 're-events' ),
		__( 'Fri', 're-events' ),
		__( 'Sat', 're-events' ),
		__( 'Sun', 're-events' ),
	];
}

echo '<div class="reevents-calendar-wrapper ' . esc_attr( (string) ( $args['args']['wrapper_class'] ?? '' ) ) . '">';
echo '<div class="reevents-calendar ' . esc_attr( (string) ( $args['args']['class'] ?? '' ) ) . '">';

if ( ! empty( $args['args']['show_nav'] ) ) {
	echo '<div class="reevents-calendar-nav">';
	echo '<a class="reevents-calendar-prev" href="' . esc_url( $args['prev_url'] ) . '">&larr;</a>';
	echo '<h2 class="reevents-calendar-title">' . esc_html( $args['month_label'] ) . '</h2>';
	echo '<a class="reevents-calendar-next" href="' . esc_url( $args['next_url'] ) . '">&rarr;</a>';
	echo '</div>';
	echo '</div>';
} else {
	echo '<h2 class="reevents-calendar-title">' . esc_html( $args['month_label'] ) . '</h2>';
}

echo '<table class="reevents-calendar-table">';
echo '<thead><tr>';

foreach ( $weekdays as $weekday ) {
	echo '<th scope="col">' . esc_html( $weekday ) . '</th>';
}

echo '</tr></thead><tbody>';

foreach ( array_chunk( $args['days'], 7 ) as $week ) {
	echo '<tr>';

	foreach ( $week as $day ) {
		$classes = 'reevents-calendar-day';

		if ( ! $day['in_month'] ) {
			$classes .= ' is-outside';
		}

		if ( ! empty( $day['events'] ) ) {
			$classes .= ' has-events';
		}

		echo '<td class="' . esc_attr( $classes ) . '">';
		echo '<span class="reevents-calendar-daynum">' . esc_html( (string) $day['label'] ) . '</span>';

		if ( ! empty( $day['events'] ) ) {
			echo '<ul class="reevents-calendar-events">';

			foreach ( $day['events'] as $event ) {
				$event_classes = 'reevents-calendar-event';

				if ( $event['cancelled'] ) {
					$event_classes .= ' is-cancelled';
				}

				echo '<li class="' . esc_attr( $event_classes ) . '">';

				if ( ! empty( $event['ongoing'] ) ) {
					echo '<span class="reevents-calendar-ongoing">' . esc_html( (string) ( $args['args']['ongoing_label'] ?? __( 'Ongoing', 're-events' ) ) ) . '</span> ';
				} elseif ( '' !== $event['time'] ) {
					echo '<span class="reevents-calendar-time">' . esc_html( $event['time'] ) . '</span> ';
				}

				echo '<a href="' . esc_url( $event['url'] ) . '">' . esc_html( $event['title'] ) . '</a>';
				echo '</li>';
			}

			echo '</ul>';
		}

		echo '</td>';
	}

	echo '</tr>';
}

echo '</tbody></table>';
echo '</div>';
echo '</div>';
