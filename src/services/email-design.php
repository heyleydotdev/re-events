<?php
/**
 * Email design detection.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services;

/**
 * Detects site-wide email designers and picks who owns the HTML envelope.
 */
final class Email_Design {

	/**
	 * Known wrapper plugins, keyed by plugin folder.
	 *
	 * @var array<string, string>
	 */
	private const WRAPPERS = [
		'wp-html-mail'                         => 'Email Template Designer (WP HTML Mail)',
		'presspilot-email-template-customizer' => 'PressPilot Email Template Customizer',
		'email-templates'                      => 'Email Templates',
		'custom-email-template'                => 'Custom Email Template',
	];

	/**
	 * Find an active email designer.
	 *
	 * @return string|null
	 */
	public static function detected(): ?string {
		$active = (array) get_option( 'active_plugins', [] );

		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
		}

		foreach ( $active as $file ) {
			$slug = dirname( (string) $file );

			if ( isset( self::WRAPPERS[ $slug ] ) ) {
				return self::WRAPPERS[ $slug ];
			}
		}

		return null;
	}

	/**
	 * Pick the mail format: html, plain or the automatic choice.
	 */
	public static function format(): string {
		/**
		 * Filter the booking mail format.
		 *
		 * @since 1.1.0
		 *
		 * @param string $format html, plain or auto.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Plugin hook.
		$format = (string) apply_filters( 'reevents_email_format', 'auto' );

		if ( in_array( $format, [ 'html', 'plain' ], true ) ) {
			return $format;
		}

		return null === self::detected() ? 'html' : 'plain';
	}
}
