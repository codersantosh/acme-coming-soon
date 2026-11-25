<?php
/**
 * Changelog tests.
 *
 * @package Acme_Coming_Soon
 */

use PHPUnit\Framework\TestCase;

class ChangelogTest extends TestCase {

	protected function setUp(): void {
		acme_test_reset_state();
	}

	public function test_full_changelog_is_read_by_default(): void {
		$changelog = acme_coming_soon_parse_changelog();

		$this->assertStringContainsString( 'Raised minimum WordPress requirement', $changelog );
		$this->assertStringContainsString( 'Initial release', $changelog );
	}

	public function test_changelog_filter_can_override_the_file(): void {
		add_filter(
			'acme_coming_soon_changelog_file',
			function () {
				return ACME_COMING_SOON_PATH . 'tests/fixtures/changelog.txt';
			}
		);

		$this->assertStringContainsString( 'Fixture release', acme_coming_soon_parse_changelog() );
	}
}
