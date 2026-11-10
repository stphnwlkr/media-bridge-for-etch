/* Build the identical Ramka viewer for both plugins from a pinned upstream checkout. */
const esbuild = require( 'esbuild' );
const { execFileSync } = require( 'node:child_process' );
const { copyFileSync, existsSync } = require( 'node:fs' );
const path = require( 'node:path' );

const revision = '3e223d5b353587e2629a7c2029dc0accbeaa65fd';
const upstream = path.resolve( process.argv[2] );
const root = path.resolve( __dirname, '..' );
const viewer = path.join( root, 'assets/ramka-gallery' );
const bricks = path.join( root, 'uplink-media-manager/assets/ramka-gallery' );

if ( execFileSync( 'git', [ '-C', upstream, 'rev-parse', 'HEAD' ], { encoding: 'utf8' } ).trim() !== revision ) {
	throw new Error( 'Check out Ramka revision ' + revision + ' before building.' );
}

esbuild.buildSync( {
	entryPoints: [ path.join( viewer, 'viewer.jsx' ) ],
	outfile: path.join( viewer, 'viewer.js' ),
	bundle: true,
	target: 'es2020',
	tsconfigRaw: { compilerOptions: { jsx: 'react' } },
	minify: true,
	legalComments: 'none',
	banner: { js: '/*! Ramka © 2026 Artur Bień, MIT. See LICENSE.txt and THIRD-PARTY-NOTICES.md. */' },
	define: { 'process.env.NODE_ENV': '"production"' },
	alias: {
		'@ramka/react/lightbox': path.join( upstream, 'packages/ramka/src/lightbox/lightbox.ts' ),
		'react': path.join( viewer, 'wordpress-react.cjs' ),
		'react-dom': path.join( viewer, 'wordpress-react-dom.cjs' ),
	},
} );

if ( ! existsSync( bricks ) ) process.exit( 0 );

for ( const file of [ 'viewer.jsx', 'viewer.js', 'viewer.css', 'wordpress-react.cjs', 'wordpress-react-dom.cjs', 'LICENSE.txt', 'THIRD-PARTY-NOTICES.md', 'inter-latin.woff2', 'INTER-OFL.txt', 'LUCIDE-LICENSE.txt', 'SOURCES.md' ] ) {
	copyFileSync( path.join( viewer, file ), path.join( bricks, file ) );
}
