/* eslint-disable no-console */
'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const ReactEsmShimPlugin = require( './react-esm-shim' );

for ( const [ request, source ] of Object.entries(
	ReactEsmShimPlugin.REACT_ESM_SHIMS
) ) {
	const relativePath =
		request === 'react-dom/client'
			? 'react-dom-client.js'
			: `${ request }.js`;
	const filePath = path.join(
		ReactEsmShimPlugin.PROJECT_ROOT,
		'node_modules',
		'.cache',
		'acme-react-shims',
		relativePath
	);
	fs.mkdirSync( path.dirname( filePath ), { recursive: true } );
	fs.writeFileSync( filePath, source );
	console.log( `Prepared React ESM shim: ${ request }` );
}
