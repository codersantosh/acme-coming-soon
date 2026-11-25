/* eslint-disable no-console */
const fs = require( 'fs' );
const path = require( 'path' );

const PROJECT_ROOT = path.resolve( __dirname, '..' );
const DESTINATION = path.join( PROJECT_ROOT, 'deploy', 'acme-coming-soon' );
const TEMPORARY_DIRECTORY = path.join(
	PROJECT_ROOT,
	'deploy',
	`.acme-coming-soon.${ process.pid }.tmp`
);
const REQUIRED_FILES = [
	'acme-coming-soon.php',
	'readme.txt',
	'changelog.txt',
	'LICENSE',
	'uninstall.php',
];
const REQUIRED_DIRECTORIES = [
	'admin',
	'assets',
	'build',
	'includes',
	'languages',
	'public',
];
const EXCLUDED_EXTENSIONS = new Set( [ '.map', '.md', '.markdown' ] );
const EXCLUDED_NAMES = new Set( [
	'.ds_store',
	'thumbs.db',
	'composer.json',
	'composer.lock',
	'package.json',
	'package-lock.json',
] );
const EXCLUDED_PATTERNS = [
	/(?:^|\/)node_modules(?:\/|$)/,
	/(?:^|\/)vendor(?:\/|$)/,
	/(?:^|\/)tests?(?:\/|$)/,
	/(?:^|\/)\.github(?:\/|$)/,
	/(?:^|\/)\.git(?:\/|$)/,
	/(?:^|\/)\.bin(?:\/|$)/,
	/(?:^|\/)src(?:\/|$)/,
	/\.(?:bak|orig|rej|swp|swo|tmp)$/i,
];

function toPosix( value ) {
	return value.split( path.sep ).join( '/' );
}

function isExcluded( relativePath ) {
	const extension = path.extname( relativePath ).toLowerCase();
	const basename = path.basename( relativePath ).toLowerCase();

	return (
		relativePath.split( '/' ).some( ( part ) => part.startsWith( '.' ) ) ||
		EXCLUDED_EXTENSIONS.has( extension ) ||
		basename === 'js.pot' ||
		EXCLUDED_NAMES.has( basename ) ||
		EXCLUDED_PATTERNS.some( ( pattern ) =>
			pattern.test( toPosix( relativePath ) )
		)
	);
}

function collectRuntimeFiles( root ) {
	const files = [];

	const visit = ( absoluteDirectory, relativeDirectory = '' ) => {
		const entries = fs
			.readdirSync( absoluteDirectory, { withFileTypes: true } )
			.sort( ( left, right ) =>
				left.name.localeCompare( right.name, 'en' )
			);

		for ( const entry of entries ) {
			const relativePath = relativeDirectory
				? `${ relativeDirectory }/${ entry.name }`
				: entry.name;
			const absolutePath = path.join( absoluteDirectory, entry.name );

			if ( isExcluded( relativePath ) ) {
				continue;
			}

			if ( entry.isSymbolicLink() ) {
				throw new Error(
					`Refusing to copy symlink: ${ relativePath }`
				);
			}

			if ( entry.isDirectory() ) {
				visit( absolutePath, relativePath );
			} else if ( entry.isFile() ) {
				files.push( relativePath );
			} else {
				throw new Error( `Unsupported file type: ${ relativePath }` );
			}
		}
	};

	visit( root );
	return files.sort( ( left, right ) =>
		toPosix( left ).localeCompare( toPosix( right ), 'en' )
	);
}

function validateRequiredRuntimeFiles() {
	const missing = [];

	for ( const relativePath of REQUIRED_FILES ) {
		const absolutePath = path.join( PROJECT_ROOT, relativePath );
		if ( ! fs.existsSync( absolutePath ) ) {
			missing.push( relativePath );
		} else if ( ! fs.lstatSync( absolutePath ).isFile() ) {
			throw new Error(
				`Required runtime file is not a file: ${ relativePath }`
			);
		}
	}

	for ( const relativePath of REQUIRED_DIRECTORIES ) {
		const absolutePath = path.join( PROJECT_ROOT, relativePath );
		if ( ! fs.existsSync( absolutePath ) ) {
			missing.push( `${ relativePath }/` );
		} else if ( ! fs.lstatSync( absolutePath ).isDirectory() ) {
			throw new Error(
				`Required runtime directory is not a directory: ${ relativePath }`
			);
		}
	}

	if ( missing.length ) {
		throw new Error(
			`Missing required runtime files: ${ missing.join( ', ' ) }. Run the build/POT steps first.`
		);
	}
}

function copyFile( relativePath ) {
	const source = path.join( PROJECT_ROOT, relativePath );
	const destination = path.join( TEMPORARY_DIRECTORY, relativePath );

	if ( fs.lstatSync( source ).isSymbolicLink() ) {
		throw new Error( `Refusing to copy symlink: ${ relativePath }` );
	}

	fs.mkdirSync( path.dirname( destination ), { recursive: true } );
	fs.copyFileSync( source, destination );
	fs.chmodSync( destination, 0o644 );
}

function main() {
	validateRequiredRuntimeFiles();

	const files = [
		...REQUIRED_FILES,
		...collectRuntimeFiles( path.join( PROJECT_ROOT, 'admin' ) ).map(
			( file ) => `admin/${ file }`
		),
		...collectRuntimeFiles( path.join( PROJECT_ROOT, 'assets' ) ).map(
			( file ) => `assets/${ file }`
		),
		...collectRuntimeFiles( path.join( PROJECT_ROOT, 'build' ) ).map(
			( file ) => `build/${ file }`
		),
		...collectRuntimeFiles( path.join( PROJECT_ROOT, 'includes' ) ).map(
			( file ) => `includes/${ file }`
		),
		...collectRuntimeFiles( path.join( PROJECT_ROOT, 'languages' ) ).map(
			( file ) => `languages/${ file }`
		),
		...collectRuntimeFiles( path.join( PROJECT_ROOT, 'public' ) ).map(
			( file ) => `public/${ file }`
		),
	].sort( ( left, right ) =>
		toPosix( left ).localeCompare( toPosix( right ), 'en' )
	);

	fs.rmSync( TEMPORARY_DIRECTORY, { recursive: true, force: true } );
	fs.mkdirSync( TEMPORARY_DIRECTORY, { recursive: true, mode: 0o755 } );

	try {
		for ( const relativePath of files ) {
			copyFile( relativePath );
		}

		const expected = new Set( files );
		const staged = collectRuntimeFiles( TEMPORARY_DIRECTORY );
		if (
			staged.length !== expected.size ||
			staged.some( ( file ) => ! expected.has( file ) )
		) {
			throw new Error(
				'The staged runtime allowlist does not match the source.'
			);
		}

		fs.rmSync( DESTINATION, { recursive: true, force: true } );
		fs.renameSync( TEMPORARY_DIRECTORY, DESTINATION );
		console.log(
			`Staged ${ files.length } runtime files in deploy/acme-coming-soon`
		);
	} finally {
		fs.rmSync( TEMPORARY_DIRECTORY, { recursive: true, force: true } );
	}
}

try {
	main();
} catch ( error ) {
	console.error( `Copy failed: ${ error.message }` );
	process.exitCode = 1;
}
