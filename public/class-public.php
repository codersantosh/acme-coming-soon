<?php // phpcs:ignore Class file names should be based on the class name with "class-" prepended.
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.acmeit.org/
 * @since      1.0.0
 *
 * @package    Acme_Coming_Soon
 * @subpackage Acme_Coming_Soon/public
 */

/**
 * Check maintenance-mode access and protect public content boundaries.
 *
 * @since      1.0.0
 * @package    Acme_Coming_Soon
 * @subpackage Acme_Coming_Soon/public
 * @author     codersantosh <codersantosh@gmail.com>
 */
class Acme_Coming_Soon_Public {

	/**
	 * Raw message for the minimal fail-closed response.
	 *
	 * @since 1.0.7
	 * @access private
	 * @var string
	 */
	private $die_message = '';

	/**
	 * Raw title for the minimal fail-closed response.
	 *
	 * @since 1.0.7
	 * @access private
	 * @var string
	 */
	private $die_title = '';

	/**
	 * Gets an instance of this object.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return Acme_Coming_Soon_Public
	 */
	public static function get_instance() {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Get the current request path without its query string.
	 *
	 * @since 1.0.7
	 * @access private
	 * @return string Request path.
	 */
	private function get_request_path() {
		$raw_path = isset( $_SERVER['REQUEST_URI'] ) && is_scalar( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';
		if ( '' === $raw_path ) {
			return '';
		}

		$path = wp_parse_url( $raw_path, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return '';
		}

		return '/' . ltrim( $path, '/' );
	}

	/**
	 * Check whether this is a WordPress login request.
	 *
	 * @since 1.0.7
	 * @access private
	 * @return bool Whether the login screen is being requested.
	 */
	private function is_login_request() {
		if ( function_exists( 'is_login' ) && is_login() ) {
			return true;
		}

		return 'wp-login.php' === basename( $this->get_request_path() );
	}

	/**
	 * Check if the request is from a bot.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return bool
	 */
	public function is_search_bot() {
		$is_search_bot = false;
		$user_agent    = isset( $_SERVER['HTTP_USER_AGENT'] ) && is_scalar( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		if ( '' !== $user_agent ) {
			/**
			 * Filters the user-agent fragments used to identify search bots.
			 *
			 * @since 1.0.7
			 *
			 * @param string[] $bots Bot user-agent fragments.
			 */
			$bots = apply_filters(
				'acme_coming_soon_search_bots',
				array(
					'AbachoBOT',
					'Slurp',
					'Spider',
					'Acoon',
					'AcoiRobot',
					'Bot',
					'adidxbot',
					'facebookexternalhit',
					'Twitterbot',
					'Altavista',
					'Scooter',
					'ASPSeek',
					'Atomz',
					'bingbot',
					'Crawler',
					'BingPreview',
					'CrocCrawler',
					'Dumbot',
					'developers',
					'eStyle',
					'FAST-WebCrawler',
					'GeonaBot',
					'Gigabot',
					'Googlebot',
					'IDBot',
					'Lycos',
					'msnbot',
					'MSRBOT',
					'Rambler',
					'Scrubby',
					'Yahoo',
					'DuckDuckGoBot',
					'YandexBot',
					'Baiduspider',
					'Exabot',
					'SeznamBot',
					'Sogou',
				)
			);

			if ( is_array( $bots ) ) {
				foreach ( $bots as $bot ) {
					if ( is_scalar( $bot ) && '' !== (string) $bot && false !== stripos( $user_agent, (string) $bot ) ) {
						$is_search_bot = true;
						break;
					}
				}
			}
		}

		/**
		 * Filters whether the current request is from a search bot.
		 *
		 * @since 1.0.7
		 *
		 * @param bool $is_search_bot Whether the request was identified as a bot.
		 */
		return (bool) apply_filters( 'acme_coming_soon_is_search_bot', $is_search_bot );
	}

	/**
	 * Check whether search bots may bypass maintenance mode.
	 *
	 * @since 1.0.7
	 * @access public
	 * @return bool
	 */
	public function pass_search_bot() {
		$settings = acme_coming_soon_include()->get_settings();
		$exclude  = isset( $settings['exclude'] ) && is_array( $settings['exclude'] ) ? $settings['exclude'] : array();

		return ! empty( $exclude['searchBot'] ) && $this->is_search_bot();
	}

	/**
	 * Check whether the exact client IP is excluded.
	 *
	 * @since 1.0.7
	 * @access public
	 * @return bool
	 */
	public function pass_ip() {
		$settings = acme_coming_soon_include()->get_settings();
		if ( ! isset( $settings['exclude'] ) || ! is_array( $settings['exclude'] ) || empty( $settings['exclude']['ips'] ) || ! is_array( $settings['exclude']['ips'] ) || ! isset( $_SERVER['REMOTE_ADDR'] ) || ! is_scalar( $_SERVER['REMOTE_ADDR'] ) ) {
			return false;
		}

		$raw_address    = isset( $_SERVER['REMOTE_ADDR'] ) && is_scalar( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$remote_address = acme_coming_soon_sanitize_ip( $raw_address );
		$remote_binary  = '' === $remote_address ? false : inet_pton( $remote_address );
		if ( false === $remote_binary ) {
			return false;
		}

		foreach ( $settings['exclude']['ips'] as $ip ) {
			$excluded_binary = inet_pton( acme_coming_soon_sanitize_ip( $ip ) );
			if ( false !== $excluded_binary && strlen( $remote_binary ) === strlen( $excluded_binary ) && hash_equals( $remote_binary, $excluded_binary ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether the normalized request path matches an excluded slug.
	 *
	 * @since 1.0.7
	 * @access public
	 * @return bool
	 */
	public function pass_slug() {
		$settings = acme_coming_soon_include()->get_settings();
		if ( ! isset( $settings['exclude'] ) || ! is_array( $settings['exclude'] ) || empty( $settings['exclude']['slugs'] ) || ! is_array( $settings['exclude']['slugs'] ) ) {
			return false;
		}

		$request_path = acme_coming_soon_sanitize_path_slug( $this->get_request_path() );
		if ( '' === $request_path ) {
			return false;
		}

		$home_parts = wp_parse_url( home_url() );
		$site_path  = is_array( $home_parts ) && isset( $home_parts['path'] ) ? acme_coming_soon_sanitize_path_slug( $home_parts['path'] ) : '';
		$relative   = $request_path;
		$candidates = array( $request_path );

		if ( '' !== $site_path ) {
			if ( $request_path === $site_path ) {
				$relative = '';
			} elseif ( str_starts_with( $request_path, $site_path . '/' ) ) {
				$relative = substr( $request_path, strlen( $site_path ) + 1 );
			}
			$candidates[] = $relative;
		}

		$candidates = array_unique( $candidates );

		foreach ( $settings['exclude']['slugs'] as $slug ) {
			$slug = acme_coming_soon_sanitize_path_slug( $slug );
			if ( '' === $slug ) {
				continue;
			}

			foreach ( $candidates as $candidate ) {
				if ( $slug === $candidate || str_starts_with( $candidate, $slug . '/' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check whether the current user passes the configured access policy.
	 *
	 * @since 1.0.7
	 * @access public
	 * @return bool
	 */
	public function pass_user_role() {
		$settings = acme_coming_soon_include()->get_settings();
		$access   = isset( $settings['access'] ) ? $settings['access'] : '';

		if ( ! $access || ! is_user_logged_in() ) {
			return false;
		}

		if ( 'admin' === $access && acme_coming_soon_can_manage() ) {
			return true;
		}

		if ( 'login' === $access ) {
			return true;
		}

		if ( 'roles' === $access ) {
			$allowed_roles = isset( $settings['exclude']['useRoles'] ) && is_array( $settings['exclude']['useRoles'] ) ? $settings['exclude']['useRoles'] : array();
			$current_user  = wp_get_current_user();
			if ( $allowed_roles && ! empty( $current_user->roles ) && array_intersect( $allowed_roles, $current_user->roles ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether maintenance mode should block the current request.
	 *
	 * Administration, login, cron and requests passing the access policy remain
	 * available. AJAX is evaluated separately so authenticated admin AJAX is not
	 * accidentally blocked.
	 *
	 * @since 1.0.7
	 * @access public
	 *
	 * @param string $context Request context, such as frontend, rest or sitemap.
	 * @return bool Whether the request should be blocked.
	 */
	public function should_block_request( $context = 'frontend' ) {
		$settings = acme_coming_soon_include()->get_settings();

		if ( empty( $settings['on'] ) || wp_doing_cron() ) {
			return false;
		}

		if ( 'ajax' !== $context && ( ( function_exists( 'is_admin' ) && is_admin() ) || $this->is_login_request() ) ) {
			return false;
		}

		if ( $this->pass_user_role() || $this->pass_search_bot() || $this->pass_ip() || $this->pass_slug() ) {
			return false;
		}

		/**
		 * Filters whether maintenance mode should block a public request.
		 *
		 * Return false to allow a specific integration. More granular REST and AJAX
		 * allowlists are also available.
		 *
		 * @since 1.0.7
		 *
		 * @param bool   $blocked Whether the request is currently blocked.
		 * @param string $context Request context.
		 * @param array  $settings Current plugin settings.
		 */
		return (bool) apply_filters( 'acme_coming_soon_should_block_request', true, $context, $settings );
	}

	/**
	 * Redirect unauthorized visitors to the published maintenance page.
	 *
	 * @since 1.0.7
	 * @access public
	 * @return void
	 */
	public function redirect_to_maintenance() {
		if ( ! $this->should_block_request( 'frontend' ) ) {
			return;
		}

		nocache_headers();

		if ( is_feed() || ( function_exists( 'is_embed' ) && is_embed() ) ) {
			$this->fail_closed();
		}

		$settings = acme_coming_soon_include()->get_settings();
		$template = absint( $settings['template'] );

		if ( ! acme_coming_soon_is_published_page( $template ) ) {
			$this->fail_closed();
		}

		if ( is_singular() && ! is_feed() && get_queried_object_id() === $template ) {
			$this->disable_frontend_discovery();
			return;
		}

		$redirect_url = get_the_permalink( $template );
		if ( ! $redirect_url ) {
			$this->fail_closed();
		}

		/**
		 * Filters the maintenance-page redirect URL.
		 *
		 * @since 1.0.7
		 *
		 * @param string $redirect_url Maintenance-page URL.
		 * @param array  $settings Current plugin settings.
		 */
		$redirect_url = apply_filters( 'acme_coming_soon_redirect_url', $redirect_url, $settings );

		/**
		 * Filters the maintenance-page redirect status.
		 *
		 * @since 1.0.7
		 *
		 * @param int $redirect_status Redirect status. Default 302.
		 * @param array $settings Current plugin settings.
		 */
		$redirect_status = apply_filters( 'acme_coming_soon_redirect_status', 302, $settings );

		/**
		 * Filters whether the redirect should be canceled.
		 *
		 * Canceled redirects fail closed with HTTP 503.
		 *
		 * @since 1.0.7
		 *
		 * @param bool  $cancel Whether to cancel the redirect.
		 * @param array $settings Current plugin settings.
		 */
		$cancel_redirect = apply_filters( 'acme_coming_soon_cancel_redirect', false, $settings );
		$redirect_status = (int) $redirect_status;

		if ( $cancel_redirect || ! $this->is_safe_redirect_url( $redirect_url ) || ! in_array( $redirect_status, array( 301, 302, 303, 307, 308 ), true ) ) {
			$this->fail_closed();
		}

		if ( ! wp_safe_redirect( $redirect_url, $redirect_status ) ) {
			$this->fail_closed();
		}
		exit;
	}

	/**
	 * Validate a redirect URL before passing it to wp_safe_redirect().
	 *
	 * @since 1.0.7
	 * @access private
	 *
	 * @param mixed $url Redirect URL.
	 * @return bool Whether the URL is a safe same-site HTTP URL or relative path.
	 */
	private function is_safe_redirect_url( $url ) {
		if ( ! is_string( $url ) || '' === trim( $url ) || preg_match( '/[\x00-\x1F\x7F\\\\]/', $url ) ) {
			return false;
		}

		$url_parts = wp_parse_url( trim( $url ) );
		if ( ! is_array( $url_parts ) || isset( $url_parts['user'] ) || isset( $url_parts['pass'] ) ) {
			return false;
		}

		$scheme = isset( $url_parts['scheme'] ) ? strtolower( $url_parts['scheme'] ) : '';
		if ( '' !== $scheme && ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return false;
		}

		if ( isset( $url_parts['host'] ) ) {
			$home_parts = wp_parse_url( home_url() );
			if ( ! is_array( $home_parts ) || ! isset( $home_parts['host'] ) || strtolower( $url_parts['host'] ) !== strtolower( $home_parts['host'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Disable core sitemaps for blocked requests.
	 *
	 * @since 1.0.7
	 * @access public
	 *
	 * @param bool $enabled Whether sitemaps are enabled.
	 * @return bool Filtered sitemap status.
	 */
	public function disable_sitemaps( $enabled ) {
		return $this->should_block_request( 'sitemap' ) ? false : $enabled;
	}

	/**
	 * Hide feed links for blocked requests.
	 *
	 * @since 1.0.7
	 * @access public
	 *
	 * @param bool $show Whether to show feed links.
	 * @return bool Filtered feed-link status.
	 */
	public function disable_feed_links( $show ) {
		return $this->should_block_request( 'frontend' ) ? false : $show;
	}

	/**
	 * Remove public discovery links while the maintenance page is rendered.
	 *
	 * @since 1.0.7
	 * @access public
	 * @return void
	 */
	public function disable_frontend_discovery() {
		if ( ! $this->should_block_request( 'frontend' ) ) {
			return;
		}

		remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 4 );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	}

	/**
	 * Block public REST callbacks before they execute.
	 *
	 * @since 1.0.7
	 * @access public
	 *
	 * @param mixed           $response Current response.
	 * @param array           $handler Route handler.
	 * @param WP_REST_Request $request REST request.
	 * @return mixed|WP_Error Filtered response.
	 */
	public function protect_rest_request( $response, $handler, $request ) {
		if ( ! $this->is_public_rest_request() || ! $this->should_block_request( 'rest' ) || $this->is_rest_route_allowed( $request->get_route(), $request ) ) {
			return $response;
		}

		return $this->get_rest_maintenance_error();
	}

	/**
	 * Block public REST responses that did not match a registered callback.
	 *
	 * @since 1.0.7
	 * @access public
	 *
	 * @param WP_REST_Response $response Current response.
	 * @param WP_REST_Server   $server REST server.
	 * @param WP_REST_Request  $request REST request.
	 * @return WP_REST_Response Filtered response.
	 */
	public function protect_rest_response( $response, $server, $request ) {
		if ( ! $this->is_public_rest_request() || ! $this->should_block_request( 'rest' ) || $this->is_rest_route_allowed( $request->get_route(), $request ) ) {
			return $response;
		}

		$maintenance_response = new WP_REST_Response(
			array(
				'code'    => 'acme_coming_soon_maintenance',
				'message' => __( 'The site is temporarily unavailable.', 'acme-coming-soon' ),
				'data'    => array( 'status' => 503 ),
			),
			503
		);

		$headers                = wp_get_nocache_headers();
		$headers['Retry-After'] = (string) $this->get_retry_after();
		$maintenance_response->set_headers( $headers );

		return $maintenance_response;
	}

	/**
	 * Check whether a REST route is allowed through the maintenance boundary.
	 *
	 * @since 1.0.7
	 * @access private
	 *
	 * @param string          $route REST route.
	 * @param WP_REST_Request $request REST request.
	 * @return bool Whether the route is allowed.
	 */
	private function is_rest_route_allowed( $route, $request ) {
		/**
		 * Filters REST routes or namespace prefixes allowed during maintenance.
		 *
		 * Entries may be exact routes or namespace prefixes. The plugin settings
		 * route remains available so its own capability check can run.
		 *
		 * @since 1.0.7
		 *
		 * @param string[]       $allowed_routes Allowed routes or namespace prefixes.
		 * @param WP_REST_Request $request REST request.
		 */
		$allowed_routes = apply_filters( 'acme_coming_soon_allowed_rest_routes', array( '/acme-coming-soon/v1/settings' ), $request );

		/**
		 * Filters whether an integration REST request may bypass maintenance.
		 *
		 * @since 1.0.7
		 *
		 * @param bool            $allow Whether to allow the request.
		 * @param WP_REST_Request $request REST request.
		 */
		if ( apply_filters( 'acme_coming_soon_allow_rest_request', false, $request ) ) {
			return true;
		}

		if ( ! is_array( $allowed_routes ) ) {
			return false;
		}

		$route = '/' . trim( (string) $route, '/' );
		foreach ( $allowed_routes as $allowed_route ) {
			if ( ! is_scalar( $allowed_route ) ) {
				continue;
			}

			$allowed_route = trim( (string) $allowed_route );
			if ( '/' === $allowed_route ) {
				return true;
			}

			$allowed_route = '/' . trim( $allowed_route, '/' );
			if ( $route === $allowed_route || str_starts_with( $route, $allowed_route . '/' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether this is a standalone public REST request.
	 *
	 * @since 1.0.7
	 * @access private
	 * @return bool Whether a public REST request is being served.
	 */
	private function is_public_rest_request() {
		if ( function_exists( 'wp_is_serving_rest_request' ) ) {
			return wp_is_serving_rest_request();
		}

		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Build a maintenance REST error.
	 *
	 * @since 1.0.7
	 * @access private
	 * @return WP_Error Maintenance error.
	 */
	private function get_rest_maintenance_error() {
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'Retry-After: ' . $this->get_retry_after() );
		}

		return new WP_Error(
			'acme_coming_soon_maintenance',
			__( 'The site is temporarily unavailable.', 'acme-coming-soon' ),
			array( 'status' => 503 )
		);
	}

	/**
	 * Block logged-out AJAX actions while allowing authenticated admin AJAX.
	 *
	 * Runs on wp_loaded because admin-ajax.php rejects a missing action before
	 * it fires admin_init.
	 *
	 * @since 1.0.7
	 * @access public
	 * @return void
	 */
	public function protect_nopriv_ajax() {
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX || is_user_logged_in() || ! $this->should_block_request( 'ajax' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the routed AJAX action name only.
		$action = isset( $_REQUEST['action'] ) && is_scalar( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : '';

		/**
		 * Filters logged-out AJAX actions allowed during maintenance.
		 *
		 * @since 1.0.7
		 *
		 * @param string[] $allowed_actions Allowed AJAX action names.
		 * @param string   $action Current AJAX action.
		 */
		$allowed_actions = apply_filters( 'acme_coming_soon_allowed_ajax_actions', array(), $action );

		/**
		 * Filters whether an integration AJAX request may bypass maintenance.
		 *
		 * @since 1.0.7
		 *
		 * @param bool   $allow Whether to allow the request.
		 * @param string $action Current AJAX action.
		 */
		if ( apply_filters( 'acme_coming_soon_allow_ajax_request', false, $action ) || ( is_array( $allowed_actions ) && in_array( $action, $allowed_actions, true ) ) ) {
			return;
		}

		status_header( 503 );
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'Retry-After: ' . $this->get_retry_after() );
		}
		wp_die(
			'0',
			'',
			array(
				'response'  => 503,
				'back_link' => false,
			)
		);
	}

	/**
	 * Block direct XML-RPC and trackback endpoints.
	 *
	 * @since 1.0.7
	 * @access public
	 * @return void
	 */
	public function protect_direct_endpoints() {
		$endpoint = basename( $this->get_request_path() );
		if ( 'xmlrpc.php' !== $endpoint && 'wp-trackback.php' !== $endpoint ) {
			return;
		}

		$context = 'xmlrpc.php' === $endpoint ? 'xmlrpc' : 'trackback';
		if ( $this->should_block_request( $context ) ) {
			$this->fail_closed();
		}
	}

	/**
	 * Get the Retry-After interval.
	 *
	 * @since 1.0.7
	 * @access private
	 * @return int Retry interval in seconds.
	 */
	private function get_retry_after() {
		/**
		 * Filters the Retry-After interval for maintenance responses.
		 *
		 * @since 1.0.7
		 *
		 * @param int $retry_after Retry interval in seconds. Default 600.
		 */
		$retry_after = absint( apply_filters( 'acme_coming_soon_retry_after', 600 ) );

		return max( 1, $retry_after );
	}

	/**
	 * Send a fail-closed maintenance response.
	 *
	 * @since 1.0.7
	 * @access private
	 * @return void
	 */
	private function fail_closed() {
		$default_message = __( 'The site is temporarily unavailable. Please try again later.', 'acme-coming-soon' );

		/**
		 * Filters the escaped maintenance message used for fail-closed responses.
		 *
		 * The returned text is escaped before output.
		 *
		 * @since 1.0.7
		 *
		 * @param string $message Maintenance message.
		 */
		$message = apply_filters( 'acme_coming_soon_maintenance_message', $default_message );
		$message = is_scalar( $message ) ? (string) $message : $default_message;
		$title   = __( 'Service temporarily unavailable', 'acme-coming-soon' );

		status_header( 503 );
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'Retry-After: ' . $this->get_retry_after() );
		}

		add_filter( 'wp_die_handler', array( $this, 'use_minimal_die_handler' ), PHP_INT_MAX );
		$this->die_message = $message;
		$this->die_title   = $title;
		wp_die(
			esc_html( $message ),
			esc_html( $title ),
			array(
				'response'  => 503,
				'back_link' => false,
				'exit'      => true,
			)
		);
	}

	/**
	 * Select the minimal maintenance response handler.
	 *
	 * @since 1.0.7
	 * @access public
	 * @return callable
	 */
	public function use_minimal_die_handler() {
		return array( $this, 'render_minimal_die_response' );
	}

	/**
	 * Render a minimal, escaped maintenance response.
	 *
	 * @since 1.0.7
	 * @access public
	 *
	 * @param string $message Response message.
	 * @param string $title Response title.
	 * @param array  $args wp_die() arguments.
	 * @return void
	 */
	public function render_minimal_die_response( $message, $title = '', $args = array() ) {
		$charset = get_option( 'blog_charset' );
		$charset = $charset ? $charset : 'UTF-8';
		if ( '' !== $this->die_message ) {
			$message = $this->die_message;
		}
		if ( '' !== $this->die_title ) {
			$title = $this->die_title;
		}

		printf(
			'<!DOCTYPE html><html><head><meta charset="%1$s"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>%2$s</title></head><body><main><h1>%2$s</h1><p>%3$s</p></main></body></html>',
			esc_attr( $charset ),
			esc_html( $title ),
			esc_html( $message )
		);

		if ( ! isset( $args['exit'] ) || $args['exit'] ) {
			exit;
		}
	}

	/**
	 * Purge public caches after settings are saved.
	 *
	 * Integrations can hook the acme_coming_soon_purge_cache action.
	 *
	 * @since 1.0.7
	 * @access public
	 * @return void
	 */
	public function purge_caches() {
		/**
		 * Fires when public caches should be purged after a settings update.
		 *
		 * @since 1.0.7
		 */
		do_action( 'acme_coming_soon_purge_cache' );

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		if ( has_action( 'litespeed_purge_all' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party LiteSpeed cache hook.
			do_action( 'litespeed_purge_all' );
		}
	}
}
