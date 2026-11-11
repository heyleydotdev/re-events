<?php
/**
 * Public PHP CRUD API.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

use ReEvents\API\Mapper;
use ReEvents\Admin\Settings;
use ReEvents\Data\Booking_Repository;
use ReEvents\Data\Event_Repository;
use ReEvents\Data\Meta;
use ReEvents\Data\Occurrence_Repository;
use ReEvents\Data\Organizer_Repository;
use ReEvents\Data\Ticket_Repository;
use ReEvents\Data\Venue_Repository;
use ReEvents\Domain\Event;
use ReEvents\Query\Event_Query;
use ReEvents\Services\Booking_Service;
use ReEvents\Services\Booking_Validator;
use ReEvents\Services\Event_Service;
use ReEvents\Services\Occurrence_Service;
use ReEvents\Services\Payment_Service;

if ( ! defined( 'RE_EVENTS_API_VERSION' ) ) {
	define( 'RE_EVENTS_API_VERSION', '1.0.0' );
}

/**
 * Return the public API version.
 *
 * @since 1.0.0
 */
function reevents_api_version(): string {
	return RE_EVENTS_API_VERSION;
}

/*
 * Events.
 */

/**
 * Read one event.
 *
 * @since 1.0.0
 *
 * @param int $event_id Event ID.
 * @return array<string, mixed>|WP_Error
 */
function reevents_get_event( int $event_id ) {
	$event = ( new Event_Repository() )->find( $event_id );

	if ( ! $event ) {
		return reevents_api_error( 'reevents_event_not_found', __( 'Event not found.', 're-events' ), 404 );
	}

	if ( ! reevents_api_can_read_post( $event_id ) ) {
		return reevents_api_error( 'reevents_event_forbidden', __( 'You are not allowed to read this event.', 're-events' ), 403 );
	}

	return reevents_api_event_data( $event );
}

/**
 * Read published events.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $args Arguments accepted by Event_Query.
 * @return array<int, array<string, mixed>>
 */
function reevents_get_events( array $args = [] ): array {
	$result = Event_Query::query( $args );
	$items  = [];

	foreach ( ( new Event_Repository() )->find_many( $result['ids'] ) as $event ) {
		$items[] = reevents_api_event_data( $event );
	}

	return $items;
}

/**
 * Create an event.
 *
 * Requires `publish_reevents_events`.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $data Event fields.
 * @return array<string, mixed>|WP_Error
 */
function reevents_create_event( array $data ) {
	if ( ! current_user_can( 'publish_reevents_events' ) ) {
		return reevents_api_error( 'reevents_event_forbidden', __( 'You are not allowed to create events.', 're-events' ), 403 );
	}

	$data['id'] = 0;

	$result = Event_Service::save( Mapper::event( $data ) );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$event = ( new Event_Repository() )->find( (int) $result );

	return $event ? reevents_api_event_data( $event ) : reevents_api_error( 'reevents_event_not_found', __( 'Event not found.', 're-events' ), 404 );
}

/**
 * Update an event.
 *
 * Requires edit access to the event.
 *
 * @since 1.0.0
 *
 * @param int                  $event_id Event ID.
 * @param array<string, mixed> $data     Event fields.
 * @return array<string, mixed>|WP_Error
 */
function reevents_update_event( int $event_id, array $data ) {
	$event = ( new Event_Repository() )->find( $event_id );

	if ( ! $event ) {
		return reevents_api_error( 'reevents_event_not_found', __( 'Event not found.', 're-events' ), 404 );
	}

	if ( ! current_user_can( 'edit_post', $event_id ) ) {
		return reevents_api_error( 'reevents_event_forbidden', __( 'You are not allowed to edit this event.', 're-events' ), 403 );
	}

	$result = Event_Service::save( Mapper::event( array_merge( $data, [ 'id' => $event_id ] ), $event ) );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return reevents_get_event( $event_id );
}

/**
 * Delete an event.
 *
 * Requires delete access to the event.
 *
 * @since 1.0.0
 *
 * @param int $event_id Event ID.
 * @return array<string, mixed>|WP_Error
 */
