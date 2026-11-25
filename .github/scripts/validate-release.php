<?php
/**
 * Validate an Acme Coming Soon WordPress.org release package.
 *
 * Usage: php .github/scripts/validate-release.php VERSION BUNDLE_ROOT [PROJECT_ROOT]
 *
 * @package Acme_Coming_Soon
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This validator must be run from the CLI.\n" );
	exit( 1 );
}

if ( $argc < 3 || $argc > 4 ) {
	fwrite( STDERR, "Usage: validate-release.php VERSION BUNDLE_ROOT [PROJECT_ROOT]\n" );
	exit( 1 );
}

$expected_version   = $argv[1];
$expected_php       = '8.3';
$expected_wordpress = '7.0';
$expected_tested    = '7.1';
$plugin_slug        = 'acme-coming-soon';
$bundle_input       = rtrim( $argv[2], '/\\' );
$project_input      = rtrim( $argv[3] ?? getcwd(), '/\\' );
$bundle             = realpath( $bundle_input );
$project_root       = realpath( $project_input );
$errors             = array();
$checks             = 0;

/**
 * Record a validation check.
 *
 * @param bool   $condition Pass/fail condition.
 * @param string $message   Failure message.
 * @param array  $errors    Error collector.
 * @param int    $checks    Check counter.
 */
function acs_release_check( $condition, $message, array &$errors, &$checks ) {
	++$checks;
	if ( ! $condition ) {
		$errors[] = $message;
	}
}

/**
 * Read a file when it exists.
 *
 * @param string $path File path.
 * @return string|false
 */
function acs_release_read( $path ) {
	return is_file( $path ) ? file_get_contents( $path ) : false;
}

/**
 * Read a colon-delimited metadata field.
 *
 * @param string $contents File contents.
 * @param string $field    Field name.
 * @return string
 */
function acs_release_field( $contents, $field ) {
	if ( preg_match( '/^[\s\/*#@]*' . preg_quote( $field, '/' ) . ':\h*(.+?)\h*$/mi', $contents, $matches ) ) {
		return trim( $matches[1] );
	}
	return '';
}

/**
 * Extract one version entry from a WordPress-style changelog.
 *
 * @param string $contents File contents.
 * @param string $version  Release version.
 * @return string
 */
function acs_release_changelog_entry( $contents, $version ) {
	$contents = str_replace( array( "\r\n", "\r" ), "\n", $contents );
	$pattern  = '/^=\h*' . preg_quote( $version, '/' ) . '\h*=\h*\n(.*?)(?=^=\h*[0-9]+\.[0-9]+(?:\.[0-9]+)*\h*=\h*$|\z)/ms';
	return preg_match( $pattern, $contents, $matches ) ? trim( $matches[1] ) : '';
}

acs_release_check(
	(bool) preg_match( '/^[0-9]+\.[0-9]+(?:\.[0-9]+)*$/', $expected_version ),
	"Invalid release version '$expected_version'.",
	$errors,
	$checks
);
acs_release_check( false !== $bundle, "Bundle directory does not exist: $bundle_input", $errors, $checks );
acs_release_check( false !== $project_root, "Project root does not exist: $project_input", $errors, $checks );

if ( false === $bundle || false === $project_root ) {
	foreach ( $errors as $error ) {
		fwrite( STDERR, "ERROR: $error\n" );
	}
	exit( 1 );
}

$required_files = array(
	'acme-coming-soon.php',
	'readme.txt',
	'changelog.txt',
	'LICENSE',
	'uninstall.php',
	'includes/json/patterns.json',
	'languages/acme-coming-soon.pot',
	'assets/library/atomic-css/atomic.min.css',
	'assets/library/fonts/open-sans.css',
	'assets/img/logo-20-20.png',
	'assets/img/logo.png',
	'assets/img/featured-image.png',
	'build/admin/admin.js',
	'build/admin/admin.css',
	'build/admin/admin-rtl.css',
	'build/admin/admin.asset.php',
);

