<?php
/**
 * Settings.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

/**
 * Registers the ReEvents settings page.
 */
final class Settings {

	/**
	 * Option name.
	 */
	private const OPTION = 'reevents_settings';

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'admin_init', [ self::class, 'register_settings' ] );
		add_action( 'admin_menu', [ self::class, 'add_page' ], 11 );
	}

	/**
	 * Register the option and fields.
	 */
	public static function register_settings(): void {
		register_setting(
			'reevents_settings',
			self::OPTION,
			[
				'type'              => 'array',
				'default'           => self::defaults(),
				'sanitize_callback' => [ self::class, 'sanitize' ],
			]
		);

		self::add_section( 'general', __( 'General', 're-events' ) );
		self::add_field(
			'general',
			'timezone',
			__( 'Default event timezone', 're-events' ),
			[
				'type'        => 'timezone',
				'description' => __( 'Used for new events; each event can override it. The WordPress site timezone is set separately under Settings > General.', 're-events' ),
			]
		);
		self::add_field(
			'general',
			'currency',
			__( 'Currency', 're-events' ),
			[
				'maxlength'   => 3,
				'description' => __( 'Three-letter ISO code, e.g. USD.', 're-events' ),
			]
		);

		self::add_section( 'formats', __( 'Formats', 're-events' ) );
		self::add_field( 'formats', 'date_format', __( 'Date format', 're-events' ), [ 'placeholder' => 'F j, Y' ] );
		self::add_field( 'formats', 'time_format', __( 'Time format', 're-events' ), [ 'placeholder' => 'g:i a' ] );

		self::add_section( 'pages', __( 'Pages', 're-events' ) );
		self::add_field( 'pages', 'submission_page', __( 'Event submission page', 're-events' ), [ 'type' => 'page' ] );
		self::add_field( 'pages', 'bookings_page', __( 'My bookings page', 're-events' ), [ 'type' => 'page' ] );

		self::add_section( 'emails', __( 'Emails', 're-events' ) );
		self::add_field( 'emails', 'from_name', __( 'From name', 're-events' ) );
		self::add_field( 'emails', 'from_email', __( 'From email', 're-events' ), [ 'type' => 'email' ] );

		self::add_section( 'maps', __( 'Maps', 're-events' ) );
		self::add_field(
			'maps',
			'map_provider',
			__( 'Map provider', 're-events' ),
			[
				'type'    => 'select',
				'options' => [
					'osm'    => __( 'OpenStreetMap', 're-events' ),
					'google' => __( 'Google Maps', 're-events' ),
				],
			]
		);
		self::add_field( 'maps', 'google_maps_key', __( 'Google Maps API key', 're-events' ), [ 'description' => __( 'Required only for Google Maps.', 're-events' ) ] );

		self::add_section( 'api', __( 'API', 're-events' ) );
		self::add_field(
			'api',
			'rest_enabled',
			__( 'REST API', 're-events' ),
			[
				'type'        => 'checkbox',
				'label'       => __( 'Enable the ReEvents REST API', 're-events' ),
				'description' => __( 'Namespace: re-events/v1', 're-events' ),
			]
		);
	}

	/**
	 * Add the settings page.
	 */
	public static function add_page(): void {
		add_submenu_page(
			're-events',
			__( 'ReEvents Settings', 're-events' ),
			__( 'Settings', 're-events' ),
			'reevents_manage_settings',
			'reevents-settings',
			[ self::class, 'render' ]
		);
	}

	/**
	 * Render the settings page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'reevents_manage_settings' ) ) {
			return;
		}

		$tabs = [
			'general' => __( 'General', 're-events' ),
			'formats' => __( 'Formats', 're-events' ),
			'pages'   => __( 'Pages', 're-events' ),
			'emails'  => __( 'Emails', 're-events' ),
			'maps'    => __( 'Maps', 're-events' ),
			'api'     => __( 'API', 're-events' ),
		];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation only.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';

		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ReEvents Settings', 're-events' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=reevents-settings&tab=' . $slug ) ); ?>" class="nav-tab <?php echo esc_attr( $tab === $slug ? 'nav-tab-active' : '' ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<form method="post" action="options.php">
				<?php settings_fields( 'reevents_settings' ); ?>
				<input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[reevents_tab]" value="<?php echo esc_attr( $tab ); ?>" />
				<?php
				do_settings_sections( 'reevents-settings-' . $tab );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Fallback.
	 * @return mixed
	 */
	public static function get( string $key, $fallback = null ) {
		$settings = get_option( self::OPTION, [] );

		if ( ! is_array( $settings ) || ! array_key_exists( $key, $settings ) ) {
			$defaults = self::defaults();

			return $defaults[ $key ] ?? $fallback;
		}

		return $settings[ $key ];
	}

	/**
	 * Sanitize settings for the submitted tab.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ): array {
		$settings = get_option( self::OPTION, self::defaults() );
		$settings = is_array( $settings ) ? $settings : self::defaults();
		$input    = is_array( $input ) ? $input : [];
		$tab      = sanitize_key( (string) ( $input['reevents_tab'] ?? '' ) );

		switch ( $tab ) {
			case 'general':
				$settings['timezone'] = self::sanitize_timezone( $input['timezone'] ?? 'UTC' );
				$settings['currency'] = self::sanitize_currency( $input['currency'] ?? 'USD' );
				break;

			case 'formats':
				$settings['date_format'] = sanitize_text_field( (string) ( $input['date_format'] ?? '' ) );
				$settings['time_format'] = sanitize_text_field( (string) ( $input['time_format'] ?? '' ) );
				break;

			case 'pages':
				$settings['submission_page'] = self::sanitize_page( $input['submission_page'] ?? 0 );
				$settings['bookings_page']   = self::sanitize_page( $input['bookings_page'] ?? 0 );
				break;

			case 'emails':
				$settings['from_name']  = sanitize_text_field( (string) ( $input['from_name'] ?? '' ) );
				$settings['from_email'] = self::sanitize_email_address( $input['from_email'] ?? '', (string) $settings['from_email'] );
				break;

			case 'maps':
				$provider                    = sanitize_key( (string) ( $input['map_provider'] ?? 'osm' ) );
				$settings['map_provider']    = in_array( $provider, [ 'osm', 'google' ], true ) ? $provider : 'osm';
				$settings['google_maps_key'] = sanitize_text_field( (string) ( $input['google_maps_key'] ?? '' ) );
				break;

			case 'api':
				$settings['rest_enabled'] = ! empty( $input['rest_enabled'] );
				break;
		}

		return $settings;
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	private static function defaults(): array {
		return [
			'timezone'        => 'UTC',
			'currency'        => 'USD',
			'date_format'     => get_option( 'date_format', 'F j, Y' ),
			'time_format'     => get_option( 'time_format', 'g:i a' ),
			'submission_page' => 0,
			'bookings_page'   => 0,
			'from_name'       => get_bloginfo( 'name' ),
			'from_email'      => get_option( 'admin_email', '' ),
			'map_provider'    => 'osm',
			'google_maps_key' => '',
			'rest_enabled'    => true,
		];
	}

	/**
	 * Add a settings section.
	 *
	 * @param string $tab   Tab slug.
	 * @param string $title Section title.
	 */
	private static function add_section( string $tab, string $title ): void {
		add_settings_section(
			'reevents_' . $tab,
			$title,
			'__return_null',
			'reevents-settings-' . $tab
		);
	}

	/**
	 * Add a settings field.
	 *
	 * @param string               $tab   Tab slug.
	 * @param string               $key   Setting key.
	 * @param string               $title Field title.
	 * @param array<string, mixed> $args  Extra arguments.
	 */
	private static function add_field( string $tab, string $key, string $title, array $args = [] ): void {
		add_settings_field(
			'reevents_' . $key,
			$title,
			[ self::class, 'render_field' ],
			'reevents-settings-' . $tab,
			'reevents_' . $tab,
			array_merge(
				[
					'key'       => $key,
					'label_for' => 'reevents_' . $key,
				],
				$args
			)
		);
	}

	/**
	 * Render a settings field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public static function render_field( array $args ): void {
		$key   = (string) ( $args['key'] ?? '' );
		$type  = (string) ( $args['type'] ?? 'text' );
		$value = self::get( $key );
		$name  = self::OPTION . '[' . $key . ']';
		$id    = 'reevents_' . $key;

		if ( 'checkbox' === $type ) {
			echo '<label for="' . esc_attr( $id ) . '">';
			echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( (bool) $value, true, false ) . ' />';

			if ( ! empty( $args['label'] ) ) {
				echo ' ' . esc_html( (string) $args['label'] );
			}

			echo '</label>';
		} elseif ( 'select' === $type ) {
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';

			foreach ( (array) ( $args['options'] ?? [] ) as $option_value => $label ) {
				echo '<option value="' . esc_attr( (string) $option_value ) . '" ' . selected( (string) $value, (string) $option_value, false ) . '>' . esc_html( (string) $label ) . '</option>';
			}

			echo '</select>';
		} elseif ( 'page' === $type ) {
			$pages = get_pages( [ 'post_status' => 'publish' ] );

			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
			echo '<option value="0">' . esc_html__( 'None', 're-events' ) . '</option>';

			foreach ( $pages as $page ) {
				echo '<option value="' . esc_attr( (string) $page->ID ) . '" ' . selected( (int) $value, $page->ID, false ) . '>' . esc_html( $page->post_title ) . '</option>';
			}

			echo '</select>';
		} elseif ( 'timezone' === $type ) {
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';

			foreach ( timezone_identifiers_list() as $zone ) {
				echo '<option value="' . esc_attr( $zone ) . '" ' . selected( (string) $value, $zone, false ) . '>' . esc_html( $zone ) . '</option>';
			}

			echo '</select>';
		} else {
			$attributes = '';

			foreach ( [ 'placeholder', 'min', 'max', 'step', 'maxlength' ] as $attribute ) {
				if ( isset( $args[ $attribute ] ) ) {
					$attributes .= ' ' . $attribute . '="' . esc_attr( (string) $args[ $attribute ] ) . '"';
				}
			}

			echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" class="regular-text"' . $attributes . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are escaped above.
		}

		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( (string) $args['description'] ) . '</p>';
		}
	}

	/**
	 * Sanitize an IANA timezone.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function sanitize_timezone( $value ): string {
		$value = sanitize_text_field( (string) $value );

		return in_array( $value, timezone_identifiers_list(), true ) ? $value : 'UTC';
	}

	/**
	 * Sanitize a three-letter currency code.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function sanitize_currency( $value ): string {
		$value = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $value ) ?? '' );

		return 3 === strlen( $value ) ? $value : 'USD';
	}

	/**
	 * Sanitize a page ID.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function sanitize_page( $value ): int {
		$page_id = absint( $value );

		return $page_id && 'page' === get_post_type( $page_id ) ? $page_id : 0;
	}

	/**
	 * Sanitize an email address, keeping the previous value if invalid.
	 *
	 * @param mixed  $value    Raw value.
	 * @param string $fallback Fallback email.
	 */
	private static function sanitize_email_address( $value, string $fallback ): string {
		$email = sanitize_email( (string) $value );

		return $email ? $email : $fallback;
	}
}