function reevents_delete_event( int $event_id ) {
	$previous = reevents_get_event( $event_id );

	if ( is_wp_error( $previous ) ) {
		return $previous;
	}

	if ( ! current_user_can( 'delete_post', $event_id ) ) {
		return reevents_api_error( 'reevents_event_forbidden', __( 'You are not allowed to delete this event.', 're-events' ), 403 );
	}

	if ( ! ( new Event_Repository() )->delete( $event_id ) ) {
		return reevents_api_error( 'reevents_event_delete_blocked', __( 'This event has bookings and cannot be deleted.', 're-events' ), 409 );
	}

	return [
		'deleted'  => true,
		'previous' => $previous,
	];
}

/**
 * Read stored occurrences for an event.
 *
 * @since 1.0.0
 *
 * @param int                  $event_id Event ID.
 * @param array<string, mixed> $args     `limit` and `offset`.
 * @return array<int, array<string, mixed>>|WP_Error
 */
function reevents_get_event_occurrences( int $event_id, array $args = [] ) {
	$event = reevents_get_event( $event_id );

	if ( is_wp_error( $event ) ) {
		return $event;
	}

	$limit  = max( 1, (int) ( $args['limit'] ?? 100 ) );
	$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );

	return ( new Occurrence_Repository() )->get_for_event( $event_id, $limit, $offset );
}

/**
 * Rebuild stored occurrences for an event.
 *
 * Requires edit access to the event.
 *
 * @since 1.0.0
 *
 * @param int $event_id Event ID.
 * @return array<int, array<string, mixed>>|WP_Error
 */
function reevents_sync_event_occurrences( int $event_id ) {
	if ( ! current_user_can( 'edit_post', $event_id ) ) {
		return reevents_api_error( 'reevents_event_forbidden', __( 'You are not allowed to edit this event.', 're-events' ), 403 );
	}

	if ( ! Occurrence_Service::sync( $event_id ) ) {
		return reevents_api_error( 'reevents_occurrence_sync_failed', __( 'Occurrences could not be rebuilt.', 're-events' ), 500 );
	}

	return reevents_get_event_occurrences( $event_id, [ 'limit' => 5000 ] );
}

/*
 * Venues.
 */

/**
 * Read one venue.
 *
 * @since 1.0.0
 *
 * @param int $venue_id Venue ID.
 * @return array<string, mixed>|WP_Error
 */
function reevents_get_venue( int $venue_id ) {
	$venue = ( new Venue_Repository() )->find( $venue_id );

	if ( ! $venue ) {
		return reevents_api_error( 'reevents_venue_not_found', __( 'Venue not found.', 're-events' ), 404 );
	}

	if ( ! reevents_api_can_read_post( $venue_id ) ) {
		return reevents_api_error( 'reevents_venue_forbidden', __( 'You are not allowed to read this venue.', 're-events' ), 403 );
	}

	return $venue;
}

/**
 * Read published venues.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $args Arguments accepted by Venue_Repository::query().
 * @return array<int, array<string, mixed>>
 */
function reevents_get_venues( array $args = [] ): array {
	return ( new Venue_Repository() )->query( $args )['items'];
}

/**
 * Create a venue.
 *
 * Requires `publish_reevents_venues`.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $data Venue fields.
 * @return array<string, mixed>|WP_Error
 */
function reevents_create_venue( array $data ) {
	if ( ! current_user_can( 'publish_reevents_venues' ) ) {
		return reevents_api_error( 'reevents_venue_forbidden', __( 'You are not allowed to create venues.', 're-events' ), 403 );
	}

	$error = reevents_api_validate_venue( $data );

	if ( is_wp_error( $error ) ) {
		return $error;
	}

	$data['id'] = 0;

	$result = ( new Venue_Repository() )->save( Mapper::venue( $data ) );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$venue = ( new Venue_Repository() )->find( (int) $result );

	return $venue ?? reevents_api_error( 'reevents_venue_not_found', __( 'Venue not found.', 're-events' ), 404 );
}

/**
 * Update a venue.
 *
 * Requires edit access to the venue.
 *
 * @since 1.0.0
 *
 * @param int                  $venue_id Venue ID.
 * @param array<string, mixed> $data     Venue fields.
 * @return array<string, mixed>|WP_Error
 */
