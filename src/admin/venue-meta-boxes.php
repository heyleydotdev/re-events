<?php
/**
 * Venue meta boxes.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

use ReEvents\Data\Meta;
use WP_Post;

/**
 * Registers venue meta boxes.
 */
final class Venue_Meta_Boxes {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes_reevents_venue', [ self::class, 'add' ] );
		add_action( 'save_post_reevents_venue', [ self::class, 'save' ] );
	}

	/**
	 * Add the venue details box.
	 */
	public static function add(): void {
		add_meta_box(
			'reevents-venue-details',
			__( 'Venue details', 're-events' ),
			[ self::class, 'render' ],
			'reevents_venue',
			'normal',
			'high'
		);
	}

	/**
	 * Render the venue details box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function render( WP_Post $post ): void {
		$address = (string) get_post_meta( $post->ID, '_reevents_address', true );
		$lat     = (string) get_post_meta( $post->ID, '_reevents_lat', true );
		$lng     = (string) get_post_meta( $post->ID, '_reevents_lng', true );
		$website = (string) get_post_meta( $post->ID, '_reevents_website', true );

		wp_nonce_field( 'reevents_venue_details', 'reevents_venue_details_nonce' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="reevents-venue-address"><?php esc_html_e( 'Address', 're-events' ); ?></label></th>
				<td>
					<textarea id="reevents-venue-address" name="reevents_venue_address" rows="3" class="large-text"><?php echo esc_textarea( $address ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="reevents-venue-lat"><?php esc_html_e( 'Latitude', 're-events' ); ?></label></th>
				<td>
					<input type="number" id="reevents-venue-lat" name="reevents_venue_lat" step="any" min="-90" max="90" value="<?php echo esc_attr( $lat ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="reevents-venue-lng"><?php esc_html_e( 'Longitude', 're-events' ); ?></label></th>
				<td>
					<input type="number" id="reevents-venue-lng" name="reevents_venue_lng" step="any" min="-180" max="180" value="<?php echo esc_attr( $lng ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="reevents-venue-website"><?php esc_html_e( 'Website', 're-events' ); ?></label></th>
				<td>
					<input type="url" id="reevents-venue-website" name="reevents_venue_website" class="regular-text" value="<?php echo esc_attr( $website ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Map', 're-events' ); ?></th>
				<td>
					<p class="description"><?php esc_html_e( 'Latitude and longitude are used by the map. Use the venue address to look them up.', 're-events' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save venue meta.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['reevents_venue_details_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['reevents_venue_details_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'reevents_venue_details' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, '_reevents_address', sanitize_textarea_field( wp_unslash( $_POST['reevents_venue_address'] ?? '' ) ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by Meta::sanitize_latitude().
		update_post_meta( $post_id, '_reevents_lat', Meta::sanitize_latitude( wp_unslash( $_POST['reevents_venue_lat'] ?? '' ) ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by Meta::sanitize_longitude().
		update_post_meta( $post_id, '_reevents_lng', Meta::sanitize_longitude( wp_unslash( $_POST['reevents_venue_lng'] ?? '' ) ) );
		update_post_meta( $post_id, '_reevents_website', esc_url_raw( wp_unslash( $_POST['reevents_venue_website'] ?? '' ) ) );
	}
}
