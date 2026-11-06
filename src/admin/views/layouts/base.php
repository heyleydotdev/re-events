<?php
/**
 * Dashboard layout: navigation and page content.
 *
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
	'layouts/root',
	array(
		'app_css'     => $app_css,
		'declarative' => $declarative,
	)
);
?>
<?php
$this->insert(
	'components/nav',
	array(
		'nav_items'    => $nav_items,
		'current_view' => $current_view,
		'home_url'     => $home_url,
	)
);
?>

<div class="px-4 pt-4 pb-12">
	<?php
	$reevents_main = $this->section( 'main' );
	echo $reevents_main; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plates section.
	?>
</div>