function reevents_update_venue( int $venue_id, array $data ) {
	$venue = ( new Venue_Repository() )->find( $venue_id );

	if ( ! $venue ) {
		return reevents_api_error( 'reevents_venue_not_found', __( 'Venue not found.', 're-events' ), 404 );
	}

	if ( ! current_user_can( 'edit_post', $venue_id ) ) {
		return reevents_api_error( 'reevents_venue_forbidden', __( 'You are not allowed to edit this venue.', 're-events' ), 403 );
	}

	$error = reevents_api_validate_venue( $data );

	if ( is_wp_error( $error ) ) {
		return $error;
	}

	$result = ( new Venue_Repository() )->save( Mapper::venue( array_merge( $data, [ 'id' => $venue_id ] ), $venue ) );

	return is_wp_error( $result ) ? $result : reevents_get_venue( $venue_id );
}

/**
 * Delete a venue.
 *
 * Requires delete access to the venue.
 *
 * @since 1.0.0
 *
 * @param int $venue_id Venue ID.
 * @return array<string, mixed>|WP_Error
 */
function reevents_delete_venue( int $venue_id ) {
	$previous = reevents_get_venue( $venue_id );

	if ( is_wp_error( $previous ) ) {
		return $previous;
	}

	if ( ! current_user_can( 'delete_post', $venue_id ) ) {
		return reevents_api_error( 'reevents_venue_forbidden', __( 'You are not allowed to delete this venue.', 're-events' ), 403 );
	}

	if ( ! ( new Venue_Repository() )->delete( $venue_id ) ) {
		return reevents_api_error( 'reevents_venue_delete_failed', __( 'The venue could not be deleted.', 're-events' ), 500 );
	}

	return [
		'deleted'  => true,
		'previous' => $previous,
	];
}

/*
 * Organizers.
 */

/**
 * Read one organizer.
 *
 * @since 1.0.0
 *
 * @param int $organizer_id Organizer ID.
 * @return array<string, mixed>|WP_Error
 */
function reevents_get_organizer( int $organizer_id ) {
	$organizer = ( new Organizer_Repository() )->find( $organizer_id );

	if ( ! $organizer ) {
		return reevents_api_error( 'reevents_organizer_not_found', __( 'Organizer not found.', 're-events' ), 404 );
	}

	if ( ! reevents_api_can_read_post( $organizer_id ) ) {
		return reevents_api_error( 'reevents_organizer_forbidden', __( 'You are not allowed to read this organizer.', 're-events' ), 403 );
	}

	return $organizer;
}

/**
 * Read published organizers.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $args Arguments accepted by Organizer_Repository::query().
 * @return array<int, array<string, mixed>>
 */
function reevents_get_organizers( array $args = [] ): array {
	return ( new Organizer_Repository() )->query( $args )['items'];
}

/**
 * Create an organizer.
 *
 * Requires `publish_reevents_organizers`.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $data Organizer fields.
 * @return array<string, mixed>|WP_Error
 */
function reevents_create_organizer( array $data ) {
	if ( ! current_user_can( 'publish_reevents_organizers' ) ) {
		return reevents_api_error( 'reevents_organizer_forbidden', __( 'You are not allowed to create organizers.', 're-events' ), 403 );
	}

	if ( '' === trim( (string) ( $data['title'] ?? '' ) ) ) {
		return reevents_api_error( 'reevents_organizer_invalid_title', __( 'An organizer title is required.', 're-events' ), 400 );
	}

	$data['id'] = 0;

	$result = ( new Organizer_Repository() )->save( Mapper::organizer( $data ) );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$organizer = ( new Organizer_Repository() )->find( (int) $result );

	return $organizer ?? reevents_api_error( 'reevents_organizer_not_found', __( 'Organizer not found.', 're-events' ), 404 );
}

/**
 * Update an organizer.
 *
 * Requires edit access to the organizer.
 *
 * @since 1.0.0
 *
 * @param int                  $organizer_id Organizer ID.
 * @param array<string, mixed> $data         Organizer fields.
 * @return array<string, mixed>|WP_Error
 */
function reevents_update_organizer( int $organizer_id, array $data ) {
	$organizer = ( new Organizer_Repository() )->find( $organizer_id );

	if ( ! $organizer ) {
		return reevents_api_error( 'reevents_organizer_not_found', __( 'Organizer not found.', 're-events' ), 404 );
	}

	if ( ! current_user_can( 'edit_post', $organizer_id ) ) {
		return reevents_api_error( 'reevents_organizer_forbidden', __( 'You are not allowed to edit this organizer.', 're-events' ), 403 );
	}

	if ( array_key_exists( 'title', $data ) && '' === trim( (string) $data['title'] ) ) {
		return reevents_api_error( 'reevents_organizer_invalid_title', __( 'An organizer title is required.', 're-events' ), 400 );
	}

	$result = ( new Organizer_Repository() )->save( Mapper::organizer( array_merge( $data, [ 'id' => $organizer_id ] ), $organizer ) );

	return is_wp_error( $result ) ? $result : reevents_get_organizer( $organizer_id );
}

