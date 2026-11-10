<?php
/**
 * Booking payload validation.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services;

use Rakit\Validation\Validator;
use ReEvents\Admin\Settings;
use ReEvents\Data\Event_Repository;
use ReEvents\Data\Ticket_Repository;

/**
 * Validates the shape of a booking payload and returns field errors.
 */
final class Booking_Validator {

	/**
	 * Maximum seats per booking.
	 */
	public const MAX_SEATS = 100;

	/**
	 * Maximum attendees per booking.
	 */
	public const MAX_ATTENDEES = 100;

	/**
	 * Validate a booking payload.
	 *
	 * @param array<string, mixed> $data Booking payload.
	 * @return array<string, string> Field errors.
	 */
	public static function validate( array $data ): array {
		$rules  = [
			'event_id'          => 'required|integer|min:1',
			'ticket_id'         => 'required|integer|min:1',
			'quantity'          => 'required|integer|min:1|max:' . self::MAX_SEATS,
			'attendees'         => 'required|array|min:1|max:' . self::MAX_ATTENDEES,
			'attendees.*.name'  => 'required|max:191',
			'attendees.*.email' => 'required|email|max:191',
			'booking_token'     => 'nullable|max:191',
		];
		$labels = [
			'event_id'          => __( 'Event', 're-events' ),
			'ticket_id'         => __( 'Ticket', 're-events' ),
			'quantity'          => __( 'Seats', 're-events' ),
			'attendees'         => __( 'Attendees', 're-events' ),
			'attendees.*.name'  => __( 'Name', 're-events' ),
			'attendees.*.email' => __( 'Email', 're-events' ),
			'booking_token'     => __( 'Booking token', 're-events' ),
		];

		$custom_labels = [];

		foreach ( Settings::attendee_fields() as $key => $field ) {
			$path = 'attendees.*.fields.' . $key;
			$rule = ! empty( $field['required'] ) ? 'required|' : 'nullable|';

			if ( 'email' === $field['type'] ) {
				$rule .= 'email|';
			}

			$rules[ $path ]        = $rule . 'max:5000';
			$custom_labels[ $key ] = (string) $field['label'];
		}

		$validation = ( new Validator() )->validate( $data, $rules );
		$errors     = [];

		foreach ( $validation->errors()->toArray() as $field => $messages ) {
			$rule             = (string) array_key_first( $messages );
			$errors[ $field ] = self::message( $rule, self::label( (string) $field, $labels, $custom_labels ) );
		}

		return $errors;
	}

