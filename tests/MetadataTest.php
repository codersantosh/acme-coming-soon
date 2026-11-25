<?php
/**
 * Release metadata tests.
 *
 * @package Acme_Coming_Soon
 */

use PHPUnit\Framework\TestCase;

class MetadataTest extends TestCase {

	private function file_contents( string $file ): string {
		return (string) file_get_contents( ACME_COMING_SOON_PATH . $file );
	}

	public function test_plugin_and_package_versions_match(): void {
		$main    = $this->file_contents( 'acme-coming-soon.php' );
		$package = json_decode( $this->file_contents( 'package.json' ), true );

		$this->assertMatchesRegularExpression( '/^ \* Version:\h+1\.0\.7$/m', $main );
		$this->assertStringContainsString( "define( 'ACME_COMING_SOON_VERSION', '1.0.7' );", $main );
		$this->assertSame( '1.0.7', $package['version'] );
	}

	public function test_support_headers_are_correct(): void {
		$main   = $this->file_contents( 'acme-coming-soon.php' );
		$readme = $this->file_contents( 'readme.txt' );

		$this->assertMatchesRegularExpression( '/^ \* Requires at least:\h+7\.0$/m', $main );
		$this->assertMatchesRegularExpression( '/^ \* Requires PHP:\h+8\.3$/m', $main );
		$this->assertDoesNotMatchRegularExpression( '/^ \* Tested up to:/m', $main );
		$this->assertMatchesRegularExpression( '/^Requires at least: 7\.0$/m', $readme );
		$this->assertMatchesRegularExpression( '/^Requires PHP: 8\.3$/m', $readme );
		$this->assertMatchesRegularExpression( '/^Tested up to: 7\.1$/m', $readme );
		$this->assertMatchesRegularExpression( '/^Stable tag: 1\.0\.7$/m', $readme );
	}

	public function test_readme_contains_only_current_changelog(): void {
		$readme = $this->file_contents( 'readme.txt' );

		$this->assertSame( 1, preg_match_all( '/^=\h*[0-9]+\.[0-9]+(?:\.[0-9]+)*\h*=\h*$/m', $readme ) );
		$this->assertStringContainsString( '= 1.0.7 =', $readme );
		$this->assertStringContainsString( 'Full changelog: changelog.txt', $readme );
	}
}
