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
$reevents_manifest_data = json_decode( (string) file_get_contents( $reevents_manifest ), true );

if ( ! is_array( $reevents_manifest_data ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output.
	fwrite( STDERR, "The Vite manifest is invalid. Run bun run build.\n" );
	exit( 1 );
}

foreach ( array( 'assets/src/admin/index.tsx', 'assets/src/admin/app.css', 'node_modules/@fontsource-variable/geist/wght.css' ) as $reevents_key ) {
	$reevents_entry = $reevents_manifest_data[ $reevents_key ] ?? null;

	if ( ! is_array( $reevents_entry ) || ! isset( $reevents_entry['file'] ) || ! is_string( $reevents_entry['file'] ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output.
		fwrite( STDERR, "The Vite manifest is missing {$reevents_key}. Run bun run build.\n" );
		exit( 1 );
	}

	$reevents_files = array( $reevents_entry['file'] );

	if ( isset( $reevents_entry['css'] ) && is_array( $reevents_entry['css'] ) ) {
		$reevents_files = array_merge( $reevents_files, $reevents_entry['css'] );
	}

	foreach ( $reevents_files as $reevents_file ) {
		if ( ! is_string( $reevents_file ) || ! is_readable( $reevents_root . '/assets/build/' . $reevents_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output.
			fwrite( STDERR, "A built file listed in the Vite manifest is missing: {$reevents_file}.\n" );
			exit( 1 );
		}
	}
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
