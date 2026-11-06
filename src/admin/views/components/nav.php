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

defined( 'ABSPATH' ) || exit;
?>
<nav class="navbar md:navbar-expand" data-bs-theme="dark">
	<div class="container-fluid">
		<a class="navbar-brand" href="<?php echo esc_url( $home_url ); ?>">ReEvents</a>
		<button class="btn-icon navbar-toggler" type="button" data-bs-toggle="drawer" data-bs-target="#navbarDarkDrawer" aria-controls="navbarDarkDrawer" aria-expanded="false" aria-label="Toggle navigation">
			<span class="navbar-toggler-icon" aria-hidden="true"></span>
		</button>
		<dialog class="drawer drawer-end" tabindex="-1" id="navbarDarkDrawer" aria-labelledby="navbarDarkDrawerLabel">
			<div class="drawer-header">
				<h5 class="drawer-title" id="navbarDarkDrawerLabel">Menu</h5>
				<button type="button" class="btn-close" data-bs-dismiss="drawer" aria-label="Close"></button>
			</div>
			<div class="drawer-body mb-2 md:mb-0">
				<ul class="nav navbar-nav fs-sm me-auto">
					<?php foreach ( $nav_items as $nav_slug => $nav_item ) : ?>
						<?php $nav_active = $nav_slug === $current_view; ?>
						<li class="nav-item">
							<a
								class="<?php echo esc_attr( 'nav-link' . ( $nav_active ? ' active' : '' ) ); ?>"
								href="<?php echo esc_url( (string) $nav_item['url'] ); ?>"
								aria-current="<?php echo esc_attr( $nav_active ? 'page' : 'false' ); ?>"
							>
								<?php echo esc_html( (string) $nav_item['label'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</dialog>
	</div>
</nav>
