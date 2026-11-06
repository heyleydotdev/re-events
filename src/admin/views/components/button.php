<?php

/**
 * Button.
 *
 * @var string      $label         Button text. Leave empty for an icon button.
 * @var string|null $variant       default, secondary, tertiary, success, danger, warning, dark, or ghost.
 * @var string|null $size          xs, sm, base, lg, or xl.
 * @var string|null $type          Button type attribute.
 * @var string|null $icon          Lucide icon name.
 * @var string|null $icon_position start or end.
 * @var string|null $aria_label    Required for icon buttons.
 * @var bool|null   $loading       Show a spinner and disable the button.
 * @var bool|null   $disabled      Disable the button.
 *
 * @package ReEvents
 */

defined('ABSPATH') || exit;

$reevents_variants = array(
	'default'   => 'text-white bg-brand border-transparent hover:bg-brand-strong focus:ring-brand-medium shadow-xs',
	'secondary' => 'text-body bg-neutral-secondary-medium border-default-medium hover:bg-neutral-tertiary-medium hover:text-heading focus:ring-neutral-tertiary shadow-xs',
	'tertiary'  => 'text-body bg-neutral-primary-soft border-default hover:bg-neutral-secondary-medium hover:text-heading focus:ring-neutral-tertiary-soft shadow-xs',
	'success'   => 'text-white bg-success border-transparent hover:bg-success-strong focus:ring-success-medium shadow-xs',
	'danger'    => 'text-white bg-danger border-transparent hover:bg-danger-strong focus:ring-danger-medium shadow-xs',
	'warning'   => 'text-white bg-warning border-transparent hover:bg-warning-strong focus:ring-warning-medium shadow-xs',
	'dark'      => 'text-white bg-dark border-transparent hover:bg-dark-strong focus:ring-neutral-tertiary shadow-xs',
	'ghost'     => 'text-heading bg-transparent border-transparent hover:bg-neutral-secondary-medium focus:ring-neutral-tertiary',
	'outline'   => 'text-fg-brand bg-neutral-primary border-brand hover:bg-brand hover:text-white focus:ring-brand-subtle',
);

$reevents_sizes = array(
	'xs'   => 'text-xs leading-5 px-3 py-[6px]',
	'sm'   => 'text-sm leading-5 px-3 py-2',
	'base' => 'text-sm leading-5 px-4 py-[10px]',
	'lg'   => 'text-base px-5 py-3',
	'xl'   => 'text-base px-6 py-[14px]',
);

$reevents_icon_sizes = array(
	'xs'   => 'w-8 h-8',
	'sm'   => 'w-9 h-9',
	'base' => 'w-10 h-10',
	'lg'   => 'w-10 h-10',
	'xl'   => 'w-10 h-10',
);

$reevents_icon  = $icon ?? '';
$reevents_only  = '' !== $reevents_icon && '' === ($label ?? '');
$reevents_style = $variant ?? 'default';
$reevents_key   = $size ?? 'base';
$reevents_load  = (bool) ($loading ?? false);
$reevents_off   = (bool) ($disabled ?? false) || $reevents_load;

if ($reevents_only) {
	$reevents_class = 'inline-flex items-center justify-center rounded-base shadow-xs focus:ring-4 focus:outline-none';

	if (in_array($reevents_style, array('secondary', 'tertiary', 'outline'), true)) {
		$reevents_class .= ' border';
	}

	$reevents_class .= ' ' . ($reevents_variants[$reevents_style] ?? $reevents_variants['default']);
	$reevents_class .= ' ' . ($reevents_icon_sizes[$reevents_key] ?? $reevents_icon_sizes['base']);
} else {
	$reevents_class  = 'inline-flex items-center border font-medium rounded-base focus:ring-4 focus:outline-none';
	$reevents_class .= ' ' . ($reevents_variants[$reevents_style] ?? $reevents_variants['default']);
	$reevents_class .= ' ' . ($reevents_sizes[$reevents_key] ?? $reevents_sizes['base']);
}

$reevents_icon_class = $reevents_only ? 'w-5 h-5' : 'w-4 h-4 me-[6px] -ms-[2px]';

if ('end' === ($icon_position ?? 'start')) {
	$reevents_icon_class = $reevents_only ? 'w-5 h-5' : 'w-4 h-4 ms-[6px] -me-[2px]';
}
?>
<button
	type="<?php echo esc_attr($type ?? 'button'); ?>"
	class="<?php echo esc_attr($reevents_class); ?>"
	<?php echo $reevents_off ? 'disabled' : ''; ?>
	<?php echo $reevents_only && isset($aria_label) ? 'aria-label="' . esc_attr($aria_label) . '"' : ''; ?>>
	<?php if ($reevents_load) : ?>
		<svg aria-hidden="true" role="status" class="<?php echo esc_attr($reevents_only ? 'size-4 animate-spin' : 'size-4 me-2 animate-spin'); ?>" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
			<path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="#E5E7EB" />
			<path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentColor" />
		</svg>
		<?php if (! $reevents_only) : ?>
			<?php echo esc_html($label ?? ''); ?>
		<?php endif; ?>
	<?php elseif ($reevents_only) : ?>
		<?php
		$this->insert(
			'components/icon',
			array(
				'name'       => $reevents_icon,
				'icon_class' => 'w-5 h-5',
			)
		);
		?>
	<?php else : ?>
		<?php if ('' !== $reevents_icon && 'end' !== ($icon_position ?? 'start')) : ?>
			<?php
			$this->insert(
				'components/icon',
				array(
					'name'       => $reevents_icon,
					'icon_class' => $reevents_icon_class,
				)
			);
			?>
		<?php endif; ?>
		<?php echo esc_html($label ?? ''); ?>
		<?php if ('' !== $reevents_icon && 'end' === ($icon_position ?? 'start')) : ?>
			<?php
			$this->insert(
				'components/icon',
				array(
					'name'       => $reevents_icon,
					'icon_class' => $reevents_icon_class,
				)
			);
			?>
		<?php endif; ?>
	<?php endif; ?>
</button>