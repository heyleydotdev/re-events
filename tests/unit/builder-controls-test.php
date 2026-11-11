<?php
/**
 * Builder control mapping tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReEvents\Builders\Controls;
use ReEvents\Builders\Elementor\Adapter;

/**
 * Covers the widget schema to builder control mapping.
 */
final class Builder_Controls_Test extends TestCase {

	/**
	 * Argument keys become readable labels.
	 */
	public function test_label_from_key(): void {
		self::assertSame( 'Wrapper Class', Controls::label( 'wrapper_class' ) );
		self::assertSame( 'Show Venue', Controls::label( 'show-venue' ) );
		self::assertSame( 'Limit', Controls::label( 'limit' ) );
	}

	/**
	 * Unknown schema types fall back to text.
	 */
	public function test_type_fallback(): void {
		self::assertSame( 'number', Controls::type( 'number' ) );
		self::assertSame( 'toggle', Controls::type( 'toggle' ) );
		self::assertSame( 'date', Controls::type( 'date' ) );
		self::assertSame( 'text', Controls::type( 'event-select' ) );
	}

	/**
	 * Widget arguments keep their defaults and descriptions.
	 */
	public function test_for_widget(): void {
		$controls = Controls::for_widget(
			[
				'args' => [
					'limit'    => [
						'type'    => 'number',
						'default' => 5,
					],
					'upcoming' => [
						'type'        => 'toggle',
						'default'     => true,
						'description' => 'Only upcoming events',
					],
				],
			]
		);

		self::assertSame(
			[
				'limit'    => [
					'type'        => 'number',
					'label'       => 'Limit',
					'description' => '',
					'default'     => 5,
				],
				'upcoming' => [
					'type'        => 'toggle',
					'label'       => 'Upcoming',
					'description' => 'Only upcoming events',
					'default'     => true,
				],
			],
			$controls
		);
	}

	/**
	 * Registry IDs map to Elementor widget classes.
	 */
	public function test_elementor_class_names(): void {
		self::assertSame( 'ReEvents\\Builders\\Elementor\\Events_List', Adapter::class_for( 'events-list' ) );
		self::assertSame( 'ReEvents\\Builders\\Elementor\\Booking_Form', Adapter::class_for( 'booking-form' ) );
	}
}
