<?php
/**
 * Event value object.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Domain;

/**
 * Event data independent of WordPress.
 */
final class Event {

	/**
	 * Constructor.
	 *
	 * @param int      $id            Post ID.
	 * @param string   $title         Title.
	 * @param string   $start_utc     Start in UTC (Y-m-d H:i:s).
	 * @param string   $end_utc       End in UTC (Y-m-d H:i:s).
	 * @param string   $timezone      IANA timezone.
	 * @param bool     $all_day       All-day flag.
	 * @param string   $rrule         Recurrence rule.
	 * @param string[] $exdates       Excluded dates.
	 * @param int      $venue_id      Venue post ID.
	 * @param int[]    $organizer_ids Organizer post IDs.
	 * @param int      $capacity      Capacity.
	 * @param string   $online_url    Online URL.
	 * @param string   $status        Event status.
	 */
	public function __construct(
		public int $id = 0,
		public string $title = '',
		public string $start_utc = '',
		public string $end_utc = '',
		public string $timezone = 'UTC',
		public bool $all_day = false,
		public string $rrule = '',
		public array $exdates = [],
		public int $venue_id = 0,
		public array $organizer_ids = [],
		public int $capacity = 0,
		public string $online_url = '',
		public string $status = 'scheduled'
	) {}
}
