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

/**
 * Validates the shape of a booking payload and returns field errors.
 */
final class Booking_Validator {

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
			'quantity'          => 'required|integer|min:1',
			'attendees'         => 'required|array|min:1',
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
