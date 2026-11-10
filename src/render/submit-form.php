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
use ReEvents\Data\Meta;
use ReEvents\Domain\Recurrence_Rule;

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

		$state  = self::state( $args );
		$post   = self::edit_post();
		$values = $state['values'];

		$timezone = (string) ( $values['timezone'] ?? ( $post ? get_post_meta( $post->ID, '_reevents_timezone', true ) : Settings::get( 'timezone', 'UTC' ) ) );
		$timezone = '' !== $timezone ? $timezone : 'UTC';
		$start    = (string) ( $values['start'] ?? ( $post ? self::local_input( (string) get_post_meta( $post->ID, '_reevents_start_utc', true ), $timezone ) : '' ) );
		$end      = (string) ( $values['end'] ?? ( $post ? self::local_input( (string) get_post_meta( $post->ID, '_reevents_end_utc', true ), $timezone ) : '' ) );

		$use_editor = is_user_logged_in();
		$rule       = Recurrence_Rule::parse( (string) ( $post ? get_post_meta( $post->ID, '_reevents_rrule', true ) : '' ) );

		if ( $use_editor ) {
			wp_enqueue_editor();
		}

		wp_enqueue_script(
			'reevents-submit',
			plugins_url( 'assets/js/frontend-submit.js', RE_EVENTS_FILE ),
			[],
			RE_EVENTS_VERSION,
			true
		);

		return Template::render(
			'submit-form',
			[
				'post_id'          => $post ? (int) $post->ID : 0,
				'title'            => (string) ( $values['title'] ?? ( $post ? $post->post_title : '' ) ),
				'excerpt'          => (string) ( $values['excerpt'] ?? ( $post ? $post->post_excerpt : '' ) ),
				'content'          => (string) ( $values['content'] ?? ( $post ? $post->post_content : '' ) ),
				'start'            => $start,
				'end'              => $end,
				'timezone'         => $timezone,
				'all_day'          => array_key_exists( 'all_day', $values ) ? (bool) $values['all_day'] : ( $post ? (bool) get_post_meta( $post->ID, '_reevents_all_day', true ) : false ),
				'venue_id'         => (int) ( $values['venue_id'] ?? ( $post ? get_post_meta( $post->ID, '_reevents_venue_id', true ) : 0 ) ),
				'capacity'         => (int) ( $values['capacity'] ?? ( $post ? get_post_meta( $post->ID, '_reevents_capacity', true ) : 0 ) ),
				'categories'       => array_map( 'intval', (array) ( $values['categories'] ?? ( $post ? wp_get_object_terms( $post->ID, 'reevents_event_category', [ 'fields' => 'ids' ] ) : [] ) ) ),
				'tags'             => (string) ( $values['tags'] ?? ( $post ? implode( ', ', wp_get_object_terms( $post->ID, 'reevents_event_tag', [ 'fields' => 'names' ] ) ) : '' ) ),
				'organizer_ids'    => array_map( 'intval', (array) ( $values['organizer_ids'] ?? ( $post ? get_post_meta( $post->ID, '_reevents_organizer_ids', true ) : [] ) ) ),
				'online_urls'      => is_array( $values['online_urls'] ?? null ) ? $values['online_urls'] : ( $post ? Meta::sanitize_urls( get_post_meta( $post->ID, '_reevents_online_urls', true ) ) : [] ),
				'name'             => (string) ( $values['name'] ?? ( $post ? get_post_meta( $post->ID, '_reevents_submitter_name', true ) : '' ) ),
				'email'            => (string) ( $values['email'] ?? ( $post ? get_post_meta( $post->ID, '_reevents_submitter_email', true ) : '' ) ),
				'new_venue'        => (string) ( $values['new_venue'] ?? '' ),
				'new_organizer'    => (string) ( $values['new_organizer'] ?? '' ),
				'thumbnail'        => $post ? (string) get_the_post_thumbnail( $post->ID, 'thumbnail' ) : '',
				'can_upload'       => current_user_can( 'upload_files' ),
				'repeat'           => (string) ( $values['repeat'] ?? $rule['freq'] ),
				'interval'         => (int) ( $values['interval'] ?? $rule['interval'] ),
				'weekdays'         => (array) ( $values['weekdays'] ?? $rule['weekdays'] ),
				'monthday'         => (int) ( $values['monthday'] ?? $rule['monthday'] ),
				'recurrence_end'   => (string) ( $values['recurrence_end'] ?? $rule['end'] ),
				'until'            => (string) ( $values['until'] ?? $rule['until'] ),
				'count'            => max( 1, (int) ( $values['count'] ?? $rule['count'] ) ),
				'exdates'          => (string) ( $values['exdates'] ?? ( $post ? self::local_lines( (array) get_post_meta( $post->ID, '_reevents_exdates', true ), $timezone ) : '' ) ),
				'rdates'           => (string) ( $values['rdates'] ?? ( $post ? self::local_lines( (array) get_post_meta( $post->ID, '_reevents_rdates', true ), $timezone ) : '' ) ),
				'timeslots'        => is_array( $values['timeslots'] ?? null ) ? $values['timeslots'] : ( $post ? Meta::sanitize_timeslots( get_post_meta( $post->ID, '_reevents_timeslots', true ) ) : [] ),
				'categories_terms' => self::categories(),
				'organizers'       => self::organizers(),
				'venues'           => self::venues(),
				'use_editor'       => $use_editor,
				'errors'           => $state['errors'],
				'state'            => $state,
				'action'           => admin_url( 'admin-post.php' ),
				'nonce'            => wp_create_nonce( 'reevents_submit' ),
				'args'             => $args,
			]
		);
	}

	/**
	 * Read the edit post, if the current user owns it.
	 */
	private static function edit_post(): ?\WP_Post {
		$post_id = self::edit_id();

		if ( 0 >= $post_id ) {
			return null;
		}

		$post = get_post( $post_id );

		if ( ! $post || 'reevents_event' !== $post->post_type || get_current_user_id() !== (int) $post->post_author ) {
			return null;
		}

		return $post;
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
	 * Convert UTC datetimes to local lines.
	 *
	 * @param array<int, mixed> $dates    UTC datetimes.
	 * @param string            $timezone IANA timezone.
	 */
	private static function local_lines( array $dates, string $timezone ): string {
		$lines = [];

		foreach ( $dates as $utc ) {
			$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', (string) $utc, new DateTimeZone( 'UTC' ) );

			if ( $date instanceof DateTimeImmutable ) {
				$lines[] = $date->setTimezone( new DateTimeZone( $timezone ) )->format( 'Y-m-d H:i' );
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Event categories.
	 *
	 * @return array<int, array{id:int,name:string}>
	 */
	private static function categories(): array {
		$terms = get_terms(
			[
				'taxonomy'   => 'reevents_event_category',
				'hide_empty' => false,
			]
		);

		if ( is_wp_error( $terms ) ) {
			return [];
		}

		return array_map(
			static function ( \WP_Term $term ): array {
				return [
					'id'   => (int) $term->term_id,
					'name' => (string) $term->name,
				];
			},
			$terms
		);
	}

	/**
	 * Published organizers.
	 *
	 * @return array<int, array{id:int,title:string}>
	 */
	private static function organizers(): array {
		$limit = max( 1, (int) apply_filters( 'reevents_admin_dropdown_limit', 200 ) );
		$posts = get_posts(
			[
				'post_type'      => 'reevents_organizer',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);
		$items = [];

		foreach ( $posts as $post ) {
			$items[] = [
				'id'    => (int) $post->ID,
				'title' => (string) $post->post_title,
			];
		}

		return $items;
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
	 * @return array{status:string,message:string,errors:array<string,string>,values:array<string,mixed>}
	 */
	private static function state( array $args ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only result flags.
		$token = isset( $_GET['reevents_submission_error'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['reevents_submission_error'] ) ) : '';
		$code  = isset( $_GET['reevents_submission'] ) ? sanitize_key( wp_unslash( (string) $_GET['reevents_submission'] ) ) : '';
		// phpcs:enable

		if ( '' !== $token ) {
			$data = get_transient( 'reevents_submission_error_' . $token );

			if ( is_array( $data ) ) {
				delete_transient( 'reevents_submission_error_' . $token );

				$errors = (array) ( $data['errors'] ?? [] );

				return [
					'status'  => 'error',
					'message' => (string) reset( $errors ),
					'errors'  => $errors,
					'values'  => (array) ( $data['values'] ?? [] ),
				];
			}
		}

		if ( 'submitted' === $code ) {
			return [
				'status'  => 'success',
				'message' => (string) ( $args['success_message'] ?? __( 'Thank you. Your event is awaiting review.', 're-events' ) ),
				'errors'  => [],
				'values'  => [],
			];
		}

		if ( 'published' === $code ) {
			return [
				'status'  => 'success',
				'message' => (string) ( $args['published_message'] ?? __( 'Thank you. Your event is published.', 're-events' ) ),
				'errors'  => [],
				'values'  => [],
			];
		}

		if ( '' !== $code ) {
			return [
				'status'  => 'error',
				'message' => self::message( $code ),
				'errors'  => [],
				'values'  => [],
			];
		}

		return [
			'status'  => '',
			'message' => '',
			'errors'  => [],
			'values'  => [],
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
			'rest_event_invalid_title' => __( 'An event title is required.', 're-events' ),
			'rest_event_invalid_range' => __( 'The end time must not be before the start time.', 're-events' ),
			'rest_event_invalid_venue' => __( 'The venue does not exist.', 're-events' ),
		];

		return $messages[ $code ] ?? __( 'The event could not be submitted.', 're-events' );
	}
}
