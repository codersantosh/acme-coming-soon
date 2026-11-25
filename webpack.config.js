'use strict';

const path = require( 'path' );
const fs = require( 'fs' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );
const AtrcReact19CompatibilityPlugin = require( './.bin/atrc-loader.js' );
const ReactEsmShimPlugin = require( './.bin/react-esm-shim.js' );

const resolveAtrcModule = ( request ) => {
	const packagePath = require.resolve( 'atrc/package.json' );
	const packageRoot = path.dirname( packagePath );
	const candidate = path.join( packageRoot, request );

	for ( const suffix of [ '', '.js', '/index.js' ] ) {
		const resolved = `${ candidate }${ suffix }`;
		if ( fs.existsSync( resolved ) && fs.statSync( resolved ).isFile() ) {
			return resolved;
		}
	}

	return request;
};

const configs = (
	Array.isArray( defaultConfig ) ? defaultConfig : [ defaultConfig ]
).map( ( defaultConfiguration ) => {
	const config = {
		...defaultConfiguration,
		module: {
			...defaultConfiguration.module,
			rules: defaultConfiguration.module.rules.map( ( rule ) => {
				if (
					rule.test &&
					rule.test.test( 'file.scss' ) &&
					rule.test.test( 'file.js' )
				) {
					return rule;
				}
				if ( String( rule.test ) === String( /\.(sc|sa)ss$/ ) ) {
					return {
						...rule,
						use: rule.use.map( ( loader ) => {
							if (
								loader.loader &&
								loader.loader.endsWith( 'sass-loader' )
							) {
								return {
									...loader,
									options: {
										...loader.options,
										api: 'modern-compiler',
										sassOptions: {
											...loader.options.sassOptions,
											charset: false,
											silenceDeprecations: [
												'legacy-js-api',
												'import',
											],
										},
									},
								};
							}
							return loader;
						} ),
					};
				}
				return rule;
			} ),
		},
		resolve: {
			...defaultConfiguration.resolve,
			alias: [
				...( Array.isArray( defaultConfiguration.resolve?.alias )
					? defaultConfiguration.resolve.alias
					: Object.entries(
							defaultConfiguration.resolve?.alias || {}
						).map( ( [ name, alias ] ) => ( { name, alias } ) ) ),
				{
					name: 'atrc/build',
					alias: path.join(
						path.dirname( require.resolve( 'atrc/package.json' ) ),
						'build'
					),
				},
				{ name: 'lodash-es', alias: 'lodash' },
				{
					name: 'react-dom',
					alias: resolveAtrcModule( '../react-dom' ),
					onlyModule: true,
				},
				{
					name: 'react-dom/client',
					alias: resolveAtrcModule( '../react-dom/client' ),
					onlyModule: true,
				},
			],
		},
		externalsType: 'window',
		externals: [
			( data, callback ) => {
				const external =
					AtrcReact19CompatibilityPlugin.defaultRequestToExternal(
						data.request
					);
				if ( external ) {
					return callback( null, external );
				}
				callback();
			},
		],
		plugins: defaultConfiguration.plugins.map( ( plugin ) => {
			if ( plugin instanceof DependencyExtractionWebpackPlugin ) {
				return new DependencyExtractionWebpackPlugin( {
					...plugin.options,
					useDefaults: false,
					requestToExternal:
						AtrcReact19CompatibilityPlugin.defaultRequestToExternal,
					requestToHandle:
						AtrcReact19CompatibilityPlugin.defaultRequestToHandle,
				} );
			}
			return plugin;
		} ),
	};

	if ( ! process.env.WP_NO_EXTERNALS ) {
		config.plugins.push( new ReactEsmShimPlugin() );
		config.plugins.push(
			new AtrcReact19CompatibilityPlugin( {
				patchReact19: true,
				patchLegacyIcons: true,
				cacheTarget: AtrcReact19CompatibilityPlugin.CACHE_TARGET,
			} )
		);
	}

	config.cache = {
		type: 'filesystem',
		cacheDirectory: path.join(
			__dirname,
			'node_modules',
			'.cache',
			'webpack'
		),
		cacheLocation: path.join(
			__dirname,
			'node_modules',
			'.cache',
			'webpack',
			'production'
		),
		buildDependencies: {
			config: [ __filename, path.join( __dirname, '.babelrc' ) ],
		},
	};

	return config;
} );

module.exports = configs;
module.exports.compatibilityPlugin = AtrcReact19CompatibilityPlugin;
module.exports.resolveAtrcModule = resolveAtrcModule;
module.exports.ReactEsmShimPlugin = ReactEsmShimPlugin;
