<?php
/**
 * Public maintenance access tests.
 *
 * @package Acme_Coming_Soon
 */

use PHPUnit\Framework\TestCase;

class PublicAccessTest extends TestCase {

	protected function setUp(): void {
		acme_test_reset_state();
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
		$_SERVER['REQUEST_URI']    = '/protected/';
		$_SERVER['REMOTE_ADDR']    = '192.0.2.10';
	}

	private function set_settings( array $settings ): void {
		$GLOBALS['__acme_options'][ ACME_COMING_SOON_OPTION_NAME ] = $settings;
		acme_coming_soon_include()->reset_settings_cache();
	}

	private function add_page( int $id, string $status = 'publish' ): void {
		$GLOBALS['__acme_posts'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => 'page',
			'post_status' => $status,
			'permalink'   => "https://example.com/maintenance-{$id}/",
		);
	}

	public function test_missing_user_agent_is_not_a_bot(): void {
		unset( $_SERVER['HTTP_USER_AGENT'] );

		$this->assertFalse( acme_coming_soon_public()->is_search_bot() );
	}

	public function test_search_bot_setting_uses_canonical_key(): void {
		$_SERVER['HTTP_USER_AGENT'] = 'Googlebot/2.1';
		$this->set_settings(
			array(
				'on'      => true,
				'exclude' => array( 'searchBot' => true ),
			)
		);

		$this->assertTrue( acme_coming_soon_public()->pass_search_bot() );
	}

	public function test_bot_exclusion_is_disabled_by_default(): void {
		$_SERVER['HTTP_USER_AGENT'] = 'Googlebot/2.1';
		$this->set_settings( array( 'on' => true ) );

		$this->assertFalse( acme_coming_soon_public()->pass_search_bot() );
	}

	public function test_ip_exclusion_requires_exact_match(): void {
		$this->set_settings(
			array(
				'on'      => true,
				'exclude' => array( 'ips' => array( '192.0.2.1' ) ),
			)
		);

		$this->assertFalse( acme_coming_soon_public()->pass_ip() );

		$_SERVER['REMOTE_ADDR'] = '192.0.2.1';
		$this->assertTrue( acme_coming_soon_public()->pass_ip() );
	}

	public function test_ipv6_exclusion_handles_equivalent_notation(): void {
		$_SERVER['REMOTE_ADDR'] = '2001:0db8:0000:0000:0000:0000:0000:0001';
		$this->set_settings(
			array(
				'on'      => true,
				'exclude' => array( 'ips' => array( '2001:db8::1' ) ),
			)
		);

		$this->assertTrue( acme_coming_soon_public()->pass_ip() );
	}

	public function test_slug_exclusion_matches_path_but_not_query_string(): void {
		$this->set_settings(
			array(
				'on'      => true,
				'exclude' => array( 'slugs' => array( 'staging' ) ),
			)
		);

		$_SERVER['REQUEST_URI'] = '/staging/private/';
		$this->assertTrue( acme_coming_soon_public()->pass_slug() );

		$_SERVER['REQUEST_URI'] = '/protected/?staging=1';
		$this->assertFalse( acme_coming_soon_public()->pass_slug() );

		$_SERVER['REQUEST_URI'] = '/staging-other/';
		$this->assertFalse( acme_coming_soon_public()->pass_slug() );
	}

	public function test_admin_access_uses_manage_options_capability(): void {
		$this->set_settings( array( 'on' => true, 'access' => 'admin' ) );
		$GLOBALS['__acme_logged_in']    = true;
		$GLOBALS['__acme_caps']['manage_options'] = true;

		$this->assertTrue( acme_coming_soon_public()->pass_user_role() );
	}

	public function test_login_access_allows_any_authenticated_user(): void {
		$this->set_settings( array( 'on' => true, 'access' => 'login' ) );
		$GLOBALS['__acme_logged_in'] = true;

		$this->assertTrue( acme_coming_soon_public()->pass_user_role() );
	}

	public function test_selected_roles_allow_matching_role_only(): void {
		$this->set_settings(
			array(
				'on'      => true,
				'access'  => 'roles',
				'exclude' => array( 'useRoles' => array( 'editor' ) ),
			)
		);
		$GLOBALS['__acme_logged_in']    = true;
		$GLOBALS['__acme_current_user'] = (object) array( 'roles' => array( 'subscriber' ) );
		$this->assertFalse( acme_coming_soon_public()->pass_user_role() );

		$GLOBALS['__acme_current_user'] = (object) array( 'roles' => array( 'editor' ) );
		$this->assertTrue( acme_coming_soon_public()->pass_user_role() );
	}

	public function test_cron_requests_are_not_blocked(): void {
		$this->add_page( 42 );
		$this->set_settings(
			array(
				'on'       => true,
				'access'   => 'admin',
				'template' => 42,
			)
		);
		$GLOBALS['__acme_doing_cron'] = true;

		$this->assertFalse( acme_coming_soon_public()->should_block_request( 'frontend' ) );
	}

	public function test_none_access_does_not_allow_authenticated_users(): void {
		$this->set_settings( array( 'on' => true, 'access' => '' ) );
		$GLOBALS['__acme_logged_in'] = true;

		$this->assertFalse( acme_coming_soon_public()->pass_user_role() );
	}

	public function test_anonymous_visitor_is_blocked_for_a_valid_maintenance_page(): void {
		$this->add_page( 42 );
		$this->set_settings(
			array(
				'on'       => true,
				'access'   => 'admin',
				'template' => 42,
			)
		);
		$GLOBALS['__acme_queried_id']  = 7;
		$GLOBALS['__acme_is_singular'] = true;

		$this->assertTrue( acme_coming_soon_public()->should_block_request( 'frontend' ) );
	}

	public function test_maintenance_page_does_not_redirect_to_itself(): void {
		$this->add_page( 42 );
		$this->set_settings(
			array(
				'on'       => true,
				'access'   => 'admin',
				'template' => 42,
			)
		);
		$GLOBALS['__acme_queried_id']  = 42;
		$GLOBALS['__acme_is_singular'] = true;

		acme_coming_soon_public()->redirect_to_maintenance();

		$this->assertSame( array(), $GLOBALS['__acme_redirects'] );
	}

	public function test_invalid_template_fails_closed_with_503(): void {
		$this->set_settings(
			array(
				'on'       => true,
				'access'   => 'admin',
				'template' => 0,
			)
		);

		try {
			acme_coming_soon_public()->redirect_to_maintenance();
			$this->fail( 'Expected fail-closed response.' );
		} catch ( RuntimeException $error ) {
			$this->assertSame( 'wp_die', $error->getMessage() );
		}

		$this->assertContains( 503, $GLOBALS['__acme_status'] );
		$this->assertGreaterThan( 0, $GLOBALS['__acme_nocache'] );
	}
}
