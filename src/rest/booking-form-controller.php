<?php
/**
 * Booking form schema controller.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\REST;

use ReEvents\Admin\Settings;
use ReEvents\Data\Event_Repository;
use ReEvents\Data\Ticket_Repository;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Exposes the data needed to build a custom booking form.
 */
final class Booking_Form_Controller extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 're-events/v1';

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/events/(?P<event_id>[\d]+)/booking-form',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'event_id' => [
							'description'       => __( 'Event ID.', 're-events' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						],
					],
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);
	}

	/**
	 * Get the form schema.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$event_id = (int) $request['event_id'];
		$event    = ( new Event_Repository() )->find( $event_id );

		if ( ! $event || 'publish' !== get_post_status( $event_id ) ) {
			return new WP_Error( 'rest_event_invalid_id', __( 'Invalid event ID.', 're-events' ), [ 'status' => 404 ] );
		}

		if ( 'cancelled' === $event->status ) {
			return new WP_Error( 'rest_event_cancelled', __( 'This event has been cancelled.', 're-events' ), [ 'status' => 400 ] );
		}

		$now     = current_time( 'mysql', true );
		$tickets = [];
		$repo    = new Ticket_Repository();

		foreach ( $repo->for_event( $event_id, 'active' ) as $ticket ) {
			$capacity = (int) $ticket['capacity'];
			$sold     = (int) $ticket['sold'];
			$on_sale  = ( '' === (string) $ticket['sale_start_utc'] || $now >= (string) $ticket['sale_start_utc'] )
				&& ( '' === (string) $ticket['sale_end_utc'] || $now <= (string) $ticket['sale_end_utc'] );

			$tickets[] = [
				'id'              => (int) $ticket['id'],
				'name'            => (string) $ticket['name'],
				'price_minor'     => (int) $ticket['price_minor'],
				'price'           => \ReEvents\Services\Currency::amount( (int) $ticket['price_minor'], (string) $ticket['currency'] ),
				'currency'        => (string) $ticket['currency'],
				'capacity'        => $capacity,
				'sold'            => $sold,
				'available'       => $capacity > 0 ? max( 0, $capacity - $sold ) : null,
				'min_per_booking' => (int) $ticket['min_per_booking'],
				'max_per_booking' => (int) $ticket['max_per_booking'],
				'sale_start_utc'  => (string) $ticket['sale_start_utc'],
				'sale_end_utc'    => (string) $ticket['sale_end_utc'],
				'on_sale'         => $on_sale,
			];
		}

		$fields = [];

		foreach ( Settings::attendee_fields() as $key => $field ) {
			$fields[] = [
				'key'         => (string) $key,
				'label'       => (string) $field['label'],
				'type'        => (string) $field['type'],
				'required'    => ! empty( $field['required'] ),
				'placeholder' => '',
			];
		}

		return rest_ensure_response(
			[
				'event_id'    => $event_id,
				'event_title' => $event->title,
				'tickets'     => $tickets,
				'fields'      => $fields,
				'submit_url'  => rest_url( $this->namespace . '/events/' . $event_id . '/bookings' ),
				'honeypot'    => 'website',
				'token_field' => 'booking_token',
				'nonce'       => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			]
		);
	}

	/**
	 * Item schema.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema() {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'booking-form',
			'type'       => 'object',
			'properties' => [
				'event_id'    => [ 'type' => 'integer' ],
				'event_title' => [ 'type' => 'string' ],
				'tickets'     => [
					'type'  => 'array',
					'items' => [ 'type' => 'object' ],
				],
				'fields'      => [
					'type'  => 'array',
					'items' => [ 'type' => 'object' ],
				],
				'submit_url'  => [ 'type' => 'string' ],
				'honeypot'    => [ 'type' => 'string' ],
				'token_field' => [ 'type' => 'string' ],
				'nonce'       => [ 'type' => 'string' ],
			],
		];
	}
}
