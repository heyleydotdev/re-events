<?php
/**
 * Uninstall ReEvents.
 *
 * @package ReEvents
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete ReEvents data for the current site.
 */
function reevents_uninstall_site(): void {
	global $wpdb;

	$settings = get_option( 'reevents_settings', [] );

	if ( ! is_array( $settings ) || empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	$post_types = [ 'reevents_event', 'reevents_venue', 'reevents_organizer' ];

	foreach ( $post_types as $post_type ) {
		$ids = get_posts(
			[
				'post_type'      => $post_type,
				'post_status'    => 'any',
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Uninstall cleanup.
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}

	foreach ( [ 'reevents_event_category', 'reevents_event_tag', 'reevents_organizer_type' ] as $taxonomy ) {
		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			]
		);

		if ( ! is_array( $terms ) ) {
			continue;
		}

		foreach ( $terms as $term_id ) {
			wp_delete_term( (int) $term_id, $taxonomy );
		}
	}

	foreach ( [ 'reevents_occurrences', 'reevents_tickets', 'reevents_bookings', 'reevents_attendees', 'reevents_payments' ] as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall cleanup.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
	}

	$patterns = [
		$wpdb->esc_like( 'reevents_' ) . '%',
		$wpdb->esc_like( '_transient_reevents_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_reevents_' ) . '%',
		$wpdb->esc_like( '_site_transient_reevents_' ) . '%',
		$wpdb->esc_like( '_site_transient_timeout_reevents_' ) . '%',
	];

	foreach ( $patterns as $pattern ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall cleanup.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );
	}

	$roles = wp_roles();

	foreach ( $roles->role_objects as $role ) {
		foreach ( array_keys( $role->capabilities ) as $capability ) {
			if ( str_contains( (string) $capability, 'reevents_' ) ) {
				$role->remove_cap( $capability );
			}
		}
	}

	wp_cache_flush();
}

if ( is_multisite() ) {
	$reevents_sites = get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	);

	foreach ( $reevents_sites as $reevents_site_id ) {
		switch_to_blog( (int) $reevents_site_id );
		reevents_uninstall_site();
		restore_current_blog();
	}
} else {
	reevents_uninstall_site();
}