	/**
	 * Validate event, ticket, sale window and capacity rules.
	 *
	 * @param int               $event_id      Event ID.
	 * @param int               $ticket_id     Ticket ID.
	 * @param int               $quantity      Seats.
	 * @param int               $occurrence_id Occurrence ID.
	 * @param array<int, mixed> $attendees     Attendees.
	 * @return array{errors:array<string, string>,code:string,message:string}
	 */
	public static function validate_domain( int $event_id, int $ticket_id, int $quantity, int $occurrence_id, array $attendees ): array {
		$errors  = [];
		$code    = '';
		$message = '';
		$event   = ( new Event_Repository() )->find( $event_id );

		if ( ! $event || 'publish' !== get_post_status( $event_id ) ) {
			self::add_error( $errors, $code, $message, 'event_id', __( 'Invalid event.', 're-events' ), 'reevents_booking_invalid_event' );
		} elseif ( 'cancelled' === $event->status ) {
			self::add_error( $errors, $code, $message, 'event_id', __( 'This event has been cancelled.', 're-events' ), 'reevents_booking_event_cancelled' );
		}

		if ( $occurrence_id > 0 ) {
			self::add_error( $errors, $code, $message, 'occurrence_id', __( 'The selected time is not available.', 're-events' ), 'reevents_booking_invalid_occurrence' );
		}

		$ticket = ( new Ticket_Repository() )->find( $ticket_id );

		if ( ! $ticket || (int) $ticket['event_id'] !== $event_id ) {
			self::add_error( $errors, $code, $message, 'ticket_id', __( 'Invalid ticket.', 're-events' ), 'reevents_booking_invalid_ticket' );
		} else {
			if ( 'active' !== $ticket['status'] ) {
				self::add_error( $errors, $code, $message, 'ticket_id', __( 'This ticket is not on sale.', 're-events' ), 'reevents_booking_ticket_inactive' );
			}

			$now = current_time( 'mysql', true );

			if ( '' !== (string) $ticket['sale_start_utc'] && $now < (string) $ticket['sale_start_utc'] ) {
				self::add_error( $errors, $code, $message, 'ticket_id', __( 'Ticket sales have not started.', 're-events' ), 'reevents_booking_sale_not_started' );
			}

			if ( '' !== (string) $ticket['sale_end_utc'] && $now > (string) $ticket['sale_end_utc'] ) {
				self::add_error( $errors, $code, $message, 'ticket_id', __( 'Ticket sales have ended.', 're-events' ), 'reevents_booking_sale_ended' );
			}

			if ( $quantity < (int) $ticket['min_per_booking'] ) {
				self::add_error( $errors, $code, $message, 'quantity', __( 'Too few seats selected.', 're-events' ), 'reevents_booking_min_quantity' );
			}

			if ( (int) $ticket['max_per_booking'] > 0 && $quantity > (int) $ticket['max_per_booking'] ) {
				self::add_error( $errors, $code, $message, 'quantity', __( 'Too many seats selected.', 're-events' ), 'reevents_booking_max_quantity' );
			}

			if ( (int) $ticket['capacity'] > 0 && (int) $ticket['sold'] + $quantity > (int) $ticket['capacity'] ) {
				self::add_error( $errors, $code, $message, 'quantity', __( 'Not enough seats are available.', 're-events' ), 'reevents_booking_sold_out' );
			}
		}

		if ( $attendees && count( $attendees ) > $quantity ) {
			self::add_error( $errors, $code, $message, 'attendees', __( 'There are more attendees than seats.', 're-events' ), 'reevents_booking_too_many_attendees' );
		}

		return [
			'errors'  => $errors,
			'code'    => $code,
			'message' => $message,
		];
	}

	/**
	 * Add the first domain error.
	 *
	 * @param array<string, string> $errors  Errors.
	 * @param string                $code    Error code.
	 * @param string                $message Error message.
	 * @param string                $field   Field key.
	 * @param string                $text    Field message.
	 * @param string                $new_code Specific error code.
	 */
	private static function add_error( array &$errors, string &$code, string &$message, string $field, string $text, string $new_code ): void {
		$errors[ $field ] = $text;

		if ( '' === $code ) {
			$code    = $new_code;
			$message = $text;
		}
	}

	/**
	 * Resolve a field label.
	 *
	 * @param string                $field         Field key.
	 * @param array<string, string> $labels        Static labels.
	 * @param array<string, string> $custom_labels Custom field labels.
	 */
	private static function label( string $field, array $labels, array $custom_labels ): string {
		if ( isset( $labels[ $field ] ) ) {
			return $labels[ $field ];
		}

		if ( preg_match( '/^attendees\.\d+\.fields\.(.+)$/', $field, $matches ) ) {
			return $custom_labels[ $matches[1] ] ?? $matches[1];
		}

		if ( str_ends_with( $field, '.name' ) ) {
			return __( 'Name', 're-events' );
		}

		if ( str_ends_with( $field, '.email' ) ) {
			return __( 'Email', 're-events' );
		}

		return $field;
	}

	/**
	 * Build a translated message.
	 *
	 * @param string $rule  Rule name.
	 * @param string $label Field label.
	 */
	private static function message( string $rule, string $label ): string {
		switch ( $rule ) {
			case 'required':
				return sprintf(
					/* translators: %s: field label. */
					__( '%s is required.', 're-events' ),
					$label
				);
			case 'email':
				return sprintf(
					/* translators: %s: field label. */
					__( '%s must be a valid email address.', 're-events' ),
					$label
				);
			case 'integer':
				return sprintf(
					/* translators: %s: field label. */
					__( '%s must be a number.', 're-events' ),
					$label
				);
			case 'min':
				return sprintf(
					/* translators: %s: field label. */
					__( '%s is too small.', 're-events' ),
					$label
				);
			case 'max':
				return sprintf(
					/* translators: %s: field label. */
					__( '%s is too long.', 're-events' ),
					$label
				);
			default:
				return sprintf(
					/* translators: %s: field label. */
					__( '%s is invalid.', 're-events' ),
					$label
				);
		}
	}
}
