<?php
/**
 * Event field renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use ReEvents\Data\Event_Repository;
use ReEvents\Domain\Event;

/**
 * Prints one event value for custom layouts.
 */
final class Field {

	/**
	 * Events hydrated during this request.
	 *
	 * @var array<int, Event|null>
	 */
	private static array $events = [];

	/**
	 * Available fields.
	 *
	 * @return array<string, string>
	 */
	public static function fields(): array {
		return [
			'title'           => __( 'Title', 're-events' ),
			'permalink'       => __( 'Permalink', 're-events' ),
			'start'           => __( 'Start date and time', 're-events' ),
			'end'             => __( 'End date and time', 're-events' ),
			'start_date'      => __( 'Start date', 're-events' ),
			'end_date'        => __( 'End date', 're-events' ),
			'start_time'      => __( 'Start time', 're-events' ),
			'end_time'        => __( 'End time', 're-events' ),
			'timezone'        => __( 'Time zone', 're-events' ),
			'status'          => __( 'Status', 're-events' ),
			'venue'           => __( 'Venue name', 're-events' ),
			'venue_address'   => __( 'Venue address', 're-events' ),
			'venue_link'      => __( 'Venue permalink', 're-events' ),
			'organizer_names' => __( 'Organizer names', 're-events' ),
			'categories'      => __( 'Categories', 're-events' ),
			'tags'            => __( 'Tags', 're-events' ),
			'excerpt'         => __( 'Excerpt', 're-events' ),
			'content'         => __( 'Content', 're-events' ),
			'online_links'    => __( 'Online links', 're-events' ),
			'calendar_links'  => __( 'Add-to-calendar links', 're-events' ),
		];
	}

	/**
	 * Shortcode arguments.
	 *
	 * @return array<string, array{default:string|int|bool,description:string}>
	 */
	public static function args(): array {
		return [
			'field'       => [
				'default'     => 'title',
				'description' => __( 'One of the fields listed below.', 're-events' ),
			],
			'event'       => [
				'default'     => 0,
				'description' => __( 'Event ID. `0` uses the current post.', 're-events' ),
			],
			'label'       => [
				'default'     => '',
				'description' => __( 'Text shown before the value.', 're-events' ),
			],
			'link'        => [
				'default'     => false,
				'description' => __( 'Wrap the value in a link where the field supports one.', 're-events' ),
			],
			'separator'   => [
				'default'     => ', ',
				'description' => __( 'Separator between list values.', 're-events' ),
			],
			'empty_label' => [
				'default'     => '',
				'description' => __( 'Text shown when the value is empty.', 're-events' ),
			],
			'class'       => [
				'default'     => '',
				'description' => __( 'Extra classes on the wrapper.', 're-events' ),
			],
			'tag'         => [
				'default'     => 'span',
				'description' => __( 'Wrapper tag: `span`, `div` or `p`.', 're-events' ),
			],
		];
	}

	/**
	 * Shortcode argument defaults.
	 *
	 * @return array<string, string|int|bool>
	 */
	private static function defaults(): array {
		$defaults = [];

		foreach ( self::args() as $key => $arg ) {
			$defaults[ $key ] = $arg['default'];
		}

		return $defaults;
	}

	/**
	 * Render a field.
	 *
	 * @param array<string, mixed> $args Shortcode arguments.
	 */
	public static function render( array $args ): string {
		$args = wp_parse_args( $args, self::defaults() );

		$name = sanitize_key( (string) $args['field'] );

		if ( ! array_key_exists( $name, self::fields() ) ) {
			return '';
		}

		$id = (int) $args['event'];

		if ( ! $id ) {
			$id = (int) get_the_ID();
		}

		$event = self::event( $id );

		if ( ! $event || ( 'publish' !== get_post_status( $id ) && ! current_user_can( 'read_post', $id ) ) ) {
			return '';
		}

		if ( 'content' === $name && post_password_required( $id ) ) {
			return '';
		}

		$value = self::value( $name, $event, $args );

		if ( '' === $value ) {
			$value = '' !== (string) $args['empty_label'] ? esc_html( (string) $args['empty_label'] ) : '';
		}

		if ( '' === $value ) {
			return '';
		}

		$tag   = in_array( $args['tag'], [ 'span', 'div', 'p' ], true ) ? (string) $args['tag'] : 'span';
		$class = trim( 'reevents-field reevents-field-' . $name . ' ' . (string) $args['class'] );
		$label = '' !== (string) $args['label'] ? '<span class="reevents-field-label">' . esc_html( (string) $args['label'] ) . '</span> ' : '';

		return '<' . $tag . ' class="' . esc_attr( $class ) . '">' . $label . $value . '</' . $tag . '>';
	}