foreach ( $required_files as $file ) {
	acs_release_check( is_file( "$bundle/$file" ), "Missing required runtime file: $file", $errors, $checks );
}

$main     = acs_release_read( "$bundle/acme-coming-soon.php" );
$readme   = acs_release_read( "$bundle/readme.txt" );
$changelog = acs_release_read( "$bundle/changelog.txt" );

if ( false === $main || false === $readme || false === $changelog ) {
	$errors[] = 'Could not read acme-coming-soon.php, readme.txt, or changelog.txt.';
} else {
	$readme   = str_replace( array( "\r\n", "\r" ), "\n", $readme );
	$changelog = str_replace( array( "\r\n", "\r" ), "\n", $changelog );

	$required_headers = array(
		'Plugin Name',
		'Version',
		'Requires at least',
		'Requires PHP',
		'License',
		'License URI',
		'Text Domain',
		'Domain Path',
	);

	foreach ( $required_headers as $header ) {
		acs_release_check( '' !== acs_release_field( $main, $header ), "Missing plugin header: $header", $errors, $checks );
	}

	acs_release_check(
		'' === acs_release_field( $main, 'Tested up to' ),
		'Tested up to must not be declared in the plugin header; keep it in readme.txt only.',
		$errors,
		$checks
	);
	acs_release_check(
		false !== stripos( acs_release_field( $main, 'Plugin Name' ), 'Acme Coming Soon' ),
		'Plugin Name must identify Acme Coming Soon.',
		$errors,
		$checks
	);
	acs_release_check(
		$expected_version === acs_release_field( $main, 'Version' ),
		"Plugin header Version must be $expected_version.",
		$errors,
		$checks
	);

	$constant_pattern = '/define\(\s*[\'"]ACME_COMING_SOON_VERSION[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/';
	$constant_version = preg_match( $constant_pattern, $main, $constant_matches ) ? $constant_matches[1] : '';

	acs_release_check(
		$expected_version === $constant_version,
		"ACME_COMING_SOON_VERSION must be $expected_version.",
		$errors,
		$checks
	);
	acs_release_check(
		$plugin_slug === acs_release_field( $main, 'Text Domain' ),
		"Plugin Text Domain must be $plugin_slug.",
		$errors,
		$checks
	);
	acs_release_check(
		'/languages' === acs_release_field( $main, 'Domain Path' ),
		'Plugin Domain Path must be /languages.',
		$errors,
		$checks
	);

	$required_readme_fields = array(
		'Contributors',
		'Tags',
		'Requires at least',
		'Tested up to',
		'Requires PHP',
		'Stable tag',
		'License',
		'License URI',
	);

	foreach ( $required_readme_fields as $field ) {
		acs_release_check( '' !== acs_release_field( $readme, $field ), "Missing readme.txt field: $field", $errors, $checks );
	}

	acs_release_check(
		$expected_version === acs_release_field( $readme, 'Stable tag' ),
		"readme.txt Stable tag must be $expected_version.",
		$errors,
		$checks
	);

	foreach ( array( 'Requires at least', 'Requires PHP' ) as $field ) {
		acs_release_check(
			acs_release_field( $main, $field ) === acs_release_field( $readme, $field ),
			"Plugin header and readme.txt disagree for '$field'.",
			$errors,
			$checks
		);
	}

	acs_release_check(
		$expected_php === acs_release_field( $main, 'Requires PHP' ),
		"Plugin header Requires PHP must be $expected_php.",
		$errors,
		$checks
	);
	acs_release_check(
		$expected_php === acs_release_field( $readme, 'Requires PHP' ),
		"readme.txt Requires PHP must be $expected_php.",
		$errors,
		$checks
	);
	acs_release_check(
		$expected_wordpress === acs_release_field( $main, 'Requires at least' ),
		"Plugin header Requires at least must be $expected_wordpress.",
		$errors,
		$checks
	);
	acs_release_check(
		$expected_wordpress === acs_release_field( $readme, 'Requires at least' ),
		"readme.txt Requires at least must be $expected_wordpress.",
		$errors,
		$checks
	);
	acs_release_check(
		$expected_tested === acs_release_field( $readme, 'Tested up to' ),
		"readme.txt Tested up to must be $expected_tested.",
		$errors,
		$checks
	);
	acs_release_check(
		stripos( acs_release_field( $main, 'License URI' ), 'gnu.org/licenses/gpl-2.0' ) !== false
		&& stripos( acs_release_field( $readme, 'License URI' ), 'gnu.org/licenses/gpl-2.0' ) !== false,
		'Plugin header and readme.txt must link to the GPLv2 license.',
		$errors,
		$checks
	);

	$tags = array_filter( array_map( 'trim', explode( ',', acs_release_field( $readme, 'Tags' ) ) ) );
	acs_release_check( count( $tags ) <= 5, 'readme.txt must contain no more than five tags.', $errors, $checks );
	acs_release_check(
		(bool) preg_match( '/^==\h*Changelog\h*==\h*$/mi', $readme ),
		'readme.txt is missing the Changelog section.',
		$errors,
		$checks
	);
	acs_release_check(
		(bool) preg_match( '/^=\h*' . preg_quote( $expected_version, '/' ) . '\h*=\h*$/m', $readme ),
		"readme.txt is missing changelog heading '= $expected_version ='.",
		$errors,
		$checks
	);
	acs_release_check(
		1 === preg_match_all( '/^=\h*[0-9]+\.[0-9]+(?:\.[0-9]+)*\h*=\h*$/m', $readme ),
		'readme.txt must contain only the current release changelog; move older entries to changelog.txt.',
		$errors,
		$checks
	);
	acs_release_check(
		(bool) preg_match( '/^==\h*Changelog\h*==\h*$/mi', $changelog )
		&& (bool) preg_match( '/^=\h*' . preg_quote( $expected_version, '/' ) . '\h*=\h*$/m', $changelog ),
		"changelog.txt must contain the Changelog section and '= $expected_version =' heading.",
		$errors,
		$checks
	);
	$first_changelog_version = preg_match( '/^=\h*([0-9]+\.[0-9]+(?:\.[0-9]+)*)\h*=\h*$/m', $changelog, $changelog_version_matches ) ? $changelog_version_matches[1] : '';
	acs_release_check(
		$expected_version === $first_changelog_version,
		"The first changelog.txt release must be $expected_version.",
		$errors,
		$checks
	);
	acs_release_check(
		strpos( $readme, 'Full changelog: changelog.txt' ) !== false,
		'readme.txt must link to changelog.txt.',
		$errors,
		$checks
	);
	$readme_entry    = acs_release_changelog_entry( $readme, $expected_version );
	$changelog_entry = acs_release_changelog_entry( $changelog, $expected_version );
	acs_release_check(
		'' !== $readme_entry && $readme_entry === $changelog_entry,
		"The $expected_version changelog entry in readme.txt and changelog.txt must match.",
		$errors,
		$checks
	);
	acs_release_check(
		stripos( acs_release_field( $readme, 'License' ), 'GPL' ) !== false,
		'readme.txt must declare a GPL-compatible license.',
		$errors,
		$checks
	);
	acs_release_check(
		strpos( $readme, 'https://github.com/codersantosh/acme-coming-soon' ) !== false,
		'readme.txt must link to the public source repository.',
		$errors,
		$checks
	);

	foreach ( array( 'acme-coming-soon.php', 'readme.txt', 'changelog.txt', 'LICENSE', 'uninstall.php' ) as $file ) {
		$source_file = "$project_root/$file";
		$same        = is_file( $source_file )
			&& is_file( "$bundle/$file" )
			&& hash_file( 'sha256', $source_file ) === hash_file( 'sha256', "$bundle/$file" );

		acs_release_check( $same, "Packaged $file differs from the reviewed source file.", $errors, $checks );
	}
}

