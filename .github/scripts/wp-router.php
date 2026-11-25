<?php
/**
 * Router for the Acme Coming Soon runtime smoke test's PHP development server.
 *
 * Existing files and directory index files are served directly. All other
 * requests are handed to WordPress so permalinks and REST routes are exercised.
 */

$root = getenv( 'ACS_WORDPRESS_DIR' );
if ( ! $root ) {
	fwrite( STDERR, "ACS_WORDPRESS_DIR is required.\n" );
	http_response_code( 500 );
	exit( 1 );
}

$root         = realpath( $root );
$request_path = parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
if ( false === $root || ! is_string( $request_path ) ) {
	http_response_code( 400 );
	exit( 1 );
}

$relative = ltrim( $request_path, '/' );
$segments = explode( '/', $relative );
if ( '' !== $relative && in_array( '..', $segments, true ) ) {
	http_response_code( 404 );
	exit( 1 );
}

$candidate = '' === $relative ? $root : realpath( $root . '/' . $relative );
if ( false !== $candidate && 0 === strpos( $candidate, $root . DIRECTORY_SEPARATOR ) ) {
	if ( is_file( $candidate ) || ( is_dir( $candidate ) && is_file( $candidate . '/index.php' ) ) ) {
		return false;
	}
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF']    = '/index.php';
require $root . '/index.php';
