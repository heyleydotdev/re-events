<?php
/**
 * Generate the public API function reference.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.WP.GlobalVariablesOverride.Prohibited -- Standalone CLI docs generator.

require dirname( __DIR__ ) . '/vendor/autoload.php';
require dirname( __DIR__ ) . '/src/api.php';

use phpDocumentor\Reflection\DocBlockFactory;

$factory   = DocBlockFactory::createInstance();
$functions = [];

foreach ( get_defined_functions()['user'] as $name ) {
	if ( ! str_starts_with( $name, 'reevents_' ) ) {
		continue;
	}

	$reflection = new ReflectionFunction( $name );
	$comment    = $reflection->getDocComment();

	if ( false === $comment || ! str_contains( $comment, '@since' ) ) {
		continue;
	}

	$doc   = $factory->create( $comment );
	$since = $doc->getTagsByName( 'since' );
	$lines = [ '## `' . $name . '()`', '', $doc->getSummary() ];

	if ( '' !== (string) $doc->getDescription() ) {
		$lines[] = '';
		$lines[] = (string) $doc->getDescription();
	}

	$params = $doc->getTagsByName( 'param' );

	if ( $params ) {
		$lines[] = '';
		$lines[] = '**Parameters**';
		$lines[] = '';

		foreach ( $params as $param ) {
			$lines[] = '- `' . $param->getType() . ' $' . $param->getVariableName() . '` — ' . $param->getDescription();
		}
	}

	foreach ( $doc->getTagsByName( 'return' ) as $return ) {
		$lines[] = '';
		$lines[] = '**Returns:** `' . $return->getType() . '`' . ( '' !== (string) $return->getDescription() ? ' — ' . $return->getDescription() : '' );
	}

	if ( $since ) {
		$lines[] = '';
		$lines[] = '**Since:** ' . $since[0]->getVersion();
	}

	$functions[] = implode( "\n", $lines );
}

sort( $functions );

$output = "# Public API function reference\n\nGenerated from PHPDoc in `src/api.php`. Do not edit by hand.\n\n" . implode( "\n\n", $functions ) . "\n";
$target = dirname( __DIR__ ) . '/docs/api/public-api-reference.md';

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI docs generator.
file_put_contents( $target, $output );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
echo "Generated {$target}\n";
