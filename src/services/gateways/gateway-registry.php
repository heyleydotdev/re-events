<?php
/**
 * Payment gateway registry.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services\Gateways;

/**
 * Holds registered payment gateways.
 */
final class Gateway_Registry {

	/**
	 * Registered gateways.
	 *
	 * @var array<string, Gateway>
	 */
	private static array $gateways = [];

	/**
	 * Register a gateway.
	 *
	 * @param Gateway $gateway Gateway.
	 */
	public static function register( Gateway $gateway ): void {
		self::$gateways[ $gateway->id() ] = $gateway;
	}

	/**
	 * Get a gateway.
	 *
	 * @param string $id Gateway ID.
	 */
	public static function get( string $id ): ?Gateway {
		return self::$gateways[ $id ] ?? null;
	}

	/**
	 * Get all gateways.
	 *
	 * @return array<string, Gateway>
	 */
	public static function all(): array {
		return self::$gateways;
	}

	/**
	 * Register built-in gateways.
	 */
	public static function register_defaults(): void {
		if ( self::$gateways ) {
			return;
		}

		self::register( new Mock_Gateway() );

		do_action( 'reevents_register_gateways', self::class );
	}
}
