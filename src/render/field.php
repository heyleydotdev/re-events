<?php
/**
 * Event field renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use ReEvents\Data\Event_Repository;
use ReEvents\Data\Meta;
use ReEvents\Data\Occurrence_Repository;
use ReEvents\Data\Organizer_Repository;
use ReEvents\Data\Ticket_Repository;
use ReEvents\Data\Venue_Repository;
use ReEvents\Domain\Event;
use ReEvents\Query\Event_Query;
use ReEvents\Services\Currency;

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
	 * @param string $context `event`, `venue` or `organizer`.
	 * @return array<string, string>
	 */
	public static function fields( string $context = 'event' ): array {
		if ( 'venue' === $context ) {
			return self::venue_fields();
		}

		if ( 'organizer' === $context ) {
			return self::organizer_fields();
		}

		return self::event_fields();
	}

	/**
	 * Event fields.
	 *
	 * @return array<string, string>
	 */
	private static function event_fields(): array {
		return [
			'title'           => __( 'Title', 're-events' ),
			'permalink'       => __( 'Permalink', 're-events' ),
			'start'           => __( 'Start date and time', 're-events' ),
			'end'             => __( 'End date and time', 're-events' ),
			'start_date'      => __( 'Start date', 're-events' ),
			'end_date'        => __( 'End date', 're-events' ),
			'start_time'      => __( 'Start time', 're-events' ),
			'end_time'        => __( 'End time', 're-events' ),
			'occurrences'     => __( 'Upcoming dates', 're-events' ),
			'timezone'        => __( 'Time zone', 're-events' ),
			'status'          => __( 'Status', 're-events' ),
			'venue'           => __( 'Venue name', 're-events' ),
			'venue_address'   => __( 'Venue address', 're-events' ),
			'venue_website'   => __( 'Venue website', 're-events' ),
			'venue_map_link'  => __( 'Venue map link', 're-events' ),
			'venue_link'      => __( 'Venue permalink', 're-events' ),
			'organizer_names' => __( 'Organizer names', 're-events' ),
			'categories'      => __( 'Categories', 're-events' ),
			'tags'            => __( 'Tags', 're-events' ),
			'excerpt'         => __( 'Excerpt', 're-events' ),
			'content'         => __( 'Content', 're-events' ),
			'image'           => __( 'Featured image', 're-events' ),
			'image_url'       => __( 'Featured image URL', 're-events' ),
			'price_from'      => __( 'Lowest paid ticket price', 're-events' ),
			'is_free'         => __( 'Free event', 're-events' ),
			'seats_left'      => __( 'Seats left', 're-events' ),
			'sold_out'        => __( 'Sold out', 're-events' ),
			'online_links'    => __( 'Online links', 're-events' ),
			'calendar_links'  => __( 'Add-to-calendar links', 're-events' ),
		];
	}

	/**
	 * Venue fields.
	 *
	 * @return array<string, string>
	 */
	private static function venue_fields(): array {
		return [
			'title'       => __( 'Title', 're-events' ),
			'permalink'   => __( 'Permalink', 're-events' ),
			'content'     => __( 'Content', 're-events' ),
			'description' => __( 'Description', 're-events' ),
			'address'     => __( 'Address', 're-events' ),
			'website'     => __( 'Website', 're-events' ),
			'lat'         => __( 'Latitude', 're-events' ),
			'lng'         => __( 'Longitude', 're-events' ),
			'map_link'    => __( 'Map link', 're-events' ),
			'image'       => __( 'Featured image', 're-events' ),
			'image_url'   => __( 'Featured image URL', 're-events' ),
		];
	}

	/**
	 * Organizer fields.
	 *
	 * @return array<string, string>
	 */
	private static function organizer_fields(): array {
		return [
			'title'       => __( 'Title', 're-events' ),
			'permalink'   => __( 'Permalink', 're-events' ),
			'content'     => __( 'Content', 're-events' ),
			'description' => __( 'Description', 're-events' ),
			'types'       => __( 'Types', 're-events' ),
			'image'       => __( 'Featured image', 're-events' ),
			'image_url'   => __( 'Featured image URL', 're-events' ),
		];
	}

	/**
	 * Shortcode arguments.
	 *
	 * @return array<string, array{default:string|int|bool,description:string}>
	 */
	public static function args(): array {
		return [
			'field'          => [
				'default'     => 'title',
				'description' => __( 'One of the fields listed below.', 're-events' ),
			],
			'event'          => [
				'default'     => 0,
				'description' => __( 'Event ID. `0` uses the current post.', 're-events' ),
			],
			'label'          => [
				'default'     => '',
				'description' => __( 'Text shown before the value.', 're-events' ),
			],
			'link'           => [
				'default'     => false,
				'description' => __( 'Wrap the value in a link where the field supports one.', 're-events' ),
			],
			'separator'      => [
				'default'     => ', ',
				'description' => __( 'Separator between list values.', 're-events' ),
			],
			'empty_label'    => [
				'default'     => '',
				'description' => __( 'Text shown when the value is empty.', 're-events' ),
			],
			'date_format'    => [
				'default'     => Event_Format::date_format(),
				'description' => __( 'Date format. Defaults to the site format.', 're-events' ),
			],
			'time_format'    => [
				'default'     => Event_Format::time_format(),
				'description' => __( 'Time format. Defaults to the site format.', 're-events' ),
			],
			'class'          => [
				'default'     => '',
				'description' => __( 'Extra classes on the wrapper.', 're-events' ),
			],
			'tag'            => [
				'default'     => 'span',
				'description' => __( 'Wrapper tag: `span`, `div`, `p`, `h2`, `h3` or `h4`.', 're-events' ),
			],
			'size'           => [
				'default'     => 'large',
				'description' => __( 'Image size for `image` and `image_url`.', 're-events' ),
			],
			'venue'          => [
				'default'     => 0,
				'description' => __( 'Venue ID. `current` uses the current venue.', 're-events' ),
			],
			'organizer'      => [
				'default'     => 0,
				'description' => __( 'Organizer ID. `current` uses the current organizer.', 're-events' ),
			],
			'limit'          => [
				'default'     => 10,
				'description' => __( 'Maximum dates for the `occurrences` field.', 're-events' ),
			],
			'from'           => [
				'default'     => '',
				'description' => __( 'Earliest date for `occurrences`, Y-m-d.', 're-events' ),
			],
			'to'             => [
				'default'     => '',
				'description' => __( 'Latest date for `occurrences`, Y-m-d.', 're-events' ),
			],
			'excerpt_length' => [
				'default'     => 0,
				'description' => __( 'Excerpt word count. 0 uses the theme length.', 're-events' ),
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

		[ $context, $id ] = self::context( $args );

		if ( ! array_key_exists( $name, self::fields( $context ) ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: field name. */
					esc_html__( 'Unknown field "%s".', 're-events' ),
					esc_html( $name )
				),
				'1.0.0'
			);

			return '';
		}

		if ( $id <= 0 || ! get_post( $id ) ) {
			return '';
		}

		if ( 'publish' !== get_post_status( $id ) && ! current_user_can( 'read_post', $id ) ) {
			return '';
		}

		if ( in_array( $name, [ 'content', 'description' ], true ) && post_password_required( $id ) ) {
			return '';
		}

		if ( 'event' === $context ) {
			$event = self::event( $id );
			$value = $event ? self::event_value( $name, $event, $args ) : '';
		} elseif ( 'venue' === $context ) {
			$value = self::venue_value( $name, $id, $args );
		} else {
			$value = self::organizer_value( $name, $id, $args );
		}

		if ( '' === $value ) {
			$value = '' !== (string) $args['empty_label'] ? esc_html( (string) $args['empty_label'] ) : '';
		}

		if ( '' === $value ) {
			return '';
		}

		$tag   = in_array( $args['tag'], [ 'span', 'div', 'p', 'h2', 'h3', 'h4' ], true ) ? (string) $args['tag'] : 'span';
		$class = trim( 'reevents-field reevents-field-' . $name . ' ' . (string) $args['class'] );
		$label = '' !== (string) $args['label'] ? '<span class="reevents-field-label">' . esc_html( (string) $args['label'] ) . '</span> ' : '';

		return '<' . $tag . ' class="' . esc_attr( $class ) . '">' . $label . $value . '</' . $tag . '>';
	}

	/**
	 * Resolve the resource and ID for a field.
	 *
	 * @param array<string, mixed> $args Arguments.
	 * @return array{0:string,1:int}
	 */
	private static function context( array $args ): array {
		$venue = $args['venue'] ?? 0;

		if ( '' !== (string) $venue && '0' !== (string) $venue ) {
			return [ 'venue', 'current' === $venue ? (int) get_the_ID() : (int) $venue ];
		}

		$organizer = $args['organizer'] ?? 0;

		if ( '' !== (string) $organizer && '0' !== (string) $organizer ) {
			return [ 'organizer', 'current' === $organizer ? (int) get_the_ID() : (int) $organizer ];
		}

		$id = (int) ( $args['event'] ?? 0 );

		if ( ! $id ) {
			$id = (int) get_the_ID();
		}

		switch ( get_post_type( $id ) ) {
			case 'reevents_venue':
				return [ 'venue', $id ];
			case 'reevents_organizer':
				return [ 'organizer', $id ];
		}

		return [ 'event', $id ];
	}

	/**
	 * Build the escaped value for a field.
	 *
	 * @param string               $name  Field name.
	 * @param Event                $event Event.
	 * @param array<string, mixed> $args  Arguments.
	 */
	private static function event_value( string $name, Event $event, array $args ): string {
		$link        = ! empty( $args['link'] );
		$separator   = esc_html( (string) $args['separator'] );
		$date_format = (string) ( $args['date_format'] ?? '' );
		$time_format = (string) ( $args['time_format'] ?? '' );

		switch ( $name ) {
			case 'title':
				return self::text( $event->title, $link, (string) get_permalink( $event->id ) );

			case 'permalink':
				return esc_url( (string) get_permalink( $event->id ) );

			case 'start':
				return esc_html( Event_Format::start( $event, true, $date_format, $time_format ) );

			case 'end':
				return esc_html( Event_Format::end( $event, true, $date_format, $time_format ) );

			case 'start_date':
				return esc_html( Event_Format::start( $event, false, $date_format ) );

			case 'end_date':
				return esc_html( Event_Format::end( $event, false, $date_format ) );

			case 'start_time':
				return esc_html( Event_Format::start_time( $event, $time_format ) );

			case 'end_time':
				return esc_html( Event_Format::end_time( $event, $time_format ) );

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
				return self::terms( $event->id, 'reevents_event_category', $separator, $link );

			case 'tags':
				return self::terms( $event->id, 'reevents_event_tag', $separator, $link );

			case 'excerpt':
				$excerpt = (string) get_the_excerpt( $event->id );
				$length  = (int) ( $args['excerpt_length'] ?? 0 );

				if ( $length > 0 ) {
					$excerpt = wp_trim_words( $excerpt, $length, '…' );
				}

				return wp_kses_post( $excerpt );

			case 'content':
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core content filter.
				return wp_kses_post( (string) apply_filters( 'the_content', (string) get_post_field( 'post_content', $event->id ) ) );

			case 'image':
			case 'image_url':
				return self::image( $name, $event->id, $args, $link );

			case 'price_from':
			case 'is_free':
			case 'seats_left':
			case 'sold_out':
				return self::tickets( $name, $event );

			case 'occurrences':
				return self::occurrences( $event, $args, $separator );

			case 'venue_website':
				return $event->venue_id ? esc_url( (string) get_post_meta( $event->venue_id, '_reevents_website', true ) ) : '';

			case 'venue_map_link':
				return $event->venue_id ? esc_url( self::map_link( (string) get_post_meta( $event->venue_id, '_reevents_lat', true ), (string) get_post_meta( $event->venue_id, '_reevents_lng', true ) ) ) : '';

			case 'online_links':
				return self::links( $event->online_urls, $separator );

			case 'calendar_links':
				return self::links( Calendar_Links::links( $event ), $separator );
		}

		return '';
	}

	/**
	 * Build a venue value.
	 *
	 * @param string               $name Field name.
	 * @param int                  $id   Venue ID.
	 * @param array<string, mixed> $args Arguments.
	 */
	private static function venue_value( string $name, int $id, array $args ): string {
		$venue = ( new Venue_Repository() )->find( $id );

		if ( ! $venue ) {
			return '';
		}

		$link      = ! empty( $args['link'] );
		$separator = esc_html( (string) $args['separator'] );

		switch ( $name ) {
			case 'title':
				return self::text( (string) $venue['title'], $link, (string) $venue['permalink'] );

			case 'permalink':
				return esc_url( (string) $venue['permalink'] );

			case 'content':
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core content filter.
				return wp_kses_post( (string) apply_filters( 'the_content', (string) get_post_field( 'post_content', $id ) ) );

			case 'description':
				return esc_html( wp_strip_all_tags( (string) $venue['description'] ) );

			case 'address':
				return esc_html( (string) $venue['address'] );

			case 'website':
				return esc_url( (string) $venue['website'] );

			case 'lat':
				return esc_html( (string) $venue['lat'] );

			case 'lng':
				return esc_html( (string) $venue['lng'] );

			case 'map_link':
				return esc_url( self::map_link( (string) $venue['lat'], (string) $venue['lng'] ) );

			case 'image':
			case 'image_url':
				return self::image( $name, $id, $args, $link );
		}

		return '';
	}

	/**
	 * Build an organizer value.
	 *
	 * @param string               $name Field name.
	 * @param int                  $id   Organizer ID.
	 * @param array<string, mixed> $args Arguments.
	 */
	private static function organizer_value( string $name, int $id, array $args ): string {
		$organizer = ( new Organizer_Repository() )->find( $id );

		if ( ! $organizer ) {
			return '';
		}

		$link      = ! empty( $args['link'] );
		$separator = esc_html( (string) $args['separator'] );

		switch ( $name ) {
			case 'title':
				return self::text( (string) $organizer['title'], $link, (string) $organizer['permalink'] );

			case 'permalink':
				return esc_url( (string) $organizer['permalink'] );

			case 'content':
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core content filter.
				return wp_kses_post( (string) apply_filters( 'the_content', (string) get_post_field( 'post_content', $id ) ) );

			case 'description':
				return esc_html( wp_strip_all_tags( (string) $organizer['description'] ) );

			case 'types':
				return self::terms( $id, 'reevents_organizer_type', $separator );

			case 'image':
			case 'image_url':
				return self::image( $name, $id, $args, $link );
		}

		return '';
	}

	/**
	 * Build a map link from coordinates.
	 *
	 * @param string $lat Latitude.
	 * @param string $lng Longitude.
	 */
	public static function map_link( string $lat, string $lng ): string {
		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
			return '';
		}

		$url = add_query_arg(
			[
				'mlat' => $lat,
				'mlon' => $lng,
			],
			'https://www.openstreetmap.org/'
		);

		return $url . '#map=16/' . rawurlencode( $lat ) . '/' . rawurlencode( $lng );
	}

	/**
	 * Join upcoming scheduled dates.
	 *
	 * @param Event                $event     Event.
	 * @param array<string, mixed> $args      Arguments.
	 * @param string               $separator Separator.
	 */
	private static function occurrences( Event $event, array $args, string $separator ): string {
		$rows        = ( new Occurrence_Repository() )->get_all_for_event( $event->id );
		$now         = current_time( 'mysql', true );
		$limit       = max( 1, min( 50, (int) ( $args['limit'] ?? 10 ) ) );
		$date_format = (string) ( $args['date_format'] ?? '' );
		$time_format = (string) ( $args['time_format'] ?? '' );
		$from        = Meta::sanitize_date( (string) ( $args['from'] ?? '' ) );
		$to          = Meta::sanitize_date( (string) ( $args['to'] ?? '' ) );
		$from_utc    = '' !== $from ? Event_Query::utc_bound( $from, '00:00:00' ) : '';
		$to_utc      = '' !== $to ? Event_Query::utc_bound( $to, '23:59:59' ) : '';
		$dates       = [];

		foreach ( $rows as $row ) {
			$status = (string) ( $row['status'] ?? '' );
			$start  = (string) ( $row['start_utc'] ?? '' );

			if ( ( '' !== $status && 'scheduled' !== $status ) || $start < $now ) {
				continue;
			}

			if ( '' !== $from_utc && $start < $from_utc ) {
				continue;
			}

			if ( '' !== $to_utc && $start > $to_utc ) {
				break;
			}

			$occurrence = new Event( 0, '', $start, (string) ( $row['end_utc'] ?? '' ), $event->timezone, $event->all_day );
			$label      = Event_Format::start( $occurrence, true, $date_format, $time_format );

			if ( '' !== $label ) {
				$dates[] = esc_html( $label );
			}

			if ( count( $dates ) >= $limit ) {
				break;
			}
		}

		return implode( $separator, $dates );
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
	 * @param int    $post_id   Post ID.
	 * @param string $taxonomy  Taxonomy.
	 * @param string $separator Separator.
	 * @param bool   $link      Link each term to its archive.
	 */
	private static function terms( int $post_id, string $taxonomy, string $separator, bool $link = false ): string {
		$terms = get_the_terms( $post_id, $taxonomy );
		$names = [];

		foreach ( is_array( $terms ) ? $terms : [] as $term ) {
			$name = esc_html( $term->name );
			$url  = $link ? get_term_link( $term ) : '';

			$names[] = $link && ! is_wp_error( $url ) && '' !== $url ? '<a href="' . esc_url( $url ) . '">' . $name . '</a>' : $name;
		}

		return implode( $separator, $names );
	}

	/**
	 * Render the featured image.
	 *
	 * @param string               $name    Field name.
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $args    Arguments.
	 * @param bool                 $link    Wrap in the post link.
	 */
	private static function image( string $name, int $post_id, array $args, bool $link ): string {
		$size = (string) ( $args['size'] ?? 'large' );
		$url  = (string) get_the_post_thumbnail_url( $post_id, $size );

		if ( '' === $url ) {
			return '';
		}

		if ( 'image_url' === $name ) {
			return esc_url( $url );
		}

		$image = get_the_post_thumbnail( $post_id, $size, [ 'class' => 'reevents-field-image' ] );

		if ( '' === $image ) {
			return '';
		}

		return $link ? '<a href="' . esc_url( (string) get_permalink( $post_id ) ) . '">' . $image . '</a>' : $image;
	}

	/**
	 * Render a ticket summary value.
	 *
	 * @param string $name  Field name.
	 * @param Event  $event Event.
	 */
	private static function tickets( string $name, Event $event ): string {
		$summary = Ticket_Summary::from_tickets( ( new Ticket_Repository() )->for_event( $event->id, 'active' ) );

		switch ( $name ) {
			case 'price_from':
				return null === $summary['price_from'] ? '' : esc_html( Currency::format( $summary['price_from'], $summary['currency'] ) );

			case 'is_free':
				return $summary['is_free'] ? '1' : '';

			case 'seats_left':
				return null === $summary['seats_left'] ? '' : esc_html( (string) $summary['seats_left'] );

			case 'sold_out':
				return $summary['sold_out'] ? '1' : '';
		}

		return '';
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
