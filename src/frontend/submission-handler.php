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

		if ( $event_id > 0 ) {
			$author = (int) get_post_field( 'post_author', $event_id );

			if ( 0 === $user_id || 'reevents_event' !== get_post_type( $event_id ) || ( $author !== $user_id && ! current_user_can( 'edit_post', $event_id ) ) ) {
				self::redirect( $redirect, 'forbidden' );
			}
		}

		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( 0 === $user_id && ( '' === $name || ! is_email( $email ) ) ) {
			self::redirect( $redirect, 'invalid_submitter' );
		}

		$timezone = Meta::sanitize_timezone( sanitize_text_field( wp_unslash( $_POST['timezone'] ?? Settings::get( 'timezone', 'UTC' ) ) ) );
		$start    = Meta::local_to_utc( sanitize_text_field( wp_unslash( $_POST['start'] ?? '' ) ), $timezone );
		$end      = Meta::local_to_utc( sanitize_text_field( wp_unslash( $_POST['end'] ?? '' ) ), $timezone );

		if ( '' === $start ) {
			self::redirect( $redirect, 'invalid_start' );
		}

		if ( '' !== $end && $end < $start ) {
			self::redirect( $redirect, 'invalid_end' );
		}

		$existing = 0 < $event_id ? ( new Event_Repository() )->find( $event_id ) : null;

		$event = new Event(
			$event_id,
			sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			$start,
			$end,
			$timezone,
			$existing ? $existing->all_day : false,
			$existing ? $existing->rrule : '',
			$existing ? $existing->exdates : [],
			$existing ? $existing->rdates : [],
			$existing ? $existing->timeslots : [],
			absint( wp_unslash( $_POST['venue_id'] ?? 0 ) ),
			$existing ? $existing->organizer_ids : [],
			absint( wp_unslash( $_POST['capacity'] ?? 0 ) ),
			$existing ? $existing->online_urls : [],
			$existing ? $existing->status : 'scheduled'
		);

		$result = Event_Service::save( $event, 0 === $event_id ? 'pending' : '' );

		if ( is_wp_error( $result ) ) {
			self::redirect( $redirect, $result->get_error_code() );
		}

		$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';

		if ( '' !== $content ) {
			wp_update_post(
				[
					'ID'           => (int) $result,
					'post_content' => $content,
				]
			);
		}

		if ( '' !== $name ) {
			update_post_meta( (int) $result, '_reevents_submitter_name', $name );
		}

		if ( '' !== $email ) {
			update_post_meta( (int) $result, '_reevents_submitter_email', $email );
		}

		self::redirect( $redirect, 'submitted' );
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
