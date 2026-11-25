/* eslint-disable no-console */
const fs = require( 'fs' );
const path = require( 'path' );
const replace = require( 'replace-in-file' );

const PROJECT_ROOT = path.resolve( __dirname, '..' );
const BUILD_DIRECTORY = path.join( PROJECT_ROOT, 'build' );
const REPLACEMENTS = new Map( [
	[ 'atrc-text-domain-atrc', 'acme-coming-soon' ],
	[ 'atrc_prefix_atrc', 'acme_coming_soon' ],
	[ 'atrc-prefix-atrc', 'acme-coming-soon' ],
	[ 'ATRC_PREFIX_ATRC', 'ACME_COMING_SOON' ],
	[ 'ATRC-PREFIX-ATRC', 'ACME-COMING-SOON' ],
	[ 'Atrc-Prefix-Atrc', 'Acme-Coming-Soon' ],
	[ 'Atrc_Prefix_Atrc', 'Acme_Coming_Soon' ],
	[ 'atrc_text_domain_atrc', 'acme-coming-soon' ],
	[ 'atrc_prefix', 'acme_coming_soon' ],
	[ 'ATRC_TEXT_DOMAIN', 'ACME_COMING_SOON' ],
	[ 'ATRC_PREFIX', 'ACME_COMING_SOON' ],
] );
const LEFTOVER_PATTERN =
	/atrc[-_](?:text[-_]domain|prefix)|ATRC[-_](?:TEXT[-_]DOMAIN|PREFIX)/i;
const BAD_DOMAIN_PATTERN =
	/(["'])(?:acme[-_]coming[-_]soon[-_]atrc|acme[-_]coming[-_]soon[-_]patterns[-_]store)\1/i;
const BAD_PREFIX_PATTERN =
	/(["'])(?:acme[-_]coming[-_]soon[-_]prefix|prefix[-_]acme[-_]coming[-_]soon)\1/i;

function collectBuildFiles( directory ) {
	const files = [];
	for ( const entry of fs.readdirSync( directory, {
		withFileTypes: true,
	} ) ) {
		const absolutePath = path.join( directory, entry.name );
		if ( entry.isSymbolicLink() ) {
			throw new Error(
				`Refusing to replace text in symlink: ${ absolutePath }`
			);
		}
		if ( entry.isDirectory() ) {
			files.push( ...collectBuildFiles( absolutePath ) );
		} else if ( entry.isFile() && /\.(?:js|css)$/.test( entry.name ) ) {
			files.push( absolutePath );
		}
	}
	return files;
}

async function main() {
	if ( ! fs.existsSync( BUILD_DIRECTORY ) ) {
		throw new Error(
			'Build directory is missing. Run npm run build first.'
		);
	}

	const files = collectBuildFiles( BUILD_DIRECTORY ).sort();
	if ( files.length === 0 ) {
		throw new Error( 'No JavaScript or CSS build files were found.' );
	}

	const results = await replace( {
		files,
		from: [ ...REPLACEMENTS.keys() ].map(
			( token ) => new RegExp( token, 'g' )
		),
		to: [ ...REPLACEMENTS.values() ],
	} );

	// Bundled library diagnostics must never use console.log in production.
	const consoleResults = await replace( {
		files: files.filter( ( filePath ) => filePath.endsWith( '.js' ) ),
		from: /\bconsole\s*\.\s*log\s*\(/g,
		to: 'console.error(',
	} );

	const invalid = [];
	for ( const filePath of files ) {
		const contents = fs.readFileSync( filePath, 'utf8' );
		if ( LEFTOVER_PATTERN.test( contents ) ) {
			invalid.push(
				`${ path.relative( PROJECT_ROOT, filePath ) }: ATRC placeholder`
			);
		}
		if ( BAD_DOMAIN_PATTERN.test( contents ) ) {
			invalid.push(
				`${ path.relative( PROJECT_ROOT, filePath ) }: invalid text domain`
			);
		}
		if ( BAD_PREFIX_PATTERN.test( contents ) ) {
			invalid.push(
				`${ path.relative( PROJECT_ROOT, filePath ) }: invalid prefix`
			);
		}
	}

	if ( invalid.length ) {
		throw new Error(
			`Invalid build replacements: ${ invalid.join( ', ' ) }`
		);
	}

	console.log(
		`Replaced ATRC placeholders in ${ results.length } build files and normalized ${
			consoleResults.length
		} console.log calls.`
	);
}

main().catch( ( error ) => {
	console.error( `Replacement failed: ${ error.message }` );
	process.exitCode = 1;
} );
