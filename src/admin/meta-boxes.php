<?php
/**
 * Event meta boxes.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

use DateTimeImmutable;
use DateTimeZone;
use ReEvents\Data\Event_Repository;
use ReEvents\Data\Meta;
use ReEvents\Domain\Event;
use ReEvents\Domain\Recurrence_Rule;
use ReEvents\Services\Event_Service;
use WP_Post;

/**
 * Registers event meta boxes.
 */
final class Meta_Boxes {

	/**
	 * Cached event data per post.
	 *
	 * @var array<int, array{
	 *     event: Event,
	 *     timezone: string,
	 *     rule: array{freq:string,interval:int,weekdays:string[],monthday:int,end:string,until:string,count:int},
	 *     monthday: int,
	 *     input_type: string,
	 *     weekdays: array<string, string>,
	 *     venues: WP_Post[],
	 *     organizers: WP_Post[]
	 * }>
	 */
	private static array $data = [];

	/**
	 * Guard against recursive saves.
	 *
	 * @var bool
	 */
	private static bool $saving = false;

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes_reevents_event', [ self::class, 'add' ] );
		add_action( 'save_post_reevents_event', [ self::class, 'save' ] );
	}

	/**
	 * Add the event meta boxes.
	 */
	public static function add(): void {
		add_meta_box(
			'reevents-event-when',
			__( 'Date & time', 're-events' ),
			[ self::class, 'render_when' ],
			'reevents_event',
			'normal',
			'high'
		);

		add_meta_box(
			'reevents-event-recurrence',
			__( 'Recurrence', 're-events' ),
			[ self::class, 'render_recurrence' ],
			'reevents_event',
			'normal',
			'high'
		);

		add_meta_box(
			'reevents-event-location',
			__( 'Location', 're-events' ),
			[ self::class, 'render_location' ],
			'reevents_event',
			'normal',
			'default'
		);

		add_meta_box(
			'reevents-event-details',
			__( 'Details', 're-events' ),
			[ self::class, 'render_details' ],
			'reevents_event',
			'normal',
			'default'
		);

		add_meta_box(
			'reevents-event-timeslots',
			__( 'Timeslots', 're-events' ),
			[ self::class, 'render_timeslots' ],
			'reevents_event',
			'normal',
			'default'
		);
	}

	/**
	 * Render the date and time box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function render_when( WP_Post $post ): void {
		$data     = self::data( $post );
		$event    = $data['event'];
		$timezone = $data['timezone'];

		wp_nonce_field( 'reevents_event_details', 'reevents_event_details_nonce' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="reevents-start"><?php esc_html_e( 'Starts', 're-events' ); ?></label></th>
				<td>
					<input type="datetime-local" id="reevents-start" name="reevents_start" value="<?php echo esc_attr( self::to_local_input( $event->start_utc, $timezone ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="reevents-end-time"><?php esc_html_e( 'Ends', 're-events' ); ?></label></th>
				<td>
					<input type="datetime-local" id="reevents-end-time" name="reevents_end" value="<?php echo esc_attr( self::to_local_input( $event->end_utc, $timezone ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="reevents-timezone"><?php esc_html_e( 'Timezone', 're-events' ); ?></label></th>
				<td>
					<select id="reevents-timezone" name="reevents_timezone">
						<?php foreach ( timezone_identifiers_list() as $zone ) : ?>
							<option value="<?php echo esc_attr( $zone ); ?>" <?php selected( $timezone, $zone ); ?>><?php echo esc_html( $zone ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: link to the ReEvents settings page. */
								__( 'Defaults to the ReEvents settings. Change it for this event only. %s', 're-events' ),
								'<a href="' . esc_url( admin_url( 'admin.php?page=reevents-settings&tab=general' ) ) . '">' . esc_html__( 'Edit default', 're-events' ) . '</a>'
							)
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'All day', 're-events' ); ?></th>
				<td>
					<label>
						<input type="checkbox" id="reevents-all-day" name="reevents_all_day" value="1" <?php checked( $event->all_day ); ?> />
						<?php esc_html_e( 'This event lasts all day.', 're-events' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the recurrence box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function render_recurrence( WP_Post $post ): void {
		$data       = self::data( $post );
		$event      = $data['event'];
		$rule       = $data['rule'];
		$monthday   = $data['monthday'];
		$input_type = $data['input_type'];
		$weekdays   = $data['weekdays'];
		$timezone   = $data['timezone'];

		wp_nonce_field( 'reevents_event_details', 'reevents_event_details_nonce' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="reevents-repeat"><?php esc_html_e( 'Repeat', 're-events' ); ?></label></th>
				<td>
					<select id="reevents-repeat" name="reevents_repeat">
						<option value="none" <?php selected( $rule['freq'], 'none' ); ?>><?php esc_html_e( 'Does not repeat', 're-events' ); ?></option>
						<option value="daily" <?php selected( $rule['freq'], 'daily' ); ?>><?php esc_html_e( 'Daily', 're-events' ); ?></option>
						<option value="weekly" <?php selected( $rule['freq'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 're-events' ); ?></option>
						<option value="monthly" <?php selected( $rule['freq'], 'monthly' ); ?>><?php esc_html_e( 'Monthly', 're-events' ); ?></option>
						<option value="yearly" <?php selected( $rule['freq'], 'yearly' ); ?>><?php esc_html_e( 'Yearly', 're-events' ); ?></option>
					</select>
				</td>
			</tr>
			<tr id="reevents-interval-row">
				<th scope="row"><label for="reevents-interval"><?php esc_html_e( 'Interval', 're-events' ); ?></label></th>
				<td>
					<label>
						<?php esc_html_e( 'Every', 're-events' ); ?>
						<input type="number" id="reevents-interval" name="reevents_interval" min="1" step="1" value="<?php echo esc_attr( (string) $rule['interval'] ); ?>" />
						<span id="reevents-interval-unit"><?php esc_html_e( 'week(s)', 're-events' ); ?></span>
					</label>
				</td>
			</tr>
			<tr id="reevents-weekly-row">
				<th scope="row"><?php esc_html_e( 'Repeat on', 're-events' ); ?></th>
				<td>
					<?php foreach ( $weekdays as $value => $label ) : ?>
						<label class="reevents-weekday">
							<input type="checkbox" name="reevents_weekdays[]" value="<?php echo esc_attr( $value ); ?>" <?php checked( in_array( $value, $rule['weekdays'], true ) ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr id="reevents-monthly-row">
				<th scope="row"><label for="reevents-monthday"><?php esc_html_e( 'Monthly on day', 're-events' ); ?></label></th>
				<td>
					<input type="number" id="reevents-monthday" name="reevents_monthday" min="1" max="31" step="1" value="<?php echo esc_attr( (string) $monthday ); ?>" />
				</td>
			</tr>
			<tr id="reevents-end-row">
				<th scope="row"><label for="reevents-end"><?php esc_html_e( 'Ends', 're-events' ); ?></label></th>
				<td>
					<select id="reevents-end" name="reevents_recurrence_end">
						<option value="never" <?php selected( $rule['end'], 'never' ); ?>><?php esc_html_e( 'Never', 're-events' ); ?></option>
						<option value="until" <?php selected( $rule['end'], 'until' ); ?>><?php esc_html_e( 'On date', 're-events' ); ?></option>
						<option value="count" <?php selected( $rule['end'], 'count' ); ?>><?php esc_html_e( 'After occurrences', 're-events' ); ?></option>
					</select>
				</td>
			</tr>
			<tr id="reevents-until-row">
				<th scope="row"><label for="reevents-until"><?php esc_html_e( 'End date', 're-events' ); ?></label></th>
				<td>
					<input type="date" id="reevents-until" name="reevents_until" value="<?php echo esc_attr( $rule['until'] ); ?>" />
				</td>
			</tr>
			<tr id="reevents-count-row">
				<th scope="row"><label for="reevents-count"><?php esc_html_e( 'Occurrences', 're-events' ); ?></label></th>
				<td>
					<input type="number" id="reevents-count" name="reevents_count" min="1" step="1" value="<?php echo esc_attr( (string) ( $rule['count'] > 0 ? $rule['count'] : 1 ) ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Advanced', 're-events' ); ?></th>
				<td>
					<details>
						<summary><?php esc_html_e( 'Advanced recurrence rule', 're-events' ); ?></summary>
						<p class="description"><?php esc_html_e( 'Leave empty to use the fields above. A value here overrides them.', 're-events' ); ?></p>
						<input type="text" id="reevents-rrule" name="reevents_rrule" class="regular-text" value="<?php echo esc_attr( $event->rrule ); ?>" placeholder="FREQ=WEEKLY;INTERVAL=1" />
					</details>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Excluded occurrences', 're-events' ); ?></th>
				<td>
					<div id="reevents-exdates">
						<?php foreach ( $event->exdates as $exdate ) : ?>
							<div class="reevents-exdate-row">
								<input type="<?php echo esc_attr( $input_type ); ?>" name="reevents_exdates[]" value="<?php echo esc_attr( $event->all_day ? self::to_local_date( $exdate, $timezone ) : self::to_local_input( $exdate, $timezone ) ); ?>" />
								<button type="button" class="button reevents-remove-exdate"><?php esc_html_e( 'Remove', 're-events' ); ?></button>
							</div>
						<?php endforeach; ?>
						<?php if ( empty( $event->exdates ) ) : ?>
							<div class="reevents-exdate-row">
								<input type="<?php echo esc_attr( $input_type ); ?>" name="reevents_exdates[]" value="" />
								<button type="button" class="button reevents-remove-exdate"><?php esc_html_e( 'Remove', 're-events' ); ?></button>
							</div>
						<?php endif; ?>
					</div>
					<p>
						<button type="button" class="button" id="reevents-add-exdate"><?php esc_html_e( 'Add occurrence', 're-events' ); ?></button>
					</p>
					<p class="description"><?php esc_html_e( 'Skip specific occurrences of this recurrence.', 're-events' ); ?></p>
					<template id="reevents-exdate-template">
						<div class="reevents-exdate-row">
							<input type="<?php echo esc_attr( $input_type ); ?>" name="reevents_exdates[]" value="" />
							<button type="button" class="button reevents-remove-exdate"><?php esc_html_e( 'Remove', 're-events' ); ?></button>
						</div>
					</template>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Added occurrences', 're-events' ); ?></th>
				<td>
					<div id="reevents-rdates">
						<?php foreach ( $event->rdates as $rdate ) : ?>
							<div class="reevents-rdate-row">
								<input type="<?php echo esc_attr( $input_type ); ?>" name="reevents_rdates[]" value="<?php echo esc_attr( $event->all_day ? self::to_local_date( $rdate, $timezone ) : self::to_local_input( $rdate, $timezone ) ); ?>" />
								<button type="button" class="button reevents-remove-rdate"><?php esc_html_e( 'Remove', 're-events' ); ?></button>
							</div>
						<?php endforeach; ?>
						<?php if ( empty( $event->rdates ) ) : ?>
							<div class="reevents-rdate-row">
								<input type="<?php echo esc_attr( $input_type ); ?>" name="reevents_rdates[]" value="" />
								<button type="button" class="button reevents-remove-rdate"><?php esc_html_e( 'Remove', 're-events' ); ?></button>
							</div>
						<?php endif; ?>
					</div>
					<p>
						<button type="button" class="button" id="reevents-add-rdate"><?php esc_html_e( 'Add occurrence', 're-events' ); ?></button>
					</p>
					<p class="description"><?php esc_html_e( 'Add one-off occurrences outside the recurrence pattern.', 're-events' ); ?></p>
					<template id="reevents-rdate-template">
						<div class="reevents-rdate-row">
							<input type="<?php echo esc_attr( $input_type ); ?>" name="reevents_rdates[]" value="" />
							<button type="button" class="button reevents-remove-rdate"><?php esc_html_e( 'Remove', 're-events' ); ?></button>
						</div>
					</template>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the location box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function render_location( WP_Post $post ): void {
		$data   = self::data( $post );
		$event  = $data['event'];
		$venues = $data['venues'];

		wp_nonce_field( 'reevents_event_details', 'reevents_event_details_nonce' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="reevents-venue"><?php esc_html_e( 'Venue', 're-events' ); ?></label></th>
				<td>
					<?php if ( empty( $venues ) ) : ?>
						<p class="description">
							<?php esc_html_e( 'No venues yet.', 're-events' ); ?>
							<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=reevents_venue' ) ); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Add a venue', 're-events' ); ?>
							</a>
						</p>
					<?php else : ?>
						<select id="reevents-venue" name="reevents_venue_id">
							<option value="0"><?php esc_html_e( 'None', 're-events' ); ?></option>
							<?php foreach ( $venues as $venue ) : ?>
								<option value="<?php echo esc_attr( (string) $venue->ID ); ?>" <?php selected( $event->venue_id, $venue->ID ); ?>>
									<?php echo esc_html( $venue->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Online links', 're-events' ); ?></th>
				<td>
					<div id="reevents-online-urls">
						<?php foreach ( $event->online_urls as $link ) : ?>
							<div class="reevents-url-row">
								<input type="text" name="reevents_online_urls[label][]" value="<?php echo esc_attr( $link['label'] ); ?>" placeholder="<?php esc_attr_e( 'Label', 're-events' ); ?>" />
								<input type="url" name="reevents_online_urls[url][]" class="regular-text" value="<?php echo esc_attr( $link['url'] ); ?>" placeholder="https://" />
								<button type="button" class="button reevents-remove-url"><?php esc_html_e( 'Remove', 're-events' ); ?></button>
							</div>
						<?php endforeach; ?>
						<?php if ( empty( $event->online_urls ) ) : ?>
							<div class="reevents-url-row">
								<input type="text" name="reevents_online_urls[label][]" value="" placeholder="<?php esc_attr_e( 'Label', 're-events' ); ?>" />
								<input type="url" name="reevents_online_urls[url][]" class="regular-text" value="" placeholder="https://" />
								<button type="button" class="button reevents-remove-url"><?php esc_html_e( 'Remove', 're-events' ); ?></button>
							</div>
						<?php endif; ?>
					</div>
					<p>
						<button type="button" class="button" id="reevents-add-url"><?php esc_html_e( 'Add link', 're-events' ); ?></button>
					</p>
					<p class="description"><?php esc_html_e( 'Optional. Add join or watch links for online and hybrid events.', 're-events' ); ?></p>
					<template id="reevents-url-template">
						<div class="reevents-url-row">
							<input type="text" name="reevents_online_urls[label][]" value="" placeholder="<?php esc_attr_e( 'Label', 're-events' ); ?>" />
							<input type="url" name="reevents_online_urls[url][]" class="regular-text" value="" placeholder="https://" />
							<button type="button" class="button reevents-remove-url"><?php esc_html_e( 'Remove', 're-events' ); ?></button>
						</div>
					</template>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the details box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function render_details( WP_Post $post ): void {
		$data       = self::data( $post );
		$event      = $data['event'];
		$organizers = $data['organizers'];

		wp_nonce_field( 'reevents_event_details', 'reevents_event_details_nonce' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="reevents-organizers"><?php esc_html_e( 'Organizers', 're-events' ); ?></label></th>
				<td>
					<?php if ( empty( $organizers ) ) : ?>
						<p class="description">
							<?php esc_html_e( 'No organizers yet.', 're-events' ); ?>
							<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=reevents_organizer' ) ); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Add an organizer', 're-events' ); ?>
							</a>
						</p>
					<?php else : ?>
						<select id="reevents-organizers" name="reevents_organizer_ids[]" multiple size="5">
							<?php foreach ( $organizers as $organizer ) : ?>
								<option value="<?php echo esc_attr( (string) $organizer->ID ); ?>" <?php selected( in_array( $organizer->ID, $event->organizer_ids, true ), true ); ?>>
									<?php echo esc_html( $organizer->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="reevents-capacity"><?php esc_html_e( 'Capacity', 're-events' ); ?></label></th>
				<td>
					<input type="number" id="reevents-capacity" name="reevents_capacity" min="0" step="1" value="<?php echo esc_attr( (string) $event->capacity ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="reevents-status"><?php esc_html_e( 'Status', 're-events' ); ?></label></th>
				<td>
					<select id="reevents-status" name="reevents_status">
						<option value="scheduled" <?php selected( $event->status, 'scheduled' ); ?>><?php esc_html_e( 'Scheduled', 're-events' ); ?></option>
						<option value="cancelled" <?php selected( $event->status, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 're-events' ); ?></option>
					</select>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the timeslots box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function render_timeslots( WP_Post $post ): void {
		$data  = self::data( $post );
		$event = $data['event'];

		wp_nonce_field( 'reevents_event_details', 'reevents_event_details_nonce' );
		?>
		<div id="reevents-timeslots">
			<?php foreach ( $event->timeslots as $slot ) : ?>
				<div class="reevents-timeslot-row">
					<input type="time" name="reevents_timeslots[start][]" value="<?php echo esc_attr( $slot['start'] ); ?>" aria-label="<?php esc_attr_e( 'Start', 're-events' ); ?>" />
					<input type="time" name="reevents_timeslots[end][]" value="<?php echo esc_attr( $slot['end'] ); ?>" aria-label="<?php esc_attr_e( 'End', 're-events' ); ?>" />
					<input type="number" name="reevents_timeslots[capacity][]" min="0" step="1" value="<?php echo esc_attr( (string) $slot['capacity'] ); ?>" placeholder="<?php esc_attr_e( 'Capacity', 're-events' ); ?>" />
					<button type="button" class="button reevents-remove-timeslot"><?php esc_html_e( 'Remove', 're-events' ); ?></button>
				</div>
			<?php endforeach; ?>
			<?php if ( empty( $event->timeslots ) ) : ?>
				<div class="reevents-timeslot-row">
					<input type="time" name="reevents_timeslots[start][]" value="" aria-label="<?php esc_attr_e( 'Start', 're-events' ); ?>" />
					<input type="time" name="reevents_timeslots[end][]" value="" aria-label="<?php esc_attr_e( 'End', 're-events' ); ?>" />
					<input type="number" name="reevents_timeslots[capacity][]" min="0" step="1" value="" placeholder="<?php esc_attr_e( 'Capacity', 're-events' ); ?>" />
					<button type="button" class="button reevents-remove-timeslot"><?php esc_html_e( 'Remove', 're-events' ); ?></button>
				</div>
			<?php endif; ?>
		</div>
		<p>
			<button type="button" class="button" id="reevents-add-timeslot"><?php esc_html_e( 'Add timeslot', 're-events' ); ?></button>
		</p>
		<p class="description"><?php esc_html_e( 'Optional. Split each event date into bookable timeslots.', 're-events' ); ?></p>
		<template id="reevents-timeslot-template">
			<div class="reevents-timeslot-row">
				<input type="time" name="reevents_timeslots[start][]" value="" aria-label="<?php esc_attr_e( 'Start', 're-events' ); ?>" />
				<input type="time" name="reevents_timeslots[end][]" value="" aria-label="<?php esc_attr_e( 'End', 're-events' ); ?>" />
				<input type="number" name="reevents_timeslots[capacity][]" min="0" step="1" value="" placeholder="<?php esc_attr_e( 'Capacity', 're-events' ); ?>" />
				<button type="button" class="button reevents-remove-timeslot"><?php esc_html_e( 'Remove', 're-events' ); ?></button>
			</div>
		</template>
		<?php
	}

	/**
	 * Get cached event data.
	 *
	 * @param WP_Post $post Post object.
	 * @return array{
	 *     event: Event,
	 *     timezone: string,
	 *     rule: array{freq:string,interval:int,weekdays:string[],monthday:int,end:string,until:string,count:int},
	 *     monthday: int,
	 *     input_type: string,
	 *     weekdays: array<string, string>,
	 *     venues: WP_Post[],
	 *     organizers: WP_Post[]
	 * }
	 */
	private static function data( WP_Post $post ): array {
		if ( isset( self::$data[ $post->ID ] ) ) {
			return self::$data[ $post->ID ];
		}

		$default_tz = (string) Settings::get( 'timezone', 'UTC' );
		$event      = ( new Event_Repository() )->find( $post->ID ) ?? new Event( timezone: $default_tz );
		$timezone   = $event->timezone ? $event->timezone : $default_tz;
		$rule       = Recurrence_Rule::parse( $event->rrule );
		$start_ts   = '' !== $event->start_utc ? strtotime( $event->start_utc . ' UTC' ) : false;
		$monthday   = $rule['monthday'] > 0 ? $rule['monthday'] : ( $start_ts ? (int) gmdate( 'j', $start_ts ) : 1 );
		$input_type = $event->all_day ? 'date' : 'datetime-local';
		$weekdays   = [
			'MO' => __( 'Mon', 're-events' ),
			'TU' => __( 'Tue', 're-events' ),
			'WE' => __( 'Wed', 're-events' ),
			'TH' => __( 'Thu', 're-events' ),
			'FR' => __( 'Fri', 're-events' ),
			'SA' => __( 'Sat', 're-events' ),
			'SU' => __( 'Sun', 're-events' ),
		];
		$venues     = get_posts(
			[
				'post_type'   => 'reevents_venue',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			]
		);
		$organizers = get_posts(
			[
				'post_type'   => 'reevents_organizer',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			]
		);

		self::$data[ $post->ID ] = [
			'event'      => $event,
			'timezone'   => $timezone,
			'rule'       => $rule,
			'monthday'   => $monthday,
			'input_type' => $input_type,
			'weekdays'   => $weekdays,
			'venues'     => $venues,
			'organizers' => $organizers,
		];

		return self::$data[ $post->ID ];
	}

	/**
	 * Save the event meta.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save( int $post_id ): void {
		if ( self::$saving ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['reevents_event_details_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['reevents_event_details_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'reevents_event_details' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$timezone = Meta::sanitize_timezone( sanitize_text_field( wp_unslash( $_POST['reevents_timezone'] ?? 'UTC' ) ) );
		$start    = self::to_utc( sanitize_text_field( wp_unslash( $_POST['reevents_start'] ?? '' ) ), $timezone );
		$end      = self::to_utc( sanitize_text_field( wp_unslash( $_POST['reevents_end'] ?? '' ) ), $timezone );
		$exdates  = [];

		if ( isset( $_POST['reevents_exdates'] ) && is_array( $_POST['reevents_exdates'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in parse_datetime_rows().
			$exdates = self::parse_datetime_rows( (array) wp_unslash( $_POST['reevents_exdates'] ), $timezone );
		}

		$rdates = [];

		if ( isset( $_POST['reevents_rdates'] ) && is_array( $_POST['reevents_rdates'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in parse_datetime_rows().
			$rdates = self::parse_datetime_rows( (array) wp_unslash( $_POST['reevents_rdates'] ), $timezone );
		}

		$organizer_ids = [];

		if ( isset( $_POST['reevents_organizer_ids'] ) && is_array( $_POST['reevents_organizer_ids'] ) ) {
			$organizer_ids = array_values( array_unique( array_map( 'absint', (array) wp_unslash( $_POST['reevents_organizer_ids'] ) ) ) );
		}

		$weekdays = [];

		if ( isset( $_POST['reevents_weekdays'] ) && is_array( $_POST['reevents_weekdays'] ) ) {
			$weekdays = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['reevents_weekdays'] ) );
		}

		$online_urls = [];

		if ( isset( $_POST['reevents_online_urls'] ) && is_array( $_POST['reevents_online_urls'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by Meta::sanitize_urls().
			$raw_urls = (array) wp_unslash( $_POST['reevents_online_urls'] );
			$labels   = isset( $raw_urls['label'] ) && is_array( $raw_urls['label'] ) ? $raw_urls['label'] : [];
			$urls     = isset( $raw_urls['url'] ) && is_array( $raw_urls['url'] ) ? $raw_urls['url'] : [];
			$items    = [];

			foreach ( $urls as $index => $url ) {
				$items[] = [
					'label' => $labels[ $index ] ?? '',
					'url'   => $url,
				];
			}

			$online_urls = Meta::sanitize_urls( $items );
		}

		$timeslots = [];

		if ( isset( $_POST['reevents_timeslots'] ) && is_array( $_POST['reevents_timeslots'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by Meta::sanitize_timeslots().
			$raw_timeslots = (array) wp_unslash( $_POST['reevents_timeslots'] );
			$starts        = isset( $raw_timeslots['start'] ) && is_array( $raw_timeslots['start'] ) ? $raw_timeslots['start'] : [];
			$ends          = isset( $raw_timeslots['end'] ) && is_array( $raw_timeslots['end'] ) ? $raw_timeslots['end'] : [];
			$capacities    = isset( $raw_timeslots['capacity'] ) && is_array( $raw_timeslots['capacity'] ) ? $raw_timeslots['capacity'] : [];
			$items         = [];

			foreach ( $starts as $index => $slot_start ) {
				$items[] = [
					'start'    => $slot_start,
					'end'      => $ends[ $index ] ?? '',
					'capacity' => $capacities[ $index ] ?? 0,
				];
			}

			$timeslots = Meta::sanitize_timeslots( $items );
		}

		$advanced = sanitize_text_field( wp_unslash( $_POST['reevents_rrule'] ?? '' ) );
		$rrule    = '' !== $advanced ? $advanced : Recurrence_Rule::build(
			[
				'freq'     => sanitize_key( wp_unslash( $_POST['reevents_repeat'] ?? 'none' ) ),
				'interval' => absint( wp_unslash( $_POST['reevents_interval'] ?? 1 ) ),
				'weekdays' => $weekdays,
				'monthday' => absint( wp_unslash( $_POST['reevents_monthday'] ?? 0 ) ),
				'end'      => sanitize_key( wp_unslash( $_POST['reevents_recurrence_end'] ?? 'never' ) ),
				'until'    => sanitize_text_field( wp_unslash( $_POST['reevents_until'] ?? '' ) ),
				'count'    => absint( wp_unslash( $_POST['reevents_count'] ?? 0 ) ),
			]
		);

		$event = new Event(
			$post_id,
			get_the_title( $post_id ),
			$start,
			$end,
			$timezone,
			isset( $_POST['reevents_all_day'] ),
			$rrule,
			$exdates,
			$rdates,
			$timeslots,
			absint( wp_unslash( $_POST['reevents_venue_id'] ?? 0 ) ),
			$organizer_ids,
			absint( wp_unslash( $_POST['reevents_capacity'] ?? 0 ) ),
			$online_urls,
			self::sanitize_status( sanitize_key( wp_unslash( $_POST['reevents_status'] ?? 'scheduled' ) ) )
		);

		self::$saving = true;
		$result       = Event_Service::save( $event );
		self::$saving = false;

		if ( is_wp_error( $result ) ) {
			set_transient( 'reevents_event_error_' . $post_id, $result->get_error_message(), 60 );
		}
	}

	/**
	 * Convert a UTC datetime to a datetime-local value.
	 *
	 * @param string $utc      UTC datetime.
	 * @param string $timezone IANA timezone.
	 */
	private static function to_local_input( string $utc, string $timezone ): string {
		if ( '' === $utc ) {
			return '';
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $utc, new DateTimeZone( 'UTC' ) );

		return $date instanceof DateTimeImmutable ? $date->setTimezone( new DateTimeZone( $timezone ) )->format( 'Y-m-d\TH:i' ) : '';
	}

	/**
	 * Convert a UTC datetime to a local date.
	 *
	 * @param string $utc      UTC datetime.
	 * @param string $timezone IANA timezone.
	 */
	private static function to_local_date( string $utc, string $timezone ): string {
		if ( '' === $utc ) {
			return '';
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $utc, new DateTimeZone( 'UTC' ) );

		return $date instanceof DateTimeImmutable ? $date->setTimezone( new DateTimeZone( $timezone ) )->format( 'Y-m-d' ) : '';
	}

	/**
	 * Convert a local datetime to UTC.
	 *
	 * @param string $local    Local datetime.
	 * @param string $timezone IANA timezone.
	 */
	private static function to_utc( string $local, string $timezone ): string {
		$local = trim( $local );

		if ( '' === $local ) {
			return '';
		}

		foreach ( [ '!Y-m-d\TH:i', '!Y-m-d H:i', '!Y-m-d H:i:s', '!Y-m-d' ] as $format ) {
			$date = DateTimeImmutable::createFromFormat( $format, $local, new DateTimeZone( $timezone ) );

			if ( $date instanceof DateTimeImmutable ) {
				return $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
			}
		}

		return '';
	}

	/**
	 * Parse local datetime rows into UTC datetimes.
	 *
	 * @param array<int, mixed> $values   Local datetimes.
	 * @param string            $timezone IANA timezone.
	 * @return string[]
	 */
	private static function parse_datetime_rows( array $values, string $timezone ): array {
		$datetimes = [];

		foreach ( $values as $value ) {
			$utc = self::to_utc( sanitize_text_field( (string) $value ), $timezone );

			if ( '' !== $utc ) {
				$datetimes[] = $utc;
			}
		}

		return $datetimes;
	}

	/**
	 * Sanitize the event status.
	 *
	 * @param string $status Raw status.
	 */
	private static function sanitize_status( string $status ): string {
		$status = sanitize_key( $status );

		return in_array( $status, [ 'scheduled', 'cancelled' ], true ) ? $status : 'scheduled';
	}
}