/**
 * Delete an organizer.
 *
 * Requires delete access to the organizer.
 *
 * @since 1.0.0
 *
 * @param int $organizer_id Organizer ID.
 * @return array<string, mixed>|WP_Error
 */
function reevents_delete_organizer( int $organizer_id ) {
	$previous = reevents_get_organizer( $organizer_id );

	if ( is_wp_error( $previous ) ) {
		return $previous;
	}

	if ( ! current_user_can( 'delete_post', $organizer_id ) ) {
		return reevents_api_error( 'reevents_organizer_forbidden', __( 'You are not allowed to delete this organizer.', 're-events' ), 403 );
	}

	if ( ! ( new Organizer_Repository() )->delete( $organizer_id ) ) {
		return reevents_api_error( 'reevents_organizer_delete_failed', __( 'The organizer could not be deleted.', 're-events' ), 500 );
	}

	return [
		'deleted'  => true,
		'previous' => $previous,
	];
}

/*
 * Categories.
 */

/**
 * Read event categories.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $args `search`, `per_page`, `page`.
 * @return array<int, array<string, mixed>>
 */
function reevents_get_categories( array $args = [] ): array {
	$terms = get_terms(
		[
			'taxonomy'   => 'reevents_event_category',
			'hide_empty' => false,
			'search'     => isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '',
			'number'     => min( 100, max( 1, (int) ( $args['per_page'] ?? 100 ) ) ),
			'offset'     => max( 0, (int) ( ( (int) ( $args['page'] ?? 1 ) - 1 ) * (int) ( $args['per_page'] ?? 100 ) ) ),
		]
	);

	if ( is_wp_error( $terms ) ) {
		return [];
	}

	return array_map( 'reevents_api_category_data', $terms );
}

/**
 * Read one event category.
 *
 * @since 1.0.0
 *
 * @param int $category_id Category term ID.
 * @return array<string, mixed>|WP_Error
 */
function reevents_get_category( int $category_id ) {
	$term = get_term( $category_id, 'reevents_event_category' );

	return $term instanceof WP_Term ? reevents_api_category_data( $term ) : reevents_api_error( 'reevents_category_not_found', __( 'Category not found.', 're-events' ), 404 );
}

/**
 * Create an event category.
 *
 * Requires `edit_reevents_events`.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $data Category fields.
 * @return array<string, mixed>|WP_Error
 */
function reevents_create_category( array $data ) {
	if ( ! current_user_can( 'edit_reevents_events' ) ) {
		return reevents_api_error( 'reevents_category_forbidden', __( 'You are not allowed to create categories.', 're-events' ), 403 );
	}

	if ( '' === trim( (string) ( $data['name'] ?? '' ) ) ) {
		return reevents_api_error( 'reevents_category_invalid_name', __( 'A category name is required.', 're-events' ), 400 );
	}

	$result = wp_insert_term(
		sanitize_text_field( (string) $data['name'] ),
		'reevents_event_category',
		[
			'description' => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
			'parent'      => absint( $data['parent'] ?? 0 ),
		]
	);

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return reevents_get_category( (int) $result['term_id'] );
}

/**
 * Update an event category.
 *
 * Requires `edit_reevents_events`.
 *
 * @since 1.0.0
 *
 * @param int                  $category_id Category term ID.
 * @param array<string, mixed> $data        Category fields.
 * @return array<string, mixed>|WP_Error
 */
function reevents_update_category( int $category_id, array $data ) {
	if ( ! current_user_can( 'edit_reevents_events' ) ) {
		return reevents_api_error( 'reevents_category_forbidden', __( 'You are not allowed to update categories.', 're-events' ), 403 );
	}

	$term = get_term( $category_id, 'reevents_event_category' );

	if ( ! $term instanceof WP_Term ) {
		return reevents_api_error( 'reevents_category_not_found', __( 'Category not found.', 're-events' ), 404 );
	}

	$fields = [];

	if ( array_key_exists( 'name', $data ) ) {
		$fields['name'] = sanitize_text_field( (string) $data['name'] );
	}

	if ( array_key_exists( 'description', $data ) ) {
		$fields['description'] = sanitize_textarea_field( (string) $data['description'] );
	}

	if ( array_key_exists( 'parent', $data ) ) {
		$fields['parent'] = absint( $data['parent'] );
	}

	$result = wp_update_term( $category_id, 'reevents_event_category', $fields );

	return is_wp_error( $result ) ? $result : reevents_get_category( $category_id );
}

