const path = require( 'path' );
const {
	defaultRequestToExternal,
	defaultRequestToHandle,
} = require( '@wordpress/dependency-extraction-webpack-plugin/lib/util' );

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
const REPLACEMENT_EXPRESSION = new RegExp(
	[ ...REPLACEMENTS.keys() ].join( '|' ),
	'g'
);
const LEFTOVER_EXPRESSION =
	/atrc[-_](?:text[-_]domain|prefix)|ATRC[-_](?:TEXT[-_]DOMAIN|PREFIX)/i;
const CACHE_TARGET = 'acme-atrc-react19-compat';
const CACHE_KEY = 'acme-atrc-react19-compat-v3';

function resolveReactDOM() {
	return require.resolve( 'react-dom' );
}

function resolveReactDOMClient() {
	return require.resolve( 'react-dom/client' );
}

function replaceAtrcPlaceholders( source ) {
	const result = source.replace( REPLACEMENT_EXPRESSION, ( token ) =>
		REPLACEMENTS.get( token )
	);

	if ( LEFTOVER_EXPRESSION.test( result ) ) {
		throw new Error(
			'Unreplaced ATRC placeholder found while processing source.'
		);
	}

	return result;
}

const LEGACY_ICON_RENAMES = {
	moreHorizontalMobile: 'moreHorizontal',
};

function patchLegacyWordPressIcons( source ) {
	return Object.entries( LEGACY_ICON_RENAMES ).reduce(
		( patched, [ legacyName, currentName ] ) =>
			patched.replaceAll( legacyName, currentName ),
		source
	);
}

