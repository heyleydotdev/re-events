<?php
/**
 * Pagination helper.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

/**
 * Builds shared pagination links for list widgets.
 */
final class Pagination {

	/**
	 * Whether a widget renders pagination.
	 *
	 * @param array<string, mixed> $args Widget arguments.
	 */
	public static function enabled( array $args ): bool {
		if ( ! isset( $args['paginate'] ) ) {
			return true;
		}

		return (bool) filter_var( $args['paginate'], FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Resolve the page query argument.
	 *
	 * @param array<string, mixed> $args   Widget arguments.
	 * @param string               $prefix Prefix for a custom key.
	 * @param string               $fallback Default query argument.
	 */
	public static function param( array $args, string $prefix, string $fallback ): string {
		$key = sanitize_key( (string) ( $args['pagination_key'] ?? '' ) );

		return '' !== $key ? $prefix . $key : $fallback;
	}

	/**
	 * Resolve the current page.
	 *
	 * @param array<string, mixed> $args    Widget arguments.
	 * @param string               $param   Query argument.
	 * @param bool                 $enabled Whether pagination is enabled.
	 */
	public static function page( array $args, string $param, bool $enabled ): int {
		$requested = 0;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Public pagination, cast below.
		if ( $enabled && isset( $_GET[ $param ] ) && is_scalar( $_GET[ $param ] ) ) {
			$requested = absint( wp_unslash( $_GET[ $param ] ) );
		}
		// phpcs:enable

		return max( 1, $requested > 0 ? $requested : (int) ( $args['page'] ?? 1 ) );
	}

	/**
	 * Render the pagination navigation.
	 *
	 * @param string $param    Query argument.
	 * @param string $base_url Current URL without the page argument.
	 * @param int    $page     Current page.
	 * @param int    $pages    Total pages.
	 * @param string $label    Accessible label.
	 */
	public static function links( string $param, string $base_url, int $page, int $pages, string $label ): string {
		if ( $pages <= 1 ) {
			return '';
		}

		$separator = str_contains( $base_url, '?' ) ? '&' : '?';

		$links = paginate_links(
			[
				'base'      => $base_url . '%_%',
				'format'    => $separator . $param . '=%#%',
				'current'   => $page,
				'total'     => $pages,
				'type'      => 'list',
				'prev_text' => __( 'Previous', 're-events' ),
				'next_text' => __( 'Next', 're-events' ),
			]
		);

		return '<nav class="reevents-pagination" aria-label="' . esc_attr( $label ) . '">' . $links . '</nav>';
	}
}