/**
 * Delete an event category.
 *
 * Requires `edit_reevents_events`.
 *
 * @since 1.0.0
 *
 * @param int $category_id Category term ID.
 * @return array<string, mixed>|WP_Error
 */
function reevents_delete_category( int $category_id ) {
	if ( ! current_user_can( 'edit_reevents_events' ) ) {
		return reevents_api_error( 'reevents_category_forbidden', __( 'You are not allowed to delete categories.', 're-events' ), 403 );
	}

	$previous = reevents_get_category( $category_id );

	if ( is_wp_error( $previous ) ) {
		return $previous;
	}

	$result = wp_delete_term( $category_id, 'reevents_event_category' );

	if ( is_wp_error( $result ) || ! $result ) {
		return reevents_api_error( 'reevents_category_delete_failed', __( 'The category could not be deleted.', 're-events' ), 500 );
	}

	return [
		'deleted'  => true,
		'previous' => $previous,
	];
}

/*
 * Tickets.
 */

/**
 * Read one ticket.
 *
 * @since 1.0.0
 *
 * @param int $ticket_id Ticket ID.
 * @return array<string, mixed>|WP_Error
 */
function reevents_get_ticket( int $ticket_id ) {
	$ticket = ( new Ticket_Repository() )->find( $ticket_id );

	if ( ! $ticket ) {
		return reevents_api_error( 'reevents_ticket_not_found', __( 'Ticket not found.', 're-events' ), 404 );
	}

	$event_id = (int) $ticket['event_id'];

	if ( ! reevents_api_can_read_post( $event_id ) || ( 'active' !== (string) $ticket['status'] && ! current_user_can( 'edit_post', $event_id ) ) ) {
		return reevents_api_error( 'reevents_ticket_forbidden', __( 'You are not allowed to read this ticket.', 're-events' ), 403 );
	}

	return $ticket;
}

/**
 * Read tickets for an event.
 *
 * @since 1.0.0
 *
 * @param int    $event_id Event ID.
 * @param string $status   Status for users who may edit the event.
 * @return array<int, array<string, mixed>>
 */
function reevents_get_event_tickets( int $event_id, string $status = 'active' ): array {
	if ( ! reevents_api_can_read_post( $event_id ) ) {
		return [];
	}

	if ( ! current_user_can( 'edit_post', $event_id ) ) {
		$status = 'active';
	}

	return ( new Ticket_Repository() )->for_event( $event_id, $status );
}

/**
 * Create a ticket.
 *
 * Requires edit access to the event or `reevents_manage_bookings`.
 *
 * @since 1.0.0
 *
 * @param int                  $event_id Event ID.
 * @param array<string, mixed> $data     Ticket fields.
 * @return array<string, mixed>|WP_Error
 */
function reevents_create_ticket( int $event_id, array $data ) {
	if ( 'reevents_event' !== get_post_type( $event_id ) ) {
		return reevents_api_error( 'reevents_event_not_found', __( 'Event not found.', 're-events' ), 404 );
	}

	if ( ! reevents_api_can_manage_event( $event_id ) ) {
		return reevents_api_error( 'reevents_ticket_forbidden', __( 'You are not allowed to manage tickets.', 're-events' ), 403 );
	}

	if ( '' === trim( (string) ( $data['name'] ?? '' ) ) ) {
		return reevents_api_error( 'reevents_ticket_invalid_name', __( 'A ticket name is required.', 're-events' ), 400 );
	}

	$data['id'] = 0;

	$result = ( new Ticket_Repository() )->save( Mapper::ticket( $event_id, $data ) );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$ticket = ( new Ticket_Repository() )->find( (int) $result );

	return $ticket ?? reevents_api_error( 'reevents_ticket_not_found', __( 'Ticket not found.', 're-events' ), 404 );
}