$package      = json_decode( (string) acs_release_read( "$project_root/package.json" ), true );
$package_lock = json_decode( (string) acs_release_read( "$project_root/package-lock.json" ), true );

acs_release_check( is_array( $package ), 'package.json could not be parsed.', $errors, $checks );
acs_release_check(
	is_array( $package ) && $plugin_slug === ( $package['name'] ?? '' ),
	"package.json name must be $plugin_slug.",
	$errors,
	$checks
);
acs_release_check(
	is_array( $package ) && $expected_version === ( $package['version'] ?? '' ),
	"package.json version must be $expected_version.",
	$errors,
	$checks
);
acs_release_check(
	is_array( $package_lock )
	&& $plugin_slug === ( $package_lock['name'] ?? '' )
	&& $expected_version === ( $package_lock['version'] ?? '' ),
	'package-lock.json root name and version must match package.json.',
	$errors,
	$checks
);
acs_release_check(
	is_array( $package_lock )
	&& $plugin_slug === ( $package_lock['packages']['']['name'] ?? '' )
	&& $expected_version === ( $package_lock['packages']['']['version'] ?? '' ),
	'package-lock.json packages[""] name and version must match package.json.',
	$errors,
	$checks
);
acs_release_check(
	is_array( $package ) && false !== strpos( (string) ( $package['scripts']['deploy'] ?? '' ), 'zip' ),
	'package.json deploy script must create the release ZIP.',
	$errors,
	$checks
);

