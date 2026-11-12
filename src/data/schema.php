<?php
/**
 * Database schema.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Data;

/**
 * Creates and upgrades plugin tables.
 */
final class Schema {

	/**
	 * Schema version.
	 */
	public const VERSION = 6;

	/**
	 * Schema version option.
	 */
	private const OPTION = 'reevents_db_version';

	/**
	 * Install tables when the schema version changes.
	 */
	public static function maybe_install(): void {
		if ( (int) get_option( self::OPTION, 0 ) >= self::VERSION ) {
			return;
		}

		self::install();
	}

	/**
	 * Create or update tables.
	 */
	public static function install(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( self::sql() );

		if ( ! self::tables_exist() ) {
			return;
		}

		self::backfill_refunds();
		update_option( self::OPTION, self::VERSION );
	}

	/**
	 * Check that all plugin tables exist.
	 */
	private static function tables_exist(): bool {
		global $wpdb;

		foreach ( self::tables() as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Install-time schema check.
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );

			if ( $found !== $table ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Copy refunded amounts from the old JSON payload into the new column.
	 */
	private static function backfill_refunds(): void {
		global $wpdb;

		$table   = $wpdb->prefix . 'reevents_payments';
		$like    = '%' . $wpdb->esc_like( '"refunded_minor"' ) . '%';
		$last_id = 0;

		do {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Install-time data migration.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, data_json FROM {$table} WHERE id > %d AND data_json LIKE %s ORDER BY id ASC LIMIT 500",
					$last_id,
					$like
				),
				ARRAY_A
			);
			// phpcs:enable

			foreach ( is_array( $rows ) ? $rows : [] as $row ) {
				$last_id  = max( $last_id, (int) ( $row['id'] ?? 0 ) );
				$data     = json_decode( (string) ( $row['data_json'] ?? '[]' ), true );
				$refunded = is_array( $data ) ? max( 0, (int) ( $data['refunded_minor'] ?? 0 ) ) : 0;

				if ( $refunded > 0 ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Install-time data migration.
					$wpdb->update( $table, [ 'refunded_minor' => $refunded ], [ 'id' => (int) $row['id'] ], [ '%d' ], [ '%d' ] );
				}
			}

			$count = is_array( $rows ) ? count( $rows ) : 0;
		} while ( 500 === $count );
	}

	/**
	 * Plugin table names.
	 *
	 * @return string[]
	 */
	private static function tables(): array {
		global $wpdb;

		return [
			$wpdb->prefix . 'reevents_occurrences',
			$wpdb->prefix . 'reevents_tickets',
			$wpdb->prefix . 'reevents_bookings',
			$wpdb->prefix . 'reevents_attendees',
			$wpdb->prefix . 'reevents_payments',
		];
	}

	/**
	 * Build the table SQL.
	 */
	private static function sql(): string {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix;

		$occurrences = "CREATE TABLE {$prefix}reevents_occurrences (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			start_utc datetime NOT NULL,
			end_utc datetime NOT NULL,
			timezone varchar(64) NOT NULL DEFAULT 'UTC',
			timeslot_key varchar(64) NOT NULL DEFAULT '',
			capacity int(11) unsigned NOT NULL DEFAULT 0,
			sold int(11) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'scheduled',
			PRIMARY KEY  (id),
			UNIQUE KEY event_start_slot (event_id,start_utc,timeslot_key),
			KEY event_start (event_id,start_utc),
			KEY start_end (start_utc,end_utc)
		) {$charset};";

		$tickets = "CREATE TABLE {$prefix}reevents_tickets (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			name varchar(191) NOT NULL DEFAULT '',
			description text NULL,
			price_minor bigint(20) NOT NULL DEFAULT 0,
			currency char(3) NOT NULL DEFAULT 'USD',
			capacity int(11) unsigned NOT NULL DEFAULT 0,
			sold int(11) unsigned NOT NULL DEFAULT 0,
			min_per_booking int(11) unsigned NOT NULL DEFAULT 1,
			max_per_booking int(11) unsigned NOT NULL DEFAULT 0,
			sale_start_utc datetime DEFAULT NULL,
			sale_end_utc datetime DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			PRIMARY KEY  (id),
			KEY event_id (event_id),
			KEY event_status (event_id,status)
		) {$charset};";

		$bookings = "CREATE TABLE {$prefix}reevents_bookings (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reference varchar(64) DEFAULT NULL,
			event_id bigint(20) unsigned NOT NULL,
			occurrence_id bigint(20) unsigned NOT NULL DEFAULT 0,
			ticket_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			quantity int(11) unsigned NOT NULL DEFAULT 1,
			status varchar(20) NOT NULL DEFAULT 'pending',
			total_minor bigint(20) NOT NULL DEFAULT 0,
			currency char(3) NOT NULL DEFAULT 'USD',
			gateway varchar(50) NOT NULL DEFAULT '',
			gateway_ref varchar(191) NOT NULL DEFAULT '',
			created_utc datetime NOT NULL,
			updated_utc datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY reference (reference),
			KEY event_occurrence (event_id,occurrence_id),
			KEY ticket_id (ticket_id),
			KEY status (status),
			KEY status_currency_created (status,currency,created_utc),
			KEY user_id (user_id)
		) {$charset};";

		$attendees = "CREATE TABLE {$prefix}reevents_attendees (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			ticket_id bigint(20) unsigned NOT NULL DEFAULT 0,
			name varchar(191) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			fields_json longtext NULL,
			checked_in tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY ticket_id (ticket_id),
			KEY email (email)
		) {$charset};";

		$payments = "CREATE TABLE {$prefix}reevents_payments (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			gateway varchar(50) NOT NULL DEFAULT '',
			gateway_ref varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			amount_minor bigint(20) NOT NULL DEFAULT 0,
			refunded_minor bigint(20) NOT NULL DEFAULT 0,
			currency char(3) NOT NULL DEFAULT 'USD',
			data_json longtext NULL,
			created_utc datetime NOT NULL,
			updated_utc datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY gateway_ref (gateway_ref),
			KEY status (status)
		) {$charset};";

		return $occurrences . "\n" . $tickets . "\n" . $bookings . "\n" . $attendees . "\n" . $payments;
	}
}
