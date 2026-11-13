<?php
/**
 * Blocks.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Frontend;

use ReEvents\Render\Booking_Form;
use ReEvents\Render\Field;
use ReEvents\Render\Widget_Registry;

/**
 * Registers the ReEvents blocks.
 */
final class Blocks {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'register_blocks' ] );
		add_action( 'enqueue_block_editor_assets', [ self::class, 'editor_data' ] );
	}

	/**
	 * Block attribute to widget argument map.
	 *
	 * @return array<string, string>
	 */
	public static function attribute_map(): array {
		return [
			'showKeyword'       => 'show_keyword',
			'showVenue'         => 'show_venue',
			'showCategory'      => 'show_category',
			'showOrganizers'    => 'show_organizers',
			'showOnlineLinks'   => 'show_online_links',
			'showContent'       => 'show_content',
			'showDates'         => 'show_dates',
			'showUpcoming'      => 'show_upcoming',
			'showResults'       => 'show_results',
			'showDescription'   => 'show_description',
			'showImage'         => 'show_image',
			'showPrice'         => 'show_price',
			'showExcerpt'       => 'show_excerpt',
			'showCategories'    => 'show_categories',
			'showTags'          => 'show_tags',
			'showEnd'           => 'show_end',
			'showSeatsLeft'     => 'show_seats_left',
			'showCancelled'     => 'show_cancelled',
			'titleTag'          => 'title_tag',
			'excerptLength'     => 'excerpt_length',
			'showTitle'         => 'show_title',
			'showStatus'        => 'show_status',
			'showCalendarLinks' => 'show_calendar_links',
			'showVenueAddress'  => 'show_venue_address',
			'showVenueMapLink'  => 'show_venue_map_link',
			'showVenueMap'      => 'show_venue_map',
			'venueMapHeight'    => 'venue_map_height',
			'showAddress'       => 'show_address',
			'showWebsite'       => 'show_website',
			'showMapLink'       => 'show_map_link',
			'showNextEvent'     => 'show_next_event',
			'showTag'           => 'show_tag',
			'showOrganizer'     => 'show_organizer',
			'showOrder'         => 'show_order',
			'showToday'         => 'show_today',
			'weekStart'         => 'week_start',
			'defaultQuantity'   => 'default_quantity',
			'maxQuantity'       => 'max_quantity',
			'hideSoldOut'       => 'hide_sold_out',
			'paginationKey'     => 'pagination_key',
			'useQuery'          => 'use_query',
			'imageSize'         => 'image_size',
			'showNav'           => 'show_nav',
			'dateFormat'        => 'date_format',
			'timeFormat'        => 'time_format',
			'monthFormat'       => 'month_format',
			'className'         => 'class',
			'wrapperClass'      => 'wrapper_class',
			'emptyLabel'        => 'empty_label',
			'showHeading'       => 'show_heading',
		];
	}

	/**
	 * Register blocks that have been built.
	 */
	public static function register_blocks(): void {
		$blocks = [
			'events-list'   => 'events-list',
			'event-details' => 'event-details',
			'venues-list'   => 'venues-list',
			'events-search' => 'events-search',
			'calendar'      => 'calendar',
			'map'           => 'map',
			'bookings'      => 'bookings',
		];

		foreach ( $blocks as $block => $widget ) {
			$path = RE_EVENTS_DIR . 'blocks/build/' . $block;

			if ( ! file_exists( $path . '/block.json' ) ) {
				continue;
			}

			register_block_type(
				$path,
				[
					'render_callback' => static function ( array $attributes ) use ( $widget ): string {
						return Widget_Registry::render( $widget, self::prepare_attributes( $attributes ) );
					},
				]
			);
		}

		self::register_field_block();
		self::register_booking_form_block();
	}

	/**
	 * Map block attributes to widget arguments.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, mixed>
	 */
	public static function prepare_attributes( array $attributes ): array {
		foreach ( self::attribute_map() as $from => $to ) {
			if ( array_key_exists( $from, $attributes ) ) {
				$attributes[ $to ] = $attributes[ $from ];
				unset( $attributes[ $from ] );
			}
		}

		return $attributes;
	}

	/**
	 * Register the field block.
	 */
	private static function register_field_block(): void {
		$path = RE_EVENTS_DIR . 'blocks/build/field/block.json';

		if ( file_exists( $path ) ) {
			register_block_type( $path, [ 'render_callback' => [ self::class, 'render_field' ] ] );
		}
	}

	/**
	 * Register the booking form block.
	 */
	private static function register_booking_form_block(): void {
		$path = RE_EVENTS_DIR . 'blocks/build/booking-form/block.json';

		if ( file_exists( $path ) ) {
			register_block_type( $path, [ 'render_callback' => [ self::class, 'render_booking_form' ] ] );
		}
	}

	/**
	 * Pass the field lists to the field block editor.
	 */
	public static function editor_data(): void {
		$handle = 'reevents-field-editor-script';

		if ( ! wp_script_is( $handle, 'registered' ) ) {
			return;
		}

		wp_localize_script(
			$handle,
			'reeventsFields',
			[
				'event'     => Field::fields( 'event' ),
				'venue'     => Field::fields( 'venue' ),
				'organizer' => Field::fields( 'organizer' ),
			]
		);
	}

	/**
	 * Render the field block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public static function render_field( array $attributes ): string {
		$context = sanitize_key( (string) ( $attributes['source'] ?? 'event' ) );
		$args    = [
			'field'       => sanitize_key( (string) ( $attributes['field'] ?? 'title' ) ),
			'label'       => (string) ( $attributes['label'] ?? '' ),
			'link'        => ! empty( $attributes['link'] ),
			'separator'   => (string) ( $attributes['separator'] ?? ', ' ),
			'empty_label' => (string) ( $attributes['emptyLabel'] ?? '' ),
			'class'       => (string) ( $attributes['className'] ?? '' ),
			'tag'         => sanitize_key( (string) ( $attributes['tag'] ?? 'span' ) ),
			'size'        => sanitize_key( (string) ( $attributes['size'] ?? 'large' ) ),
			'date_format' => (string) ( $attributes['dateFormat'] ?? '' ),
			'time_format' => (string) ( $attributes['timeFormat'] ?? '' ),
		];
		$id      = (int) ( $attributes['id'] ?? 0 );

		if ( 'venue' === $context ) {
			$args['venue'] = $id;
		} elseif ( 'organizer' === $context ) {
			$args['organizer'] = $id;
		} else {
			$args['event'] = $id;
		}

		return Field::render( $args );
	}

	/**
	 * Render the booking form block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public static function render_booking_form( array $attributes ): string {
		$args = [
			'event' => (int) ( $attributes['event'] ?? 0 ),
			'class' => (string) ( $attributes['className'] ?? '' ),
		];

		foreach ( [
			'button_label'    => 'buttonLabel',
			'success_message' => 'successMessage',
			'heading'         => 'heading',
			'date_format'     => 'dateFormat',
			'time_format'     => 'timeFormat',
		] as $arg => $attribute ) {
			if ( '' !== (string) ( $attributes[ $attribute ] ?? '' ) ) {
				$args[ $arg ] = (string) $attributes[ $attribute ];
			}
		}

		foreach ( [
			'ticket'     => 'ticket',
			'occurrence' => 'occurrence',
		] as $arg => $attribute ) {
			if ( ! empty( $attributes[ $attribute ] ) ) {
				$args[ $arg ] = (int) $attributes[ $attribute ];
			}
		}

		foreach ( [
			'show_price'    => 'showPrice',
			'hide_sold_out' => 'hideSoldOut',
		] as $arg => $attribute ) {
			if ( array_key_exists( $attribute, $attributes ) ) {
				$args[ $arg ] = (bool) $attributes[ $attribute ];
			}
		}

		foreach ( [
			'default_quantity' => 'defaultQuantity',
			'max_quantity'     => 'maxQuantity',
		] as $arg => $attribute ) {
			if ( array_key_exists( $attribute, $attributes ) ) {
				$args[ $arg ] = (int) $attributes[ $attribute ];
			}
		}

		return Booking_Form::render( $args );
	}
}
