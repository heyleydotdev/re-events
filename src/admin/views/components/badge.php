<?php
/**
 * Status badge.
 *
 * @var string $label Badge text.
 * @var string $tone  One of success, warning, neutral, danger.
 *
 * @package ReEvents
 */

defined( 'ABSPATH' ) || exit;

$reevents_tones = array(
	'success' => 'bg-green-100 text-green-800',
	'warning' => 'bg-yellow-100 text-yellow-800',
	'neutral' => 'bg-gray-100 text-gray-800',
	'danger'  => 'bg-red-100 text-red-800',
);

$reevents_class = $reevents_tones[ $tone ] ?? $reevents_tones['neutral'];
?>
<span class="<?php echo esc_attr( 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ' . $reevents_class ); ?>"><?php echo esc_html( $label ); ?></span>
