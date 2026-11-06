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
<div class="row g-3">
	<div class="col-12">
		<div class="card">
			<div class="card-body">
				<h2 class="h4 card-title"><?php echo esc_html( $view_label ); ?></h2>
				<p class="card-text mb-0">
					<?php esc_html_e( 'This screen is not built yet.', 're-events' ); ?>
				</p>
			</div>
		</div>
	</div>
</div>
<?php $this->stop(); ?>
