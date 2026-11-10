<?php
/**
 * Settings.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

use ReEvents\Services\API_Keys;
use ReEvents\Services\Booking_Emails;
use ReEvents\Services\Occurrence_Service;

/**
 * Registers the ReEvents settings page.
 */
final class Settings {

	/**
	 * Option name.
	 */
	private const OPTION = 'reevents_settings';

	/**
	 * Placeholders available in mail templates.
	 */
	public const PLACEHOLDERS = [ '{event}', '{date}', '{venue}', '{reference}', '{name}', '{email}', '{quantity}', '{total}', '{site}', '{status}' ];

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'admin_init', [ self::class, 'register_settings' ] );
		add_action( 'admin_menu', [ self::class, 'add_page' ], 11 );
		add_action( 'admin_post_reevents_rebuild_occurrences', [ self::class, 'rebuild' ] );
		add_action( 'admin_post_reevents_send_test_email', [ self::class, 'send_test_email' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
		add_filter( 'option_page_capability_reevents_settings', [ self::class, 'option_page_capability' ] );
	}

	/**
	 * Use the plugin capability when saving the settings form.
	 */
	public static function option_page_capability(): string {
		return 'reevents_manage_settings';
	}

	/**
	 * Enqueue the mail editor script.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue( string $hook ): void {
		if ( 'reevents_page_reevents-settings' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'reevents-settings',
			plugins_url( 'assets/js/admin-settings.js', RE_EVENTS_FILE ),
			[],
			RE_EVENTS_VERSION,
			true
		);

		wp_enqueue_style(
			'reevents-settings',
			plugins_url( 'assets/css/admin-settings.css', RE_EVENTS_FILE ),
			[],
			RE_EVENTS_VERSION
		);
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
		self::add_field(
			'formats',
			'frontend_style',
			__( 'Frontend styles', 're-events' ),
			[
				'type'        => 'select',
				'options'     => [
					'none'    => __( 'None', 're-events' ),
					'default' => __( 'Default', 're-events' ),
				],
				'description' => __( 'None outputs semantic HTML without plugin CSS. Use Default only if you do not style the widgets yourself.', 're-events' ),
			]
		);

		self::add_section( 'bookings', __( 'Booking rules', 're-events' ), 'bookings_rules' );
		self::add_field(
			'bookings',
			'require_approval',
			__( 'Approval', 're-events' ),
			[
				'type'        => 'checkbox',
				'label'       => __( 'Require approval for every booking', 're-events' ),
				'description' => __( 'Free bookings stay pending until an administrator confirms them.', 're-events' ),
			],
			'bookings_rules'
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
			],
			'bookings_rules'
		);
		self::add_section( 'bookings', __( 'Attendee fields', 're-events' ), 'bookings_fields' );
		self::add_field(
			'bookings',
			'attendee_fields',
			__( 'Attendee fields', 're-events' ),
			[
				'type'        => 'attendee_fields',
				'description' => __( 'Name and email are always collected. These fields are added to the booking form.', 're-events' ),
			],
			'bookings_fields'
		);
		self::add_section( 'payments', __( 'Payment gateway', 're-events' ), 'payments_gateway' );
		self::add_field(
			'payments',
			'payment_gateway',
			__( 'Gateway', 're-events' ),
			[
				'type'        => 'select',
				'options'     => [
					'none'   => __( 'None', 're-events' ),
					'mock'   => __( 'Mock (testing)', 're-events' ),
					'stripe' => __( 'Stripe', 're-events' ),
					'paypal' => __( 'PayPal', 're-events' ),
				],
				'description' => __( 'The mock gateway is for local checkout tests only.', 're-events' ),
			],
			'payments_gateway'
		);
		self::add_field(
			'payments',
			'stripe_secret_key',
			__( 'Stripe secret key', 're-events' ),
			[
				'type'        => 'password',
				'description' => __( 'Secret API key from the Stripe dashboard.', 're-events' ),
			],
			'payments_gateway'
		);
		self::add_field(
			'payments',
			'stripe_webhook_secret',
			__( 'Stripe webhook secret', 're-events' ),
			[
				'type'        => 'password',
				'description' => __( 'Signing secret of the webhook endpoint.', 're-events' ),
			],
			'payments_gateway'
		);
		self::add_field(
			'payments',
			'stripe_success_url',
			__( 'Success URL', 're-events' ),
			[
				'type'        => 'url',
				'description' => __( 'Page shown after a completed payment. Defaults to the home page.', 're-events' ),
			],
			'payments_gateway'
		);
		self::add_field(
			'payments',
			'stripe_cancel_url',
			__( 'Cancel URL', 're-events' ),
			[
				'type'        => 'url',
				'description' => __( 'Page shown after a cancelled payment. Defaults to the home page.', 're-events' ),
			],
			'payments_gateway'
		);
		self::add_field(
			'payments',
			'paypal_mode',
			__( 'PayPal mode', 're-events' ),
			[
				'type'    => 'select',
				'options' => [
					'sandbox' => __( 'Sandbox', 're-events' ),
					'live'    => __( 'Live', 're-events' ),
				],
			],
			'payments_gateway'
		);
		self::add_field(
			'payments',
			'paypal_client_id',
			__( 'PayPal client ID', 're-events' ),
			[
				'type'        => 'text',
				'description' => __( 'REST app client ID from the PayPal developer dashboard.', 're-events' ),
			],
			'payments_gateway'
		);
		self::add_field(
			'payments',
			'paypal_client_secret',
			__( 'PayPal client secret', 're-events' ),
			[
				'type'        => 'password',
				'description' => __( 'REST app secret from the PayPal developer dashboard.', 're-events' ),
			],
			'payments_gateway'
		);
		self::add_field(
			'payments',
			'paypal_webhook_id',
			__( 'PayPal webhook ID', 're-events' ),
			[
				'type'        => 'text',
				'description' => __( 'Webhook ID shown after creating the endpoint in PayPal.', 're-events' ),
			],
			'payments_gateway'
		);
		self::add_field(
			'payments',
			'paypal_success_url',
			__( 'PayPal success URL', 're-events' ),
			[
				'type'        => 'url',
				'description' => __( 'Page shown after a completed payment. Defaults to the home page.', 're-events' ),
			],
			'payments_gateway'
		);
		self::add_field(
			'payments',
			'paypal_cancel_url',
			__( 'PayPal cancel URL', 're-events' ),
			[
				'type'        => 'url',
				'description' => __( 'Page shown after a cancelled payment. Defaults to the home page.', 're-events' ),
			],
			'payments_gateway'
		);

		self::add_section( 'emails', __( 'Admin mail', 're-events' ), 'emails_admin' );
		self::add_field( 'emails', 'email_admin_subject', __( 'Subject', 're-events' ), [ 'description' => __( 'Placeholders: {event}, {date}, {venue}, {reference}, {name}, {email}, {quantity}, {total}, {site}, {status}.', 're-events' ) ], 'emails_admin' );
		self::add_field(
			'emails',
			'email_admin_body',
			__( 'Body', 're-events' ),
			[
				'type'    => 'textarea',
				'palette' => true,
			],
			'emails_admin'
		);
		self::add_section( 'emails', __( 'Attendee mail', 're-events' ), 'emails_attendee' );
		self::add_field( 'emails', 'email_attendee_subject', __( 'Subject', 're-events' ), [ 'description' => __( 'Same placeholders as the admin mail.', 're-events' ) ], 'emails_attendee' );
		self::add_field(
			'emails',
			'email_attendee_body',
			__( 'Body', 're-events' ),
			[
				'type'    => 'textarea',
				'palette' => true,
			],
			'emails_attendee'
		);

		self::add_section( 'api', __( 'REST API', 're-events' ), 'api_rest' );
		self::add_field(
			'api',
			'api_enabled',
			__( 'API', 're-events' ),
			[
				'type'        => 'checkbox',
				'label'       => __( 'Enable the plugin REST API', 're-events' ),
				'description' => __( 'When disabled, external API requests are blocked. Admin screens continue to work.', 're-events' ),
			],
			'api_rest'
		);
		self::add_field(
			'api',
			'api_public',
			__( 'Public access', 're-events' ),
			[
				'type'        => 'checkbox',
				'label'       => __( 'Allow unauthenticated read requests', 're-events' ),
				'description' => __( 'Writes always require authentication.', 're-events' ),
			],
			'api_rest'
		);
		self::add_field(
			'api',
			'api_per_page',
			__( 'Default page size', 're-events' ),
			[
				'type'        => 'number',
				'min'         => 1,
				'max'         => 100,
				'description' => __( 'Used when a request does not set per_page. Between 1 and 100.', 're-events' ),
			],
			'api_rest'
		);
		self::add_field(
			'api',
			'api_rate_limit',
			__( 'Rate limit', 're-events' ),
			[
				'type'        => 'number',
				'min'         => 0,
				'max'         => 1000,
				'description' => __( 'Maximum unauthenticated requests per minute per IP. 0 disables the limit.', 're-events' ),
			],
			'api_rest'
		);
		self::add_field(
			'api',
			'api_cors',
			__( 'Allowed origins', 're-events' ),
			[
				'type'        => 'textarea',
				'description' => __( 'One origin per line, for example https://app.example.com. Leave empty to disable CORS headers.', 're-events' ),
			],
			'api_rest'
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
			8
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
			'payments' => __( 'Payments', 're-events' ),
			'emails'   => __( 'Emails', 're-events' ),
			'api'      => __( 'API', 're-events' ),
			'tools'    => __( 'Tools', 're-events' ),
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
			<?php if ( 'tools' === $tab ) : ?>
				<?php self::render_tools(); ?>
			<?php else : ?>
				<?php if ( 'emails' === $tab ) : ?>
					<?php self::render_email_notice(); ?>
				<?php endif; ?>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'reevents_settings' );
					?>
					<input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[reevents_tab]" value="<?php echo esc_attr( $tab ); ?>" />
					<?php
					do_settings_sections( 'reevents-settings-' . $tab );
					?>
					<p class="submit" style="text-align:right;">
						<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Changes', 're-events' ); ?>" />
					</p>
				</form>
				<?php if ( 'emails' === $tab ) : ?>
					<?php self::render_test_email(); ?>
				<?php elseif ( 'api' === $tab ) : ?>
					<?php self::render_api_keys(); ?>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the tools tab.
	 */
	private static function render_tools(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
		$notice = isset( $_GET['reevents_notice'] ) ? sanitize_key( wp_unslash( $_GET['reevents_notice'] ) ) : '';

		if ( 'rebuilt' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Occurrences rebuilt.', 're-events' ) . '</p></div>';
		}
		?>
		<h2><?php esc_html_e( 'Maintenance', 're-events' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="reevents_rebuild_occurrences" />
			<?php wp_nonce_field( 'reevents_rebuild_occurrences' ); ?>
			<p><?php esc_html_e( 'Rebuild stored occurrences for every published event.', 're-events' ); ?></p>
			<?php submit_button( __( 'Rebuild occurrences', 're-events' ), 'secondary', 'submit', false ); ?>
		</form>
		<h2><?php esc_html_e( 'Uninstall', 're-events' ); ?></h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'reevents_settings' ); ?>
			<input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[reevents_tab]" value="tools" />
			<input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[delete_data_on_uninstall]" value="0" />
			<p>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[delete_data_on_uninstall]" value="1" <?php checked( (bool) self::get( 'delete_data_on_uninstall', false ) ); ?> />
					<?php esc_html_e( 'Delete all ReEvents data when the plugin is uninstalled.', 're-events' ); ?>
				</label>
			</p>
			<p class="description"><?php esc_html_e( 'Removes events, venues, organizers, bookings, attendees, settings and API keys. This cannot be undone.', 're-events' ); ?></p>
			<?php submit_button( __( 'Save', 're-events' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Render the mail test result notice.
	 */
	private static function render_email_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
		$notice = isset( $_GET['reevents_notice'] ) ? sanitize_key( wp_unslash( $_GET['reevents_notice'] ) ) : '';

		if ( 'test_sent' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Test email sent.', 're-events' ) . '</p></div>';
		} elseif ( 'test_failed' === $notice ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'The test email could not be sent. Check your site mail configuration.', 're-events' ) . '</p></div>';
		}
	}

	/**
	 * Render the test email form.
	 */
	private static function render_test_email(): void {
		?>
		<h2><?php esc_html_e( 'Send a test', 're-events' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Sends the saved template with sample values to the address below.', 're-events' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="reevents_send_test_email" />
			<?php wp_nonce_field( 'reevents_send_test_email' ); ?>
			<p>
				<label for="reevents-test-type"><?php esc_html_e( 'Template', 're-events' ); ?></label>
				<select id="reevents-test-type" name="type">
					<option value="admin"><?php esc_html_e( 'Admin mail', 're-events' ); ?></option>
					<option value="attendee"><?php esc_html_e( 'Attendee mail', 're-events' ); ?></option>
				</select>
			</p>
			<p>
				<label for="reevents-test-email"><?php esc_html_e( 'Recipient', 're-events' ); ?></label>
				<input type="email" id="reevents-test-email" name="email" class="regular-text" value="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>" required />
			</p>
			<?php submit_button( __( 'Send test email', 're-events' ), 'secondary', 'reevents-test-submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Send a test email with sample values.
	 */
	public static function send_test_email(): void {
		if ( ! current_user_can( 'reevents_manage_settings' ) ) {
			wp_die( esc_html__( 'Forbidden', 're-events' ), 403 );
		}

		check_admin_referer( 'reevents_send_test_email' );

		$type  = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'admin';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( ! is_email( $email ) ) {
			wp_die( esc_html__( 'Enter a valid email address.', 're-events' ), 400 );
		}

		$sent = Booking_Emails::send_test( $type, $email );

		wp_safe_redirect(
			add_query_arg(
				'reevents_notice',
				$sent ? 'test_sent' : 'test_failed',
				admin_url( 'admin.php?page=reevents-settings&tab=emails' )
			)
		);
		exit;
	}

	/**
	 * Render API key management.
	 */
	private static function render_api_keys(): void {
		$keys = API_Keys::all();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
		$notice = isset( $_GET['reevents_notice'] ) ? sanitize_key( wp_unslash( $_GET['reevents_notice'] ) ) : '';
		$new    = get_transient( 'reevents_new_api_key_' . get_current_user_id() );

		if ( is_string( $new ) && '' !== $new ) {
			delete_transient( 'reevents_new_api_key_' . get_current_user_id() );
		}
		?>
		<div id="reevents-api-keys" class="reevents-api-keys">
			<h2><?php esc_html_e( 'API keys', 're-events' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Send a key as an Authorization: Bearer header. Keys inherit the capabilities of the user who created them.', 're-events' ); ?></p>
			<?php if ( 'key_revoked' === $notice ) : ?>
				<div class="notice notice-success is-dismissible">
					<button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 're-events' ); ?></span></button>
					<p><?php esc_html_e( 'API key revoked.', 're-events' ); ?></p>
				</div>
			<?php endif; ?>
			<?php if ( is_string( $new ) && '' !== $new ) : ?>
				<dialog id="reevents-api-key-dialog" class="reevents-api-key-dialog">
					<form method="dialog" class="reevents-api-key-dialog-close">
						<button type="submit" class="button-link" aria-label="<?php esc_attr_e( 'Close', 're-events' ); ?>">&times;</button>
					</form>
					<h2><?php esc_html_e( 'Copy this key now', 're-events' ); ?></h2>
					<p><?php esc_html_e( 'It will not be shown again.', 're-events' ); ?></p>
					<p class="reevents-api-key-copy">
						<input type="text" id="reevents-new-api-key" class="regular-text code" value="<?php echo esc_attr( $new ); ?>" readonly />
						<button type="button" class="button button-primary reevents-copy-key" data-target="reevents-new-api-key" data-copied="<?php esc_attr_e( 'Copied', 're-events' ); ?>"><?php esc_html_e( 'Copy key', 're-events' ); ?></button>
					</p>
				</dialog>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="reevents-api-key-form">
				<input type="hidden" name="action" value="reevents_create_api_key" />
				<?php wp_nonce_field( 'reevents_create_api_key' ); ?>
				<p>
					<label for="reevents-api-key-label" class="screen-reader-text"><?php esc_html_e( 'Label', 're-events' ); ?></label>
					<input type="text" id="reevents-api-key-label" name="label" class="regular-text" placeholder="<?php esc_attr_e( 'Label, e.g. Zapier', 're-events' ); ?>" required />
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Generate key', 're-events' ); ?></button>
				</p>
			</form>
			<?php if ( empty( $keys ) ) : ?>
				<p><?php esc_html_e( 'No API keys yet. Generate one above.', 're-events' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Label', 're-events' ); ?></th>
							<th scope="col"><?php esc_html_e( 'User', 're-events' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Created', 're-events' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Last used', 're-events' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Actions', 're-events' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $keys as $hash => $key ) : ?>
							<?php
							$user    = get_userdata( $key['user_id'] );
							$created = self::format_utc( $key['created'] );
							$last    = '' !== $key['last_used'] ? self::format_utc( $key['last_used'] ) : __( 'Never', 're-events' );
							$url     = wp_nonce_url(
								admin_url( 'admin-post.php?action=reevents_revoke_api_key&key=' . $hash ),
								'reevents_revoke_api_key_' . $hash
							);
							?>
							<tr>
								<td><?php echo esc_html( $key['label'] ); ?></td>
								<td><?php echo esc_html( $user ? $user->display_name : __( 'Unknown', 're-events' ) ); ?></td>
								<td><?php echo esc_html( $created ); ?></td>
								<td><?php echo esc_html( $last ); ?></td>
								<td><a class="button-link-delete reevents-revoke-api-key" data-confirm="<?php esc_attr_e( 'Revoke this API key? This cannot be undone.', 're-events' ); ?>" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Revoke', 're-events' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Format a UTC datetime with the site format.
	 *
	 * @param string $value UTC datetime.
	 */
	private static function format_utc( string $value ): string {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new \DateTimeZone( 'UTC' ) );

		return $date instanceof \DateTimeImmutable ? wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $date->getTimestamp() ) : $value;
	}

	/**
	 * Rebuild occurrences for published events.
	 */
	public static function rebuild(): void {
		if ( ! current_user_can( 'reevents_manage_settings' ) ) {
			wp_die( esc_html__( 'Forbidden', 're-events' ), 403 );
		}

		check_admin_referer( 'reevents_rebuild_occurrences' );

		$offset = 0;

		do {
			$events = get_posts(
				[
					'post_type'      => 'reevents_event',
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'offset'         => $offset,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				]
			);

			$event_count = count( $events );

			foreach ( $events as $event_id ) {
				Occurrence_Service::sync( (int) $event_id );
			}

			$offset += 100;
		} while ( 100 === $event_count );

		$redirect = wp_get_referer();

		if ( ! $redirect ) {
			$redirect = admin_url( 'admin.php?page=reevents-settings&tab=tools' );
		}

		wp_safe_redirect( add_query_arg( 'reevents_notice', 'rebuilt', $redirect ) );
		exit;
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
		$settings = array_merge( self::defaults(), is_array( $settings ) ? $settings : [] );
		$input    = is_array( $input ) ? $input : [];
		$tab      = sanitize_key( (string) ( $input['reevents_tab'] ?? '' ) );

		switch ( $tab ) {
			case 'general':
				$settings['timezone']     = self::sanitize_timezone( $input['timezone'] ?? 'UTC' );
				$settings['time_display'] = self::sanitize_time_display( $input['time_display'] ?? 'event' );
				break;

			case 'formats':
				$settings['date_format']    = sanitize_text_field( (string) ( $input['date_format'] ?? '' ) );
				$settings['time_format']    = sanitize_text_field( (string) ( $input['time_format'] ?? '' ) );
				$frontend_style             = sanitize_key( (string) ( $input['frontend_style'] ?? 'none' ) );
				$settings['frontend_style'] = in_array( $frontend_style, [ 'none', 'default' ], true ) ? $frontend_style : 'none';
				break;

			case 'bookings':
				$settings['require_approval'] = ! empty( $input['require_approval'] );
				$settings['hold_minutes']     = max( 5, min( 1440, absint( $input['hold_minutes'] ?? 30 ) ) );
				$settings['attendee_fields']  = self::sanitize_attendee_fields( $input['attendee_fields'] ?? [] );
				break;

			case 'payments':
				$gateway                           = sanitize_key( (string) ( $input['payment_gateway'] ?? 'none' ) );
				$settings['payment_gateway']       = in_array( $gateway, [ 'none', 'mock', 'stripe', 'paypal' ], true ) ? $gateway : 'none';
				$settings['stripe_secret_key']     = sanitize_text_field( (string) ( $input['stripe_secret_key'] ?? '' ) );
				$settings['stripe_webhook_secret'] = sanitize_text_field( (string) ( $input['stripe_webhook_secret'] ?? '' ) );
				$settings['stripe_success_url']    = esc_url_raw( (string) ( $input['stripe_success_url'] ?? '' ) );
				$settings['stripe_cancel_url']     = esc_url_raw( (string) ( $input['stripe_cancel_url'] ?? '' ) );
				$settings['paypal_mode']           = 'live' === (string) ( $input['paypal_mode'] ?? '' ) ? 'live' : 'sandbox';
				$settings['paypal_client_id']      = sanitize_text_field( (string) ( $input['paypal_client_id'] ?? '' ) );
				$settings['paypal_client_secret']  = sanitize_text_field( (string) ( $input['paypal_client_secret'] ?? '' ) );
				$settings['paypal_webhook_id']     = sanitize_text_field( (string) ( $input['paypal_webhook_id'] ?? '' ) );
				$settings['paypal_success_url']    = esc_url_raw( (string) ( $input['paypal_success_url'] ?? '' ) );
				$settings['paypal_cancel_url']     = esc_url_raw( (string) ( $input['paypal_cancel_url'] ?? '' ) );
				break;

			case 'emails':
				$settings['email_admin_subject']    = sanitize_text_field( (string) ( $input['email_admin_subject'] ?? '' ) );
				$settings['email_admin_body']       = sanitize_textarea_field( (string) ( $input['email_admin_body'] ?? '' ) );
				$settings['email_attendee_subject'] = sanitize_text_field( (string) ( $input['email_attendee_subject'] ?? '' ) );
				$settings['email_attendee_body']    = sanitize_textarea_field( (string) ( $input['email_attendee_body'] ?? '' ) );
				break;

			case 'tools':
				$settings['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );
				break;

			case 'api':
				$settings['api_enabled']    = ! empty( $input['api_enabled'] );
				$settings['api_public']     = ! empty( $input['api_public'] );
				$settings['api_per_page']   = max( 1, min( 100, absint( $input['api_per_page'] ?? 10 ) ) );
				$settings['api_rate_limit'] = max( 0, min( 1000, absint( $input['api_rate_limit'] ?? 0 ) ) );
				$settings['api_cors']       = self::sanitize_origins( (string) ( $input['api_cors'] ?? '' ) );
				break;
		}

		return $settings;
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return [
			'timezone'                 => 'UTC',
			'time_display'             => 'event',
			'date_format'              => get_option( 'date_format', 'F j, Y' ),
			'time_format'              => get_option( 'time_format', 'g:i a' ),
			'frontend_style'           => 'none',
			'require_approval'         => false,
			'hold_minutes'             => 30,
			'attendee_fields'          => [],
			'email_admin_subject'      => __( '[{site}] New booking {reference}', 're-events' ),
			'email_admin_body'         => __( "A new booking was created.\n\nEvent: {event}\nWhen: {date}\nVenue: {venue}\nReference: {reference}\nAttendee: {name} <{email}>\nSeats: {quantity}\nTotal: {total}", 're-events' ),
			'email_attendee_subject'   => __( 'Booking {status}: {event}', 're-events' ),
			'email_attendee_body'      => __( "Hello {name},\n\nYour booking is {status}.\n\nEvent: {event}\nWhen: {date}\nVenue: {venue}\nReference: {reference}\nSeats: {quantity}\nTotal: {total}", 're-events' ),
			'api_enabled'              => true,
			'api_public'               => true,
			'api_per_page'             => 10,
			'api_rate_limit'           => 0,
			'api_cors'                 => '',
			'payment_gateway'          => 'none',
			'stripe_secret_key'        => '',
			'stripe_webhook_secret'    => '',
			'stripe_success_url'       => '',
			'stripe_cancel_url'        => '',
			'paypal_mode'              => 'sandbox',
			'paypal_client_id'         => '',
			'paypal_client_secret'     => '',
			'paypal_webhook_id'        => '',
			'paypal_success_url'       => '',
			'paypal_cancel_url'        => '',
			'delete_data_on_uninstall' => false,
		];
	}

	/**
	 * Add a settings section.
	 *
	 * @param string $tab     Tab slug.
	 * @param string $title   Section title.
	 * @param string $section Optional section slug.
	 */
	private static function add_section( string $tab, string $title, string $section = '' ): void {
		$section = '' !== $section ? $section : $tab;

		add_settings_section(
			'reevents_' . $section,
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
	 * @param string               $title   Field title.
	 * @param array<string, mixed> $args    Extra arguments.
	 * @param string               $section Optional section slug.
	 */
	private static function add_field( string $tab, string $key, string $title, array $args = [], string $section = '' ): void {
		$section = '' !== $section ? $section : $tab;

		add_settings_field(
			'reevents_' . $key,
			$title,
			[ self::class, 'render_field' ],
			'reevents-settings-' . $tab,
			'reevents_' . $section,
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
	 * Render the attendee field repeater.
	 *
	 * @param array<string, mixed> $fields Saved fields.
	 */
	private static function render_attendee_fields( array $fields ): void {
		$types = [
			'text'     => __( 'Text', 're-events' ),
			'email'    => __( 'Email', 're-events' ),
			'tel'      => __( 'Telephone', 're-events' ),
			'textarea' => __( 'Textarea', 're-events' ),
		];
		?>
		<div id="reevents-attendee-fields" class="reevents-attendee-fields">
			<div id="reevents-attendee-rows">
				<?php foreach ( $fields as $key => $field ) : ?>
					<?php self::render_attendee_field_row( (string) $key, is_array( $field ) ? $field : [], $types ); ?>
				<?php endforeach; ?>
			</div>
			<template id="reevents-attendee-field-template">
				<?php self::render_attendee_field_row( '', [], $types, true ); ?>
			</template>
			<p><button type="button" class="button" id="reevents-add-attendee-field"><?php esc_html_e( 'Add field', 're-events' ); ?></button></p>
		</div>
		<?php
	}

	/**
	 * Render one attendee field row.
	 *
	 * @param string                $key    Field key.
	 * @param array<string, mixed>  $field  Field data.
	 * @param array<string, string> $types Field types.
	 * @param bool                  $is_new Whether this is the template row.
	 */
	private static function render_attendee_field_row( string $key, array $field, array $types, bool $is_new = false ): void {
		$name = self::OPTION . '[attendee_fields][]';
		?>
		<div class="reevents-attendee-row" data-new="<?php echo esc_attr( $is_new ? '1' : '' ); ?>">
			<input type="text" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( (string) ( $field['label'] ?? '' ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Label', 're-events' ); ?>" data-reevents-label />
			<select name="<?php echo esc_attr( $name ); ?>[type]" aria-label="<?php esc_attr_e( 'Type', 're-events' ); ?>">
				<?php foreach ( $types as $type => $label ) : ?>
					<option value="<?php echo esc_attr( $type ); ?>" <?php selected( (string) ( $field['type'] ?? 'text' ), $type ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<label class="reevents-attendee-required">
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[required]" value="1" <?php checked( ! empty( $field['required'] ) ); ?> />
				<?php esc_html_e( 'Required', 're-events' ); ?>
			</label>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[key]" value="<?php echo esc_attr( $key ); ?>" data-reevents-key />
			<button type="button" class="button-link reevents-move-up" aria-label="<?php esc_attr_e( 'Move up', 're-events' ); ?>">&uarr;</button>
			<button type="button" class="button-link reevents-move-down" aria-label="<?php esc_attr_e( 'Move down', 're-events' ); ?>">&darr;</button>
			<button type="button" class="button-link reevents-remove-attendee-field" aria-label="<?php esc_attr_e( 'Remove field', 're-events' ); ?>"><?php esc_html_e( 'Remove', 're-events' ); ?></button>
		</div>
		<?php
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
		} elseif ( 'attendee_fields' === $type ) {
			self::render_attendee_fields( is_array( $value ) ? $value : [] );
		} elseif ( 'textarea' === $type ) {
			echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="6" class="large-text code">' . esc_textarea( (string) $value ) . '</textarea>';

			if ( ! empty( $args['palette'] ) ) {
				echo '<p class="reevents-placeholder-palette">';

				foreach ( self::PLACEHOLDERS as $token ) {
					echo '<button type="button" class="button button-small reevents-placeholder" data-target="' . esc_attr( $id ) . '" data-token="' . esc_attr( $token ) . '">' . esc_html( $token ) . '</button> ';
				}

				echo '</p><p class="description">' . esc_html__( 'Click a placeholder to insert it at the cursor.', 're-events' ) . '</p>';
			}
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
	 * Sanitize allowed CORS origins.
	 *
	 * @param string $value Raw textarea.
	 */
	private static function sanitize_origins( string $value ): string {
		$lines   = preg_split( '/\r\n|\r|\n/', $value );
		$lines   = is_array( $lines ) ? $lines : [];
		$origins = [];

		foreach ( $lines as $line ) {
			$origin = untrailingslashit( esc_url_raw( trim( $line ) ) );

			if ( '' !== $origin ) {
				$origins[] = $origin;
			}
		}

		return implode( "\n", array_unique( $origins ) );
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
	 * @param mixed $value Repeater rows or legacy one-per-line text.
	 * @return array<string, array{label:string,type:string,required:bool}>
	 */
	private static function sanitize_attendee_fields( $value ): array {
		if ( is_string( $value ) ) {
			$value = self::parse_attendee_field_lines( $value );
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		$fields = [];
		$types  = [ 'text', 'email', 'tel', 'textarea' ];

		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );

			if ( '' === $label ) {
				continue;
			}

			$key = sanitize_key( (string) ( $row['key'] ?? '' ) );

			if ( '' === $key ) {
				$key = sanitize_key( sanitize_title( $label ) );
			}

			if ( '' === $key ) {
				$key = 'field-' . ( count( $fields ) + 1 );
			}

			$base = $key;
			$i    = 2;

			while ( isset( $fields[ $key ] ) ) {
				$key = $base . '-' . $i;
				++$i;
			}

			$type = sanitize_key( (string) ( $row['type'] ?? 'text' ) );

			if ( ! in_array( $type, $types, true ) ) {
				$type = 'text';
			}

			$fields[ $key ] = [
				'label'    => $label,
				'type'     => $type,
				'required' => ! empty( $row['required'] ),
			];
		}

		return $fields;
	}

	/**
	 * Parse legacy one-per-line attendee fields.
	 *
	 * @param string $value Raw textarea.
	 * @return array<int, array<string, mixed>>
	 */
	private static function parse_attendee_field_lines( string $value ): array {
		$rows  = [];
		$lines = preg_split( '/\r\n|\r|\n/', $value );
		$lines = is_array( $lines ) ? $lines : [];

		foreach ( $lines as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );

			if ( count( $parts ) < 2 ) {
				continue;
			}

			$rows[] = [
				'key'      => $parts[0],
				'label'    => $parts[1],
				'type'     => $parts[2] ?? 'text',
				'required' => '1' === ( $parts[3] ?? '' ),
			];
		}

		return $rows;
	}
}
