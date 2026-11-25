<?php
/**
 * Runtime assertions loaded by WP-CLI inside the disposable Acme Coming Soon
 * smoke-test site. Keep WordPress assertions here instead of inline wp-eval
 * strings so failures remain readable and shell quoting cannot change meaning.
 *
 * @package Acme_Coming_Soon
 */

/**
 * Read an environment variable.
 *
 * @param string $name    Variable name.
 * @param string $default Default value.
 * @return string
 */
function acs_runtime_env( $name, $default = '' ) {
	$value = getenv( $name );
	return false === $value ? $default : $value;
}

/**
 * Resolve the plugin option name while the plugin may be inactive.
 *
 * The plugin constants are unavailable after deactivation and uninstall, so
 * the stored option name must fall back to its literal value.
 *
 * @return string Option name.
 */
function acs_runtime_option_name() {
	return defined( 'ACME_COMING_SOON_OPTION_NAME' ) ? ACME_COMING_SOON_OPTION_NAME : 'acme_coming_soon_options';
}

/**
 * Assert a runtime condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 */
function acs_runtime_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Assert strict equality with a readable label.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $label    Assertion label.
 */
function acs_runtime_same( $expected, $actual, $label ) {
	if ( $expected !== $actual ) {
		throw new RuntimeException(
			$label . ': expected ' . var_export( $expected, true ) . ', received ' . var_export( $actual, true )
		);
	}
}

/**
 * Decode a required JSON environment variable.
 *
 * @param string $name Variable name.
 * @return mixed
 */
