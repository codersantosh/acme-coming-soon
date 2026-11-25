/* eslint-disable no-console */
const fs = require( 'fs' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );
const gettextParser = require( 'gettext-parser' );

const PROJECT_ROOT = path.resolve( __dirname, '..' );
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

	const commitTimestamp = execFileSync(
		'git',
		[ 'log', '-1', '--format=%ct' ],
		{
			cwd: PROJECT_ROOT,
			encoding: 'utf8',
			stdio: [ 'ignore', 'pipe', 'pipe' ],
		}
	).trim();

	if ( ! /^\d+$/.test( commitTimestamp ) ) {
		throw new Error(
			'Could not determine the latest git commit timestamp.'
		);
	}

	return Number( commitTimestamp );
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
