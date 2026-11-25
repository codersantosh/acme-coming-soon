<?php // phpcs:ignore Class file names should be based on the class name with "class-" prepended.
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The common bothend functionality of the plugin.
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://addonspress.com/
 * @since      1.0.0
 *
 * @package    Acme_Coming_Soon
 * @subpackage Acme_Coming_Soon/includes
 */

/**
 * The common bothend functionality of the plugin.
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @since      1.0.0
 * @package    Acme_Coming_Soon
 * @subpackage Acme_Coming_Soon/includes
 * @author     codersantosh <codersantosh@gmail.com>
 */
class Acme_Coming_Soon_Include {

	/**
	 * Static property to store Options Settings
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    settings All settings for this plugin.
	 */
	private static $settings = null;

	/**
	 * Static property to store white label settings
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    settings All settings for this plugin.
	 */
	private static $white_label = null;

	/**
	 * Reset the cached settings after the option changes.
	 *
	 * @since 1.0.7
	 * @access public
	 * @return void
	 */
	public static function reset_settings_cache() {
		self::$settings = null;
		self::register_option_hooks();
	}

	/**
	 * Register atomic option merging and cache invalidation hooks.
	 *
	 * @since 1.0.7
	 * @access private
	 * @return void
	 */
	private static function register_option_hooks() {
		add_filter( 'pre_update_option_' . ACME_COMING_SOON_OPTION_NAME, 'acme_coming_soon_merge_option_update', 10, 3 );

		$callback = array( __CLASS__, 'reset_settings_cache' );
		add_action( 'added_option_' . ACME_COMING_SOON_OPTION_NAME, $callback );
		add_action( 'updated_option_' . ACME_COMING_SOON_OPTION_NAME, $callback );
		add_action( 'deleted_option_' . ACME_COMING_SOON_OPTION_NAME, $callback );
	}

	/**
	 * Gets an instance of this object.
	 * Prevents duplicate instances which avoid artefacts and improves performance.
	 *
	 * @static
	 * @access public
	 * @return object
	 * @since 1.0.0
	 */
	public static function get_instance() {
		// Store the instance locally to avoid private static replication.
		static $instance = null;

		// Only run these methods if they haven't been ran previously.
		if ( null === $instance ) {
			$instance = new self();
			$instance->register_cache_invalidation();
		}

		// Always return the instance.
		return $instance;
	}

	/**
	 * Register option hooks that invalidate the in-request settings cache.
	 *
	 * @since 1.0.7
	 * @access private
	 * @return void
	 */
	private function register_cache_invalidation() {
		self::register_option_hooks();
	}

	/**
	 * Get the settings from the class instance.
	 *
	 * @access public
	 * @return array|null
	 */
	public function get_settings() {
		if ( null === self::$settings ) {
			self::$settings = acme_coming_soon_get_options();
		}
		return self::$settings;
	}

	/**
	 * Get options related to white label.
	 *
	 * @access public
	 * @return array|null
	 */
	public function get_white_label() {
		if ( null === self::$white_label ) {
			self::$white_label = acme_coming_soon_get_white_label();
		}
		return self::$white_label;
	}

	/**
	 * Register scripts and styles
	 *
	 * @since    1.0.0
	 * @access   public
	 * @return void
	 */
	public function register_scripts_and_styles() {
		/* Atomic css */
		wp_register_style( 'acme-coming-soon-atomic', ACME_COMING_SOON_URL . 'assets/library/atomic-css/atomic.min.css', array(), ACME_COMING_SOON_VERSION );
	}
}
