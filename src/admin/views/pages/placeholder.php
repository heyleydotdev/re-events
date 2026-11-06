<?php
/**
 * Placeholder page.
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

defined( 'ABSPATH' ) || exit;

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

$this->start( 'main' );
?>
<div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
	<h2 class="mb-2 text-xl font-semibold text-gray-900"><?php echo esc_html( $view_label ); ?></h2>
	<p class="text-gray-500"><?php esc_html_e( 'This screen is not built yet.', 're-events' ); ?></p>
</div>
<?php $this->stop(); ?>
