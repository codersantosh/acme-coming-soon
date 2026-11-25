<?php // phpcs:ignore Class file names should be based on the class name with "class-" prepended.
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The file that defines the core plugin class.
 *
 * @link       https://addonspress.com/
 * @since      1.0.0
 *
 * @package    Acme_Coming_Soon
 * @subpackage Acme_Coming_Soon/main
 */

/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    Acme_Coming_Soon
 * @subpackage Acme_Coming_Soon/main
 * @author     codersantosh <codersantosh@gmail.com>
 */
class Acme_Coming_Soon {

	/**
	 * Hook loader.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @var Acme_Coming_Soon_Loader
	 */
	protected $loader;

	/**
	 * Load dependencies and define hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->define_include_hooks();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies.
	 *
	 * @since 1.0.0
	 * @access private
	 * @return void
	 */
	private function load_dependencies() {
		require_once ACME_COMING_SOON_PATH . 'includes/functions.php';
		require_once ACME_COMING_SOON_PATH . 'includes/api/index.php';
		require_once ACME_COMING_SOON_PATH . 'includes/class-loader.php';
		require_once ACME_COMING_SOON_PATH . 'includes/class-include.php';
		require_once ACME_COMING_SOON_PATH . 'includes/class-patterns.php';
		require_once ACME_COMING_SOON_PATH . 'admin/class-admin.php';
		require_once ACME_COMING_SOON_PATH . 'public/class-public.php';

		$this->loader = new Acme_Coming_Soon_Loader();
	}

	/**
	 * Register hooks shared by admin and public requests.
	 *
	 * @since 1.0.0
	 * @access private
	 * @return void
	 */
	private function define_include_hooks() {
		$plugin_include = acme_coming_soon_include();

		$this->loader->add_action( 'init', $plugin_include, 'register_scripts_and_styles' );
	}

	/**
	 * Register admin hooks.
	 *
	 * @since 1.0.0
	 * @access private
	 * @return void
	 */
	private function define_admin_hooks() {
		$plugin_admin = acme_coming_soon_admin();

		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'display_missing_build_notice' );
		$this->loader->add_filter( 'admin_body_class', $plugin_admin, 'add_has_sticky_header' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_resources' );
		$this->loader->add_action( 'rest_api_init', $plugin_admin, 'register_settings', 1 );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings', 1 );
		$this->loader->add_filter( 'plugin_action_links_acme-coming-soon/acme-coming-soon.php', $plugin_admin, 'add_plugin_links' );
	}

	/**
	 * Register public maintenance and content-boundary hooks.
	 *
	 * @since 1.0.0
	 * @access private
	 * @return void
	 */
	private function define_public_hooks() {
		$plugin_public = acme_coming_soon_public();

		$this->loader->add_action( 'template_redirect', $plugin_public, 'redirect_to_maintenance', 1 );
		$this->loader->add_action( 'wp', $plugin_public, 'disable_frontend_discovery', 1 );
		$this->loader->add_filter( 'wp_sitemaps_enabled', $plugin_public, 'disable_sitemaps' );
		$this->loader->add_filter( 'feed_links_show', $plugin_public, 'disable_feed_links' );
		$this->loader->add_filter( 'rest_request_before_callbacks', $plugin_public, 'protect_rest_request', 10, 3 );
		$this->loader->add_filter( 'rest_post_dispatch', $plugin_public, 'protect_rest_response', 10, 3 );
		$this->loader->add_action( 'wp_loaded', $plugin_public, 'protect_direct_endpoints' );
		$this->loader->add_action( 'wp_loaded', $plugin_public, 'protect_nopriv_ajax', 1 );
		$this->loader->add_action( 'acme_coming_soon_settings_updated', $plugin_public, 'purge_caches' );
	}

	/**
	 * Run the hook loader.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Return the hook loader.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return Acme_Coming_Soon_Loader
	 */
	public function get_loader() {
		return $this->loader;
	}
}
