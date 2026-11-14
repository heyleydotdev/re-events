<?php
/**
 * Self-service booking tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\CLI\Test_Factory;
use ReEvents\Data\Booking_Repository;
use ReEvents\Data\Payment_Repository;
use ReEvents\Data\Ticket_Repository;
use ReEvents\Frontend\Booking_Actions;
use ReEvents\Frontend\Blocks;
use ReEvents\Render\Bookings;
use ReEvents\Render\Widget_Registry;
use ReEvents\Services\Booking_Access;
use ReEvents\Services\Booking_Emails;
use ReEvents\Services\Booking_Self_Service;
use ReEvents\Services\Booking_Service;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Covers views, rules, actions and REST access.
 */
final class My_Bookings_Test extends WP_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private int $admin = 0;

	/**
	 * Owner user ID.
	 *
	 * @var int
	 */
	private int $owner = 0;

	/**
	 * Reset settings and users.
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( 'reevents_settings', [] );
		$this->admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->owner = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		add_filter( 'pre_wp_mail', '__return_true' );
	}

	/**
	 * Future event with one free ticket.
	 *
	 * @param int $hours Hours from now.
	 * @param int $capacity Ticket capacity.
	 * @param int $price Ticket price in minor units.
	 * @return array{event:int,ticket:int}
	 */
	private function fixture( int $hours = 168, int $capacity = 10, int $price = 0 ): array {
		$event = Test_Factory::event(
			[
				'title' => 'Self-service event',
				'meta'  => [
					'_reevents_start_utc' => gmdate( 'Y-m-d H:i:s', time() + $hours * HOUR_IN_SECONDS ),
					'_reevents_end_utc'   => gmdate( 'Y-m-d H:i:s', time() + $hours * HOUR_IN_SECONDS + HOUR_IN_SECONDS ),
					'_reevents_timezone'  => 'UTC',
				],
			]
		);

		return [
			'event'  => $event,
			'ticket' => Test_Factory::ticket(
				$event,
				[
					'capacity'    => $capacity,
					'price_minor' => $price,
				]
			),
		];
	}

	/**
	 * Create a booking.
	 *
	 * @param array{event:int,ticket:int} $fixture Fixture.
	 * @param int                         $user    User ID.
	 * @param int                         $quantity Seats.
	 * @param string                      $email   Attendee email.
	 * @return int
	 */
	private function booking( array $fixture, int $user = 0, int $quantity = 1, string $email = 'owner@example.com' ): int {
		$id = Booking_Service::create(
			[
				'event_id'  => $fixture['event'],
				'ticket_id' => $fixture['ticket'],
				'quantity'  => $quantity,
				'attendees' => [
					[
						'name'  => 'Owner',
						'email' => $email,
					],
				],
				'user_id'   => $user,
			]
		);

		self::assertIsInt( $id );

		return $id;
	}

	/**
	 * The owner sees their bookings in the widget.
	 */
	public function test_owner_list_renders(): void {
		$fixture = $this->fixture();
		$this->booking( $fixture, $this->owner );
		wp_set_current_user( $this->owner );

		$html = Bookings::render( [] );

		self::assertStringContainsString( 'Self-service event', $html );
		self::assertStringContainsString( 'reevents-booking-cancel', $html );
		self::assertStringContainsString( 'Confirmed', $html );
	}

	/**
	 * Guests get the lookup form, not other people's bookings.
	 */
	public function test_guest_sees_lookup_form_only(): void {
		$fixture = $this->fixture();
		$this->booking( $fixture, $this->owner );
		wp_set_current_user( 0 );

		$html = Bookings::render( [] );

		self::assertStringContainsString( 'reevents-bookings-lookup', $html );
		self::assertStringNotContainsString( 'Self-service event', $html );
	}

	/**
	 * A signed link shows one booking.
	 */
	public function test_signed_link_renders(): void {
		$fixture = $this->fixture();
		$id      = $this->booking( $fixture, 0 );
		$booking = ( new Booking_Repository() )->find( $id );

		$_GET = [
			'reevents_booking'   => (string) $id,
			'reevents_reference' => (string) $booking['reference'],
			'reevents_key'       => Booking_Access::key( (array) $booking ),
		];

		$html = Bookings::render( [] );
		$_GET = [];

		self::assertStringContainsString( 'Self-service event', $html );
		self::assertStringContainsString( 'name="key"', $html );
	}

	/**
	 * A wrong key shows a generic error.
	 */
	public function test_bad_key_is_rejected(): void {
		$fixture = $this->fixture();
		$id      = $this->booking( $fixture, 0 );

		$_GET = [
			'reevents_booking'   => (string) $id,
			'reevents_reference' => 'wrong',
			'reevents_key'       => str_repeat( 'a', 64 ),
		];

		$html = Bookings::render( [] );
		$_GET = [];

		self::assertStringNotContainsString( 'Self-service event', $html );
		self::assertStringContainsString( 'not valid', $html );
	}

	/**
	 * Lookup needs the matching email.
	 */
	public function test_lookup_requires_matching_email(): void {
		$fixture = $this->fixture();
		$id      = $this->booking( $fixture, 0, 1, 'guest@example.com' );
		$booking = ( new Booking_Repository() )->find( $id );

		$_POST = [
			'reevents_lookup'       => '1',
			'reevents_reference'    => (string) $booking['reference'],
			'reevents_email'        => 'wrong@example.com',
			'reevents_lookup_nonce' => wp_create_nonce( 'reevents_lookup' ),
		];

		$wrong = Bookings::render( [] );

		$_POST['reevents_email'] = 'guest@example.com';
		$right                   = Bookings::render( [] );
		$_POST                   = [];

		self::assertStringNotContainsString( 'Self-service event', $wrong );
		self::assertStringContainsString( 'Self-service event', $right );
	}

	/**
	 * Cancelling releases seats and the owner may restore a free booking.
	 */
	public function test_cancel_and_reactivate(): void {
		$fixture = $this->fixture( 168, 5 );
		$id      = $this->booking( $fixture, $this->owner, 2 );

		self::assertSame( 2, (int) ( new Ticket_Repository() )->find( $fixture['ticket'] )['sold'] );

		self::assertTrue( Booking_Self_Service::cancel( $id ) );
		self::assertSame( 'cancelled', ( new Booking_Repository() )->find( $id )['status'] );
		self::assertSame( 0, (int) ( new Ticket_Repository() )->find( $fixture['ticket'] )['sold'] );

		self::assertTrue( Booking_Self_Service::reactivate( $id ) );
		self::assertSame( 'confirmed', ( new Booking_Repository() )->find( $id )['status'] );
		self::assertSame( 2, (int) ( new Ticket_Repository() )->find( $fixture['ticket'] )['sold'] );
	}

	/**
	 * The cutoff blocks actions.
	 */
	public function test_cutoff_blocks_cancel(): void {
		$fixture = $this->fixture( 24 );
		$id      = $this->booking( $fixture, $this->owner );

		update_option( 'reevents_settings', [ 'self_service_cutoff' => 48 ] );

		$result = Booking_Self_Service::cancel( $id );

		self::assertWPError( $result );
		self::assertSame( 'closed', $result->get_error_data()['reason'] );
		self::assertSame( 'confirmed', ( new Booking_Repository() )->find( $id )['status'] );
	}

	/**
	 * A paid booking is refunded before it is cancelled.
	 */
	public function test_paid_cancel_refunds(): void {
		$fixture = $this->fixture( 168, 5, 2500 );
		$id      = $this->booking( $fixture, $this->owner, 2 );
		$payment = ( new Payment_Repository() )->create(
			[
				'booking_id'   => $id,
				'gateway'      => 'mock',
				'gateway_ref'  => 'mock_self_service',
				'status'       => 'succeeded',
				'amount_minor' => 5000,
				'currency'     => 'USD',
			]
		);

		self::assertTrue( Booking_Self_Service::cancel( $id ) );

		$stored = ( new Payment_Repository() )->find( (int) $payment );

		self::assertSame( 'refunded', $stored['status'] );
		self::assertSame( 'cancelled', ( new Booking_Repository() )->find( $id )['status'] );
	}

	/**
	 * Refunds can be switched off for paid bookings.
	 */
	public function test_paid_cancel_can_be_disabled(): void {
		$fixture = $this->fixture( 168, 5, 2500 );
		$id      = $this->booking( $fixture, $this->owner );
		( new Payment_Repository() )->create(
			[
				'booking_id'   => $id,
				'gateway'      => 'mock',
				'gateway_ref'  => 'mock_self_service_off',
				'status'       => 'succeeded',
				'amount_minor' => 2500,
				'currency'     => 'USD',
			]
		);

		update_option( 'reevents_settings', [ 'self_service_refund' => false ] );

		$result = Booking_Self_Service::cancel( $id );

		self::assertWPError( $result );
		self::assertSame( 'paid', $result->get_error_data()['reason'] );
		self::assertSame( 'pending', ( new Booking_Repository() )->find( $id )['status'] );
	}

	/**
	 * Restoring a free booking fails when the event sold out meanwhile.
	 */
	public function test_reactivate_reports_sold_out(): void {
		$fixture = $this->fixture( 168, 1 );
		$first   = $this->booking( $fixture, $this->owner );

		Booking_Self_Service::cancel( $first );
		$this->booking( $fixture, $this->owner + 1 );

		$result = Booking_Self_Service::reactivate( $first );

		self::assertWPError( $result );
	}

	/**
	 * The booking list pages through the current user's bookings.
	 */
	public function test_pagination(): void {
		$fixture = $this->fixture( 168, 20 );

		for ( $i = 0; $i < 3; $i++ ) {
			$this->booking( $fixture, $this->owner );
		}

		wp_set_current_user( $this->owner );

		$first = Bookings::render( [ 'limit' => 2 ] );

		self::assertSame( 2, substr_count( $first, '<li class="reevents-booking' ) );
		self::assertStringContainsString( 'reevents-pagination', $first );
		self::assertStringContainsString( 'reevents_bookings_page=2', $first );

		$_GET['reevents_bookings_page'] = '2';

		$second = Bookings::render( [ 'limit' => 2 ] );

		self::assertSame( 1, substr_count( $second, '<li class="reevents-booking' ) );

		$_GET['reevents_bookings_page'] = '3';

		$last = Bookings::render( [ 'limit' => 2 ] );

		self::assertSame( 1, substr_count( $last, '<li class="reevents-booking' ) );
		self::assertStringNotContainsString( 'reevents-bookings-lookup', $last );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Test fixture value, restored below.
		$original_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Test fixture value.
		$_SERVER['REQUEST_URI'] = '/my-bookings/?reevents_booking_result=cancelled&reevents_bookings_page=2';

		$notice_page = Bookings::render( [ 'limit' => 2 ] );

		$_SERVER['REQUEST_URI'] = $original_uri;
		unset( $_GET['reevents_bookings_page'] );

		self::assertMatchesRegularExpression( '/<nav class="reevents-pagination".*?<\/nav>/s', $notice_page );
		preg_match( '/<nav class="reevents-pagination".*?<\/nav>/s', $notice_page, $pager );
		self::assertStringNotContainsString( 'reevents_booking_result', $pager[0] );
		self::assertStringContainsString( 'href="/my-bookings/"', $pager[0] );

		$off = Bookings::render(
			[
				'limit'    => 2,
				'paginate' => false,
			]
		);

		self::assertStringNotContainsString( 'reevents-pagination', $off );

		$keyed = Bookings::render(
			[
				'limit'          => 2,
				'pagination_key' => 'mine',
			]
		);

		self::assertStringContainsString( 'reevents_bookings_page_mine=2', $keyed );
	}

	/**
	 * The block falls back to the registry labels and heading.
	 */
	public function test_block_uses_registry_defaults(): void {
		$data = wp_json_file_decode( RE_EVENTS_DIR . 'blocks/src/bookings/block.json', [ 'associative' => true ] );

		self::assertIsArray( $data );

		$attributes = [];

		foreach ( (array) ( $data['attributes'] ?? [] ) as $name => $definition ) {
			$attributes[ (string) $name ] = $definition['default'] ?? null;
		}

		wp_set_current_user( 0 );

		$html = Widget_Registry::render( 'bookings', Blocks::prepare_attributes( $attributes ) );

		self::assertStringContainsString( 'reevents-bookings-heading', $html );
		self::assertStringContainsString( '>Find booking<', $html );

		$attributes['showHeading'] = false;
		$without                   = Widget_Registry::render( 'bookings', Blocks::prepare_attributes( $attributes ) );

		self::assertStringNotContainsString( 'reevents-bookings-heading', $without );
	}

	/**
	 * The lookup form keeps the subdirectory out of its action URL.
	 */
	public function test_current_url_strips_subdirectory(): void {
		$home   = 'http://example.com/subdir';
		$filter = static function ( $url, $path ) use ( $home ) {
			unset( $url );

			return $home . $path;
		};

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Test fixture value, restored below.
		$original = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';

		add_filter( 'home_url', $filter, 10, 2 );
		$_SERVER['REQUEST_URI'] = '/subdir/my-bookings/';

		$html = Bookings::render( [] );

		$_SERVER['REQUEST_URI'] = $original;
		remove_filter( 'home_url', $filter, 10 );

		self::assertStringContainsString( 'action="' . $home . '/my-bookings/"', $html );
		self::assertStringNotContainsString( 'subdir/subdir', $html );
	}

	/**
	 * A payment still processing blocks self-cancel.
	 */
	public function test_pending_payment_blocks_cancel(): void {
		$fixture = $this->fixture( 168, 5, 2500 );
		$id      = $this->booking( $fixture, $this->owner );
		( new Payment_Repository() )->create(
			[
				'booking_id'   => $id,
				'gateway'      => 'stripe',
				'gateway_ref'  => 'cs_test_pending',
				'status'       => 'pending',
				'amount_minor' => 2500,
				'currency'     => 'USD',
			]
		);

		$rules = Booking_Self_Service::rules( (array) ( new Booking_Repository() )->find( $id ) );

		self::assertFalse( $rules['can_cancel'] );
		self::assertSame( 'processing', $rules['cancel_reason'] );

		$result = Booking_Self_Service::cancel( $id );

		self::assertWPError( $result );
		self::assertSame( 'processing', $result->get_error_data()['reason'] );
	}

	/**
	 * The admin-post decision cancels for the owner.
	 */
	public function test_action_handler_cancels(): void {
		$fixture = $this->fixture();
		$id      = $this->booking( $fixture, $this->owner );
		wp_set_current_user( $this->owner );

		$decision = Booking_Actions::process(
			[
				'action'                 => 'reevents_booking_cancel',
				'booking_id'             => $id,
				'redirect_to'            => home_url( '/my-bookings/' ),
				'reevents_booking_nonce' => wp_create_nonce( 'reevents_booking_action_' . $id ),
			]
		);

		self::assertSame( 'cancelled', $decision['result'] );
		self::assertSame( 'cancelled', ( new Booking_Repository() )->find( $id )['status'] );
	}

	/**
	 * A bad nonce changes nothing.
	 */
	public function test_action_handler_rejects_bad_nonce(): void {
		$fixture = $this->fixture();
		$id      = $this->booking( $fixture, $this->owner );
		wp_set_current_user( $this->owner );

		$decision = Booking_Actions::process(
			[
				'action'                 => 'reevents_booking_cancel',
				'booking_id'             => $id,
				'redirect_to'            => home_url( '/my-bookings/' ),
				'reevents_booking_nonce' => 'invalid',
			]
		);

		self::assertSame( 'expired', $decision['reason'] );
		self::assertSame( 'confirmed', ( new Booking_Repository() )->find( $id )['status'] );
	}

	/**
	 * A guest with a valid nonce but the wrong email is refused.
	 */
	public function test_action_handler_rejects_wrong_email(): void {
		$fixture = $this->fixture();
		$id      = $this->booking( $fixture, 0, 1, 'guest@example.com' );
		$booking = ( new Booking_Repository() )->find( $id );
		wp_set_current_user( 0 );

		$decision = Booking_Actions::process(
			[
				'action'                 => 'reevents_booking_cancel',
				'booking_id'             => $id,
				'redirect_to'            => home_url( '/my-bookings/' ),
				'reevents_booking_nonce' => wp_create_nonce( 'reevents_booking_action_' . $id ),
				'reference'              => (string) $booking['reference'],
				'email'                  => 'wrong@example.com',
			]
		);

		self::assertSame( 'error', $decision['result'] );
		self::assertSame( 'confirmed', ( new Booking_Repository() )->find( $id )['status'] );
	}

	/**
	 * The admin-post decision restores a cancelled free booking.
	 */
	public function test_action_handler_reactivates(): void {
		$fixture = $this->fixture();
		$id      = $this->booking( $fixture, $this->owner );
		wp_set_current_user( $this->owner );
		Booking_Self_Service::cancel( $id );

		$decision = Booking_Actions::process(
			[
				'action'                 => 'reevents_booking_reactivate',
				'booking_id'             => $id,
				'redirect_to'            => home_url( '/my-bookings/' ),
				'reevents_booking_nonce' => wp_create_nonce( 'reevents_booking_action_' . $id ),
			]
		);

		self::assertSame( 'reactivated', $decision['result'] );
		self::assertSame( 'confirmed', ( new Booking_Repository() )->find( $id )['status'] );
	}

	/**
	 * Turning self-service off closes the REST reads too.
	 */
	public function test_rest_respects_self_service_switch(): void {
		$fixture = $this->fixture();
		$id      = $this->booking( $fixture, $this->owner );
		update_option( 'reevents_settings', [ 'self_service' => false ] );
		wp_set_current_user( $this->owner );

		$list = rest_do_request( new WP_REST_Request( 'GET', '/re-events/v1/my-bookings' ) );
		$item = rest_do_request( new WP_REST_Request( 'GET', '/re-events/v1/my-bookings/' . $id ) );

		self::assertSame( 403, $list->get_status() );
		self::assertSame( 403, $item->get_status() );
	}

	/**
	 * The manage link carries the booking and a signed key.
	 */
	public function test_manage_url(): void {
		update_option(
			'reevents_settings',
			[
				'self_service_page' => home_url( '/my-bookings/' ),
			]
		);

		$fixture = $this->fixture();
		$booking = ( new Booking_Repository() )->find( $this->booking( $fixture, $this->owner ) );
		$url     = Booking_Access::manage_url( (array) $booking );

		self::assertStringContainsString( 'reevents_booking=' . $booking['id'], $url );
		self::assertStringContainsString( 'reevents_key=' . Booking_Access::key( (array) $booking ), $url );
		self::assertContains( '{manage_url}', Booking_Emails::tokens() );
	}

	/**
	 * REST: the owner lists bookings, guests get 401.
	 */
	public function test_rest_list_requires_owner(): void {
		$fixture = $this->fixture();
		$this->booking( $fixture, $this->owner );

		wp_set_current_user( 0 );
		$guest = rest_do_request( new WP_REST_Request( 'GET', '/re-events/v1/my-bookings' ) );

		self::assertSame( 401, $guest->get_status() );

		wp_set_current_user( $this->owner );
		$response = rest_do_request( new WP_REST_Request( 'GET', '/re-events/v1/my-bookings' ) );

		self::assertSame( 200, $response->get_status() );
		self::assertCount( 1, $response->get_data() );
	}

	/**
	 * REST: lookup and cancel with reference plus email.
	 */
	public function test_rest_lookup_and_cancel(): void {
		$fixture = $this->fixture();
		$id      = $this->booking( $fixture, 0, 1, 'guest@example.com' );
		$booking = ( new Booking_Repository() )->find( $id );

		$request = new WP_REST_Request( 'GET', '/re-events/v1/my-bookings/lookup' );
		$request->set_query_params(
			[
				'reference' => $booking['reference'],
				'email'     => 'guest@example.com',
			]
		);

		$lookup = rest_do_request( $request );

		self::assertSame( 200, $lookup->get_status() );
		self::assertSame( $id, $lookup->get_data()['id'] );

		$cancel = new WP_REST_Request( 'PATCH', '/re-events/v1/my-bookings/' . $id );
		$cancel->set_body_params(
			[
				'reference' => $booking['reference'],
				'email'     => 'guest@example.com',
				'action'    => 'reactivate',
			]
		);

		$blocked = rest_do_request( $cancel );

		self::assertSame( 409, $blocked->get_status() );

		$cancel->set_body_params(
			[
				'reference' => $booking['reference'],
				'email'     => 'guest@example.com',
				'action'    => 'cancel',
			]
		);

		$done = rest_do_request( $cancel );

		self::assertSame( 200, $done->get_status() );
		self::assertSame( 'cancelled', $done->get_data()['status'] );
	}

	/**
	 * REST: a wrong email cannot cancel.
	 */
	public function test_rest_wrong_email_is_forbidden(): void {
		$fixture = $this->fixture();
		$id      = $this->booking( $fixture, 0, 1, 'guest@example.com' );
		$booking = ( new Booking_Repository() )->find( $id );

		$request = new WP_REST_Request( 'PATCH', '/re-events/v1/my-bookings/' . $id );
		$request->set_body_params(
			[
				'reference' => $booking['reference'],
				'email'     => 'wrong@example.com',
				'action'    => 'cancel',
			]
		);

		$response = rest_do_request( $request );

		self::assertSame( 403, $response->get_status() );
		self::assertSame( 'confirmed', ( new Booking_Repository() )->find( $id )['status'] );
	}

	/**
	 * The list query count does not grow with the number of bookings.
	 */
	public function test_query_count_is_flat(): void {
		$fixture = $this->fixture( 168, 50 );

		for ( $i = 0; $i < 5; $i++ ) {
			$this->booking( $fixture, $this->owner );
		}

		wp_set_current_user( $this->owner );

		$before = (int) $GLOBALS['wpdb']->num_queries;
		Bookings::render( [] );
		$five = (int) $GLOBALS['wpdb']->num_queries - $before;

		for ( $i = 0; $i < 5; $i++ ) {
			$this->booking( $fixture, $this->owner );
		}

		$before = (int) $GLOBALS['wpdb']->num_queries;
		Bookings::render( [] );
		$ten = (int) $GLOBALS['wpdb']->num_queries - $before;

		self::assertLessThanOrEqual( $five + 2, $ten );
		self::assertLessThanOrEqual( 20, $ten );
	}
}
