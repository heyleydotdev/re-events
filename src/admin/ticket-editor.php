<?php
/**
 * Ticket editor.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

use ReEvents\Data\Meta;
use ReEvents\Data\Ticket_Repository;
use ReEvents\Services\Currency;
use WP_Post;

/**
 * Adds a ticket table to the event editor.
 */
final class Ticket_Editor {

	/**
	 * Nonce action.
	 */
	private const NONCE = 'reevents_save_tickets';

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes_reevents_event', [ self::class, 'add_meta_box' ] );
		add_action( 'save_post_reevents_event', [ self::class, 'save' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	/**
	 * Add the meta box.
	 */
	public static function add_meta_box(): void {
		add_meta_box(
			'reevents-tickets',
			__( 'Tickets', 're-events' ),
			[ self::class, 'render' ],
			'reevents_event',
			'normal',
			'default'
		);
	}

	/**
	 * Load the row script on event screens.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'reevents_event' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'reevents-admin-tickets',
			plugins_url( 'assets/css/admin-tickets.css', RE_EVENTS_FILE ),
			[],
			RE_EVENTS_VERSION
		);

		wp_enqueue_script(
			'reevents-admin-tickets',
			plugins_url( 'assets/js/admin-tickets.js', RE_EVENTS_FILE ),
			[],
			RE_EVENTS_VERSION,
			true
		);
	}

	/**
	 * Render the ticket table.
	 *
	 * @param WP_Post $post Event post.
	 */
	public static function render( WP_Post $post ): void {
		$tickets  = ( new Ticket_Repository() )->for_event( $post->ID );
		$currency = (string) Settings::get( 'default_currency', 'USD' );

		wp_nonce_field( self::NONCE, 'reevents_tickets_nonce' );

		if ( isset( $_GET['reevents_ticket_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin notice flag.
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Some tickets could not be saved.', 're-events' ) . '</p></div>';
		}

		echo '<div class="reevents-tickets">';
		echo '<div id="reevents-tickets-list" data-next="' . esc_attr( (string) max( 1, count( $tickets ) ) ) . '">';

		$index = 0;

		foreach ( $tickets as $ticket ) {
			self::row( $index, $ticket, $currency );
			++$index;
		}

		if ( ! $tickets ) {
			self::row( 0, [], $currency );
		}

		echo '</div>';
		echo '<template id="reevents-ticket-template">';
		self::row( '__i__', [], $currency );
		echo '</template>';
		echo '<p><button type="button" class="button" id="reevents-add-ticket">' . esc_html__( 'Add ticket', 're-events' ) . '</button></p>';
		echo '<p class="description">' . esc_html__( 'Capacity 0 means unlimited. Sale dates use UTC and accept YYYY-MM-DD HH:MM:SS. Prices use a dot for decimals, for example 25.00.', 're-events' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render one ticket row.
	 *
	 * @param int|string           $index    Row index.
	 * @param array<string, mixed> $ticket   Ticket row.
	 * @param string               $currency Default currency.
	 */
	private static function row( $index, array $ticket, string $currency ): void {
		$name      = (string) ( $ticket['name'] ?? '' );
		$currency  = (string) ( $ticket['currency'] ?? $currency );
		$price     = isset( $ticket['price_minor'] ) ? Currency::amount( (int) $ticket['price_minor'], $currency ) : '0.00';
		$id        = (int) ( $ticket['id'] ?? 0 );
		$status    = (string) ( $ticket['status'] ?? 'active' );
		$name_attr = 'reevents_tickets[' . $index . ']';

		echo '<div class="reevents-ticket">';
		echo '<input type="hidden" name="' . esc_attr( $name_attr . '[id]' ) . '" value="' . esc_attr( (string) $id ) . '" />';
		echo '<input type="hidden" name="' . esc_attr( $name_attr . '[delete]' ) . '" value="0" />';
		echo '<div class="reevents-ticket-grid">';

		echo '<label class="reevents-ticket-field reevents-ticket-field-name"><span>' . esc_html__( 'Name', 're-events' ) . '</span><input type="text" name="' . esc_attr( $name_attr . '[name]' ) . '" value="' . esc_attr( $name ) . '" /></label>';
		echo '<label class="reevents-ticket-field"><span>' . esc_html__( 'Price', 're-events' ) . '</span><input type="text" inputmode="decimal" name="' . esc_attr( $name_attr . '[price]' ) . '" value="' . esc_attr( $price ) . '" /></label>';
		echo '<label class="reevents-ticket-field"><span>' . esc_html__( 'Currency', 're-events' ) . '</span><input type="text" maxlength="3" name="' . esc_attr( $name_attr . '[currency]' ) . '" value="' . esc_attr( $currency ) . '" /></label>';

		foreach ( [
			'capacity'        => __( 'Capacity', 're-events' ),
			'min_per_booking' => __( 'Min seats', 're-events' ),
			'max_per_booking' => __( 'Max seats', 're-events' ),
		] as $key => $label ) {
			$value = (int) ( $ticket[ $key ] ?? ( 'min_per_booking' === $key ? 1 : 0 ) );
			echo '<label class="reevents-ticket-field"><span>' . esc_html( $label ) . '</span><input type="number" min="0" name="' . esc_attr( $name_attr . '[' . $key . ']' ) . '" value="' . esc_attr( (string) $value ) . '" /></label>';
		}

		$sale_labels = [
			'sale_start_utc' => __( 'Sale start (UTC)', 're-events' ),
			'sale_end_utc'   => __( 'Sale end (UTC)', 're-events' ),
		];

		foreach ( $sale_labels as $key => $label ) {
			echo '<label class="reevents-ticket-field"><span>' . esc_html( $label ) . '</span><input type="text" placeholder="YYYY-MM-DD HH:MM:SS" name="' . esc_attr( $name_attr . '[' . $key . ']' ) . '" value="' . esc_attr( (string) ( $ticket[ $key ] ?? '' ) ) . '" /></label>';
		}

		echo '<label class="reevents-ticket-field"><span>' . esc_html__( 'Status', 're-events' ) . '</span><select name="' . esc_attr( $name_attr . '[status]' ) . '">';
		echo '<option value="active"' . selected( $status, 'active', false ) . '>' . esc_html__( 'Active', 're-events' ) . '</option>';
		echo '<option value="inactive"' . selected( $status, 'inactive', false ) . '>' . esc_html__( 'Inactive', 're-events' ) . '</option>';
		echo '</select></label>';

		echo '</div>';
		echo '<p class="reevents-ticket-actions"><button type="button" class="button-link reevents-remove-ticket">' . esc_html__( 'Remove', 're-events' ) . '</button></p>';
		echo '</div>';
	}

	/**
	 * Save the submitted tickets.
	 *
	 * @param int $post_id Event ID.
	 */
	public static function save( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['reevents_tickets_nonce'] ) ) {
			return;
		}

		$nonce = is_string( $_POST['reevents_tickets_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['reevents_tickets_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$rows = isset( $_POST['reevents_tickets'] ) && is_array( $_POST['reevents_tickets'] ) ? wp_unslash( $_POST['reevents_tickets'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field below.

		// The block editor can submit the same meta box payload twice.
		$lock_key = 'reevents_tickets_saved_' . $post_id;
		$payload  = md5( (string) wp_json_encode( $rows ) );

		if ( get_transient( $lock_key ) === $payload ) {
			return;
		}

		set_transient( $lock_key, $payload, 30 );

		$repo = new Ticket_Repository();
		$fail = false;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$id = (int) ( $row['id'] ?? 0 );

			if ( $id ) {
				$existing = $repo->find( $id );

				if ( ! $existing || (int) $existing['event_id'] !== $post_id ) {
					$fail = true;
					continue;
				}
			}

			if ( ! empty( $row['delete'] ) ) {
				if ( $id && ! $repo->delete( $id ) ) {
					$fail = true;
				}

				continue;
			}

			$name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );

			if ( '' === $name ) {
				continue;
			}

			$code     = sanitize_text_field( (string) ( $row['currency'] ?? '' ) );
			$currency = '' !== $code ? Currency::sanitize_code( $code ) : (string) Settings::get( 'default_currency', 'USD' );
			$price    = str_replace( ' ', '', sanitize_text_field( (string) ( $row['price'] ?? '0' ) ) );
			$price    = str_contains( $price, ',' ) && ! str_contains( $price, '.' ) ? str_replace( ',', '.', $price ) : str_replace( ',', '', $price );

			if ( ! is_numeric( $price ) || (float) $price < 0 ) {
				$fail = true;
				continue;
			}

			$status = sanitize_key( (string) ( $row['status'] ?? 'active' ) );
			$result = $repo->save(
				[
					'id'              => $id,
					'event_id'        => $post_id,
					'name'            => $name,
					'price_minor'     => Currency::minor_units( $price, $currency ),
					'currency'        => $currency,
					'capacity'        => absint( $row['capacity'] ?? 0 ),
					'min_per_booking' => max( 1, absint( $row['min_per_booking'] ?? 1 ) ),
					'max_per_booking' => absint( $row['max_per_booking'] ?? 0 ),
					'sale_start_utc'  => Meta::sanitize_datetime( (string) ( $row['sale_start_utc'] ?? '' ) ),
					'sale_end_utc'    => Meta::sanitize_datetime( (string) ( $row['sale_end_utc'] ?? '' ) ),
					'status'          => in_array( $status, [ 'active', 'inactive' ], true ) ? $status : 'active',
				]
			);

			if ( is_wp_error( $result ) ) {
				$fail = true;
			}
		}

		if ( $fail ) {
			add_filter(
				'redirect_post_location',
				static function ( string $location ): string {
					return add_query_arg( 'reevents_ticket_error', '1', $location );
				}
			);
		}
	}
}
