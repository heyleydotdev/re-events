<?php
/**
 * Blocks.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Frontend;

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
			'submit-form'   => 'submit-form',
			'my-events'     => 'my-events',
			'my-bookings'   => 'my-bookings',
		];

		$attribute_map = [
			'showKeyword'      => 'show_keyword',
			'showVenue'        => 'show_venue',
			'showCategory'     => 'show_category',
			'showOrganizers'   => 'show_organizers',
			'showOnlineLinks'  => 'show_online_links',
			'showContent'      => 'show_content',
			'showDates'        => 'show_dates',
			'showUpcoming'     => 'show_upcoming',
			'showResults'      => 'show_results',
			'showDescription'  => 'show_description',
			'showNav'          => 'show_nav',
			'className'        => 'class',
			'wrapperClass'     => 'wrapper_class',
			'submitLabel'      => 'submit_label',
			'saveLabel'        => 'save_label',
			'loginMessage'     => 'login_message',
			'successMessage'   => 'success_message',
			'publishedMessage' => 'published_message',
			'emptyLabel'       => 'empty_label',
			'editLabel'        => 'edit_label',
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
	}
}
