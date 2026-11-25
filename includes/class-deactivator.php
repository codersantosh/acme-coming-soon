<?php // phpcs:ignore Class file names should be based on the class name with "class-" prepended.
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin deactivation.
 *
 * @link       https://addonspress.com/
 * @since      1.0.0
 *
 * @package    Acme_Coming_Soon
 * @subpackage Acme_Coming_Soon/includes
 */

/**
 * Plugin deactivation cleanup.
 *
 * @since      1.0.0
 * @package    Acme_Coming_Soon
 * @subpackage Acme_Coming_Soon/includes
 * @author     codersantosh <codersantosh@gmail.com>
 */
class Acme_Coming_Soon_Deactivator {

	/**
	 * Delete settings only when the site's deleteAll preference is enabled.
	 *
	 * @since 1.0.7
	 * @access public
	 *
	 * @param bool|null $network_deactivating Whether this is a network-wide deactivation.
	 * @return void
	 */
	public static function deactivate( $network_deactivating = null ) {
		if ( null === $network_deactivating ) {
			$network_deactivating = is_multisite();
		}

		if ( ! $network_deactivating ) {
			self::delete_settings_if_requested();
			return;
		}

		$offset = 0;
		do {
			$sites = get_sites(
				array(
					'number'        => 100,
					'offset'        => $offset,
					'fields'        => 'ids',
					'orderby'       => 'id',
					'order'         => 'ASC',
					'no_found_rows' => true,
				)
			);

			if ( is_wp_error( $sites ) ) {
				return;
			}

			foreach ( $sites as $site_id ) {
				if ( switch_to_blog( (int) $site_id ) ) {
					self::delete_settings_if_requested();
					restore_current_blog();
				}
			}

			$count   = count( $sites );
			$offset += 100;
		} while ( 100 === $count );
	}

	/**
	 * Delete the current site's option when requested.
	 *
	 * The selected maintenance page is never deleted.
	 *
	 * @since 1.0.7
	 * @access private
	 * @return void
	 */
	private static function delete_settings_if_requested() {
		$options = get_option( ACME_COMING_SOON_OPTION_NAME, array() );
		if ( is_array( $options ) && ! empty( $options['deleteAll'] ) ) {
			delete_option( ACME_COMING_SOON_OPTION_NAME );
		}
	}
}
