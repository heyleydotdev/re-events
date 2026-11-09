<?php
/**
 * Attendees list table.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

use ReEvents\Data\Booking_Repository;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Attendee list table.
 */
final class Attendees_Table extends \WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'attendee',
				'plural'   => 'attendees',
				'ajax'     => false,
			]
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return self::default_columns();
	}

	/**
	 * Default columns, available before the table is constructed.
	 *
	 * @return array<string, string>
	 */
	public static function default_columns(): array {
		return [
			'cb'         => '<input type="checkbox" />',
			'reference'  => __( 'Booking', 're-events' ),
			'status'     => __( 'Status', 're-events' ),
			'event'      => __( 'Event', 're-events' ),
			'ticket'     => __( 'Ticket', 're-events' ),
			'name'       => __( 'Name', 're-events' ),
			'email'      => __( 'Email', 're-events' ),
			'checked_in' => __( 'Checked in', 're-events' ),
			'created'    => __( 'Created', 're-events' ),
		];
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function get_sortable_columns(): array {
		return [
			'reference' => [ 'reference', false ],
			'status'    => [ 'status', false ],
			'name'      => [ 'name', false ],
			'email'     => [ 'email', false ],
			'created'   => [ 'created', true ],
		];
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items(): void {
		$this->process_bulk_action();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only table state.
		$event_id = isset( $_GET['event'] ) ? absint( wp_unslash( $_GET['event'] ) ) : 0;
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$orderby  = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created';
		$order    = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc';
		// phpcs:enable

		$per_page = $this->get_items_per_page( 'reevents_attendees_per_page', 20 );
		$paged    = $this->get_pagenum();
		$result   = ( new Booking_Repository() )->attendees_query(
			[
				'event_id' => $event_id,
				'search'   => $search,
				'orderby'  => $orderby,
				'order'    => $order,
				'limit'    => $per_page,
				'offset'   => ( $paged - 1 ) * $per_page,
			]
		);

		$this->items = $result['items'];

		$this->_column_headers = [
			$this->get_columns(),
			get_hidden_columns( $this->screen ),
			$this->get_sortable_columns(),
			$this->get_primary_column_name(),
		];

		$this->set_pagination_args(
			[
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / $per_page ),
			]
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	public function get_bulk_actions(): array {
		return [
			'check_in'  => __( 'Mark checked in', 're-events' ),
			'check_out' => __( 'Mark not checked in', 're-events' ),
		];
	}

	/**
	 * Process bulk actions.
	 */
	private function process_bulk_action(): void {
		$action = $this->current_action();

		if ( ! in_array( $action, [ 'check_in', 'check_out' ], true ) ) {
			return;
		}

		check_admin_referer( 'bulk-attendees' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked above.
		$ids = isset( $_REQUEST['attendee'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['attendee'] ) ) : [];

		( new Booking_Repository() )->set_checked_in( $ids, 'check_in' === $action );
	}

	/**
	 * Checkbox column.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	public function column_cb( $item ): string {
		return '<input type="checkbox" name="attendee[]" value="' . esc_attr( (string) ( $item['id'] ?? 0 ) ) . '" />';
	}

	/**
	 * Event filter and export in the top tablenav.
	 *
	 * @param string $which Top or bottom.
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$event_id = isset( $_GET['event'] ) ? absint( wp_unslash( $_GET['event'] ) ) : 0;
		$events   = get_posts(
			[
				'post_type'      => 'reevents_event',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);
		$export   = wp_nonce_url(
			add_query_arg(
				[
					'action' => 'reevents_export_attendees',
					'event'  => $event_id,
				],
				admin_url( 'admin-post.php' )
			),
			'reevents_export_attendees'
		);

		echo '<div class="alignleft actions">';
		echo '<label class="screen-reader-text" for="reevents-attendee-event">' . esc_html__( 'Event', 're-events' ) . '</label>';
		echo '<select id="reevents-attendee-event" name="event">';
		echo '<option value="0">' . esc_html__( 'All events', 're-events' ) . '</option>';

		foreach ( $events as $event ) {
			echo '<option value="' . esc_attr( (string) $event->ID ) . '" ' . selected( $event_id, $event->ID, false ) . '>' . esc_html( $event->post_title ) . '</option>';
		}

		echo '</select>';
		submit_button( __( 'Filter', 're-events' ), '', 'filter_action', false );
		echo '<a class="button" style="margin-left:8px;" href="' . esc_url( $export ) . '">' . esc_html__( 'Export CSV', 're-events' ) . '</a>';
		echo '</div>';
	}

	/**
	 * Primary column for narrow screens.
	 */
	protected function get_primary_column_name(): string {
		return 'name';
	}

	/**
	 * Default column output.
	 *
	 * @param array<string, mixed> $item        Row.
	 * @param string               $column_name Column.
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'event':
				return esc_html( (string) get_the_title( (int) ( $item['event_id'] ?? 0 ) ) );
			case 'checked_in':
				return ! empty( $item['checked_in'] ) ? esc_html__( 'Yes', 're-events' ) : esc_html__( 'No', 're-events' );
			case 'created':
				$date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', (string) ( $item['created_utc'] ?? '' ), new \DateTimeZone( 'UTC' ) );

				return $date instanceof \DateTimeImmutable ? esc_html( wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $date->getTimestamp() ) ) : '';
			case 'reference':
				return '<code>' . esc_html( (string) ( $item['reference'] ?? '' ) ) . '</code>';
			case 'status':
				return esc_html( (string) ( $item['booking_status'] ?? '' ) );
			case 'ticket':
				return esc_html( (string) ( $item['ticket_name'] ?? '' ) );
			case 'name':
				return esc_html( (string) ( $item['name'] ?? '' ) );
			case 'email':
				return esc_html( (string) ( $item['email'] ?? '' ) );
			default:
				return '';
		}
	}

	/**
	 * Empty state.
	 */
	public function no_items(): void {
		esc_html_e( 'No attendees found.', 're-events' );
	}
}
