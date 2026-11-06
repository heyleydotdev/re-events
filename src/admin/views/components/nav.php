<?php

/**
 * Admin navigation.
 *
 * @var array<string, array{label: string, url: string}>  $nav_items
 * @var string                                            $current_view
 * @var string                                            $home_url
 *
 * @package ReEvents
 */

defined('ABSPATH') || exit;
?>
<nav class="sticky top-(--wp-admin--admin-bar--height) z-50 h-12 border-b bg-white backdrop-blur-md supports-backdrop-filter:bg-white/50">
	<div class="container flex h-full items-center">
		<a class="text-base font-semibold text-gray-950" href="<?php echo esc_url($home_url); ?>">ReEvents</a>
		<button
			type="button"
			class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-800 hover:text-white focus:ring-2 focus:ring-gray-600 focus:outline-none md:hidden"
			data-reevents-toggle="drawer"
			data-reevents-target="#re-events-drawer"
			aria-controls="re-events-drawer"
			aria-expanded="false">
			<span class="sr-only"><?php esc_html_e('Open menu', 're-events'); ?></span>
			<?php
			$this->insert(
				'components/icon',
				array(
					'name'       => 'menu',
					'icon_class' => 'h-6 w-6',
				)
			);
			?>
		</button>
		<ul class="flex h-full flex-1 items-center justify-end gap-1 max-md:hidden">
			<?php foreach ($nav_items as $nav_slug => $nav_item) : ?>
				<?php $nav_active = $nav_slug === $current_view; ?>
				<li>
					<a
						class="<?php echo esc_attr('rounded-lg px-3 py-2 text-sm/6 font-medium ' . ($nav_active ? 'text-950' : 'text-gray-400 hover:text-gray-950')); ?>"
						href="<?php echo esc_url((string) $nav_item['url']); ?>"
						<?php echo $nav_active ? 'aria-current="page"' : ''; ?>>
						<?php echo esc_html((string) $nav_item['label']); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<dialog
		id="re-events-drawer"
		data-reevents-drawer
		class="m-0 ml-auto h-full max-h-none w-80 max-w-none bg-gray-900 p-4 text-gray-400 backdrop:bg-gray-900/60">
		<div class="mb-4 flex items-center justify-between">
			<h2 class="text-lg font-semibold text-white"><?php esc_html_e('Menu', 're-events'); ?></h2>
			<button
				type="button"
				class="inline-flex h-9 w-9 items-center justify-center rounded-lg hover:bg-gray-800 hover:text-white focus:ring-2 focus:ring-gray-600 focus:outline-none"
				data-reevents-dismiss="drawer">
				<span class="sr-only"><?php esc_html_e('Close menu', 're-events'); ?></span>
				<?php
				$this->insert(
					'components/icon',
					array(
						'name'       => 'x',
						'icon_class' => 'h-5 w-5',
					)
				);
				?>
			</button>
		</div>
		<ul class="space-y-1">
			<?php foreach ($nav_items as $nav_slug => $nav_item) : ?>
				<?php $nav_active = $nav_slug === $current_view; ?>
				<li>
					<a
						class="<?php echo esc_attr('block rounded-lg px-3 py-2 text-base font-medium ' . ($nav_active ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white')); ?>"
						href="<?php echo esc_url((string) $nav_item['url']); ?>"
						<?php echo $nav_active ? 'aria-current="page"' : ''; ?>>
						<?php echo esc_html((string) $nav_item['label']); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</dialog>
</nav>