<?php
/**
 * CSV helper tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReEvents\Services\Csv;

/**
 * Covers spreadsheet formula escaping.
 */
final class CsvTest extends TestCase {

	/**
	 * Formula prefixes are neutralized.
	 */
	public function test_cell_escapes_formula_prefixes(): void {
		self::assertSame( "'=SUM(1,2)", Csv::cell( '=SUM(1,2)' ) );
		self::assertSame( "'+1", Csv::cell( '+1' ) );
		self::assertSame( "'-1", Csv::cell( '-1' ) );
		self::assertSame( "'@cmd", Csv::cell( '@cmd' ) );
		self::assertSame( 'plain', Csv::cell( 'plain' ) );
	}

	/**
	 * The import reverses the protective prefix.
	 */
	public function test_cell_unescapes_formula_prefixes(): void {
		self::assertSame( '=SUM(1,2)', Csv::unescape_cell( "'=SUM(1,2)" ) );
		self::assertSame( '- one', Csv::unescape_cell( "'- one" ) );
		self::assertSame( 'plain', Csv::unescape_cell( 'plain' ) );
		self::assertSame( "'quote", Csv::unescape_cell( "'quote" ) );
	}
}
