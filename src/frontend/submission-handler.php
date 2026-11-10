<?php
/**
 * Front-end event submission handler.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Frontend;

use ReEvents\Admin\Settings;
use ReEvents\Data\Event_Repository;
use ReEvents\Data\Meta;
use ReEvents\Domain\Event;
use ReEvents\Domain\Recurrence_Rule;
use ReEvents\Services\Event_Service;
use ReEvents\Services\Rate_Limit;

/**
 * Handles the no-JS event submission form.
 */
final class Submission_Handler {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'admin_post_nopriv_reevents_submit', [ self::class, 'handle' ] );
		add_action( 'admin_post_reevents_submit', [ self::class, 'handle' ] );
	}

	/**
	 * Handle a submission.
	 */
	public static function handle(): void {
		$redirect = wp_get_referer();

		if ( ! $redirect ) {
			$redirect = home_url( '/' );
		}

		if ( ! isset( $_POST['reevents_submit_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['reevents_submit_nonce'] ) ), 'reevents_submit' ) ) {
			self::redirect( $redirect, 'invalid' );
		}

		if ( ! empty( $_POST['website'] ) ) {
			self::redirect( $redirect, 'invalid' );
		}

		$mode = (string) Settings::get( 'submission_mode', 'off' );

		if ( 'off' === $mode ) {
			self::redirect( $redirect, 'submissions_disabled' );
		}

		if ( 'members' === $mode && ! is_user_logged_in() ) {
			self::redirect( $redirect, 'login_required' );
		}

		if ( Rate_Limit::hit( 'submission' ) ) {
			self::redirect( $redirect, 'rate_limited' );
		}

		$user_id  = get_current_user_id();
		$event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;

		if ( $event_id > 0 && ! self::can_edit( $event_id, $user_id ) ) {
			self::redirect( $redirect, 'forbidden' );
		}

		$values = self::posted_values();
		$errors = [];

		if ( '' === $values['title'] ) {
			$errors['title'] = __( 'An event title is required.', 're-events' );
		}

		if ( 0 === $user_id && '' === $values['name'] ) {
			$errors['name'] = __( 'Enter your name.', 're-events' );
		}

		if ( 0 === $user_id && ! is_email( $values['email'] ) ) {
			$errors['email'] = __( 'Enter a valid email address.', 're-events' );
		}

		$timezone = Meta::sanitize_timezone( (string) $values['timezone'] );
		$start    = Meta::local_to_utc( (string) $values['start'], $timezone );
		$end      = Meta::local_to_utc( (string) $values['end'], $timezone );

		if ( '' === $start ) {
			$errors['start'] = __( 'Enter a valid start time.', 're-events' );
		}

		if ( '' !== $end && '' !== $start && $end < $start ) {
			$errors['end'] = __( 'The end time must not be before the start time.', 're-events' );
		}

		if ( $errors ) {
			self::redirect_errors( $redirect, $errors, $values );
		}

		$existing = 0 < $event_id ? ( new Event_Repository() )->find( $event_id ) : null;
		$rrule    = Recurrence_Rule::build(
			[
				'freq'     => (string) $values['repeat'],
				'interval' => (int) $values['interval'],
				'weekdays' => (array) $values['weekdays'],
				'monthday' => (int) $values['monthday'],
				'end'      => (string) $values['recurrence_end'],
				'until'    => (string) $values['until'],
				'count'    => (int) $values['count'],
			]
		);
		$exdates  = self::parse_date_lines( (string) $values['exdates'], $timezone );
		$rdates   = self::parse_date_lines( (string) $values['rdates'], $timezone );

		$event = new Event(
			$event_id,
			$values['title'],
			$start,
			$end,
			$timezone,
			(bool) $values['all_day'],
			$rrule,
			$exdates,
			$rdates,
			$values['timeslots'],
			(int) $values['venue_id'],
			$values['organizer_ids'],
			(int) $values['capacity'],
			$values['online_urls'],
			$existing ? $existing->status : 'scheduled'
		);

		$is_new = 0 === $event_id;
		$status = $is_new ? ( self::can_publish( $user_id ) ? 'publish' : 'pending' ) : '';
		$result = Event_Service::save( $event, $status );

		if ( is_wp_error( $result ) ) {
			$field = self::error_field( $result->get_error_code() );

			self::redirect_errors( $redirect, [ $field => $result->get_error_message() ], $values );
		}

		wp_update_post(
			[
				'ID'           => (int) $result,
				'post_content' => $values['content'],
				'post_excerpt' => $values['excerpt'],
			]
		);

		wp_set_object_terms( (int) $result, $values['categories'], 'reevents_event_category' );
		wp_set_object_terms( (int) $result, $values['tag_names'], 'reevents_event_tag' );

		if ( '' !== $values['name'] ) {
			update_post_meta( (int) $result, '_reevents_submitter_name', $values['name'] );
		}

		if ( '' !== $values['email'] ) {
			update_post_meta( (int) $result, '_reevents_submitter_email', $values['email'] );
		}

		if ( $is_new && 'pending' === get_post_status( (int) $result ) ) {
			self::notify_admin( (int) $result, $values['name'], $values['email'] );
		}

		self::redirect( $redirect, $is_new && 'publish' === $status ? 'published' : 'submitted' );
	}

	/**
	 * Read and sanitize posted values.
	 *
	 * @return array<string, mixed>
	 */
	private static function posted_values(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce checked in handle().
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by Meta::sanitize_urls().
		$labels = isset( $_POST['online_label'] ) && is_array( $_POST['online_label'] ) ? (array) wp_unslash( $_POST['online_label'] ) : [];
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by Meta::sanitize_urls().
		$urls  = isset( $_POST['online_url'] ) && is_array( $_POST['online_url'] ) ? (array) wp_unslash( $_POST['online_url'] ) : [];
		$links = [];

		foreach ( $urls as $index => $url ) {
			$links[] = [
				'label' => $labels[ $index ] ?? '',
				'url'   => $url,
			];
		}

		$tags = isset( $_POST['tags'] ) ? sanitize_text_field( wp_unslash( $_POST['tags'] ) ) : '';
		$tags = array_values( array_filter( array_map( 'trim', explode( ',', $tags ) ) ) );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by Meta::sanitize_timeslots().
		$raw_slots = isset( $_POST['timeslots'] ) && is_array( $_POST['timeslots'] ) ? (array) wp_unslash( $_POST['timeslots'] ) : [];
		$starts    = isset( $raw_slots['start'] ) && is_array( $raw_slots['start'] ) ? (array) $raw_slots['start'] : [];
		$ends      = isset( $raw_slots['end'] ) && is_array( $raw_slots['end'] ) ? (array) $raw_slots['end'] : [];
		$caps      = isset( $raw_slots['capacity'] ) && is_array( $raw_slots['capacity'] ) ? (array) $raw_slots['capacity'] : [];
		$slots     = [];

		foreach ( $starts as $index => $slot_start ) {
			$slots[] = [
				'start'    => $slot_start,
				'end'      => $ends[ $index ] ?? '',
				'capacity' => $caps[ $index ] ?? 0,
			];
		}

		$values = [
			'title'          => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'excerpt'        => isset( $_POST['excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['excerpt'] ) ) : '',
			'content'        => isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '',
			'start'          => isset( $_POST['start'] ) ? sanitize_text_field( wp_unslash( $_POST['start'] ) ) : '',
			'end'            => isset( $_POST['end'] ) ? sanitize_text_field( wp_unslash( $_POST['end'] ) ) : '',
			'timezone'       => isset( $_POST['timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['timezone'] ) ) : (string) Settings::get( 'timezone', 'UTC' ),
			'all_day'        => ! empty( $_POST['all_day'] ),
			'venue_id'       => isset( $_POST['venue_id'] ) ? absint( wp_unslash( $_POST['venue_id'] ) ) : 0,
			'capacity'       => isset( $_POST['capacity'] ) ? absint( wp_unslash( $_POST['capacity'] ) ) : 0,
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by Meta::sanitize_id_array().
			'categories'     => Meta::sanitize_id_array( wp_unslash( $_POST['categories'] ?? [] ) ),
			'tags'           => implode( ', ', $tags ),
			'tag_names'      => $tags,
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by Meta::sanitize_id_array().
			'organizer_ids'  => Meta::sanitize_id_array( wp_unslash( $_POST['organizer_ids'] ?? [] ) ),
			'online_urls'    => Meta::sanitize_urls( $links ),
			'repeat'         => isset( $_POST['repeat'] ) ? sanitize_key( wp_unslash( $_POST['repeat'] ) ) : 'none',
			'interval'       => isset( $_POST['interval'] ) ? max( 1, absint( wp_unslash( $_POST['interval'] ) ) ) : 1,
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
			'weekdays'       => array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['weekdays'] ?? [] ) ),
			'monthday'       => isset( $_POST['monthday'] ) ? absint( wp_unslash( $_POST['monthday'] ) ) : 0,
			'recurrence_end' => isset( $_POST['recurrence_end'] ) ? sanitize_key( wp_unslash( $_POST['recurrence_end'] ) ) : 'never',
			'until'          => isset( $_POST['until'] ) ? sanitize_text_field( wp_unslash( $_POST['until'] ) ) : '',
			'count'          => isset( $_POST['count'] ) ? absint( wp_unslash( $_POST['count'] ) ) : 0,
			'exdates'        => isset( $_POST['exdates'] ) ? sanitize_textarea_field( wp_unslash( $_POST['exdates'] ) ) : '',
			'rdates'         => isset( $_POST['rdates'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rdates'] ) ) : '',
			'timeslots'      => Meta::sanitize_timeslots( $slots ),
			'name'           => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'email'          => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
		];
		// phpcs:enable

		return $values;
	}

	/**
	 * Parse one local date per line into UTC values.
	 *
	 * @param string $text     Raw lines.
	 * @param string $timezone IANA timezone.
	 * @return string[]
	 */
	private static function parse_date_lines( string $text, string $timezone ): array {
		$dates = [];
		$lines = preg_split( '/\r\n|\r|\n/', $text );

		foreach ( is_array( $lines ) ? $lines : [] as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			$utc = Meta::local_to_utc( $line, $timezone );

			if ( '' !== $utc ) {
				$dates[] = $utc;
			}
		}

		return $dates;
	}

	/**
	 * Map an event error code to a form field.
	 *
	 * @param string $code Error code.
	 */
	private static function error_field( string $code ): string {
		$map = [
			'rest_event_invalid_title' => 'title',
			'rest_event_invalid_start' => 'start',
			'rest_event_invalid_range' => 'end',
			'rest_event_invalid_venue' => 'venue_id',
			'rest_event_invalid_rrule' => 'recurrence',
		];

		return $map[ $code ] ?? 'form';
	}

	/**
	 * Notify the admin about a new submission.
	 *
	 * @param int    $event_id Event ID.
	 * @param string $name     Guest name.
	 * @param string $email    Guest email.
	 */
	private static function notify_admin( int $event_id, string $name, string $email ): void {
		$user = wp_get_current_user();

		if ( '' === $name ) {
			$name = (string) $user->display_name;
		}

		if ( '' === $email ) {
			$email = (string) $user->user_email;
		}

		$subject = sprintf(
			/* translators: %s: event title. */
			__( 'New event submission: %s', 're-events' ),
			(string) get_the_title( $event_id )
		);
		$body = sprintf(
			/* translators: 1: event title, 2: submitter name, 3: submitter email, 4: review URL. */
			__( "A new event is awaiting review.\n\nEvent: %1\$s\nSubmitter: %2\$s <%3\$s>\nReview: %4\$s", 're-events' ),
			(string) get_the_title( $event_id ),
			$name,
			$email,
			admin_url( 'post.php?post=' . $event_id . '&action=edit' )
		);

		wp_mail( (string) get_option( 'admin_email' ), $subject, $body );
	}

	/**
	 * Whether a user may publish an event without review.
	 *
	 * @param int $user_id User ID.
	 */
	public static function can_publish( int $user_id ): bool {
		return $user_id > 0 && user_can( $user_id, 'publish_reevents_events' );
	}

	/**
	 * Whether a user may edit an event.
	 *
	 * @param int $event_id Event ID.
	 * @param int $user_id  User ID.
	 */
	public static function can_edit( int $event_id, int $user_id ): bool {
		if ( $event_id <= 0 || $user_id <= 0 ) {
			return false;
		}

		if ( 'reevents_event' !== get_post_type( $event_id ) ) {
			return false;
		}

		return (int) get_post_field( 'post_author', $event_id ) === $user_id || user_can( $user_id, 'edit_post', $event_id );
	}

	/**
	 * Redirect back with field errors.
	 *
	 * @param string                $url    Redirect URL.
	 * @param array<string, string> $errors Field errors.
	 * @param array<string, mixed>  $values Posted values.
	 */
	private static function redirect_errors( string $url, array $errors, array $values ): void {
		$token = wp_generate_password( 32, false, false );

		set_transient(
			'reevents_submission_error_' . $token,
			[
				'errors' => $errors,
				'values' => $values,
			],
			5 * MINUTE_IN_SECONDS
		);

		wp_safe_redirect( add_query_arg( 'reevents_submission_error', $token, $url ) );
		exit;
	}

	/**
	 * Redirect back with a result code.
	 *
	 * @param string $url  Redirect URL.
	 * @param string $code Result code.
	 */
	private static function redirect( string $url, string $code ): void {
		wp_safe_redirect( add_query_arg( 'reevents_submission', $code, $url ) );
		exit;
	}
}
