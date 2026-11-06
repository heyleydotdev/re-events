<?php
/**
 * Pagination.
 *
 * @var int    $current Current page.
 * @var int    $total   Number of pages.
 * @var string $summary Summary text shown before the page links.
 * @var string $label   Accessible label for the navigation landmark.
 *
 * @package ReEvents
 */

defined( 'ABSPATH' ) || exit;

$reevents_link = 'box-border flex h-9 w-9 items-center justify-center border border-default-medium bg-neutral-secondary-medium text-sm font-medium text-body hover:bg-neutral-tertiary-medium hover:text-heading focus:outline-none';
$reevents_side = 'box-border flex h-9 items-center justify-center border border-default-medium bg-neutral-secondary-medium px-3 text-sm font-medium text-body hover:bg-neutral-tertiary-medium hover:text-heading focus:outline-none';
?>
<nav class="flex flex-col gap-3 pt-4 sm:flex-row sm:items-center sm:justify-between" aria-label="<?php echo esc_attr( $label ); ?>">
	<p class="text-sm text-gray-500"><?php echo esc_html( $summary ); ?></p>
	<ul class="flex -space-x-px text-sm">
		<li>
			<a href="#" class="<?php echo esc_attr( 'rounded-s-base ' . $reevents_side ); ?>"><?php esc_html_e( 'Previous', 're-events' ); ?></a>
		</li>
		<?php for ( $reevents_page = 1; $reevents_page <= $total; $reevents_page++ ) : ?>
			<?php $reevents_active = $reevents_page === $current; ?>
			<li>
				<a href="#" class="<?php echo esc_attr( $reevents_active ? 'bg-neutral-tertiary-medium text-fg-brand ' . $reevents_link : $reevents_link ); ?>" <?php echo $reevents_active ? 'aria-current="page"' : ''; ?>><?php echo esc_html( (string) $reevents_page ); ?></a>
			</li>
		<?php endfor; ?>
		<li>
			<a href="#" class="<?php echo esc_attr( 'rounded-e-base ' . $reevents_side ); ?>"><?php esc_html_e( 'Next', 're-events' ); ?></a>
		</li>
	</ul>
</nav>