/**
 * Update a ticket.
 *
 * Requires edit access to the event or `reevents_manage_bookings`.
 *
 * @since 1.0.0
 *
 * @param int                  $ticket_id Ticket ID.
 * @param array<string, mixed> $data      Ticket fields.
 * @return array<string, mixed>|WP_Error
 */
function reevents_update_ticket( int $ticket_id, array $data ) {
	$ticket = ( new Ticket_Repository() )->find( $ticket_id );

	if ( ! $ticket ) {
		return reevents_api_error( 'reevents_ticket_not_found', __( 'Ticket not found.', 're-events' ), 404 );
	}

	$event_id = (int) $ticket['event_id'];

	if ( ! reevents_api_can_manage_event( $event_id ) ) {
		return reevents_api_error( 'reevents_ticket_forbidden', __( 'You are not allowed to manage tickets.', 're-events' ), 403 );
	}

	$result = ( new Ticket_Repository() )->save( Mapper::ticket( $event_id, array_merge( $data, [ 'id' => $ticket_id ] ), $ticket ) );

	return is_wp_error( $result ) ? $result : reevents_get_ticket( $ticket_id );
}

/**
 * Delete a ticket.
 *
 * Requires edit access to the event or `reevents_manage_bookings`.
 *
 * @since 1.0.0
 *
 * @param int $ticket_id Ticket ID.
 * @return array<string, mixed>|WP_Error
 */
function reevents_delete_ticket( int $ticket_id ) {
	$previous = reevents_get_ticket( $ticket_id );

	if ( is_wp_error( $previous ) ) {
		return $previous;
	}

	$event_id = (int) $previous['event_id'];

	if ( ! reevents_api_can_manage_event( $event_id ) ) {
		return reevents_api_error( 'reevents_ticket_forbidden', __( 'You are not allowed to manage tickets.', 're-events' ), 403 );
	}

	if ( ! ( new Ticket_Repository() )->delete( $ticket_id ) ) {
		return reevents_api_error( 'reevents_ticket_delete_failed', __( 'The ticket could not be deleted.', 're-events' ), 500 );
	}

	return [
		'deleted'  => true,
		'previous' => $previous,
	];
}

/*
 * Bookings.
 */

/**
 * Read bookings for an event.
 *
 * Requires edit access to the event or `reevents_manage_bookings`.
 *
 * @since 1.0.0
 *
 * @param int                  $event_id Event ID.
 * @param array<string, mixed> $args     `status`, `per_page`, `page`.
 * @return array<int, array<string, mixed>>|WP_Error
 */
function reevents_get_event_bookings( int $event_id, array $args = [] ) {
	if ( ! reevents_api_can_manage_event( $event_id ) ) {
		return reevents_api_error( 'reevents_booking_forbidden', __( 'You are not allowed to read bookings.', 're-events' ), 403 );
	}

	$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
	$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
	$result   = ( new Booking_Repository() )->query(
		[
			'event_id' => $event_id,
			'status'   => isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '',
			'limit'    => $per_page,
			'offset'   => ( $page - 1 ) * $per_page,
		]
	);

	$items = [];

	foreach ( $result['items'] as $booking ) {
		$items[] = reevents_api_booking_data( $booking );
	}

	return $items;
}

/**
 * Read one booking.
 *
 * Requires edit access to the event or `reevents_manage_bookings`.
 *
 * @since 1.0.0
 *
 * @param int $booking_id Booking ID.
 * @return array<string, mixed>|WP_Error
 */
function reevents_get_booking( int $booking_id ) {
	$booking = ( new Booking_Repository() )->find( $booking_id );

	if ( ! $booking ) {
		return reevents_api_error( 'reevents_booking_not_found', __( 'Booking not found.', 're-events' ), 404 );
	}

	if ( ! reevents_api_can_read_booking( $booking ) ) {
		return reevents_api_error( 'reevents_booking_forbidden', __( 'You are not allowed to read this booking.', 're-events' ), 403 );
	}

	return reevents_api_booking_data( $booking );
}

/**
 * Read attendees for a booking.
 *
 * Requires edit access to the event or `reevents_manage_bookings`.
 *
 * @since 1.0.0
 *
 * @param int $booking_id Booking ID.
 * @return array<int, array<string, mixed>>|WP_Error
 */
