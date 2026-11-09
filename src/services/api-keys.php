<?php
/**
 * API keys.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services;

/**
 * Issues and authenticates integration API keys.
 */
final class API_Keys {

	/**
	 * Option name.
	 */
	private const OPTION = 'reevents_api_keys';

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_filter( 'determine_current_user', [ self::class, 'authenticate' ], 20 );
		add_action( 'admin_post_reevents_create_api_key', [ self::class, 'handle_create' ] );
		add_action( 'admin_post_reevents_revoke_api_key', [ self::class, 'handle_revoke' ] );
	}

	/**
	 * Stored keys.
	 *
	 * @return array<string, array{label:string,user_id:int,created:string,last_used:string}>
	 */
	public static function all(): array {
		$keys = get_option( self::OPTION, [] );

		return is_array( $keys ) ? $keys : [];
	}

	/**
	 * Create a key.
	 *
	 * @param string $label   Label.
	 * @param int    $user_id Owner.
	 * @return string Plaintext key.
	 */
	public static function create( string $label, int $user_id ): string {
		$keys = self::all();

		do {
			$key  = 're_' . bin2hex( random_bytes( 24 ) );
			$hash = self::hash( $key );
		} while ( isset( $keys[ $hash ] ) );

		$keys[ $hash ] = [
			'label'     => $label,
			'user_id'   => $user_id,
			'created'   => gmdate( 'Y-m-d H:i:s' ),
			'last_used' => '',
		];

		update_option( self::OPTION, $keys, false );

		return $key;
	}

	/**
	 * Revoke a key.
	 *
	 * @param string $hash Key hash.
	 */
	public static function revoke( string $hash ): bool {
		$keys = self::all();

		if ( ! isset( $keys[ $hash ] ) ) {
			return false;
		}

		unset( $keys[ $hash ] );
		update_option( self::OPTION, $keys, false );

		return true;
	}

	/**
	 * Authenticate a request with a key.
	 *
	 * @param int|false $user_id Current user ID.
	 * @return int|false
	 */
	public static function authenticate( $user_id ) {
		if ( $user_id ) {
			return $user_id;
		}

		$key = self::request_key();

		if ( '' === $key ) {
			return $user_id;
		}

		$hash = self::hash( $key );
		$keys = self::all();

		if ( ! isset( $keys[ $hash ] ) ) {
			return $user_id;
		}

		$owner = (int) $keys[ $hash ]['user_id'];

		if ( ! get_userdata( $owner ) ) {
			return $user_id;
		}

		self::touch( $hash, $keys );

		return $owner;
	}

	/**
	 * Whether the request carries an authentication header.
	 */
	public static function request_uses_auth_header(): bool {
		return isset( $_SERVER['HTTP_AUTHORIZATION'] ) || isset( $_SERVER['HTTP_X_REEVENTS_KEY'] );
	}

	/**
	 * Create a key from the settings form.
	 */
	public static function handle_create(): void {
		if ( ! current_user_can( 'reevents_manage_settings' ) ) {
			wp_die( esc_html__( 'Forbidden', 're-events' ), 403 );
		}

		check_admin_referer( 'reevents_create_api_key' );

		$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$key   = self::create( '' !== $label ? $label : __( 'API key', 're-events' ), get_current_user_id() );

		set_transient( 'reevents_new_api_key_' . get_current_user_id(), $key, 5 * MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'admin.php?page=reevents-settings&tab=api&reevents_notice=key_created' ) );
		exit;
	}

	/**
	 * Revoke a key from the settings list.
	 */
	public static function handle_revoke(): void {
		if ( ! current_user_can( 'reevents_manage_settings' ) ) {
			wp_die( esc_html__( 'Forbidden', 're-events' ), 403 );
		}

		$hash = isset( $_GET['key'] ) ? sanitize_key( wp_unslash( $_GET['key'] ) ) : '';
		check_admin_referer( 'reevents_revoke_api_key_' . $hash );

		self::revoke( $hash );

		wp_safe_redirect( admin_url( 'admin.php?page=reevents-settings&tab=api&reevents_notice=key_revoked' ) );
		exit;
	}

	/**
	 * Update the last-used time at most once per five minutes.
	 *
	 * @param string                              $hash Key hash.
	 * @param array<string, array<string, mixed>> $keys Stored keys.
	 */
	private static function touch( string $hash, array $keys ): void {
		$last = (string) ( $keys[ $hash ]['last_used'] ?? '' );
		$time = '' !== $last ? strtotime( $last . ' UTC' ) : false;

		if ( false !== $time && time() - $time < 5 * MINUTE_IN_SECONDS ) {
			return;
		}

		$keys[ $hash ]['last_used'] = gmdate( 'Y-m-d H:i:s' );
		update_option( self::OPTION, $keys, false );
	}

	/**
	 * Read the key from the request headers.
	 */
	private static function request_key(): string {
		$authorization = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ) : '';
		$header        = isset( $_SERVER['HTTP_X_REEVENTS_KEY'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REEVENTS_KEY'] ) ) : '';

		if ( '' !== $header ) {
			return $header;
		}

		if ( 0 === stripos( $authorization, 'Bearer ' ) ) {
			return trim( substr( $authorization, 7 ) );
		}

		return '';
	}

	/**
	 * Hash a key for storage and lookup.
	 *
	 * @param string $key Plaintext key.
	 */
	private static function hash( string $key ): string {
		return hash_hmac( 'sha256', $key, wp_salt( 'auth' ) );
	}
}
