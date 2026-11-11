<?php
/**
 * Privacy tools.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents;

use ReEvents\Admin\Settings;
use ReEvents\Data\Booking_Repository;

/**
 * Registers the attendee exporter and eraser.
 */
final class Privacy {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', [ self::class, 'exporters' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ self::class, 'erasers' ] );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array<string, array<string, mixed>> $exporters Exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public static function exporters( array $exporters ): array {
		$exporters['reevents-attendees'] = [
			'exporter_friendly_name' => __( 'ReEvents bookings', 're-events' ),
			'callback'               => [ self::class, 'export' ],
		];

		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array<string, array<string, mixed>> $erasers Erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public static function erasers( array $erasers ): array {
		$erasers['reevents-attendees'] = [
			'eraser_friendly_name' => __( 'ReEvents bookings', 're-events' ),
			'callback'             => [ self::class, 'erase' ],
		];

		return $erasers;
	}

	/**
	 * Export attendee data for an email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array{data:array<int, array<string, mixed>>,done:bool}
	 */
	public static function export( string $email, int $page = 1 ): array {
		$limit = 100;
		$rows  = ( new Booking_Repository() )->attendees_by_email( $email, $limit, ( max( 1, $page ) - 1 ) * $limit );
		$items = [];

		foreach ( $rows as $row ) {
			$data = [
				[
					'name'  => __( 'Event', 're-events' ),
					'value' => (string) ( $row['event_title'] ?? '' ),
				],
				[
					'name'  => __( 'Booking reference', 're-events' ),
					'value' => (string) ( $row['reference'] ?? '' ),
				],
				[
					'name'  => __( 'Booking status', 're-events' ),
					'value' => (string) ( $row['booking_status'] ?? '' ),
				],
				[
					'name'  => __( 'Ticket', 're-events' ),
					'value' => (string) ( $row['ticket_name'] ?? '' ),
				],
				[
					'name'  => __( 'Name', 're-events' ),
					'value' => (string) ( $row['name'] ?? '' ),
				],
				[
					'name'  => __( 'Email', 're-events' ),
					'value' => (string) ( $row['email'] ?? '' ),
				],
			];

			$fields = json_decode( (string) ( $row['fields_json'] ?? '' ), true );
			$labels = Settings::attendee_fields();

			foreach ( is_array( $fields ) ? $fields : [] as $key => $value ) {
				if ( ! is_scalar( $value ) ) {
					continue;
				}

				$data[] = [
					'name'  => (string) ( $labels[ $key ]['label'] ?? $key ),
					'value' => (string) $value,
				];
			}

			$items[] = [
				'group_id'    => 'reevents-attendees',
				'group_label' => __( 'ReEvents bookings', 're-events' ),
				'item_id'     => 'attendee-' . (int) $row['id'],
				'data'        => $data,
			];
		}

		return [
			'data' => $items,
			'done' => count( $rows ) < $limit,
		];
	}

	/**
	 * Erase attendee data for an email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array{items_removed:bool,items_retained:bool,messages:array<int, string>,done:bool}
	 */
	public static function erase( string $email, int $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress passes the page number; erasure always reads the first page.
		$limit = 100;
		// Rows stop matching after anonymization, so always read the first page.
		$rows = ( new Booking_Repository() )->attendees_by_email( $email, $limit );

		if ( ! $rows ) {
			return [
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => [],
				'done'           => true,
			];
		}

		$removed = ( new Booking_Repository() )->anonymize_attendees( wp_list_pluck( $rows, 'id' ) );

		return [
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => [
				__( 'Attendee details were removed. Booking rows stay for capacity and accounting.', 're-events' ),
			],
			'done'           => count( $rows ) < $limit,
		];
	}
}
