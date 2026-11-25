<?php
/**
 * Lifecycle tests.
 *
 * @package Acme_Coming_Soon
 */

use PHPUnit\Framework\TestCase;

class LifecycleTest extends TestCase {

	protected function setUp(): void {
		acme_test_reset_state();
	}

	public function test_deactivation_preserves_settings_by_default(): void {
		$GLOBALS['__acme_options'][ ACME_COMING_SOON_OPTION_NAME ] = array( 'on' => true );

		Acme_Coming_Soon_Deactivator::deactivate();

		$this->assertTrue( get_option( ACME_COMING_SOON_OPTION_NAME )['on'] );
	}

	public function test_deactivation_deletes_settings_when_requested(): void {
		$GLOBALS['__acme_options'][ ACME_COMING_SOON_OPTION_NAME ] = array(
			'on'        => true,
			'deleteAll' => true,
		);

		Acme_Coming_Soon_Deactivator::deactivate();

		$this->assertFalse( get_option( ACME_COMING_SOON_OPTION_NAME ) );
	}

	public function test_uninstall_deletes_settings_but_not_the_maintenance_page(): void {
		$GLOBALS['__acme_options'][ ACME_COMING_SOON_OPTION_NAME ] = array( 'on' => true );
		$GLOBALS['__acme_posts'][42]                       = (object) array( 'ID' => 42 );

		define( 'WP_UNINSTALL_PLUGIN', 'acme-coming-soon/acme-coming-soon.php' );
		require ACME_COMING_SOON_PATH . 'uninstall.php';

		$this->assertFalse( get_option( ACME_COMING_SOON_OPTION_NAME ) );
		$this->assertArrayHasKey( 42, $GLOBALS['__acme_posts'] );
	}
}