$composer = json_decode( (string) acs_release_read( "$project_root/composer.json" ), true );

acs_release_check( is_array( $composer ), 'composer.json could not be parsed.', $errors, $checks );
acs_release_check(
	is_array( $composer ) && (bool) preg_match( '#^[a-z0-9._-]+/acme-coming-soon$#', (string) ( $composer['name'] ?? '' ) ),
	'composer.json name must use the acme-coming-soon package basename.',
	$errors,
	$checks
);
acs_release_check(
	is_array( $composer ) && '^8.3' === ( $composer['require']['php'] ?? '' ),
	'composer.json must require PHP ^8.3.',
	$errors,
	$checks
);
acs_release_check(
	is_array( $composer ) && '8.3.0' === ( $composer['config']['platform']['php'] ?? '' ),
	'composer.json must set config.platform.php to 8.3.0.',
	$errors,
	$checks
);

$composer_lock = json_decode( (string) acs_release_read( "$project_root/composer.lock" ), true );
acs_release_check(
	is_array( $composer_lock ) && ! empty( $composer_lock['packages-dev'] ) && isset( $composer_lock['content-hash'] ),
	'composer.lock must be present and contain locked development dependencies.',
	$errors,
	$checks
);

$forbidden_directories = array(
	'.git',
	'.svn',
	'.github',
	'.wordpress-org',
	'.bin',
	'.idea',
	'.vscode',
	'node_modules',
	'vendor',
	'src',
	'tests',
	'docs',
	'deploy',
);
$forbidden_files       = array(
	'.gitignore',
	'.gitattributes',
	'.distignore',
	'.babelrc',
	'.env',
	'.env.example',
	'.DS_Store',
	'Thumbs.db',
	'.phpunit.result.cache',
	'composer.json',
	'composer.lock',
	'package.json',
	'package-lock.json',
	'phpcs.xml.dist',
	'phpunit.xml.dist',
	'readme.md',
	'js.pot',
	'translation-js.php',
	'artifact-manifest.json',
);
$forbidden_extensions  = array( 'zip', 'tar', 'gz', 'bz2', 'xz', '7z', 'rar', 'map', 'md', 'sh', 'py', 'yml', 'yaml', 'sha256' );
$bundle_realpath = $bundle;
$directories     = array( $bundle );

