const { createHash } = require( 'node:crypto' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );

/** Fingerprint the actual release, including PHP-only same-version rebuilds. */
class ReleaseManifestPlugin {
	apply( compiler ) {
		compiler.hooks.thisCompilation.tap(
			'ReleaseManifestPlugin',
			( compilation ) => {
				compilation.hooks.processAssets.tap(
					{
						name: 'ReleaseManifestPlugin',
						stage: compiler.webpack.Compilation
							.PROCESS_ASSETS_STAGE_REPORT,
					},
					() => {
						const hash = createHash( 'sha256' );
						const root = compiler.context;
						const addFile = ( relative ) => {
							const absolute = path.join( root, relative );
							if ( fs.statSync( absolute ).isDirectory() ) {
								fs.readdirSync( absolute )
									.sort()
									.forEach( ( name ) =>
										addFile( path.join( relative, name ) )
									);
								return;
							}
							compilation.fileDependencies.add( absolute );
							hash.update( relative )
								.update( '\0' )
								.update( fs.readFileSync( absolute ) )
								.update( '\0' );
						};
						[
							'overcustomise.php',
							'includes',
							'templates',
							'assets/css',
							'assets/js',
							'composer.lock',
						].forEach( addFile );
						compilation
							.getAssets()
							.sort( ( a, b ) => a.name.localeCompare( b.name ) )
							.forEach( ( asset ) => {
								hash.update( asset.name )
									.update( '\0' )
									.update( asset.source.buffer() )
									.update( '\0' );
							} );
						compilation.emitAsset(
							'release.json',
							new compiler.webpack.sources.RawSource(
								JSON.stringify( {
									build: hash.digest( 'hex' ),
									assets: compilation
										.getAssets()
										.map( ( asset ) => asset.name )
										.sort(),
								} ) + '\n'
							)
						);
					}
				);
			}
		);
	}
}

module.exports = ReleaseManifestPlugin;
