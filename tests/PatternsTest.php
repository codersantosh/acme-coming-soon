<?php
/**
 * Block pattern tests.
 *
 * @package Acme_Coming_Soon
 */

use PHPUnit\Framework\TestCase;

class PatternsTest extends TestCase {

	protected function setUp(): void {
		acme_test_reset_state();
	}

	public function test_patterns_load_from_local_json_without_http(): void {
		$patterns = acme_coming_soon_patterns();
		$patterns->register_block_pattern_category();
		$patterns->register_patterns();

		$this->assertArrayHasKey( 'pwp-coming-soon-and-maintenance', $GLOBALS['__acme_registered']['categories'] );
		$this->assertArrayHasKey( 'pwp-coming-soon-template-1', $GLOBALS['__acme_registered']['patterns'] );
		$this->assertArrayHasKey( 'pwp-maintenance-template-1', $GLOBALS['__acme_registered']['patterns'] );
	}
}
