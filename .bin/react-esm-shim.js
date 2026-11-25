'use strict';

const path = require( 'path' );

const PROJECT_ROOT = path.resolve( __dirname, '..' );
const ATRC_ROOT = path.join(
	path.dirname( require.resolve( 'atrc/package.json' ) ),
	'build'
);
const REACT_ESM_SHIMS = {
	react: `
import * as __acmeReactNamespace from 'react';
const React = __acmeReactNamespace.default && typeof __acmeReactNamespace.default.createElement === 'function'
	? __acmeReactNamespace.default
	: __acmeReactNamespace;
export default React;
export const {
	Children, Component, Fragment, Profiler, PureComponent, StrictMode, Suspense,
	cloneElement, createContext, createElement, createRef, forwardRef, isValidElement,
	lazy, memo, startTransition, use, useActionState, useCallback, useContext,
	useDebugValue, useDeferredValue, useEffect, useId, useImperativeHandle,
	useInsertionEffect, useLayoutEffect, useMemo, useOptimistic, useReducer,
	useRef, useState, useSyncExternalStore, useTransition, version, cache,
	unstable_Activity: Activity, unstable_ViewTransition: ViewTransition,
} = React;
`,
	'react-dom': `
import * as __acmeReactDomNamespace from 'react-dom';
const ReactDOM = __acmeReactDomNamespace.default && typeof __acmeReactDomNamespace.default.createPortal === 'function'
	? __acmeReactDomNamespace.default
	: __acmeReactDomNamespace;
export default ReactDOM;
export const {
	createPortal, flushSync, preconnect, prefetchDNS, preinit, preinitModule,
	preload, preloadModule, requestFormReset, unstable_batchedUpdates: batchedUpdates,
	version, createRoot, hydrateRoot,
} = ReactDOM;
`,
	'react-dom/client': `
import { createRoot, hydrateRoot } from 'react-dom/client';
export { createRoot, hydrateRoot };
export default { createRoot, hydrateRoot };
`,
};

class ReactEsmShimPlugin {
	constructor() {
		this.pluginName = 'ReactEsmShimPlugin';
	}

	apply( compiler ) {
		compiler.hooks.normalModuleFactory.tap(
			this.pluginName,
			( factory ) => {
				factory.hooks.beforeResolve.tap( this.pluginName, ( data ) => {
					const request = data.request;
					if (
						! request ||
						! data.context ||
						! data.context.startsWith( ATRC_ROOT + path.sep )
					) {
						return;
					}

					let shimRequest;
					if ( request === 'react' ) {
						shimRequest = 'react';
					} else if ( request === 'react-dom' ) {
						shimRequest = 'react-dom';
					} else if ( request === 'react-dom/client' ) {
						shimRequest = 'react-dom/client';
					}
					if ( ! shimRequest ) {
						return;
					}

					const shimPath = path.join(
						PROJECT_ROOT,
						'node_modules',
						'.cache',
						'acme-react-shims',
						shimRequest === 'react-dom/client'
							? 'react-dom-client.js'
							: `${ shimRequest }.js`
					);
					data.request = shimPath;
				} );
			}
		);
	}
}

module.exports = ReactEsmShimPlugin;
module.exports.REACT_ESM_SHIMS = REACT_ESM_SHIMS;
module.exports.PROJECT_ROOT = PROJECT_ROOT;
