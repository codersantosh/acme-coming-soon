/* eslint-disable no-console */
const fs = require( 'fs' );
const path = require( 'path' );
const gettextParser = require( 'gettext-parser' );

const PROJECT_ROOT = path.resolve( __dirname, '..' );

/*
 * Stable default for POT-Creation-Date (the last 1.0.x release commit).
 * Deriving the date from the current HEAD made every commit rewrite the
 * committed POT files, so builds could never leave a clean working tree.
 * Set SOURCE_DATE_EPOCH to override it for reproducible release builds.
 */
const DEFAULT_SOURCE_DATE_EPOCH = 1744911780;
const POT_FILES = [
	path.join( PROJECT_ROOT, 'languages', 'acme-coming-soon.pot' ),
	path.join( PROJECT_ROOT, 'languages', 'js.pot' ),
];

function getSourceDateEpoch() {
	const configuredEpoch = process.env.SOURCE_DATE_EPOCH;
	if ( configuredEpoch !== undefined ) {
		if ( ! /^\d+$/.test( configuredEpoch ) ) {
			throw new Error(
				'SOURCE_DATE_EPOCH must be a non-negative integer.'
			);
		}

		const epoch = Number( configuredEpoch );
		if ( ! Number.isSafeInteger( epoch ) || epoch < 0 ) {
			throw new Error(
				'SOURCE_DATE_EPOCH is outside the supported range.'
			);
		}
		return epoch;
	}

	return DEFAULT_SOURCE_DATE_EPOCH;
}

function normalizePot( filePath, epoch ) {
	if ( ! fs.existsSync( filePath ) ) {
		throw new Error( `Missing POT file: ${ filePath }` );
	}

	const source = fs.readFileSync( filePath );
	const parsed = gettextParser.po.parse( source );
	const headers = parsed.headers || {};
	const creationDate = new Date( epoch * 1000 )
		.toISOString()
		.replace( /\.000Z$/, '+0000' );

	for ( const key of Object.keys( headers ) ) {
		if ( key.toLowerCase() === 'pot-creation-date' ) {
			delete headers[ key ];
		}
	}
	headers[ 'POT-Creation-Date' ] = creationDate;

	const normalized = gettextParser.po.compile( parsed, {
		foldLength: 0,
		sort: true,
	} );
	fs.writeFileSync( filePath, normalized );
	console.log(
		`Normalized ${ path.relative( PROJECT_ROOT, filePath ) } to ${ creationDate }`
	);
}

try {
	const epoch = getSourceDateEpoch();
	for ( const filePath of POT_FILES ) {
		normalizePot( filePath, epoch );
	}
} catch ( error ) {
	console.error( `POT normalization failed: ${ error.message }` );
	process.exitCode = 1;
}
