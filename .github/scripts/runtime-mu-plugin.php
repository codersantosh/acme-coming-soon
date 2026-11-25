<?php
/**
 * Disable WordPress.org update checks and block network access in the disposable
 * Acme Coming Soon runtime smoke-test site.
 *
 * @package Acme_Coming_Soon
 */

/**
 * Return a complete, fresh update transient without making an HTTP request.
 *
 * @param mixed  $value     Default transient value.
 * @param string $transient Transient name.
 * @return object
 */
function acme_runtime_empty_update_transient( $value, $transient ) {
	unset( $value );
	$now = time();

	if ( 'update_core' === $transient ) {
		return (object) array(
			'version_checked' => get_bloginfo( 'version' ),
			'last_checked'    => $now,
			'updates'         => array(),
			'translations'    => array(),
		);
	}

	$checked = array();
	if ( 'update_plugins' === $transient && function_exists( 'get_plugins' ) ) {
		foreach ( get_plugins() as $file => $plugin ) {
			$checked[ $file ] = $plugin['Version'];
		}
	} elseif ( 'update_themes' === $transient && function_exists( 'wp_get_themes' ) ) {
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$checked[ $stylesheet ] = $theme->get( 'Version' );
		}
	}

	return (object) array(
		'last_checked' => $now,
		'response'     => array(),
		'no_update'    => array(),
		'checked'      => $checked,
		'translations' => array(),
	);
}

add_filter( 'pre_site_transient_update_plugins', 'acme_runtime_empty_update_transient', 10, 2 );
add_filter( 'pre_site_transient_update_core', 'acme_runtime_empty_update_transient', 10, 2 );
add_filter( 'pre_site_transient_update_themes', 'acme_runtime_empty_update_transient', 10, 2 );

/**
 * Stub WordPress.org update APIs with type-correct empty responses and reject
 * every other HTTP request, including the plugin's own loopback URL.
 *
 * @param mixed  $preempt Preempting response.
 * @param array  $args    HTTP request arguments.
 * @param string $url     Request URL.
 * @return array|WP_Error
 */
function acme_runtime_control_http( $preempt, $args, $url ) {
	unset( $args );

	if ( ! preg_match( '#^https?://api\.wordpress\.org/(?:core/version-check|plugins/update-check|themes/update-check|translations)(?:/|$)#', $url ) ) {
		return new WP_Error(
			'acme_runtime_http_blocked',
			'External HTTP is blocked in the Acme Coming Soon runtime smoke test.'
		);
	}

	if ( false !== strpos( $url, '/plugins/update-check/' ) ) {
		$body = array(
			'plugins'      => (object) array(),
			'no_update'    => (object) array(),
			'translations' => array(),
		);
	} elseif ( false !== strpos( $url, '/themes/update-check/' ) ) {
		$body = array(
			'themes'       => (object) array(),
			'no_update'    => (object) array(),
			'translations' => array(),
		);
	} elseif ( false !== strpos( $url, '/translations/' ) ) {
		$body = array( 'translations' => array() );
	} else {
		$body = array(
			'offers'       => array(),
			'translations' => array(),
		);
	}

	return array(
		'headers'  => array(),
		'body'     => (string) wp_json_encode( $body ),
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'cookies'  => array(),
		'filename' => null,
	);
}

add_filter( 'pre_http_request', 'acme_runtime_control_http', 1, 3 );

/**
 * Remove scheduled and installation-triggered WordPress.org update checks.
 */
function acme_runtime_disable_update_checks() {
	$callbacks = array(
		'admin_init'                => array( '_maybe_update_core', '_maybe_update_plugins', '_maybe_update_themes' ),
		'init'                      => array( 'wp_schedule_update_checks' ),
		'wp_version_check'          => array( 'wp_version_check' ),
		'wp_update_plugins'         => array( 'wp_update_plugins' ),
		'wp_update_themes'          => array( 'wp_update_themes' ),
		'load-plugins.php'          => array( 'wp_update_plugins' ),
		'load-themes.php'           => array( 'wp_update_themes' ),
		'load-update.php'           => array( 'wp_update_plugins', 'wp_update_themes' ),
		'load-update-core.php'      => array( 'wp_update_plugins', 'wp_update_themes' ),
		'upgrader_process_complete' => array( 'wp_version_check', 'wp_update_plugins', 'wp_update_themes' ),
	);

	foreach ( $callbacks as $hook => $functions ) {
		foreach ( $functions as $function ) {
			remove_action( $hook, $function );
		}
	}
}

add_action( 'init', 'acme_runtime_disable_update_checks', PHP_INT_MAX );
add_action( 'admin_init', 'acme_runtime_disable_update_checks', PHP_INT_MAX );
