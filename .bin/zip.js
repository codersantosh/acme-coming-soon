/* eslint-disable no-console */
const fs = require( 'fs' );
const path = require( 'path' );
const archiver = require( 'archiver' );

const PROJECT_ROOT = path.resolve( __dirname, '..' );
const PLUGIN_SLUG = 'acme-coming-soon';
const VERSION = require( '../package.json' ).version;
const SOURCE = path.join( PROJECT_ROOT, 'deploy', PLUGIN_SLUG );
const OUTPUT = path.join(
	PROJECT_ROOT,
	'deploy',
	`${ PLUGIN_SLUG }-${ VERSION }.zip`
);
const TEMPORARY_OUTPUT = path.join(
	PROJECT_ROOT,
	'deploy',
	`.${ PLUGIN_SLUG }-${ VERSION }.${ process.pid }.tmp`
);
const CHECKSUM_OUTPUT = `${ OUTPUT }.sha256`;
const EXCLUDED_EXTENSIONS = new Set( [ '.map', '.md', '.markdown', '.pot~' ] );
const EXCLUDED_NAMES = new Set( [
	'js.pot',
	'.ds_store',
	'thumbs.db',
	'composer.lock',
] );

function toPosix( value ) {
	return value.split( path.sep ).join( '/' );
}

function collectFiles( directory, prefix = '' ) {
	const entries = fs
		.readdirSync( directory, { withFileTypes: true } )
		.sort( ( left, right ) => left.name.localeCompare( right.name, 'en' ) );
	const files = [];

	for ( const entry of entries ) {
		const relativePath = prefix
			? `${ prefix }/${ entry.name }`
			: entry.name;
		const absolutePath = path.join( directory, entry.name );
		const extension = path.extname( entry.name ).toLowerCase();

		if (
			entry.name.startsWith( '.' ) ||
			EXCLUDED_NAMES.has( entry.name.toLowerCase() ) ||
			EXCLUDED_EXTENSIONS.has( extension ) ||
			/\.(?:bak|orig|rej|swp|swo|tmp)$/i.test( entry.name )
		) {
			continue;
		}

		if ( entry.isSymbolicLink() ) {
			throw new Error( `Refusing to package symlink: ${ relativePath }` );
		}

		if ( entry.isDirectory() ) {
			files.push( ...collectFiles( absolutePath, relativePath ) );
		} else if ( entry.isFile() ) {
			files.push( relativePath );
		} else {
			throw new Error( `Unsupported file type: ${ relativePath }` );
		}
	}

	return files;
}

function validateFile( relativePath ) {
	if (
		path.isAbsolute( relativePath ) ||
		relativePath.split( '/' ).includes( '..' )
	) {
		throw new Error( `Unsafe archive path: ${ relativePath }` );
	}

	const absolutePath = path.join( SOURCE, relativePath );
	if ( fs.lstatSync( absolutePath ).isSymbolicLink() ) {
		throw new Error( `Refusing to package symlink: ${ relativePath }` );
	}
}

function makeArchive( files ) {
	return new Promise( ( resolve, reject ) => {
		const output = fs.createWriteStream( TEMPORARY_OUTPUT, {
			flags: 'wx',
			mode: 0o644,
		} );
		const archive = new archiver.ZipArchive( {
			zlib: { level: 9 },
			forceZip64: true,
		} );
		let settled = false;

		const fail = ( error ) => {
			if ( settled ) {
				return;
			}
			settled = true;
			archive.abort();
			output.destroy();
			reject( error );
		};

		output.on( 'error', fail );
		archive.on( 'error', fail );
		archive.on( 'warning', fail );
		output.on( 'close', () => {
			if ( ! settled ) {
				settled = true;
				resolve();
			}
		} );

		archive.pipe( output );

		try {
			for ( const relativePath of files ) {
				validateFile( relativePath );
				const absolutePath = path.join( SOURCE, relativePath );
				const data = fs.readFileSync( absolutePath );

				archive.append( data, {
					name: `${ PLUGIN_SLUG }/${ toPosix( relativePath ) }`,
					date: new Date( '1980-01-01T00:00:00.000Z' ),
					mode: 0o100644,
				} );
			}
		} catch ( error ) {
			fail( error );
			return;
		}

		archive.finalize().catch( fail );
	} );
}

async function main() {
	const sourceStats = fs.lstatSync( SOURCE );
	if ( ! sourceStats.isDirectory() || sourceStats.isSymbolicLink() ) {
		throw new Error(
			`Run npm run copy first: ${ SOURCE } is not a directory.`
		);
	}

	const files = collectFiles( SOURCE ).sort( ( left, right ) =>
		toPosix( left ).localeCompare( toPosix( right ), 'en' )
	);
	if ( files.length === 0 ) {
		throw new Error( `No files found in ${ SOURCE }.` );
	}

	fs.mkdirSync( path.dirname( OUTPUT ), { recursive: true } );
	fs.rmSync( TEMPORARY_OUTPUT, { force: true } );

	try {
		await makeArchive( files );
		fs.renameSync( TEMPORARY_OUTPUT, OUTPUT );
		fs.chmodSync( OUTPUT, 0o644 );

		const checksum = require( 'crypto' )
			.createHash( 'sha256' )
			.update( fs.readFileSync( OUTPUT ) )
			.digest( 'hex' );
		const checksumContents = `${ checksum }  ${ path.basename( OUTPUT ) }\n`;

		fs.writeFileSync( `${ CHECKSUM_OUTPUT }.tmp`, checksumContents, {
			mode: 0o644,
		} );
		fs.renameSync( `${ CHECKSUM_OUTPUT }.tmp`, CHECKSUM_OUTPUT );
		console.log(
			`Created ${ path.relative( PROJECT_ROOT, OUTPUT ) } (${ files.length } files)`
		);
		console.log( `SHA256: ${ checksum }` );
	} finally {
		fs.rmSync( TEMPORARY_OUTPUT, { force: true } );
	}
}

main().catch( ( error ) => {
	console.error( `ZIP failed: ${ error.message }` );
	process.exitCode = 1;
} );