function reevents_get_booking_attendees( int $booking_id ) {
	$booking = ( new Booking_Repository() )->find( $booking_id );

	if ( ! $booking ) {
		return reevents_api_error( 'reevents_booking_not_found', __( 'Booking not found.', 're-events' ), 404 );
	}

	if ( ! reevents_api_can_read_booking( $booking ) ) {
		return reevents_api_error( 'reevents_booking_forbidden', __( 'You are not allowed to read this booking.', 're-events' ), 403 );
	}

	return ( new Booking_Repository() )->attendees( $booking_id );
}

/**
 * Create a booking.
 *
 * Public, matching the REST booking endpoint. Pass a unique `booking_token`
 * to make retries return the booking that was already created.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $data Booking payload.
 * @return array<string, mixed>|WP_Error
 */
function reevents_create_booking( array $data ) {
	$token     = sanitize_text_field( (string) ( $data['booking_token'] ?? '' ) );
	$event_id  = (int) ( $data['event_id'] ?? 0 );
	$cache_key = Booking_Service::token_cache_key( $token, $event_id );

	if ( '' !== $cache_key ) {
		$existing = get_transient( $cache_key );
		$booking  = is_numeric( $existing ) ? ( new Booking_Repository() )->find( (int) $existing ) : null;

		if ( $booking ) {
			return reevents_api_booking_data( $booking );
		}
	}

	$errors = Booking_Validator::validate( array_merge( $data, [ 'booking_token' => $token ] ) );

	if ( $errors ) {
		return new WP_Error(
			'reevents_booking_invalid',
			__( 'Please correct the booking fields.', 're-events' ),
			[
				'status' => 400,
				'errors' => $errors,
			]
		);
	}

	if ( '' !== $cache_key && ! Booking_Service::claim_token( $token, $event_id ) ) {
		return reevents_api_error( 'reevents_booking_in_progress', __( 'This booking is already being processed. Please wait a moment.', 're-events' ), 409 );
	}

	try {
		$result = Booking_Service::create(
			[
				'event_id'      => $event_id,
				'ticket_id'     => (int) ( $data['ticket_id'] ?? 0 ),
				'occurrence_id' => (int) ( $data['occurrence_id'] ?? 0 ),
				'quantity'      => max( 1, (int) ( $data['quantity'] ?? 1 ) ),
				'attendees'     => (array) ( $data['attendees'] ?? [] ),
				'user_id'       => get_current_user_id(),
			]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$booking = ( new Booking_Repository() )->find( (int) $result );

		if ( ! $booking ) {
			return reevents_api_error( 'reevents_booking_not_found', __( 'Booking not found.', 're-events' ), 404 );
		}

		$checkout = Payment_Service::checkout( (int) $booking['id'] );

		if ( is_wp_error( $checkout ) ) {
			Booking_Service::cancel( (int) $booking['id'] );
			return $checkout;
		}

		if ( '' !== $cache_key ) {
			set_transient( $cache_key, (int) $result, 5 * MINUTE_IN_SECONDS );
		}

		$payload = reevents_api_booking_data( $booking );

		if ( '' !== $checkout ) {
			$payload['checkout_url'] = $checkout;
		}

		return $payload;
	} finally {
		Booking_Service::release_token( $token, $event_id );
	}
}

/**
 * Update a booking status.
 *
 * Requires edit access to the event or `reevents_manage_bookings`.
 *
 * @since 1.0.0
 *
 * @param int    $booking_id Booking ID.
 * @param string $status     New status.
 * @return array<string, mixed>|WP_Error
 */
function reevents_update_booking_status( int $booking_id, string $status ) {
	$booking = ( new Booking_Repository() )->find( $booking_id );

	if ( ! $booking ) {
		return reevents_api_error( 'reevents_booking_not_found', __( 'Booking not found.', 're-events' ), 404 );
	}

	if ( ! reevents_api_can_read_booking( $booking ) ) {
		return reevents_api_error( 'reevents_booking_forbidden', __( 'You are not allowed to update this booking.', 're-events' ), 403 );
	}

	$result = Booking_Service::update_status( $booking_id, sanitize_key( $status ) );

	return is_wp_error( $result ) ? $result : reevents_get_booking( $booking_id );
}

/*
 * Settings.
 */

/**
 * Read public plugin settings.
 *
 * Requires `reevents_manage_settings`.
 *
 * @since 1.0.0
 *
 * @return array<string, mixed>|WP_Error
 */
function reevents_get_settings() {
	if ( ! current_user_can( 'reevents_manage_settings' ) ) {
		return reevents_api_error( 'reevents_settings_forbidden', __( 'You are not allowed to read settings.', 're-events' ), 403 );
	}

	return [
		'timezone'     => (string) Settings::get( 'timezone', 'UTC' ),
		'time_display' => (string) Settings::get( 'time_display', 'event' ),
		'date_format'  => (string) Settings::get( 'date_format', get_option( 'date_format' ) ),
		'time_format'  => (string) Settings::get( 'time_format', get_option( 'time_format' ) ),
	];
}

/*
 * Helpers.
 */

/**
 * Build an API error.
 *
 * @param string               $code    Error code.
 * @param string               $message Message.
 * @param int                  $status  HTTP-like status.
 * @param array<string, mixed> $data    Extra data.
 */
function reevents_api_error( string $code, string $message, int $status = 400, array $data = [] ): WP_Error {
	return new WP_Error( $code, $message, array_merge( [ 'status' => $status ], $data ) );
}

/**
 * Whether the current request may read a post.
 *
 * @param int $post_id Post ID.
 */
function reevents_api_can_read_post( int $post_id ): bool {
	if ( $post_id <= 0 ) {
		return false;
	}

	return 'publish' === get_post_status( $post_id ) || current_user_can( 'edit_post', $post_id );
}

/**
 * Whether the current request may read a booking.
 *
 * @param array<string, mixed> $booking Booking row.
 */
function reevents_api_can_read_booking( array $booking ): bool {
	return current_user_can( 'reevents_manage_bookings' ) || current_user_can( 'edit_post', (int) $booking['event_id'] );
}

/**
 * Whether the current request may manage an event's tickets or bookings.
 *
 * @param int $event_id Event ID.
 */
function reevents_api_can_manage_event( int $event_id ): bool {
	return current_user_can( 'edit_post', $event_id ) || current_user_can( 'reevents_manage_bookings' );
}

/**
 * Validate venue coordinates before saving.
 *
 * @param array<string, mixed> $data Venue fields.
 */
function reevents_api_validate_venue( array $data ): ?WP_Error {
	if ( array_key_exists( 'lat', $data ) && '' !== (string) $data['lat'] && '' === Meta::sanitize_latitude( $data['lat'] ) ) {
		return reevents_api_error( 'reevents_venue_invalid_latitude', __( 'Latitude must be between -90 and 90.', 're-events' ), 400 );
	}

	if ( array_key_exists( 'lng', $data ) && '' !== (string) $data['lng'] && '' === Meta::sanitize_longitude( $data['lng'] ) ) {
		return reevents_api_error( 'reevents_venue_invalid_longitude', __( 'Longitude must be between -180 and 180.', 're-events' ), 400 );
	}

	return null;
}

/**
 * Serialize an event.
 *
 * @param Event $event Event.
 * @return array<string, mixed>
 */
function reevents_api_event_data( Event $event ): array {
	return [
		'id'            => $event->id,
		'title'         => $event->title,
		'start_utc'     => $event->start_utc,
		'end_utc'       => $event->end_utc,
		'timezone'      => $event->timezone,
		'all_day'       => $event->all_day,
		'rrule'         => $event->rrule,
		'exdates'       => $event->exdates,
		'rdates'        => $event->rdates,
		'timeslots'     => $event->timeslots,
		'venue_id'      => $event->venue_id,
		'organizer_ids' => $event->organizer_ids,
		'capacity'      => $event->capacity,
		'online_urls'   => $event->online_urls,
		'status'        => $event->status,
		'permalink'     => (string) get_permalink( $event->id ),
	];
}

/**
 * Serialize a category.
 *
 * @param WP_Term $term Term.
 * @return array<string, mixed>
 */
function reevents_api_category_data( WP_Term $term ): array {
	$link = get_term_link( $term );

	return [
		'id'          => $term->term_id,
		'name'        => $term->name,
		'slug'        => $term->slug,
		'description' => $term->description,
		'parent'      => $term->parent,
		'count'       => $term->count,
		'link'        => is_wp_error( $link ) ? '' : $link,
	];
}

/**
 * Serialize a booking.
 *
 * @param array<string, mixed> $booking Booking row.
 * @return array<string, mixed>
 */
function reevents_api_booking_data( array $booking ): array {
	$booking['event_title'] = (string) ( $booking['event_title'] ?? get_the_title( (int) $booking['event_id'] ) );

	return $booking;
}