function acs_runtime_json_env( $name ) {
	$raw = getenv( $name );
	if ( false === $raw || '' === $raw ) {
		throw new RuntimeException( "$name is required." );
	}
	try {
		return json_decode( $raw, true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		throw new RuntimeException( "$name is not valid JSON: " . $exception->getMessage() );
	}
}

/**
 * Decode a JSON file.
 *
 * @param string $path File path.
 * @return mixed
 */
function acs_runtime_json_file( $path ) {
	if ( ! is_file( $path ) ) {
		throw new RuntimeException( "JSON file does not exist: $path" );
	}
	try {
		return json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		throw new RuntimeException( "Invalid JSON in $path: " . $exception->getMessage() );
	}
}

/**
 * Assert an expected subset exists in an actual array.
 *
 * @param array  $expected Expected subset.
 * @param mixed  $actual   Actual value.
 * @param string $path     Assertion path.
 */
function acs_runtime_assert_subset( array $expected, $actual, $path ) {
	if ( ! is_array( $actual ) ) {
		throw new RuntimeException( "$path must be an array." );
	}
	foreach ( $expected as $key => $value ) {
		if ( ! array_key_exists( $key, $actual ) ) {
			throw new RuntimeException( "$path is missing '$key'." );
		}
		if ( is_array( $value ) ) {
			acs_runtime_assert_subset( $value, $actual[ $key ], "$path.$key" );
		} elseif ( $value !== $actual[ $key ] ) {
			throw new RuntimeException(
				"$path.$key: expected " . var_export( $value, true ) . ', received ' . var_export( $actual[ $key ], true )
			);
		}
	}
}

/**
 * Read a JSON array of strings from the environment.
 *
 * @param string $name Variable name.
 * @return string[]
 */
function acs_runtime_string_array_env( $name ) {
	$value = acs_runtime_json_env( $name );
	if ( ! is_array( $value ) || ! array_is_list( $value ) || array_filter( $value, 'is_string' ) !== $value ) {
		throw new RuntimeException( "$name must be a JSON array of strings." );
	}
	return array_values( $value );
}

/**
 * Create or update disposable runtime fixtures and test users.
 */
function acs_runtime_create_fixtures() {
	$fixture_file = acs_runtime_env( 'ACS_FIXTURE_FILE' );
	$env_file     = acs_runtime_env( 'ACS_FIXTURE_ENV_FILE' );
	acs_runtime_assert( '' !== $fixture_file && '' !== $env_file, 'Fixture output paths are required.' );

	$page_specs = array(
		'maintenance' => array(
			'slug'   => 'acme-runtime-maintenance',
			'title'  => 'Acme Runtime Maintenance',
			'marker' => 'ACS_MAINTENANCE_MARKER',
			'status' => 'publish',
		),
		'private'     => array(
			'slug'   => 'acme-runtime-private',
			'title'  => 'Acme Runtime Private',
			'marker' => 'ACS_PRIVATE_MARKER',
			'status' => 'publish',
		),
		'open'        => array(
			'slug'   => 'acme-runtime-open',
			'title'  => 'Acme Runtime Open',
			'marker' => 'ACS_OPEN_MARKER',
			'status' => 'publish',
		),
		'draft'       => array(
			'slug'   => 'acme-runtime-draft',
			'title'  => 'Acme Runtime Draft',
			'marker' => 'ACS_DRAFT_MARKER',
			'status' => 'draft',
		),
	);
	$fixtures    = array();

	foreach ( $page_specs as $key => $spec ) {
		$existing = get_page_by_path( $spec['slug'], OBJECT, 'page' );
		$post     = array(
			'post_title'   => $spec['title'],
			'post_name'    => $spec['slug'],
			'post_status'  => $spec['status'],
			'post_type'    => 'page',
			'post_content' => '<p>' . $spec['marker'] . '</p>',
		);
		if ( $existing ) {
			$post['ID'] = $existing->ID;
			$result     = wp_update_post( wp_slash( $post ), true );
		} else {
			$result = wp_insert_post( wp_slash( $post ), true );
		}
		if ( is_wp_error( $result ) || ! is_numeric( $result ) ) {
			throw new RuntimeException( 'Could not create fixture ' . $key . ': ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'unknown error' ) );
		}
		$fixtures[ $key . '_id' ] = (int) $result;
	}

	$post_id = wp_insert_post(
		wp_slash(
			array(
				'post_title'   => 'Acme Runtime REST Post',
				'post_name'    => 'acme-runtime-post',
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_content' => '<p>ACS_POST_MARKER</p>',
			)
		),
		true
	);
	if ( is_wp_error( $post_id ) || ! is_numeric( $post_id ) ) {
		throw new RuntimeException( 'Could not create the runtime REST post.' );
	}
	$fixtures['post_id'] = (int) $post_id;

	$user_specs = array(
		'editor'     => array(
			'login'    => acs_runtime_env( 'EDITOR_USER', 'acmeeditor' ),
			'password' => acs_runtime_env( 'EDITOR_PASSWORD', 'Acme-Editor-Runtime-2026!' ),
			'role'     => 'editor',
		),
		'subscriber' => array(
			'login'    => acs_runtime_env( 'SUBSCRIBER_USER', 'acmesubscriber' ),
			'password' => acs_runtime_env( 'SUBSCRIBER_PASSWORD', 'Acme-Subscriber-Runtime-2026!' ),
			'role'     => 'subscriber',
		),
	);
	$admin_login = acs_runtime_env( 'ADMIN_USER', 'acmeadmin' );
	$admin       = get_user_by( 'login', $admin_login );
	acs_runtime_assert( $admin instanceof WP_User, 'The runtime administrator does not exist.' );
	$user_specs['admin'] = array(
		'login'    => $admin_login,
		'password' => acs_runtime_env( 'ADMIN_PASSWORD', 'Acme-Admin-Runtime-2026!' ),
		'role'     => 'administrator',
	);

	$env_lines = array();
	foreach ( $fixtures as $key => $value ) {
		$env_lines[] = strtoupper( $key ) . '=' . $value;
	}

	foreach ( $user_specs as $key => $spec ) {
		$user = get_user_by( 'login', $spec['login'] );
		if ( ! $user ) {
			$user_id = wp_insert_user(
				array(
					'user_login'   => $spec['login'],
					'user_pass'    => $spec['password'],
					'user_email'   => $spec['login'] . '@example.com',
					'display_name' => $spec['login'],
					'role'         => $spec['role'],
				)
			);
			if ( is_wp_error( $user_id ) ) {
				throw new RuntimeException( 'Could not create runtime user ' . $key . ': ' . $user_id->get_error_message() );
			}
			$user = get_user_by( 'id', $user_id );
		} else {
			$user->set_role( $spec['role'] );
		}
		acs_runtime_assert( $user instanceof WP_User, 'Could not load runtime user ' . $key . '.' );
		$fixtures[ $key . '_id' ] = (int) $user->ID;
		$env_lines[]              = strtoupper( $key ) . '_ID=' . (int) $user->ID;

		acs_runtime_assert( class_exists( 'WP_Application_Passwords' ), 'Application Passwords support is unavailable.' );
		acs_runtime_assert( wp_is_application_passwords_available(), 'Application Passwords are disabled; set WP_ENVIRONMENT_TYPE to local.' );
		$application_password = WP_Application_Passwords::create_new_application_password(
			$user->ID,
			array( 'name' => 'Acme Coming Soon runtime smoke' )
		);
		if ( is_wp_error( $application_password ) ) {
			throw new RuntimeException( 'Could not create an application password for ' . $key . ': ' . $application_password->get_error_message() );
		}
		$authorization = 'Basic ' . base64_encode( $spec['login'] . ':' . $application_password[0] );
		$fixtures[ $key . '_authorization' ] = $authorization;
		$env_lines[]                         = strtoupper( $key ) . '_AUTHORIZATION=' . escapeshellarg( $authorization );
	}

	if ( false === file_put_contents( $fixture_file, wp_json_encode( $fixtures ) . "\n" ) ) {
		throw new RuntimeException( 'Could not write the fixture JSON file.' );
	}
	if ( false === file_put_contents( $env_file, implode( "\n", $env_lines ) . "\n" ) ) {
		throw new RuntimeException( 'Could not write the fixture environment file.' );
	}

	echo "Runtime fixtures and API users created.\n";
}

/**
 * Assert activation, versions, routes, settings registration, assets, patterns,
 * and offline pattern registration.
 */
function acs_runtime_initial_assertions() {
	acs_runtime_same( acs_runtime_env( 'ACS_EXPECTED_VERSION' ), ACME_COMING_SOON_VERSION, 'Plugin version constant' );
	acs_runtime_same( 'acme-coming-soon', ACME_COMING_SOON_PLUGIN_NAME, 'Plugin slug constant' );
	acs_runtime_assert(
		in_array( 'acme-coming-soon/acme-coming-soon.php', get_option( 'active_plugins', array() ), true ),
		'Plugin is not active.'
	);
	acs_runtime_assert( is_array( acme_coming_soon_get_options() ), 'Settings defaults are unavailable.' );

	$changelog = acme_coming_soon_parse_changelog();
	acs_runtime_assert(
		false !== strpos( $changelog, 'Raised minimum WordPress requirement to 7.0' ),
		'The current changelog is unavailable to the admin reader.'
	);

	foreach ( array( 'admin.js', 'admin.css', 'admin-rtl.css', 'admin.asset.php' ) as $asset ) {
		acs_runtime_assert(
			is_file( ACME_COMING_SOON_PATH . 'build/admin/' . $asset ),
			"Missing admin asset: $asset"
		);
	}

	do_action( 'rest_api_init' );
	$routes = rest_get_server()->get_routes();
	acs_runtime_assert(
		isset( $routes['/acme-coming-soon/v1/settings'] ),
		'Missing REST route /acme-coming-soon/v1/settings.'
	);
	$registered_settings = get_registered_settings();
	$setting             = $registered_settings[ ACME_COMING_SOON_OPTION_NAME ] ?? array();
	acs_runtime_assert(
		false === ( $setting['show_in_rest'] ?? false ),
		'Plugin settings must not be exposed through the core settings REST schema.'
	);

	$patterns_file = ACME_COMING_SOON_PATH . 'includes/json/patterns.json';
	acs_runtime_assert( is_readable( $patterns_file ), 'The local patterns JSON file is unavailable.' );
	$patterns = json_decode( (string) file_get_contents( $patterns_file ), true );
	acs_runtime_assert( is_array( $patterns ) && array() !== $patterns, 'The local patterns JSON file is empty or invalid.' );
	$expected_names = array();
	foreach ( $patterns as $pattern ) {
		if ( ! isset( $pattern['slug'] ) ) {
			throw new RuntimeException( 'A local pattern is missing its slug.' );
		}
		$expected_names[] = sanitize_title( 'pwp-' . $pattern['slug'] );
	}

	// WP 7.x returns a re-indexed list from get_all_registered(), so the
	// pattern names must be read from each entry's name property.
	$registry          = WP_Block_Patterns_Registry::get_instance();
	$registered_names  = array();
	foreach ( $registry->get_all_registered() as $registered_pattern ) {
		if ( isset( $registered_pattern['name'] ) && is_string( $registered_pattern['name'] ) ) {
			$registered_names[] = $registered_pattern['name'];
		}
	}
	$missing = array_diff( $expected_names, $registered_names );
	if ( array() !== $missing ) {
		acme_coming_soon_patterns()->register_patterns();
		$registered_names = array();
		foreach ( $registry->get_all_registered() as $registered_pattern ) {
			if ( isset( $registered_pattern['name'] ) && is_string( $registered_pattern['name'] ) ) {
				$registered_names[] = $registered_pattern['name'];
			}
		}
	}
	foreach ( $expected_names as $name ) {
		acs_runtime_assert( in_array( $name, $registered_names, true ), "Pattern was not registered from local JSON: $name" );
	}

	echo "Activation, REST registration, admin assets, and offline patterns passed.\n";
}

/**
 * Configure the plugin options for one runtime scenario.
 */
function acs_runtime_configure_options() {
	$access = acs_runtime_env( 'ACS_ACCESS', 'admin' );
	acs_runtime_assert( in_array( $access, array( '', 'login', 'admin', 'roles' ), true ), "Invalid access mode: $access" );
	$options = array(
		'on'        => filter_var( acs_runtime_env( 'ACS_ON', 'false' ), FILTER_VALIDATE_BOOLEAN ),
		'access'    => $access,
		'exclude'   => array(
			'useRoles'  => acs_runtime_string_array_env( 'ACS_ROLES_JSON' ),
			'searchBot' => filter_var( acs_runtime_env( 'ACS_SEARCH_BOT', 'false' ), FILTER_VALIDATE_BOOLEAN ),
			'ips'       => acs_runtime_string_array_env( 'ACS_IPS_JSON' ),
			'slugs'     => acs_runtime_string_array_env( 'ACS_SLUGS_JSON' ),
		),
		'template'  => (int) acs_runtime_env( 'ACS_TEMPLATE_ID', '0' ),
		'deleteAll' => filter_var( acs_runtime_env( 'ACS_DELETE_ALL', 'false' ), FILTER_VALIDATE_BOOLEAN ),
	);
	$existing = get_option( ACME_COMING_SOON_OPTION_NAME, null );
	if ( $existing !== $options ) {
		acs_runtime_assert( false !== update_option( ACME_COMING_SOON_OPTION_NAME, $options ), 'Could not persist runtime options.' );
	}
	echo "Runtime options configured.\n";
}

/**
 * Assert persisted plugin options.
 */
function acs_runtime_option_assertions() {
	$expected_exists = filter_var( acs_runtime_env( 'ACS_EXPECTED_OPTION_EXISTS', 'true' ), FILTER_VALIDATE_BOOLEAN );
	$options         = get_option( ACME_COMING_SOON_OPTION_NAME, false );
	if ( ! $expected_exists ) {
		acs_runtime_same( false, $options, 'Plugin option persistence' );
	} else {
		acs_runtime_assert( is_array( $options ), 'Plugin option is missing or invalid.' );
		$expected = acs_runtime_json_env( 'ACS_EXPECTED_OPTIONS' );
		acs_runtime_assert( is_array( $expected ), 'ACS_EXPECTED_OPTIONS must be a JSON object.' );
		acs_runtime_assert_subset( $expected, $options, 'options' );
	}
	echo "Persisted option assertions passed.\n";
}

/**
 * Assert a JSON HTTP response body.
 */
function acs_runtime_rest_response_assertions() {
	$path = acs_runtime_env( 'ACS_RESPONSE_FILE' );
	$json = acs_runtime_json_file( $path );
	$expected = acs_runtime_json_env( 'ACS_EXPECTED_JSON' );
	acs_runtime_assert( is_array( $expected ), 'ACS_EXPECTED_JSON must be a JSON object.' );
	acs_runtime_assert_subset( $expected, $json, 'response' );
	if ( filter_var( acs_runtime_env( 'ACS_ASSERT_NO_OPTION_KEY', 'false' ), FILTER_VALIDATE_BOOLEAN ) ) {
		$contents = (string) file_get_contents( $path );
		acs_runtime_assert( false === strpos( $contents, ACME_COMING_SOON_OPTION_NAME ), 'Plugin option leaked through the core settings response.' );
	}
	echo "REST response assertions passed.\n";
}

/**
 * Assert the rendered admin page contains its root and local build assets.
 */
function acs_runtime_admin_html_assertions() {
	$path = acs_runtime_env( 'ACS_ADMIN_HTML' );
	if ( ! is_file( $path ) ) {
		throw new RuntimeException( "Admin HTML file does not exist: $path" );
	}
	$html = (string) file_get_contents( $path );
	foreach ( array( 'id="acme-coming-soon"', 'AcmeComingSoonLocalize', 'build/admin/admin.js', 'build/admin/admin.css' ) as $needle ) {
		acs_runtime_assert( false !== strpos( $html, $needle ), "Admin page is missing $needle." );
	}
	echo "Admin page and asset assertions passed.\n";
}

/**
 * Assert exact IP exclusion behavior.
 */
function acs_runtime_ip_assertions() {
	$_SERVER['REMOTE_ADDR'] = acs_runtime_env( 'ACS_REMOTE_ADDR' );
	$actual                 = acme_coming_soon_public()->pass_ip();
	$expected               = filter_var( acs_runtime_env( 'ACS_EXPECTED_BOOL' ), FILTER_VALIDATE_BOOLEAN );
	acs_runtime_same( $expected, $actual, 'IP exclusion result' );
	echo "IP exclusion assertions passed.\n";
}

/**
 * Assert path-only slug exclusion behavior.
 */
function acs_runtime_slug_assertions() {
	$_SERVER['REQUEST_URI'] = acs_runtime_env( 'ACS_REQUEST_URI' );
	$actual                 = acme_coming_soon_public()->pass_slug();
	$expected               = filter_var( acs_runtime_env( 'ACS_EXPECTED_BOOL' ), FILTER_VALIDATE_BOOLEAN );
	acs_runtime_same( $expected, $actual, 'Slug exclusion result' );
	echo "Slug exclusion assertions passed.\n";
}

/**
 * Assert bot detection and the configured search-bot exclusion.
 */
function acs_runtime_bot_assertions() {
	$user_agent = acs_runtime_env( 'ACS_USER_AGENT' );
	if ( '<missing>' === $user_agent ) {
		unset( $_SERVER['HTTP_USER_AGENT'] );
	} else {
		$_SERVER['HTTP_USER_AGENT'] = $user_agent;
	}
	$public      = acme_coming_soon_public();
	$actual_bot  = $public->is_search_bot();
	$actual_pass = $public->pass_search_bot();
	acs_runtime_same( filter_var( acs_runtime_env( 'ACS_EXPECTED_BOT' ), FILTER_VALIDATE_BOOLEAN ), $actual_bot, 'Search-bot detection' );
	acs_runtime_same( filter_var( acs_runtime_env( 'ACS_EXPECTED_PASS' ), FILTER_VALIDATE_BOOLEAN ), $actual_pass, 'Search-bot exclusion' );
	echo "Search-bot assertions passed.\n";
}

/**
 * Assert access behavior for one runtime user.
 */
function acs_runtime_user_assertions() {
	$user_id = (int) acs_runtime_env( 'ACS_USER_ID' );
	wp_set_current_user( $user_id );
	$actual   = acme_coming_soon_public()->pass_user_role();
	$expected = filter_var( acs_runtime_env( 'ACS_EXPECTED_BOOL' ), FILTER_VALIDATE_BOOLEAN );
	acs_runtime_same( $expected, $actual, 'User access result' );
	wp_set_current_user( 0 );
	echo "User access assertions passed.\n";
}

/**
 * Assert activation and option persistence after reactivation.
 */
function acs_runtime_persistence_assertions() {
	acs_runtime_assert(
		in_array( 'acme-coming-soon/acme-coming-soon.php', get_option( 'active_plugins', array() ), true ),
		'Plugin is not active after reactivation.'
	);
	$options = get_option( ACME_COMING_SOON_OPTION_NAME, false );
	acs_runtime_assert( is_array( $options ), 'Options did not survive deactivation and reactivation.' );
	acs_runtime_assert_subset( acs_runtime_json_env( 'ACS_EXPECTED_OPTIONS' ), $options, 'options' );
	echo "Reactivation persistence assertions passed.\n";
}

/**
 * Assert deactivation and optional option cleanup.
 */
function acs_runtime_deactivation_assertions() {
	acs_runtime_assert(
		! in_array( 'acme-coming-soon/acme-coming-soon.php', get_option( 'active_plugins', array() ), true ),
		'Plugin remained active after deactivation.'
	);
	$expected_exists = filter_var( acs_runtime_env( 'ACS_EXPECTED_OPTION_EXISTS', 'true' ), FILTER_VALIDATE_BOOLEAN );
	$options         = get_option( acs_runtime_option_name(), false );
	if ( $expected_exists ) {
		acs_runtime_assert( is_array( $options ), 'Deactivation unexpectedly removed plugin options.' );
		acs_runtime_assert_subset( acs_runtime_json_env( 'ACS_EXPECTED_OPTIONS' ), $options, 'options' );
	} else {
		acs_runtime_same( false, $options, 'Deactivation option cleanup' );
	}
	echo "Deactivation assertions passed.\n";
}

/**
 * Assert uninstall removed the plugin files and stored options.
 */
function acs_runtime_uninstall_assertions() {
	acs_runtime_assert(
		! in_array( 'acme-coming-soon/acme-coming-soon.php', get_option( 'active_plugins', array() ), true ),
		'Uninstalled plugin remains active.'
	);
	acs_runtime_assert(
		! file_exists( WP_PLUGIN_DIR . '/acme-coming-soon/acme-coming-soon.php' ),
		'Uninstalled plugin files remain.'
	);
	acs_runtime_same( false, get_option( acs_runtime_option_name(), false ), 'Uninstall option cleanup' );
	echo "Uninstall assertions passed.\n";
}

/**
 * Fail if WordPress logged PHP problems or database errors.
 */
function acs_runtime_debug_log_assertions() {
	$path = defined( 'WP_DEBUG_LOG' ) ? WP_DEBUG_LOG : false;
	if ( true === $path ) {
		$path = WP_CONTENT_DIR . '/debug.log';
	}
	if ( ! is_string( $path ) || ! is_file( $path ) ) {
		echo "No debug log was created.\n";
		return;
	}
	$contents = (string) file_get_contents( $path );
	$pattern  = '/PHP (?:Warning|Notice|Deprecated|Fatal error)|Uncaught|WordPress database error/i';
	acs_runtime_assert( 1 !== preg_match( $pattern, $contents ), "WordPress debug log contains errors:\n$contents" );
	echo "WordPress debug log is clean.\n";
}

$phase = getenv( 'ACS_SMOKE_PHASE' );

if ( 'fixtures' === $phase ) {
	acs_runtime_create_fixtures();
} elseif ( 'initial' === $phase ) {
	acs_runtime_initial_assertions();
} elseif ( 'configure' === $phase ) {
	acs_runtime_configure_options();
} elseif ( 'assert-options' === $phase ) {
	acs_runtime_option_assertions();
} elseif ( 'assert-rest-response' === $phase ) {
	acs_runtime_rest_response_assertions();
} elseif ( 'assert-admin-html' === $phase ) {
	acs_runtime_admin_html_assertions();
} elseif ( 'check-ip' === $phase ) {
	acs_runtime_ip_assertions();
} elseif ( 'check-slug' === $phase ) {
	acs_runtime_slug_assertions();
} elseif ( 'check-bot' === $phase ) {
	acs_runtime_bot_assertions();
} elseif ( 'check-user' === $phase ) {
	acs_runtime_user_assertions();
} elseif ( 'persistence' === $phase ) {
	acs_runtime_persistence_assertions();
} elseif ( 'deactivated' === $phase ) {
	acs_runtime_deactivation_assertions();
} elseif ( 'uninstalled' === $phase ) {
	acs_runtime_uninstall_assertions();
} elseif ( 'check-debug-log' === $phase ) {
	acs_runtime_debug_log_assertions();
} else {
	throw new RuntimeException( 'ACS_SMOKE_PHASE must name a supported runtime assertion phase.' );
}
