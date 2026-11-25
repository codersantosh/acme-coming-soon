<?php
/**
 * REST settings tests.
 *
 * @package Acme_Coming_Soon
 */

use PHPUnit\Framework\TestCase;

class ApiSettingsTest extends TestCase {

	protected function setUp(): void {
		acme_test_reset_state();
	}

	public function test_permissions_require_manage_options(): void {
		$api    = acme_coming_soon_api_settings();
		$request = new WP_REST_Request();

		$this->assertFalse( $api->get_item_permissions_check( $request ) );

		$GLOBALS['__acme_caps']['manage_options'] = true;
		$this->assertTrue( $api->get_item_permissions_check( $request ) );
	}

	public function test_network_super_admin_can_manage_settings(): void {
		$GLOBALS['__acme_multisite']       = true;
		$GLOBALS['__acme_caps']['super_admin'] = true;

		$this->assertTrue( acme_coming_soon_api_settings()->get_item_permissions_check( new WP_REST_Request() ) );
	}

	public function test_registered_setting_is_not_exposed_through_core_rest(): void {
		acme_coming_soon_admin()->register_settings();

		$args = $GLOBALS['__acme_registered']['settings'][ ACME_COMING_SOON_OPTION_NAME ];
		$this->assertFalse( $args['show_in_rest'] );
	}

	public function test_partial_update_preserves_saved_siblings(): void {
		$GLOBALS['__acme_options'][ ACME_COMING_SOON_OPTION_NAME ] = array(
			'on'       => false,
			'template' => 42,
			'exclude'  => array(
				'ips'   => array( '192.0.2.1' ),
				'slugs' => array( 'staging' ),
			),
		);

		$api = acme_coming_soon_api_settings();
		$api->update_item( new WP_REST_Request( array( 'access' => 'login' ) ) );

		$options = acme_coming_soon_get_options();
		$this->assertFalse( $options['on'] );
		$this->assertSame( 42, $options['template'] );
		$this->assertSame( 'login', $options['access'] );
		$this->assertSame( array( '192.0.2.1' ), $options['exclude']['ips'] );
	}

	public function test_invalid_client_data_returns_400(): void {
		$GLOBALS['__acme_validate'] = false;
		$api                        = acme_coming_soon_api_settings();

		$result = $api->update_item( new WP_REST_Request( array( 'on' => 'invalid' ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 400, $result->data['status'] );
	}

	public function test_scalar_value_for_array_setting_returns_400(): void {
		$api = acme_coming_soon_api_settings();

		$result = $api->update_item( new WP_REST_Request( array( 'exclude' => array( 'ips' => 'not-an-array' ) ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 400, $result->data['status'] );
		$this->assertSame( 'settings[exclude][ips]', $result->data['param'] );
	}

	public function test_enabling_with_a_published_page_is_accepted(): void {
		$GLOBALS['__acme_posts'][42] = (object) array(
			'ID'          => 42,
			'post_type'   => 'page',
			'post_status' => 'publish',
		);
		$api = acme_coming_soon_api_settings();

		$result = $api->update_item( new WP_REST_Request( array( 'on' => true, 'template' => 42 ) ) );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['on'] );
		$this->assertSame( 42, $result['template'] );
	}

	public function test_enabling_without_a_published_page_is_rejected(): void {
		$api = acme_coming_soon_api_settings();

		$result = $api->update_item( new WP_REST_Request( array( 'on' => true, 'template' => 0 ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 400, $result->data['status'] );
		$this->assertFalse( acme_coming_soon_get_options( 'on' ) );
	}
}
