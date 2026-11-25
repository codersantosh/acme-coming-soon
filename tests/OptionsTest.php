<?php
/**
 * Settings option tests.
 *
 * @package Acme_Coming_Soon
 */

use PHPUnit\Framework\TestCase;

class OptionsTest extends TestCase {

	protected function setUp(): void {
		acme_test_reset_state();
	}

	public function test_defaults_are_returned_without_saved_options(): void {
		$options = acme_coming_soon_get_options();

		$this->assertFalse( $options['on'] );
		$this->assertSame( 'admin', $options['access'] );
		$this->assertFalse( $options['exclude']['searchBot'] );
		$this->assertSame( array(), $options['exclude']['ips'] );
		$this->assertSame( 0, $options['template'] );
	}

	public function test_nested_defaults_are_merged_for_partial_legacy_settings(): void {
		$GLOBALS['__acme_options'][ ACME_COMING_SOON_OPTION_NAME ] = array(
			'on'      => true,
			'exclude' => array( 'ips' => array( '192.0.2.1' ) ),
		);

		$options = acme_coming_soon_get_options();

		$this->assertTrue( $options['on'] );
		$this->assertSame( array( '192.0.2.1' ), $options['exclude']['ips'] );
		$this->assertSame( array(), $options['exclude']['slugs'] );
		$this->assertFalse( $options['exclude']['searchBot'] );
	}

	public function test_key_lookup_merges_nested_defaults(): void {
		$GLOBALS['__acme_options'][ ACME_COMING_SOON_OPTION_NAME ] = array(
			'exclude' => array( 'ips' => array( '192.0.2.1' ) ),
		);

		$exclude = acme_coming_soon_get_options( 'exclude' );

		$this->assertSame( array( '192.0.2.1' ), $exclude['ips'] );
		$this->assertSame( array(), $exclude['slugs'] );
	}

	public function test_single_key_update_preserves_raw_siblings_without_persisting_defaults(): void {
		$GLOBALS['__acme_options'][ ACME_COMING_SOON_OPTION_NAME ] = array(
			'on'     => true,
			'template' => 42,
		);

		acme_coming_soon_update_options( 'access', 'login' );
		$stored = get_option( ACME_COMING_SOON_OPTION_NAME );

		$this->assertTrue( $stored['on'] );
		$this->assertSame( 42, $stored['template'] );
		$this->assertSame( 'login', $stored['access'] );
		$this->assertArrayNotHasKey( 'exclude', $stored );
	}

	public function test_array_update_deep_merges_associative_values(): void {
		$GLOBALS['__acme_options'][ ACME_COMING_SOON_OPTION_NAME ] = array(
			'on'       => true,
			'template' => 42,
			'exclude'  => array(
				'ips'   => array( '192.0.2.1' ),
				'slugs' => array( 'staging' ),
			),
		);

		acme_coming_soon_update_options(
			array(
				'exclude' => array( 'ips' => array( '198.51.100.2' ) ),
			)
		);

		$options = acme_coming_soon_get_options();
		$this->assertTrue( $options['on'] );
		$this->assertSame( 42, $options['template'] );
		$this->assertSame( array( '198.51.100.2' ), $options['exclude']['ips'] );
		$this->assertSame( array( 'staging' ), $options['exclude']['slugs'] );
	}

	public function test_direct_update_option_uses_atomic_merge_filter(): void {
		$GLOBALS['__acme_options'][ ACME_COMING_SOON_OPTION_NAME ] = array(
			'on'       => true,
			'template' => 42,
		);

		update_option( ACME_COMING_SOON_OPTION_NAME, array( 'access' => 'login' ) );

		$options = acme_coming_soon_get_options();
		$this->assertTrue( $options['on'] );
		$this->assertSame( 42, $options['template'] );
		$this->assertSame( 'login', $options['access'] );
	}

	public function test_settings_cache_is_invalidated_after_update(): void {
		acme_coming_soon_include()->get_settings();
		acme_coming_soon_update_options( 'on', true );

		$this->assertTrue( acme_coming_soon_include()->get_settings()['on'] );
	}
}
