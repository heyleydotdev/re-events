<?php
/**
 * Verifies the plugin is ready for a release package.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

$reevents_root      = dirname( __DIR__ );
$reevents_manifest  = $reevents_root . '/assets/build/.vite/manifest.json';
$reevents_installed = $reevents_root . '/vendor/composer/installed.json';

if ( ! is_readable( $reevents_manifest ) || ! is_readable( $reevents_installed ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output.
	fwrite( STDERR, "Missing built assets or production Composer vendor.\n" );
	exit( 1 );
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local release metadata.
$reevents_data = json_decode( (string) file_get_contents( $reevents_installed ), true );

if ( ! is_array( $reevents_data ) || ! empty( $reevents_data['dev-package-names'] ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output.
	fwrite( STDERR, "Development Composer packages are installed. Run composer install --no-dev -o.\n" );
	exit( 1 );
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output.
fwrite( STDOUT, "Release check passed.\n" );
exit( 0 );
