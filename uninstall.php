<?php
/**
 * Uninstall cleanup for Acme Coming Soon.
 *
 * @package Acme_Coming_Soon
 * @since 1.0.7
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) || ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Delete the plugin option for the current site.
 *
 * The maintenance page is intentionally preserved.
 *
 * @since 1.0.7
 */
$acme_coming_soon_delete_options = static function () {
	delete_option( 'acme_coming_soon_options' );
};

if ( ! is_multisite() ) {
	$acme_coming_soon_delete_options();
	return;
}

$acme_coming_soon_offset = 0;
do {
	$acme_coming_soon_sites = get_sites(
		array(
			'number'        => 100,
			'offset'        => $acme_coming_soon_offset,
			'fields'        => 'ids',
			'orderby'       => 'id',
			'order'         => 'ASC',
			'no_found_rows' => true,
		)
	);

	if ( is_wp_error( $acme_coming_soon_sites ) ) {
		return;
	}

	foreach ( $acme_coming_soon_sites as $acme_coming_soon_site_id ) {
		if ( switch_to_blog( (int) $acme_coming_soon_site_id ) ) {
			$acme_coming_soon_delete_options();
			restore_current_blog();
		}
	}

	$acme_coming_soon_count   = count( $acme_coming_soon_sites );
	$acme_coming_soon_offset += 100;
} while ( 100 === $acme_coming_soon_count );

unset( $acme_coming_soon_delete_options, $acme_coming_soon_sites, $acme_coming_soon_site_id, $acme_coming_soon_count, $acme_coming_soon_offset );