while ( array() !== $directories ) {
	$directory = array_pop( $directories );
	$entries   = new FilesystemIterator( $directory, FilesystemIterator::SKIP_DOTS );

	foreach ( $entries as $file ) {
		/** @var SplFileInfo $file */
		$relative  = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $bundle ) + 1 ) );
		$basename  = $file->getBasename();
		$extension = strtolower( $file->getExtension() );
		$is_link   = $file->isLink();

		acs_release_check( ! $is_link, "Symlinks are not allowed in the package: $relative", $errors, $checks );
		acs_release_check(
			$file->isFile() || $file->isDir(),
			"Unsupported file type in package: $relative",
			$errors,
			$checks
		);

		if ( $file->isDir() ) {
			acs_release_check(
				! in_array( $basename, $forbidden_directories, true ),
				"Development directory found in package: $relative",
				$errors,
				$checks
			);
			if ( ! $is_link && ! in_array( $basename, $forbidden_directories, true ) ) {
				$directories[] = $file->getPathname();
			}
			continue;
		}

		if ( ! $file->isFile() ) {
			continue;
		}

		acs_release_check( ! in_array( $basename, $forbidden_files, true ), "Development file found in package: $relative", $errors, $checks );
		acs_release_check( ! in_array( $extension, $forbidden_extensions, true ), "Forbidden file type found in package: $relative", $errors, $checks );

		if ( $is_link ) {
			continue;
		}

		if ( in_array( $extension, array( 'js', 'css', 'php', 'pot', 'json', 'txt' ), true ) ) {
			$contents = (string) file_get_contents( $file->getPathname() );
			foreach ( array( 'atrc-text-domain-atrc', 'atrc_prefix_atrc', 'atrc-prefix-atrc', 'ATRC_PREFIX_ATRC', 'ATRC-PREFIX-ATRC', 'Atrc-Prefix-Atrc', 'Atrc_Prefix_Atrc' ) as $placeholder ) {
				acs_release_check(
					false === stripos( $contents, $placeholder ),
					"Unreplaced ATRC placeholder '$placeholder' in $relative.",
					$errors,
					$checks
				);
			}
			acs_release_check(
				false === stripos( $contents, 'patterns-store' ),
				"Foreign patterns-store text domain found in $relative.",
				$errors,
				$checks
			);
		}

		if ( 'js' === $extension ) {
			$contents = (string) file_get_contents( $file->getPathname() );
			acs_release_check(
				0 === preg_match( '/\bconsole\s*\.\s*log\s*\(/i', $contents ),
				"Production console.log found in $relative.",
				$errors,
				$checks
			);
		}

		if ( 'php' === $extension ) {
			$contents = (string) file_get_contents( $file->getPathname() );

			if ( preg_match_all( '/(?:ACME_COMING_SOON_PATH|ACME_COMING_SOON_URL)\s*\.\s*[\'"]([^\'"]+)[\'"]/', $contents, $matches ) ) {
				foreach ( $matches[1] as $reference ) {
					$target = realpath( "$bundle/$reference" );
					acs_release_check(
						false !== $target && 0 === strpos( $target, $bundle_realpath . DIRECTORY_SEPARATOR ),
						"Missing runtime reference '$reference' from $relative.",
						$errors,
						$checks
					);
				}
			}
		}

		if ( 'css' === $extension && preg_match_all( '/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', (string) file_get_contents( $file->getPathname() ), $matches ) ) {
			foreach ( $matches[1] as $reference ) {
				$reference = preg_replace( '/[?#].*$/', '', trim( $reference ) );
				if ( '' === $reference || preg_match( '/^(?:data:|https?:|\/\/|#|\/)/i', $reference ) ) {
					continue;
				}

				$reference = preg_replace( '#/+#', '/', $reference );
				$target    = realpath( dirname( $file->getPathname() ) . '/' . $reference );
				acs_release_check(
					false !== $target && 0 === strpos( $target, $bundle_realpath . DIRECTORY_SEPARATOR ),
					"Missing CSS asset '$reference' from $relative.",
					$errors,
					$checks
				);
			}
		}
	}
}

if ( $errors ) {
	foreach ( $errors as $error ) {
		fwrite( STDERR, "ERROR: $error\n" );
	}
	fwrite( STDERR, sprintf( "\nRelease validation failed with %d error(s).\n", count( $errors ) ) );
	exit( 1 );
}

printf( "Release validation passed for Acme Coming Soon %s (%d checks).\n", $expected_version, $checks );
