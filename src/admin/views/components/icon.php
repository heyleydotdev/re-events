<?php
/**
 * Lucide icon.
 *
 * Icons live in components/icons and come from https://lucide.dev (ISC).
 *
 * @var string $name       Icon file name, e.g. "search".
 * @var string $icon_class Tailwind classes for size and color.
 *
 * @package ReEvents
 */

defined( 'ABSPATH' ) || exit;

$this->insert(
	'components/icons/' . $name,
	array(
		'icon_class' => $icon_class,
	)
);
