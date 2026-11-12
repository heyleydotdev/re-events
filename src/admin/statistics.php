<?php
/**
 * Statistics admin page.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

use ReEvents\Data\Meta;
use ReEvents\Data\Statistics_Repository;
use ReEvents\Query\Event_Query;
use ReEvents\Services\Currency;

/**
 * Shows sales and booking totals.
 */
final class Statistics {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_page' ], 13 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	/**
	 * Add the statistics page.
	 */
	public static function add_page(): void {
		add_submenu_page(
			're-events',
			__( 'Statistics', 're-events' ),
			__( 'Statistics', 're-events' ),
			'reevents_manage_bookings',
			'reevents-statistics',
			[ self::class, 'render' ],
			7
		);
	}

	/**
	 * Enqueue page styles.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue( string $hook ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'reevents-statistics' !== $page && false === strpos( $hook, 'reevents-statistics' ) ) {
			return;
		}

		wp_enqueue_style(
			'reevents-statistics',
			plugins_url( 'assets/css/admin-statistics.css', RE_EVENTS_FILE ),
			[],
			RE_EVENTS_VERSION
		);
	}

	/**
	 * Render the page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'reevents_manage_bookings' ) ) {
			return;
		}

		$filters = self::filters();
		$repo    = new Statistics_Repository();

		$summary = $repo->summary(
			[
				'currency' => $filters['currency'],
				'from'     => $filters['from_utc'],
				'to'       => $filters['to_utc'],
			]
		);

		$monthly     = $repo->monthly(
			[
				'currency' => $filters['currency'],
				'from'     => $filters['from_utc'],
				'to'       => $filters['to_utc'],
				'months'   => 12,
			]
		);
		$chart_total = array_sum( array_column( $monthly, 'sales' ) );

		$cards = [
			[
				'label' => __( 'Total Sales', 're-events' ),
				'value' => Currency::format( (int) $summary['sales'], $filters['currency'] ),
				'icon'  => 'dashicons-money-alt',
			],
			[
				'label' => __( 'Total Tickets', 're-events' ),
				'value' => number_format_i18n( (int) $summary['tickets'] ),
				'icon'  => 'dashicons-tickets-alt',
			],
			[
				'label' => __( 'Total Bookings', 're-events' ),
				'value' => number_format_i18n( (int) $summary['bookings'] ),
				'icon'  => 'dashicons-calendar-alt',
			],
			[
				'label' => __( 'Average Sale', 're-events' ),
				'value' => Currency::format( self::average( (int) $summary['sales'], (int) $summary['bookings'] ), $filters['currency'] ),
				'icon'  => 'dashicons-chart-line',
			],
			[
				'label' => __( 'Average Ticket', 're-events' ),
				'value' => Currency::format( self::average( (int) $summary['sales'], (int) $summary['tickets'] ), $filters['currency'] ),
				'icon'  => 'dashicons-tag',
			],
			[
				'label' => __( 'Tickets / Booking', 're-events' ),
				'value' => number_format_i18n( self::ratio( (int) $summary['tickets'], (int) $summary['bookings'] ), 2 ),
				'icon'  => 'dashicons-chart-pie',
			],
		];

		?>
		<div class="wrap reevents-statistics">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Statistics', 're-events' ); ?></h1>

			<form class="reevents-stat-filters" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="reevents-statistics" />
				<label for="reevents-stat-from"><?php esc_html_e( 'From', 're-events' ); ?></label>
				<input type="date" id="reevents-stat-from" name="from" value="<?php echo esc_attr( $filters['from'] ); ?>" />
				<label for="reevents-stat-to"><?php esc_html_e( 'To', 're-events' ); ?></label>
				<input type="date" id="reevents-stat-to" name="to" value="<?php echo esc_attr( $filters['to'] ); ?>" />
				<label for="reevents-stat-currency"><?php esc_html_e( 'Currency', 're-events' ); ?></label>
				<select id="reevents-stat-currency" name="currency">
					<?php foreach ( $filters['currencies'] as $code ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $filters['currency'], $code ); ?>><?php echo esc_html( $code ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'Apply', 're-events' ); ?></button>
				<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=reevents-statistics' ) ); ?>"><?php esc_html_e( 'Reset', 're-events' ); ?></a>
				<a class="button-link" href="
				<?php
				echo esc_url(
					add_query_arg(
						[
							'page'     => 'reevents-statistics',
							'all'      => 1,
							'currency' => $filters['currency'],
						],
						admin_url( 'admin.php' )
					)
				);
				?>
												"><?php esc_html_e( 'All time', 're-events' ); ?></a>
			</form>

			<p class="description"><?php esc_html_e( 'Defaults to the last 30 days. Choose All time to show the full history.', 're-events' ); ?></p>

			<?php if ( '' !== $filters['notice'] ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html( $filters['notice'] ); ?></p></div>
			<?php endif; ?>

			<div class="reevents-stat-grid">
				<?php foreach ( $cards as $card ) : ?>
					<div class="card reevents-stat-card">
						<span class="reevents-stat-icon dashicons <?php echo esc_attr( $card['icon'] ); ?>" aria-hidden="true"></span>
						<span class="reevents-stat-label"><?php echo esc_html( $card['label'] ); ?></span>
						<strong class="reevents-stat-value"><?php echo esc_html( $card['value'] ); ?></strong>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="card reevents-chart-card">
				<h2><?php esc_html_e( 'Monthly Sales', 're-events' ); ?></h2>
				<?php if ( $monthly && $chart_total > 0 ) : ?>
					<?php self::chart( $monthly, $filters['currency'] ); ?>
				<?php else : ?>
					<p class="reevents-stat-empty"><?php esc_html_e( 'No confirmed sales in this range.', 're-events' ); ?></p>
				<?php endif; ?>
			</div>

			<p class="description">
				<?php esc_html_e( 'Confirmed bookings only. Free bookings count toward tickets and bookings. Sales are net of partial refunds. Amounts are shown in the selected currency.', 're-events' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Read and validate filters.
	 *
	 * @return array{from:string,to:string,from_utc:string,to_utc:string,currency:string,currencies:string[],notice:string}
	 */
	private static function filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$raw_from = isset( $_GET['from'] ) && is_scalar( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		$raw_to   = isset( $_GET['to'] ) && is_scalar( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
		$from     = Meta::sanitize_date( $raw_from );
		$to       = Meta::sanitize_date( $raw_to );
		$currency = isset( $_GET['currency'] ) && is_scalar( $_GET['currency'] ) ? Currency::sanitize_code( sanitize_text_field( wp_unslash( $_GET['currency'] ) ) ) : '';
		$all      = isset( $_GET['all'] ) && is_scalar( $_GET['all'] ) && ! empty( $_GET['all'] );
		// phpcs:enable

		$notice = '';

		if ( $all ) {
			$from = '';
			$to   = '';
		} elseif ( ( '' !== $raw_from && '' === $from ) || ( '' !== $raw_to && '' === $to ) ) {
			$notice = __( 'One or more dates were invalid. Showing the last 30 days.', 're-events' );
			$from   = '';
			$to     = '';
		} elseif ( '' !== $from && '' !== $to && $from > $to ) {
			$notice = __( 'The start date must be before the end date. Showing the last 30 days.', 're-events' );
			$from   = '';
			$to     = '';
		}

		if ( ! $all && '' === $from && '' === $to ) {
			$today = current_datetime();
			$to    = $today->format( 'Y-m-d' );
			$from  = $today->modify( '-29 days' )->format( 'Y-m-d' );
		}

		$currencies = ( new Statistics_Repository() )->currencies();

		if ( '' === $currency ) {
			$currency = Currency::sanitize_code( (string) Settings::get( 'default_currency', 'USD' ) );
		}

		if ( ! in_array( $currency, $currencies, true ) ) {
			$currencies[] = $currency;
			sort( $currencies );
		}

		return [
			'from'       => $from,
			'to'         => $to,
			'from_utc'   => '' !== $from ? Event_Query::utc_bound( $from, '00:00:00' ) : '',
			'to_utc'     => '' !== $to ? Event_Query::utc_bound( $to, '23:59:59' ) : '',
			'currency'   => $currency,
			'currencies' => $currencies,
			'notice'     => $notice,
		];
	}

	/**
	 * Integer average with a zero-safe denominator.
	 *
	 * @param int $total Total amount.
	 * @param int $count Count.
	 */
	private static function average( int $total, int $count ): int {
		return $count > 0 ? (int) round( $total / $count ) : 0;
	}

	/**
	 * Zero-safe ratio.
	 *
	 * @param int $numerator   Numerator.
	 * @param int $denominator Denominator.
	 */
	private static function ratio( int $numerator, int $denominator ): float {
		return $denominator > 0 ? $numerator / $denominator : 0.0;
	}

	/**
	 * Render the monthly sales chart.
	 *
	 * @param array<int, array{month:string,sales:int}> $rows     Monthly rows.
	 * @param string                                    $currency Currency code.
	 */
	private static function chart( array $rows, string $currency ): void {
		$count  = count( $rows );
		$max    = max( 1, max( array_column( $rows, 'sales' ) ) );
		$width  = 720;
		$height = 210;
		$left   = 56;
		$top    = 18;
		$plot_w = 640;
		$plot_h = 150;
		$step   = $plot_w / max( 1, $count );
		$bar_w  = max( 4, $step * 0.55 );
		$axis_y = $top + $plot_h;
		$labels = [];
		$total  = 0;

		foreach ( $rows as $row ) {
			$labels[] = self::month_label( (string) $row['month'] );
			$total   += (int) $row['sales'];
		}

		?>
		<svg class="reevents-chart" viewBox="0 0 <?php echo esc_attr( (string) $width ); ?> <?php echo esc_attr( (string) $height ); ?>" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: currency code. */ __( 'Monthly net sales in %s', 're-events' ), $currency ) ); ?>">
			<title><?php esc_html_e( 'Monthly net sales', 're-events' ); ?></title>
			<?php for ( $i = 0; $i <= 4; $i++ ) : ?>
				<?php $grid_y = $top + ( $plot_h / 4 ) * $i; ?>
				<line class="reevents-chart-grid" x1="<?php echo esc_attr( (string) $left ); ?>" y1="<?php echo esc_attr( (string) $grid_y ); ?>" x2="<?php echo esc_attr( (string) ( $left + $plot_w ) ); ?>" y2="<?php echo esc_attr( (string) $grid_y ); ?>" />
			<?php endfor; ?>
			<line class="reevents-chart-axis" x1="<?php echo esc_attr( (string) $left ); ?>" y1="<?php echo esc_attr( (string) $axis_y ); ?>" x2="<?php echo esc_attr( (string) ( $left + $plot_w ) ); ?>" y2="<?php echo esc_attr( (string) $axis_y ); ?>" />
			<?php foreach ( $rows as $index => $row ) : ?>
				<?php
				$bar_h = (int) round( ( (int) $row['sales'] / $max ) * $plot_h );
				$x     = $left + ( $index + 0.5 ) * $step - $bar_w / 2;
				$y     = $axis_y - $bar_h;
				$month = $labels[ $index ];
				$value = Currency::format( (int) $row['sales'], $currency );
				?>
				<rect class="reevents-chart-bar" x="<?php echo esc_attr( (string) round( $x, 2 ) ); ?>" y="<?php echo esc_attr( (string) round( $y, 2 ) ); ?>" width="<?php echo esc_attr( (string) round( $bar_w, 2 ) ); ?>" height="<?php echo esc_attr( (string) max( 0, $bar_h ) ); ?>">
					<title><?php echo esc_html( $month . ': ' . $value ); ?></title>
				</rect>
				<text class="reevents-chart-label<?php echo 0 !== $index % 2 ? ' reevents-chart-label-alt' : ''; ?>" x="<?php echo esc_attr( (string) round( $left + ( $index + 0.5 ) * $step, 2 ) ); ?>" y="<?php echo esc_attr( (string) ( $axis_y + 18 ) ); ?>" text-anchor="middle"><?php echo esc_html( $month ); ?></text>
			<?php endforeach; ?>
		</svg>
		<table class="screen-reader-text">
			<caption><?php esc_html_e( 'Monthly net sales', 're-events' ); ?></caption>
			<tbody>
				<?php foreach ( $rows as $index => $row ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $labels[ $index ] ); ?></th>
						<td><?php echo esc_html( Currency::format( (int) $row['sales'], $currency ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="reevents-chart-total"><?php echo esc_html( sprintf( /* translators: %s: formatted amount. */ __( 'Chart total: %s', 're-events' ), Currency::format( $total, $currency ) ) ); ?></p>
		<?php
	}

	/**
	 * Short month label.
	 *
	 * @param string $month Y-m.
	 */
	private static function month_label( string $month ): string {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $month . '-01', wp_timezone() );

		return $date instanceof \DateTimeImmutable ? (string) wp_date( 'M Y', $date->getTimestamp() ) : $month;
	}
}
