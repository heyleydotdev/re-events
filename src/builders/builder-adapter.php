<?php
/**
 * Page builder adapter contract.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Builders;

/**
 * A page builder integration.
 *
 * Adapters register native builder elements that call `Widget_Registry::render()`.
 * They contain no markup and no business logic.
 */
interface Builder_Adapter {

	/**
	 * Stable adapter ID.
	 */
	public static function id(): string;

	/**
	 * Human-readable builder name.
	 */
	public static function label(): string;

	/**
	 * Whether the builder is loaded on this request.
	 */
	public static function is_active(): bool;

	/**
	 * Register builder hooks.
	 *
	 * Called on every request. The hooks only fire when the builder is active.
	 */
	public static function register(): void;
}
