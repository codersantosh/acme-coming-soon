<?php // phpcs:ignore Class file names should be based on the class name with "class-" prepended.
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://addonspress.com/
 * @since      1.0.0
 *
 * @package    Acme_Coming_Soon
 * @subpackage Acme_Coming_Soon/Admin
 */

/**
 * Admin menus, resources and settings registration.
 *
 * @since      1.0.0
 * @package    Acme_Coming_Soon
 * @subpackage Acme_Coming_Soon/Admin
 * @author     codersantosh <codersantosh@gmail.com>
 */
class Acme_Coming_Soon_Admin {

	/**
	 * Menu info.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var array
	 */
	private $menu_info;

	/**
	 * Gets an instance of this object.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return Acme_Coming_Soon_Admin
	 */
	public static function get_instance() {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Get the filtered admin menu information.
	 *
	 * @since 1.0.7
	 * @access private
	 * @return array Menu information.
	 */
	private function get_menu_info() {
		$white_label = acme_coming_soon_include()->get_white_label();

		return isset( $white_label['admin_menu_page'] ) && is_array( $white_label['admin_menu_page'] ) ? $white_label['admin_menu_page'] : array();
	}

	/**
	 * Add the admin menu page.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function add_admin_menu() {
		$this->menu_info = $this->get_menu_info();
		if ( empty( $this->menu_info['menu_slug'] ) ) {
			return;
		}

		add_menu_page(
			$this->menu_info['page_title'],
			$this->menu_info['menu_title'],
			'manage_options',
			$this->menu_info['menu_slug'],
			array( $this, 'add_setting_root_div' ),
			$this->menu_info['icon_url'],
			$this->menu_info['position']
		);
	}

	/**
	 * Check whether the current screen is the plugin settings page.
	 *
	 * @since 1.0.7
	 * @access public
	 * @return bool
	 */
	public function is_menu_page() {
		$menu_info = $this->get_menu_info();
		if ( empty( $menu_info['menu_slug'] ) ) {
			return false;
		}

		$screen = get_current_screen();
		return $screen && isset( $screen->base ) && 'toplevel_page_' . $menu_info['menu_slug'] === $screen->base;
	}

	/**
	 * Add the sticky-header body class on the settings screen.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string Filtered body classes.
	 */
	public function add_has_sticky_header( $classes ) {
		if ( ! $this->is_menu_page() ) {
			return $classes;
		}

		return $classes . ' at-has-hdr-stky ';
	}

	/**
	 * Add the React root element.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function add_setting_root_div() {
		if ( ! acme_coming_soon_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'acme-coming-soon' ), '', array( 'response' => 403 ) );
		}

		echo '<div id="' . esc_attr( ACME_COMING_SOON_PLUGIN_NAME ) . '"></div>';
	}

	/**
	 * Register admin CSS and JavaScript resources.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function enqueue_resources() {
		if ( ! $this->is_menu_page() || ! $this->has_required_build_assets() ) {
			return;
		}

		wp_enqueue_style( 'acme-coming-soon-atomic' );
		wp_style_add_data( 'acme-coming-soon-atomic', 'rtl', 'replace' );

		$dependency = array();
		$version    = ACME_COMING_SOON_VERSION;
		$deps_file  = ACME_COMING_SOON_PATH . 'build/admin/admin.asset.php';

		if ( is_readable( $deps_file ) ) {
			$asset = require $deps_file;
			if ( is_array( $asset ) && isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ) {
				$dependency = $asset['dependencies'];
				if ( isset( $asset['version'] ) ) {
					$version = $asset['version'];
				}
			}
		}

		wp_enqueue_script( ACME_COMING_SOON_PLUGIN_NAME, ACME_COMING_SOON_URL . 'build/admin/admin.js', $dependency, $version, true );
		wp_enqueue_style( 'acme-coming-soon-google-fonts-open-sans', ACME_COMING_SOON_URL . 'assets/library/fonts/open-sans.css', array(), $version );
		wp_enqueue_style( ACME_COMING_SOON_PLUGIN_NAME, ACME_COMING_SOON_URL . 'build/admin/admin.css', array( 'wp-components' ), $version );
		wp_style_add_data( ACME_COMING_SOON_PLUGIN_NAME, 'rtl', 'replace' );

		$menu_info = $this->get_menu_info();
		$localize  = apply_filters(
			'acme_coming_soon_admin_localize',
			array(
				'version'              => $version,
				'root_id'              => ACME_COMING_SOON_PLUGIN_NAME,
				'nonce'                => wp_create_nonce( 'wp_rest' ),
				'store'                => 'acme-coming-soon',
				'rest_url'             => get_rest_url(),
				'base_url'             => menu_page_url( $menu_info['menu_slug'], false ),
				'ACME_COMING_SOON_URL' => ACME_COMING_SOON_URL,
				'white_label'          => acme_coming_soon_include()->get_white_label(),
				'userRoles'            => wp_roles()->get_names(),
			)
		);

		wp_set_script_translations( ACME_COMING_SOON_PLUGIN_NAME, ACME_COMING_SOON_PLUGIN_NAME );
		wp_localize_script( ACME_COMING_SOON_PLUGIN_NAME, 'AcmeComingSoonLocalize', $localize );
	}

	/**
	 * Check whether the required compiled admin assets exist.
	 *
	 * @since 1.0.7
	 * @access private
	 * @return bool Whether the build assets are available.
	 */
	private function has_required_build_assets() {
		return is_readable( ACME_COMING_SOON_PATH . 'build/admin/admin.js' ) && is_readable( ACME_COMING_SOON_PATH . 'build/admin/admin.css' );
	}

	/**
	 * Display a notice when compiled admin assets are missing.
	 *
	 * @since 1.0.7
	 * @access public
	 * @return void
	 */
	public function display_missing_build_notice() {
		if ( ! $this->is_menu_page() || ! acme_coming_soon_can_manage() || $this->has_required_build_assets() ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'The Acme Coming Soon admin build is missing. Rebuild the plugin assets before configuring maintenance mode.', 'acme-coming-soon' );
		echo '</p></div>';
	}

	/**
	 * Get the settings JSON schema.
	 *
	 * @since 1.0.7
	 * @access public
	 * @return array Settings schema.
	 */
	public function get_settings_schema() {
		$setting_properties = apply_filters(
			'acme_coming_soon_setting_properties',
			array(
				'on'        => array(
					'type' => 'boolean',
				),
				'access'    => array(
					'type' => 'string',
					'enum' => array( '', 'login', 'admin', 'roles' ),
				),
				'exclude'   => array( // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Plugin settings key, not a WP_Query argument.
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'useRoles'  => array(
							'type'              => 'array',
							'items'             => array( 'type' => 'string' ),
							'sanitize_callback' => 'acme_coming_soon_sanitize_roles',
						),
						'searchBot' => array(
							'type' => 'boolean',
						),
						'ips'       => array(
							'type'  => 'array',
							'items' => array(
								'type'              => 'string',
								'sanitize_callback' => 'acme_coming_soon_sanitize_ip',
							),
						),
						'slugs'     => array(
							'type'  => 'array',
							'items' => array(
								'type'              => 'string',
								'sanitize_callback' => 'acme_coming_soon_sanitize_path_slug',
							),
						),
					),
				),
				'template'  => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
				'deleteAll' => array(
					'type' => 'boolean',
				),
			)
		);

		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => $setting_properties,
		);
	}

	/**
	 * Register the plugin option without exposing it through core REST settings.
	 *
	 * @since 1.0.7
	 * @access public
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'acme_coming_soon_setting_group',
			ACME_COMING_SOON_OPTION_NAME,
			array(
				'type'         => 'object',
				'default'      => acme_coming_soon_default_options(),
				'show_in_rest' => false,
			)
		);
	}

	/**
	 * Add the Settings action link.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param array $actions Plugin action links.
	 * @return array Filtered action links.
	 */
	public function add_plugin_links( $actions ) {
		$menu_info = $this->get_menu_info();
		if ( acme_coming_soon_can_manage() && ! empty( $menu_info['menu_slug'] ) ) {
			$actions[] = '<a href="' . esc_url( menu_page_url( $menu_info['menu_slug'], false ) ) . '">' . esc_html__( 'Settings', 'acme-coming-soon' ) . '</a>';
		}

		return $actions;
	}
}
