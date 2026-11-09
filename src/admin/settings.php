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
			'time_display',
			__( 'Time display', 're-events' ),
			[
				'type'        => 'select',
				'options'     => [
					'site'    => __( 'Site timezone', 're-events' ),
					'event'   => __( 'Event timezone', 're-events' ),
					'visitor' => __( 'Visitor timezone', 're-events' ),
				],
				'description' => __( 'How event times are shown on the front end. The calendar always uses the site or visitor timezone.', 're-events' ),
			]
		);

		self::add_section( 'formats', __( 'Formats', 're-events' ) );
		self::add_field( 'formats', 'date_format', __( 'Date format', 're-events' ), [ 'placeholder' => 'F j, Y' ] );
		self::add_field( 'formats', 'time_format', __( 'Time format', 're-events' ), [ 'placeholder' => 'g:i a' ] );

		self::add_section( 'bookings', __( 'Bookings', 're-events' ) );
		self::add_field(
			'bookings',
			'require_approval',
			__( 'Approval', 're-events' ),
			[
				'type'        => 'checkbox',
				'label'       => __( 'Require approval for every booking', 're-events' ),
				'description' => __( 'Free bookings stay pending until an administrator confirms them.', 're-events' ),
			]
		);
		self::add_field(
			'bookings',
			'hold_minutes',
			__( 'Hold time', 're-events' ),
			[
				'type'        => 'number',
				'min'         => 5,
				'max'         => 1440,
				'description' => __( 'Minutes before an unpaid booking is cancelled. Minimum 5.', 're-events' ),
			]
		);
		self::add_field(
			'bookings',
			'attendee_fields',
			__( 'Attendee fields', 're-events' ),
			[
				'type'        => 'textarea',
				'description' => __( 'One per line: key|Label|type|required. Types: text, email, tel, textarea. Required: 1 or 0.', 're-events' ),
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
			[ self::class, 'render' ],
			6
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
			'general'  => __( 'General', 're-events' ),
			'formats'  => __( 'Formats', 're-events' ),
			'bookings' => __( 'Bookings', 're-events' ),
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
				$settings['timezone']     = self::sanitize_timezone( $input['timezone'] ?? 'UTC' );
				$settings['time_display'] = self::sanitize_time_display( $input['time_display'] ?? 'event' );
				break;

			case 'formats':
				$settings['date_format'] = sanitize_text_field( (string) ( $input['date_format'] ?? '' ) );
				$settings['time_format'] = sanitize_text_field( (string) ( $input['time_format'] ?? '' ) );
				break;

			case 'bookings':
				$settings['require_approval'] = ! empty( $input['require_approval'] );
				$settings['hold_minutes']     = max( 5, min( 1440, absint( $input['hold_minutes'] ?? 30 ) ) );
				$settings['attendee_fields']  = self::sanitize_attendee_fields( (string) ( $input['attendee_fields'] ?? '' ) );
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
			'timezone'         => 'UTC',
			'time_display'     => 'event',
			'date_format'      => get_option( 'date_format', 'F j, Y' ),
			'time_format'      => get_option( 'time_format', 'g:i a' ),
			'require_approval' => false,
			'hold_minutes'     => 30,
			'attendee_fields'  => [],
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
		} elseif ( 'textarea' === $type ) {
			if ( 'attendee_fields' === $key ) {
				$lines = [];

				foreach ( (array) $value as $field_key => $field ) {
					if ( ! is_array( $field ) ) {
						continue;
					}

					$lines[] = $field_key . '|' . (string) ( $field['label'] ?? '' ) . '|' . (string) ( $field['type'] ?? 'text' ) . '|' . ( ! empty( $field['required'] ) ? '1' : '0' );
				}

				$value = implode( "\n", $lines );
			}

			echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="6" class="large-text code">' . esc_textarea( (string) $value ) . '</textarea>';
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
	 * Sanitize the time display mode.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function sanitize_time_display( $value ): string {
		$value = sanitize_key( (string) $value );

		return in_array( $value, [ 'site', 'event', 'visitor' ], true ) ? $value : 'event';
	}

	/**
	 * Get custom attendee fields.
	 *
	 * @return array<string, array{label:string,type:string,required:bool}>
	 */
	public static function attendee_fields(): array {
		$fields = self::get( 'attendee_fields', [] );

		return is_array( $fields ) ? $fields : [];
	}

	/**
	 * Sanitize attendee field definitions.
	 *
	 * @param string $value Raw textarea.
	 * @return array<string, array{label:string,type:string,required:bool}>
	 */
	private static function sanitize_attendee_fields( string $value ): array {
		$fields = [];
		$lines  = preg_split( '/\r\n|\r|\n/', $value );
		$lines  = is_array( $lines ) ? $lines : [];

		foreach ( $lines as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );

			if ( count( $parts ) < 2 ) {
				continue;
			}

			$key   = sanitize_key( $parts[0] );
			$label = sanitize_text_field( $parts[1] );
			$type  = $parts[2] ?? 'text';

			if ( ! in_array( $type, [ 'text', 'email', 'tel', 'textarea' ], true ) ) {
				$type = 'text';
			}

			if ( '' === $key || '' === $label ) {
				continue;
			}

			$fields[ $key ] = [
				'label'    => $label,
				'type'     => $type,
				'required' => '1' === ( $parts[3] ?? '' ),
			];
		}

		return $fields;
	}
}
