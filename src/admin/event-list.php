<?php
/**
 * Event list table.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use WP_Query;

/**
 * Customises the event list table.
 */
final class Event_List {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_filter( 'manage_reevents_event_posts_columns', [ self::class, 'columns' ] );
		add_action( 'manage_reevents_event_posts_custom_column', [ self::class, 'render_column' ], 10, 2 );
		add_filter( 'manage_edit-reevents_event_sortable_columns', [ self::class, 'sortable_columns' ] );
		add_action( 'restrict_manage_posts', [ self::class, 'filters' ], 10, 2 );
		add_action( 'pre_get_posts', [ self::class, 'query' ] );
	}

	/**
	 * Add columns.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		$new = [];

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'title' === $key ) {
				$new['reevents_start']  = __( 'Starts', 're-events' );
				$new['reevents_venue']  = __( 'Venue', 're-events' );
				$new['reevents_status'] = __( 'Status', 're-events' );
			}
		}

		return $new;
	}

	/**
	 * Render a custom column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function render_column( string $column, int $post_id ): void {
		if ( 'reevents_start' === $column ) {
			echo esc_html( self::format_start( $post_id ) );
		} elseif ( 'reevents_venue' === $column ) {
			$venue_id = (int) get_post_meta( $post_id, '_reevents_venue_id', true );

			echo $venue_id ? esc_html( get_the_title( $venue_id ) ) : '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped or static.
		} elseif ( 'reevents_status' === $column ) {
			$status = (string) get_post_meta( $post_id, '_reevents_status', true );

			echo esc_html( self::status_label( $status ) );
		}
	}

	/**
	 * Make the start column sortable.
	 *
	 * @param array<string, string> $columns Sortable columns.
	 * @return array<string, string>
	 */
	public static function sortable_columns( array $columns ): array {
		$columns['reevents_start'] = 'reevents_start';

		return $columns;
	}

	/**
	 * Render list filters.
	 *
	 * @param string $post_type Current post type.
	 * @param string $which     Tablenav position.
	 */
	public static function filters( string $post_type, string $which ): void {
		if ( 'reevents_event' !== $post_type || 'top' !== $which ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$category = isset( $_GET['reevents_event_category'] ) ? absint( wp_unslash( $_GET['reevents_event_category'] ) ) : 0;
		$venue    = isset( $_GET['reevents_venue'] ) ? absint( wp_unslash( $_GET['reevents_venue'] ) ) : 0;
		// phpcs:enable

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated dropdowns.
		$terms = get_terms(
			[
				'taxonomy'   => 'reevents_event_category',
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 1,
			]
		);

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			wp_dropdown_categories(
				[
					'taxonomy'        => 'reevents_event_category',
					'name'            => 'reevents_event_category',
					'value_field'     => 'term_id',
					'show_option_all' => __( 'All categories', 're-events' ),
					'selected'        => $category,
					'hide_empty'      => false,
					'hierarchical'    => true,
					'class'           => 'postform',
				]
			);
		}

		$venues = get_posts(
			[
				'post_type'      => 'reevents_venue',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'post_status'    => 'publish',
			]
		);

		if ( ! empty( $venues ) ) {
			wp_dropdown_pages(
				[
					'post_type'         => 'reevents_venue',
					'name'              => 'reevents_venue',
					'show_option_none'  => __( 'All venues', 're-events' ),
					'option_none_value' => '0',
					'selected'          => $venue,
					'class'             => 'postform',
				]
			);
		}
		// phpcs:enable
	}

	/**
	 * Apply sorting and filters to the event list query.
	 *
	 * @param WP_Query $query Query object.
	 */
	public static function query( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || 'reevents_event' !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( 'reevents_start' === $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', '_reevents_start_utc' );
			$query->set( 'orderby', 'meta_value' );
			$query->set( 'meta_type', 'DATETIME' );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$category = isset( $_GET['reevents_event_category'] ) ? absint( wp_unslash( $_GET['reevents_event_category'] ) ) : 0;
		$venue    = isset( $_GET['reevents_venue'] ) ? absint( wp_unslash( $_GET['reevents_venue'] ) ) : 0;
		// phpcs:enable

		if ( $category ) {
			$query->set(
				'tax_query',
				[
					[
						'taxonomy' => 'reevents_event_category',
						'field'    => 'term_id',
						'terms'    => $category,
					],
				]
			);
		}

		if ( $venue ) {
			$meta_query = (array) $query->get( 'meta_query' );

			$meta_query[] = [
				'key'   => '_reevents_venue_id',
				'value' => $venue,
				'type'  => 'NUMERIC',
			];

			$query->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * Format an event start.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function format_start( int $post_id ): string {
		$utc = (string) get_post_meta( $post_id, '_reevents_start_utc', true );

		if ( '' === $utc ) {
			return '—';
		}

		$timezone = (string) get_post_meta( $post_id, '_reevents_timezone', true );
		$date     = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $utc, new DateTimeZone( 'UTC' ) );

		if ( ! $date instanceof DateTimeImmutable ) {
			return '—';
		}

		$format = (string) Settings::get( 'date_format', get_option( 'date_format' ) );

		if ( ! get_post_meta( $post_id, '_reevents_all_day', true ) ) {
			$format .= ' ' . (string) Settings::get( 'time_format', get_option( 'time_format' ) );
		}

		$formatted = wp_date( $format, $date->getTimestamp(), self::timezone( $timezone ) );

		return false !== $formatted ? $formatted : '—';
	}

	/**
	 * Status label.
	 *
	 * @param string $status Status key.
	 */
	private static function status_label( string $status ): string {
		$labels = [
			'scheduled' => __( 'Scheduled', 're-events' ),
			'cancelled' => __( 'Cancelled', 're-events' ),
		];

		return $labels[ $status ] ?? $labels['scheduled'];
	}

	/**
	 * Get a timezone, falling back to UTC.
	 *
	 * @param string $name IANA timezone.
	 */
	private static function timezone( string $name ): DateTimeZone {
		try {
			return new DateTimeZone( $name );
		} catch ( Exception $e ) {
			return new DateTimeZone( 'UTC' );
		}
	}
}