	/**
	 * Build the escaped value for a field.
	 *
	 * @param string               $name  Field name.
	 * @param Event                $event Event.
	 * @param array<string, mixed> $args  Arguments.
	 */
	private static function value( string $name, Event $event, array $args ): string {
		$link      = ! empty( $args['link'] );
		$separator = esc_html( (string) $args['separator'] );

		switch ( $name ) {
			case 'title':
				return self::text( $event->title, $link, (string) get_permalink( $event->id ) );

			case 'permalink':
				return esc_url( (string) get_permalink( $event->id ) );

			case 'start':
				return esc_html( Event_Format::start( $event ) );

			case 'end':
				return esc_html( Event_Format::end( $event ) );

			case 'start_date':
				return esc_html( Event_Format::start( $event, false ) );

			case 'end_date':
				return esc_html( Event_Format::end( $event, false ) );

			case 'start_time':
				return esc_html( Event_Format::start_time( $event ) );

			case 'end_time':
				return esc_html( Event_Format::end_time( $event ) );

			case 'timezone':
				return esc_html( $event->timezone );

			case 'status':
				return esc_html( $event->status );

			case 'venue':
				$venue_id = (int) $event->venue_id;

				return $venue_id ? self::text( (string) get_the_title( $venue_id ), $link, (string) get_permalink( $venue_id ) ) : '';

			case 'venue_address':
				return $event->venue_id ? esc_html( (string) get_post_meta( $event->venue_id, '_reevents_address', true ) ) : '';

			case 'venue_link':
				return $event->venue_id ? esc_url( (string) get_permalink( $event->venue_id ) ) : '';

			case 'organizer_names':
				return self::names( $event->organizer_ids, $separator );

			case 'categories':
				return self::terms( $event->id, 'reevents_event_category', $separator );

			case 'tags':
				return self::terms( $event->id, 'reevents_event_tag', $separator );

			case 'excerpt':
				return wp_kses_post( (string) get_the_excerpt( $event->id ) );

			case 'content':
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core content filter.
				return wp_kses_post( (string) apply_filters( 'the_content', (string) get_post_field( 'post_content', $event->id ) ) );

			case 'online_links':
				return self::links( $event->online_urls, $separator );

			case 'calendar_links':
				return self::links( Calendar_Links::links( $event ), $separator );
		}

		return '';
	}

	/**
	 * Escape a value, optionally linked.
	 *
	 * @param string $value Value.
	 * @param bool   $link  Wrap in a link.
	 * @param string $url   Link target.
	 */
	private static function text( string $value, bool $link, string $url ): string {
		if ( '' === $value ) {
			return '';
		}

		return $link && '' !== $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $value ) . '</a>' : esc_html( $value );
	}

	/**
	 * Join post titles.
	 *
	 * @param array<int, int> $ids       Post IDs.
	 * @param string          $separator Separator.
	 */
	private static function names( array $ids, string $separator ): string {
		$names = [];

		foreach ( $ids as $id ) {
			$title = (string) get_the_title( (int) $id );

			if ( '' !== $title ) {
				$names[] = esc_html( $title );
			}
		}

		return implode( $separator, $names );
	}

	/**
	 * Join term names.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @param string $separator Separator.
	 */
	private static function terms( int $post_id, string $taxonomy, string $separator ): string {
		$terms = get_the_terms( $post_id, $taxonomy );
		$names = [];

		foreach ( is_array( $terms ) ? $terms : [] as $term ) {
			$names[] = esc_html( $term->name );
		}

		return implode( $separator, $names );
	}

	/**
	 * Join link lists.
	 *
	 * @param array<int, array{label?:string, url?:string}> $links     Links.
	 * @param string                                        $separator Separator.
	 */
	private static function links( array $links, string $separator ): string {
		$items = [];

		foreach ( $links as $link ) {
			$url = (string) ( $link['url'] ?? '' );

			if ( '' === $url ) {
				continue;
			}

			$label   = '' !== (string) ( $link['label'] ?? '' ) ? (string) $link['label'] : $url;
			$items[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}

		return implode( $separator, $items );
	}

	/**
	 * Read an event once per request.
	 *
	 * @param int $id Event ID.
	 */
	private static function event( int $id ): ?Event {
		if ( ! array_key_exists( $id, self::$events ) ) {
			self::$events[ $id ] = $id > 0 ? ( new Event_Repository() )->find( $id ) : null;
		}

		return self::$events[ $id ];
	}
}