function patchReact19RemovedAPIs( source ) {
	const patched = source
		.replace(
			/import \{ findDOMNode \} from ['"]react-dom['"];/,
			`const legacyFindDOMNode = undefined;`
		)
		.replace(
			/import React from ['"]react['"];/,
			`import React, { createRef } from 'react';`
		)
		.replace( /\bfindDOMNode\(this\)/g, 'this.getDomNode()' )
		.replaceAll( 'displayName = ', 'legacyDisplayName = ' );

	if ( ! patched.includes( 'getDomNode()' ) ) {
		throw new Error(
			'The sortable compatibility patch did not match its target.'
		);
	}

	if (
		/\bfindDOMNode\(/.test( patched ) ||
		/"defaultProps"|"propTypes"|\.displayName\s*=/.test( patched )
	) {
		throw new Error(
			'React 19 removed APIs remain in the sortable compatibility patch.'
		);
	}

	return patched;
}

function appendSortableCompatibility( source ) {
	const helper = `
const __acmeReactDom = __acmeRequireReactDom();
const __acmeFindDomNode = __acmeReactDom && typeof __acmeReactDom.findDOMNode === 'function'
	? (instance) => __acmeReactDom.findDOMNode(instance)
	: (instance) => {
		if (instance && typeof instance.getDomNode === 'function') {
			return instance.getDomNode();
		}
		if (instance && instance.nodeType) {
			return instance;
		}
		throw new Error('acme-coming-soon: unable to resolve a sortable DOM node.');
	};

function __acmeWithDefaults(Component, defaults, displayName) {
	if (!defaults) {
		return Component;
	}
	Component.__acmeDefaults = defaults;
	if (displayName) {
		Component.__acmeDisplayName = displayName;
	}
	return Component;
}

class __acmeSortableHandleCompat extends React.Component {
	constructor(props) {
		super(props);
		this.node = createRef();
	}
	getDomNode() {
		return this.node.current;
	}
	componentDidMount() {
		this.getDomNode().sortableHandle = true;
	}
	getWrappedInstance() {
		return this.node.current;
	}
	render() {
		const { forwardedRef, ...props } = this.props;
		return React.createElement(this.props.component, {
			...props,
			ref: (node) => {
				this.node.current = node;
				if (typeof forwardedRef === 'function') {
					forwardedRef(node);
				} else if (forwardedRef) {
					forwardedRef.current = node;
				}
			},
		});
	}
}

class __acmeSortableElementCompat extends React.Component {
	constructor(props) {
		super(props);
		this.node = createRef();
	}
	getDomNode() {
		return this.node.current;
	}
	componentDidMount() {
		this.register();
	}
	componentDidUpdate(previousProps) {
		if (this.getDomNode()) {
			if (previousProps.index !== this.props.index) {
				this.getDomNode().sortableInfo.index = this.props.index;
			}
			if (previousProps.disabled !== this.props.disabled) {
				this.getDomNode().sortableInfo.disabled = this.props.disabled;
			}
		}
		if (previousProps.collection !== this.props.collection) {
			this.unregister(previousProps.collection);
			this.register();
		}
	}
	componentWillUnmount() {
		this.unregister();
	}
	register() {
		const { collection = 0, disabled, index } = this.props;
		this.ref = { node: this.getDomNode() };
		this.getDomNode().sortableInfo = {
			collection,
			disabled,
			index,
			manager: this.context.manager,
		};
		this.context.manager.add(collection, this.ref);
	}
	unregister(collection = this.props.collection || 0) {
		this.context.manager.remove(collection, this.ref);
	}
	getWrappedInstance() {
		return this.node.current;
	}
	render() {
		const { forwardedRef, ...props } = this.props;
		return React.createElement(this.props.component, {
			...props,
			ref: (node) => {
				this.node.current = node;
				if (typeof forwardedRef === 'function') {
					forwardedRef(node);
				} else if (forwardedRef) {
					forwardedRef.current = node;
				}
			},
		});
	}
}
__acmeSortableElementCompat.contextType = SortableContext;

class __acmeSortableContainerCompat extends React.Component {
	constructor(props) {
		super(props);
		this.node = createRef();
	}
	getDomNode() {
		return this.node.current;
	}
	getWrappedInstance() {
		return this.node.current;
	}
	getContainer() {
		if (typeof this.props.getContainer === 'function') {
			return this.props.getContainer(this.getWrappedInstance());
		}
		return this.getDomNode();
	}
	render() {
		const { forwardedRef, ...props } = this.props;
		return React.createElement(SortableContext.Provider, {
			value: this.sortableContextValue,
		}, React.createElement(this.props.component, {
			...props,
			ref: (node) => {
				this.node.current = node;
				if (typeof forwardedRef === 'function') {
					forwardedRef(node);
				} else if (forwardedRef) {
					forwardedRef.current = node;
				}
			},
		}));
	}
}

const __acmeOriginalCreateElement = React.createElement;
React.createElement = function(type, props, ...children) {
	if (typeof type === 'function' && type.__acmeDefaults && props && typeof props === 'object') {
		props = { ...props };
		for (const [key, value] of Object.entries(type.__acmeDefaults)) {
			if (props[key] === undefined) {
				props[key] = value;
			}
		}
	}
	return __acmeOriginalCreateElement(type, props, ...children);
};

function __acmeCompatElement(Component) {
	const Compat = React.forwardRef((props, ref) => React.createElement(__acmeSortableElementCompat, {
		...props,
		forwardedRef: ref,
		component: Component,
	}));
	Compat.__acmeDefaults = Component.__acmeDefaults || { collection: 0 };
	Compat.__acmeDisplayName = Component.__acmeDisplayName;
	return Compat;
}

function __acmeCompatHandle(Component) {
	const Compat = React.forwardRef((props, ref) => React.createElement(__acmeSortableHandleCompat, {
		...props,
		forwardedRef: ref,
		component: Component,
	}));
	Compat.__acmeDisplayName = Component.__acmeDisplayName;
	return Compat;
}

function __acmeCompatContainer(Component) {
	const Compat = React.forwardRef((props, ref) => React.createElement(__acmeSortableContainerCompat, {
		...props,
		forwardedRef: ref,
		component: Component,
	}));
	Compat.__acmeDefaults = Component.__acmeDefaults || {};
	Compat.__acmeDisplayName = Component.__acmeDisplayName;
	return Compat;
}

SortableContainer = __acmeCompatContainer(SortableContainer);
SortableElement = __acmeCompatElement(SortableElement);
SortableHandle = __acmeCompatHandle(SortableHandle);
`;

	return `${ source }\n${ helper }\nexport { SortableContainer, SortableElement, SortableHandle, arrayMove };\n`;
}

class AtrcReact19CompatibilityPlugin {
	constructor( options = {} ) {
		this.options = options;
		this.pluginName = 'AtrcReact19CompatibilityPlugin';
	}

	apply( compiler ) {
		compiler.hooks.normalModuleFactory.tap(
			this.pluginName,
			( factory ) => {
				factory.hooks.afterResolve.tap( this.pluginName, ( result ) => {
					const resource =
						result.createData && result.createData.resource;
					if (
						! resource ||
						! resource.includes(
							`${ path.sep }atrc${ path.sep }build${ path.sep }`
						)
					) {
						return;
					}

					const isSortable = resource.endsWith(
						'react-sortable-hoc.esm.js'
					);
					const isLegacyIconConsumer = resource.endsWith(
						`${ path.join( 'templates', 'headers', 'template-1', 'index.js' ) }`
					);
					if (
						! isSortable &&
						! (
							this.options.patchLegacyIcons &&
							isLegacyIconConsumer
						)
					) {
						return;
					}

					result.createData = {
						...result.createData,
						resourceResolveData: {
							...result.createData.resourceResolveData,
							descriptionFileData: {
								...result.createData.resourceResolveData
									.descriptionFileData,
								type: 'module',
							},
						},
						loaders: [
							...( result.createData.loaders || [] ),
							{
								loader: require.resolve( './atrc-loader-runner.js' ),
								options: {
									compatibilityLoader:
										require.resolve( './atrc-loader.js' ),
									...this.options,
								},
								ident: null,
								type: null,
							},
						],
					};
				} );
			}
		);
	}
}

class AtrcReact19CompatibilityLoader {
	constructor( source, map, meta ) {
		this.source = source;
		this.map = map;
		this.meta = meta;
	}

	getCacheElement() {
		return JSON.stringify( { version: CACHE_KEY, source: this.source } );
	}

	readonly() {
		return true;
	}

	cacheable() {
		return true;
	}

	raw() {
		return true;
	}

	async transform( source ) {
		let result = replaceAtrcPlaceholders( source.toString() );
		if ( this.options.patchLegacyIcons ) {
			result = patchLegacyWordPressIcons( result );
		}
		if (
			this.options.patchReact19 &&
			this.source.includes( 'react-sortable-hoc.esm.js' )
		) {
			result = patchReact19RemovedAPIs( result );
			result = appendSortableCompatibility( result );
		}
		return result;
	}

	process( source ) {
		return this.transform( source ).then( ( code ) => ( {
			code,
			map: this.map,
			meta: this.meta,
		} ) );
	}
}

module.exports = AtrcReact19CompatibilityPlugin;
module.exports.Loader = AtrcReact19CompatibilityLoader;
module.exports.CACHE_TARGET = CACHE_TARGET;
module.exports.CACHE_KEY = CACHE_KEY;
module.exports.defaultRequestToExternal = defaultRequestToExternal;
module.exports.defaultRequestToHandle = defaultRequestToHandle;
module.exports.resolveReactDOM = resolveReactDOM;
module.exports.resolveReactDOMClient = resolveReactDOMClient;
