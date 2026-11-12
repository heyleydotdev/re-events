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
		];

		$attribute_map = [
			'showKeyword'     => 'show_keyword',
			'showVenue'       => 'show_venue',
			'showCategory'    => 'show_category',
			'showOrganizers'  => 'show_organizers',
			'showOnlineLinks' => 'show_online_links',
			'showContent'     => 'show_content',
			'showDates'       => 'show_dates',
			'showUpcoming'    => 'show_upcoming',
			'showResults'     => 'show_results',
			'showDescription' => 'show_description',
			'showImage'       => 'show_image',
			'showPrice'       => 'show_price',
			'showExcerpt'     => 'show_excerpt',
			'showCategories'  => 'show_categories',
			'showTags'        => 'show_tags',
			'showEnd'         => 'show_end',
			'showSeatsLeft'   => 'show_seats_left',
			'showCancelled'   => 'show_cancelled',
			'titleTag'        => 'title_tag',
			'excerptLength'   => 'excerpt_length',
			'paginationKey'   => 'pagination_key',
			'useQuery'        => 'use_query',
			'imageSize'       => 'image_size',
			'showNav'         => 'show_nav',
			'className'       => 'class',
			'wrapperClass'    => 'wrapper_class',
			'emptyLabel'      => 'empty_label',
		];

		foreach ( $blocks as $block => $widget ) {
			$path = RE_EVENTS_DIR . 'blocks/build/' . $block;

			if ( ! file_exists( $path . '/block.json' ) ) {
				continue;
			}

			register_block_type(
				$path,
				[
					'render_callback' => static function ( array $attributes ) use ( $widget, $attribute_map ): string {
						foreach ( $attribute_map as $from => $to ) {
							if ( array_key_exists( $from, $attributes ) ) {
								$attributes[ $to ] = $attributes[ $from ];
								unset( $attributes[ $from ] );
							}
						}

						return Widget_Registry::render( $widget, $attributes );
					},
				]
			);
		}

		self::register_field_block();
		self::register_booking_form_block();
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
		] as $arg => $attribute ) {
			if ( '' !== (string) ( $attributes[ $attribute ] ?? '' ) ) {
				$args[ $arg ] = (string) $attributes[ $attribute ];
			}
		}

		return Booking_Form::render( $args );
	}
}
