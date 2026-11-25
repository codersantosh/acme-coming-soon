/* eslint-disable no-console */
'use strict';

const fs = require( 'fs' );
const path = require( 'path' );

const BUILD_DIRECTORY = path.join(
	path.resolve( __dirname, '..' ),
	'build',
	'admin'
);

try {
	fs.rmSync( BUILD_DIRECTORY, { recursive: true, force: true } );
	console.log( 'Cleaned build/admin' );
} catch ( error ) {
	console.error( `Clean failed: ${ error.message }` );
	process.exitCode = 1;
}
