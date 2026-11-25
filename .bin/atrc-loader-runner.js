module.exports = function acmeAtrcLoader( source, map, meta ) {
	const callback = this.async();
	const { compatibilityLoader, ...options } = this.getOptions();
	const Loader = require( compatibilityLoader ).Loader;
	const loader = new Loader( this.resourcePath, map, meta );
	loader.options = options;
	loader.process( source.toString() ).then(
		( result ) => callback( null, result.code, result.map, result.meta ),
		( error ) => callback( error )
	);
};

module.exports.raw = false;
