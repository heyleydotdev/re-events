<?php
/**
 * Submission form renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use DateTimeImmutable;
use DateTimeZone;
use ReEvents\Admin\Settings;

/**
 * Renders the front-end event submission form.
 */
final class Submit_Form {

	/**
	 * Render the form.
	 *
	 * @param array<string, mixed> $args Widget arguments.
	 */
	public static function render( array $args ): string {
		$mode = (string) Settings::get( 'submission_mode', 'off' );

		if ( 'off' === $mode ) {
			return '';
		}

		if ( 'members' === $mode && ! is_user_logged_in() ) {
			return '<p class="reevents-empty">' . esc_html( (string) ( $args['login_message'] ?? __( 'Log in to submit an event.', 're-events' ) ) ) . ' <a href="' . esc_url( wp_login_url( self::current_url() ) ) . '">' . esc_html__( 'Log in', 're-events' ) . '</a></p>';
		}

		$post_id = self::edit_id();
		$post    = 0 < $post_id ? get_post( $post_id ) : null;

		if ( $post && ( 'reevents_event' !== $post->post_type || get_current_user_id() !== (int) $post->post_author ) ) {
			$post = null;
		}

		$timezone = $post ? (string) get_post_meta( $post->ID, '_reevents_timezone', true ) : (string) Settings::get( 'timezone', 'UTC' );
		$timezone = '' !== $timezone ? $timezone : 'UTC';

		return Template::render(
			'submit-form',
			[
				'post_id'  => $post ? (int) $post->ID : 0,
				'title'    => $post ? (string) $post->post_title : '',
				'start'    => $post ? self::local_input( (string) get_post_meta( $post->ID, '_reevents_start_utc', true ), $timezone ) : '',
				'end'      => $post ? self::local_input( (string) get_post_meta( $post->ID, '_reevents_end_utc', true ), $timezone ) : '',
				'timezone' => $timezone,
				'venue_id' => $post ? (int) get_post_meta( $post->ID, '_reevents_venue_id', true ) : 0,
				'capacity' => $post ? (int) get_post_meta( $post->ID, '_reevents_capacity', true ) : 0,
				'content'  => $post ? (string) $post->post_content : '',
				'name'     => $post ? (string) get_post_meta( $post->ID, '_reevents_submitter_name', true ) : '',
				'email'    => $post ? (string) get_post_meta( $post->ID, '_reevents_submitter_email', true ) : '',
				'venues'   => self::venues(),
				'action'   => admin_url( 'admin-post.php' ),
				'nonce'    => wp_create_nonce( 'reevents_submit' ),
				'state'    => self::state( $args ),
				'args'     => $args,
			]
		);
	}

	/**
	 * Read the edit ID.
	 */
	private static function edit_id(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only edit selection.
		return isset( $_GET['reevents_edit'] ) ? absint( wp_unslash( $_GET['reevents_edit'] ) ) : 0;
	}

	/**
	 * Build the current URL for the login redirect.
	 */
	private static function current_url(): string {
		$url = get_permalink();

		return is_string( $url ) && '' !== $url ? $url : home_url( '/' );
	}

	/**
	 * Convert a UTC datetime to a datetime-local value.
	 *
	 * @param string $utc      UTC datetime.
	 * @param string $timezone IANA timezone.
	 */
	private static function local_input( string $utc, string $timezone ): string {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $utc, new DateTimeZone( 'UTC' ) );

		if ( ! $date instanceof DateTimeImmutable ) {
			return '';
		}

		return $date->setTimezone( new DateTimeZone( $timezone ) )->format( 'Y-m-d\TH:i' );
	}

	/**
	 * Published venues.
	 *
	 * @return array<int, array{id:int,title:string}>
	 */
	private static function venues(): array {
		$limit  = max( 1, (int) apply_filters( 'reevents_admin_dropdown_limit', 200 ) );
		$posts  = get_posts(
			[
				'post_type'      => 'reevents_venue',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);
		$venues = [];

		foreach ( $posts as $post ) {
			$venues[] = [
				'id'    => (int) $post->ID,
				'title' => (string) $post->post_title,
			];
		}

		return $venues;
	}

	/**
	 * Read the result state.
	 *
	 * @param array<string, mixed> $args Widget arguments.
	 * @return array{status:string,message:string}
	 */
	private static function state( array $args ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result flag.
		$code = isset( $_GET['reevents_submission'] ) ? sanitize_key( wp_unslash( (string) $_GET['reevents_submission'] ) ) : '';

		if ( '' === $code ) {
			return [
				'status'  => '',
				'message' => '',
			];
		}

		if ( 'submitted' === $code ) {
			return [
				'status'  => 'success',
				'message' => (string) ( $args['success_message'] ?? __( 'Thank you. Your event is awaiting review.', 're-events' ) ),
			];
		}

		return [
			'status'  => 'error',
			'message' => self::message( $code ),
		];
	}

	/**
	 * Map a result code to a message.
	 *
	 * @param string $code Result code.
	 */
	private static function message( string $code ): string {
		$messages = [
			'invalid'                  => __( 'The form could not be verified.', 're-events' ),
			'submissions_disabled'     => __( 'Event submissions are closed.', 're-events' ),
			'login_required'           => __( 'Log in to submit an event.', 're-events' ),
			'rate_limited'             => __( 'Too many attempts. Try again later.', 're-events' ),
			'forbidden'                => __( 'You cannot edit this event.', 're-events' ),
			'invalid_submitter'        => __( 'Enter your name and a valid email address.', 're-events' ),
			'invalid_start'            => __( 'Enter a valid start time.', 're-events' ),
			'invalid_end'              => __( 'The end time must not be before the start time.', 're-events' ),
			'rest_event_invalid_title' => __( 'An event title is required.', 're-events' ),
			'rest_event_invalid_range' => __( 'The end time must not be before the start time.', 're-events' ),
			'rest_event_invalid_venue' => __( 'The venue does not exist.', 're-events' ),
		];

		return $messages[ $code ] ?? __( 'The event could not be submitted.', 're-events' );
	}
}
