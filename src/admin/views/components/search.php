<?php

/**
 * Search input.
 *
 * @var string $id          Input id.
 * @var string $placeholder Placeholder text.
 *
 * @package ReEvents
 */

defined('ABSPATH') || exit;
?>
<div class="relative">
	<div class="pointer-events-none absolute inset-y-0 inset-s-0 flex items-center ps-3">
		<?php
		$this->insert(
			'components/icon',
			array(
				'name'       => 'search',
				'icon_class' => 'w-4 h-4 text-body',
			)
		);
		?>
	</div>
	<input type="search" id="<?php echo esc_attr($id); ?>" class="block w-full rounded-base border border-default-medium bg-neutral-secondary-medium py-2 ps-9 pe-2.5 text-sm text-heading shadow-xs placeholder:text-body focus:border-brand focus:ring-brand" placeholder="<?php echo esc_attr($placeholder); ?>" aria-label="<?php echo esc_attr($placeholder); ?>">
</div>