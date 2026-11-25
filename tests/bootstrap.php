<?php
/**
 * PHPUnit bootstrap for Acme Coming Soon.
 *
 * @package Acme_Coming_Soon
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, Squiz.Commenting.FunctionComment.Missing, Generic.CodeAnalysis.UnusedFunctionParameter, Universal.NamingConventions.NoReservedKeywordParameterNames, WordPress.WP.AlternativeFunctions, Universal.Operators.DisallowShortTernary, Universal.Files.SeparateFunctionsFromOO, Squiz.PHP.EmbeddedPhp, Squiz.Commenting.VariableComment.Missing, WordPress.WP.GlobalVariablesOverride, Generic.NamingConventions.CamelCapsFunctionName, Squiz.Classes.ClassFileName, Squiz.Classes.ClassDeclaration, Generic.Files.OneObjectStructurePerFile, WordPress.NamingConventions.ValidFunctionName

define( 'ABSPATH', sys_get_temp_dir() . '/' );
define( 'ACME_COMING_SOON_PATH', dirname( __DIR__ ) . '/' );
define( 'ACME_COMING_SOON_URL', 'https://example.com/wp-content/plugins/acme-coming-soon/' );
define( 'ACME_COMING_SOON_VERSION', '1.0.7' );
define( 'ACME_COMING_SOON_PLUGIN_NAME', 'acme-coming-soon' );
define( 'ACME_COMING_SOON_OPTION_NAME', 'acme_coming_soon_options' );
define( 'OBJECT', 'OBJECT' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'ARRAY_N', 'ARRAY_N' );

$GLOBALS['__acme_options']      = array();
$GLOBALS['__acme_filters']      = array();
$GLOBALS['__acme_actions']      = array();
$GLOBALS['__acme_caps']         = array();
$GLOBALS['__acme_posts']        = array();
$GLOBALS['__acme_registered']   = array();
$GLOBALS['__acme_redirects']    = array();
$GLOBALS['__acme_status']       = array();
$GLOBALS['__acme_nocache']      = 0;
$GLOBALS['__acme_queried_id']   = 0;
$GLOBALS['__acme_is_singular']  = false;
$GLOBALS['__acme_is_feed']      = false;
$GLOBALS['__acme_doing_cron']   = false;
$GLOBALS['__acme_logged_in']    = false;
$GLOBALS['__acme_current_user'] = (object) array( 'roles' => array() );
$GLOBALS['__acme_multisite']   = false;
$GLOBALS['__acme_validate']     = true;

function acme_test_reset_state() {
	$GLOBALS['__acme_options']      = array();
	$GLOBALS['__acme_filters']      = $GLOBALS['__acme_base_filters'] ?? array();
	$GLOBALS['__acme_actions']      = $GLOBALS['__acme_base_actions'] ?? array();
	$GLOBALS['__acme_caps']         = array();
	$GLOBALS['__acme_posts']        = array();
	$GLOBALS['__acme_registered']   = array();
	$GLOBALS['__acme_redirects']    = array();
	$GLOBALS['__acme_status']       = array();
	$GLOBALS['__acme_nocache']      = 0;
	$GLOBALS['__acme_queried_id']   = 0;
	$GLOBALS['__acme_is_singular']  = false;
	$GLOBALS['__acme_is_feed']      = false;
	$GLOBALS['__acme_doing_cron']   = false;
	$GLOBALS['__acme_logged_in']    = false;
	$GLOBALS['__acme_current_user'] = (object) array( 'roles' => array() );
	$GLOBALS['__acme_multisite']   = false;
$GLOBALS['__acme_validate']     = true;
	if ( method_exists( acme_coming_soon_include(), 'reset_settings_cache' ) ) {
		acme_coming_soon_include()->reset_settings_cache();
	}
}

function get_option( $name, $default = false ) {
	return isset( $GLOBALS['__acme_options'][ $name ] ) ? $GLOBALS['__acme_options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) {
	unset( $autoload );
	$old   = get_option( $name );
	$value = apply_filters( "pre_update_option_{$name}", $value, $name, $old );
	if ( $old === $value ) {
		return false;
	}
	$GLOBALS['__acme_options'][ $name ] = $value;
	do_action( "updated_option_{$name}", $old, $value, $name );
	do_action( 'updated_option', $name, $old, $value );
	return true;
}
function add_option( $name, $value, $deprecated = '', $autoload = true ) {
	unset( $deprecated, $autoload );
	if ( get_option( $name, null ) !== null ) {
		return false;
	}
	$GLOBALS['__acme_options'][ $name ] = $value;
	do_action( "added_option_{$name}", $name, $value );
	do_action( 'added_option', $name, $value );
	return true;
}
function delete_option( $name ) {
	$old = get_option( $name );
	unset( $GLOBALS['__acme_options'][ $name ] );
	do_action( "deleted_option_{$name}", $name, $old );
	do_action( 'deleted_option', $name, $old );
	return true;
}
function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['__acme_filters'][ $tag ][ $priority ][] = array( $callback, $accepted_args );
	return true;
}
function remove_filter( $tag, $callback, $priority = 10 ) {
	if ( empty( $GLOBALS['__acme_filters'][ $tag ][ $priority ] ) ) {
		return false;
	}
	foreach ( $GLOBALS['__acme_filters'][ $tag ][ $priority ] as $index => $entry ) {
		if ( $entry[0] === $callback ) {
			unset( $GLOBALS['__acme_filters'][ $tag ][ $priority ][ $index ] );
			return true;
		}
	}
	return false;
}
function apply_filters( $tag, $value, ...$args ) {
	if ( empty( $GLOBALS['__acme_filters'][ $tag ] ) ) {
		return $value;
	}
	$priorities = $GLOBALS['__acme_filters'][ $tag ];
	ksort( $priorities );
	foreach ( $priorities as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			$value = call_user_func_array( $entry[0], array_merge( array( $value ), array_slice( $args, 0, $entry[1] - 1 ) ) );
		}
	}
	return $value;
}
function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	return add_filter( $tag, $callback, $priority, $accepted_args );
}
function remove_action( $tag, $callback, $priority = 10 ) {
	return remove_filter( $tag, $callback, $priority );
}
function do_action( $tag, ...$args ) {
	if ( empty( $GLOBALS['__acme_actions'][ $tag ] ) && empty( $GLOBALS['__acme_filters'][ $tag ] ) ) {
		return;
	}
	$hooks = $GLOBALS['__acme_actions'][ $tag ] ?? $GLOBALS['__acme_filters'][ $tag ];
	ksort( $hooks );
	foreach ( $hooks as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] - 1 ) );
		}
	}
}
function has_action( $tag, $callback = false ) {
	if ( empty( $GLOBALS['__acme_actions'][ $tag ] ) ) {
		return false;
	}
	if ( ! $callback ) {
		return true;
	}
	foreach ( $GLOBALS['__acme_actions'][ $tag ] as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			if ( $entry[0] === $callback ) {
				return true;
			}
		}
	}
	return false;
}
function __( $text, $domain = 'default' ) {
	unset( $domain );
	return $text;
}
function esc_html__( $text, $domain = 'default' ) {
	return esc_html( __( $text, $domain ) );
}
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $text ) {
	return esc_html( $text );
}
function esc_url( $url ) {
	return filter_var( (string) $url, FILTER_SANITIZE_URL ) ?: '';
}
function wp_kses_post( $text ) {
	return $text;
}
function sanitize_text_field( $text ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $text ) ) );
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function sanitize_title( $title ) {
	return trim( preg_replace( '/[^a-z0-9_\-]+/', '-', strtolower( (string) $title ) ), '-' );
}
function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}
function absint( $value ) {
	return abs( (int) $value );
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( (string) $url, $component );
}
function home_url( $path = '' ) {
	return 'https://example.com/' . ltrim( (string) $path, '/' );
}
function site_url( $path = '' ) {
	return home_url( $path );
}
function get_bloginfo( $key ) {
	return 'version' === $key ? '7.1.5' : '';
}
function wp_create_nonce( $action ) {
	return 'nonce-' . $action;
}
function get_rest_url() {
	return 'https://example.com/wp-json/';
}
function menu_page_url( $slug, $echo = true ) {
	unset( $echo );
	return 'https://example.com/wp-admin/admin.php?page=' . $slug;
}
function wp_roles() {
	return new class() {
		public function get_names() {
			return array( 'administrator' => 'Administrator', 'editor' => 'Editor' );
		}
	};
}
function current_user_can( $cap, ...$args ) {
	unset( $args );
	return ! empty( $GLOBALS['__acme_caps'][ $cap ] );
}
function is_user_logged_in() {
	return $GLOBALS['__acme_logged_in'];
}
function wp_get_current_user() {
	return $GLOBALS['__acme_current_user'];
}
function is_multisite() {
	return $GLOBALS['__acme_multisite'];
}
function is_super_admin( $user_id = 0 ) {
	unset( $user_id );
	return ! empty( $GLOBALS['__acme_caps']['super_admin'] );
}
function wp_doing_cron() {
	return $GLOBALS['__acme_doing_cron'];
}
function get_queried_object_id() {
	return $GLOBALS['__acme_queried_id'];
}
function get_the_ID() {
	return get_queried_object_id();
}
function is_singular() {
	return $GLOBALS['__acme_is_singular'];
}
function is_feed() {
	return $GLOBALS['__acme_is_feed'];
}
function is_page( $page = '' ) {
	$id = get_queried_object_id();
	if ( ! $id ) {
		return false;
	}
	$post = get_post( $id );
	return $post && 'page' === $post->post_type && ( '' === $page || $id === $page );
}
function get_post( $id ) {
	return $GLOBALS['__acme_posts'][ $id ] ?? null;
}
function get_post_status( $id ) {
	$post = get_post( $id );
	return $post ? $post->post_status : false;
}
function get_post_type( $id ) {
	$post = get_post( $id );
	return $post ? $post->post_type : false;
}
function get_the_permalink( $id ) {
	$post = get_post( $id );
	return $post ? $post->permalink : false;
}
function wp_safe_redirect( $location, $status = 302 ) {
	$GLOBALS['__acme_redirects'][] = array( $location, $status );
	return true;
}
function status_header( $status ) {
	$GLOBALS['__acme_status'][] = $status;
}
function nocache_headers() {
	++$GLOBALS['__acme_nocache'];
}
function wp_die( $message = '', $title = '', $args = array() ) {
	$GLOBALS['__acme_status'][] = $args['response'] ?? 500;
	$GLOBALS['__acme_die']    = $message;
	throw new RuntimeException( 'wp_die' );
}
function register_setting( $group, $option, $args = array() ) {
	$GLOBALS['__acme_registered']['settings'][ $option ] = $args;
}
function register_block_pattern_category( $slug, $args ) {
	$GLOBALS['__acme_registered']['categories'][ $slug ] = $args;
}
function register_block_pattern( $slug, $args ) {
	$GLOBALS['__acme_registered']['patterns'][ $slug ] = $args;
}
function wp_json_encode( $value ) {
	return json_encode( $value );
}
function wp_remote_get( $url ) {
	throw new RuntimeException( 'Unexpected HTTP request: ' . $url );
}
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
class WP_Error {
	public $errors = array();
	public function __construct( $code = '', $message = '', $data = null ) {
		$this->errors[ $code ][] = $message;
		$this->data         = $data;
	}
}
class WP_REST_Request {
	private $params;
	public function __construct( $params = array() ) {
		$this->params = $params;
	}
	public function get_params() {
		return $this->params;
	}
}
class WP_REST_Server {
	const READABLE = 'GET';
	const CREATABLE = 'POST';
	const EDITABLE = 'POST, PUT, PATCH';
}
class WP_REST_Controller {
	protected $schema;
	public function add_additional_fields_schema( $schema ) {
		return $schema;
	}
}
function rest_get_endpoint_args_for_schema( $schema, $method = WP_REST_Server::CREATABLE ) {
	unset( $schema, $method );
	return array();
}
function rest_validate_value_from_schema( $value, $schema ) {
	unset( $schema );
	return $GLOBALS['__acme_validate'] ? true : new WP_Error( 'invalid', 'Invalid value' );
}
function rest_sanitize_value_from_schema( $value, $schema ) {
	unset( $schema );
	return $value;
}
function register_rest_route( ...$args ) {
	$GLOBALS['__acme_registered']['routes'][] = $args;
	return true;
}

require ACME_COMING_SOON_PATH . 'includes/functions.php';
require ACME_COMING_SOON_PATH . 'includes/api/class-api.php';
require ACME_COMING_SOON_PATH . 'includes/api/class-api-settings.php';
require ACME_COMING_SOON_PATH . 'includes/class-include.php';
require ACME_COMING_SOON_PATH . 'admin/class-admin.php';
require ACME_COMING_SOON_PATH . 'public/class-public.php';
require ACME_COMING_SOON_PATH . 'includes/class-patterns.php';
require ACME_COMING_SOON_PATH . 'includes/class-deactivator.php';

$GLOBALS['__acme_base_filters'] = $GLOBALS['__acme_filters'];
$GLOBALS['__acme_base_actions'] = $GLOBALS['__acme_actions'];
