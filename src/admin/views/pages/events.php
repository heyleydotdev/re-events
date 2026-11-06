<?php

/**
 * Events page.
 *
 * @var string                                            $view_label
 * @var string                                            $app_css
 * @var bool                                              $declarative
 * @var array<string, array{label: string, url: string}>  $nav_items
 * @var string                                            $current_view
 * @var string                                            $home_url
 *
 * @package ReEvents
 */

defined('ABSPATH') || exit;

$this->layout(
	'layouts/base',
	array(
		'app_css'      => $app_css,
		'declarative'  => $declarative,
		'nav_items'    => $nav_items,
		'current_view' => $current_view,
		'home_url'     => $home_url,
	)
);

$reevents_events = array(
	array(
		'title'  => 'WordPress Meetup',
		'date'   => 'Nov 12, 2026',
		'venue'  => 'Berlin',
		'status' => __('Published', 're-events'),
		'tone'   => 'success',
	),
	array(
		'title'  => 'Design Systems Workshop',
		'date'   => 'Nov 20, 2026',
		'venue'  => 'Online',
		'status' => __('Draft', 're-events'),
		'tone'   => 'warning',
	),
	array(
		'title'  => 'ReEvents Launch Party',
		'date'   => 'Dec 3, 2026',
		'venue'  => 'Amsterdam',
		'status' => __('Pending', 're-events'),
		'tone'   => 'neutral',
	),
);

$this->start('main');
?>
<div class="flex flex-col gap-3 pb-4 md:flex-row md:items-center md:justify-between">
	<div class="w-full md:w-1/3">
		<?php
		$this->insert(
			'components/search',
			array(
				'id'          => 're-events-search',
				'placeholder' => __('Search events', 're-events'),
			)
		);
		?>
	</div>

	<div class="shrink-0">
		<?php
		$this->insert(
			'components/button',
			array(
				'label'   => __('Create event', 're-events'),
				'icon'    => 'plus',
				'variant' => 'default',
				'size'	  => "sm",
			)
		);
		?>
	</div>
</div>

<div class="relative overflow-x-auto rounded-base border border-default bg-neutral-primary-soft shadow-xs">
	<table class="w-full text-left text-sm text-body rtl:text-right">
		<thead class="rounded-base border-b border-default bg-neutral-secondary-soft text-sm text-body">
			<tr>
				<th scope="col" class="px-6 py-3 font-medium"><?php esc_html_e('Event', 're-events'); ?></th>
				<th scope="col" class="px-6 py-3 font-medium"><?php esc_html_e('Date', 're-events'); ?></th>
				<th scope="col" class="px-6 py-3 font-medium"><?php esc_html_e('Venue', 're-events'); ?></th>
				<th scope="col" class="px-6 py-3 font-medium"><?php esc_html_e('Status', 're-events'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($reevents_events as $reevents_event) : ?>
				<tr class="border-b border-default bg-neutral-primary last:border-0">
					<th scope="row" class="px-6 py-4 font-medium whitespace-nowrap text-heading"><?php echo esc_html($reevents_event['title']); ?></th>
					<td class="px-6 py-4"><?php echo esc_html($reevents_event['date']); ?></td>
					<td class="px-6 py-4"><?php echo esc_html($reevents_event['venue']); ?></td>
					<td class="px-6 py-4">
						<?php
						$this->insert(
							'components/badge',
							array(
								'label' => $reevents_event['status'],
								'tone'  => $reevents_event['tone'],
							)
						);
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<?php
$this->insert(
	'components/pagination',
	array(
		'current' => 3,
		'total'   => 5,
		'summary' => __('Showing 1–3 of 3 events', 're-events'),
		'label'   => __('Events pagination', 're-events'),
	)
);
?>
<?php $this->stop(); ?>