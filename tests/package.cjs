const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );
const { execFileSync } = require( 'node:child_process' );
const { createRequire } = require( 'node:module' );
const { buildPackage } = require( '../scripts/package.cjs' );

// Use the same ZIP reader as wp-scripts. Test real release source, not a Pro stub.
const AdmZip = createRequire(
	require.resolve( '@wordpress/scripts/scripts/plugin-zip.js' )
)( 'adm-zip' );
const root = path.resolve( __dirname, '..' );
const source = fs.mkdtempSync( path.join( os.tmpdir(), 'sll-package-check-' ) );
const manifest = require( '../package.json' );
const prefix = 'simple-ldap-login/';
const mainFile = `${ prefix }Simple-LDAP-Login.php`;
const originalMain = fs.readFileSync(
	path.join( root, 'Simple-LDAP-Login.php' ),
	'utf8'
);
const originalVendor = fs.readFileSync(
	path.join( root, 'vendor/composer/installed.json' ),
	'utf8'
);
const originalProLock = fs.readFileSync(
	path.join( root, 'pro/composer.lock' ),
	'utf8'
);

try {
	for ( const file of [
		'package.json',
		...manifest.files.filter(
			( item ) => ! /^(?:vendor|pro)(?:\/|$)/.test( item )
		),
	] ) {
		fs.cpSync( path.join( root, file ), path.join( source, file ), {
			recursive: true,
		} );
	}
	assert.throws(
		() => buildPackage( 'other', source ),
		/Choose free or pro/
	);
	assert.throws( () => buildPackage( 'pro', source ), /source is missing/ );
	for ( const removed of [ 'legacy', 'transition' ] ) {
		assert.throws(
			() => buildPackage( removed, source ),
			/Choose free or pro/
		);
	}
	fs.cpSync( path.join( root, 'pro' ), path.join( source, 'pro' ), {
		recursive: true,
		filter: ( file ) => path.basename( file ) !== 'vendor',
	} );
	for ( const folder of [
		'vendor',
		'pro/vendor',
		'pro/tests',
		'pro/node_modules',
	] ) {
		fs.mkdirSync( path.join( source, folder ), { recursive: true } );
	}
	// These markers must never enter a package.
	fs.writeFileSync( path.join( source, '.env' ), 'PACKAGE_SECRET_MARKER' );
	fs.writeFileSync(
		path.join( source, 'pro/.env' ),
		'PACKAGE_SECRET_MARKER'
	);
	fs.writeFileSync(
		path.join( source, 'vendor/development-tool.php' ),
		'PACKAGE_DEV_MARKER'
	);
	fs.writeFileSync(
		path.join( source, 'pro/vendor/development-tool.php' ),
		'PACKAGE_DEV_MARKER'
	);
	fs.writeFileSync(
		path.join( source, 'pro/tests/private.php' ),
		'PACKAGE_PRIVATE_MARKER'
	);
	fs.writeFileSync(
		path.join( source, 'pro/node_modules/private.js' ),
		'PACKAGE_PRIVATE_MARKER'
	);

	for ( const edition of [ 'free', 'pro' ] ) {
		const archive = buildPackage( edition, source );
		const suffix = edition === 'free' ? '' : `-${ edition }`;
		assert.equal(
			path.basename( archive ),
			`simple-ldap-login${ suffix }.zip`
		);
		const zip = new AdmZip( archive );
		const names = zip.getEntries().map( ( entry ) => entry.entryName );
		assert( names.every( ( name ) => name.startsWith( prefix ) ) );
		for ( const file of [
			'Simple-LDAP-Login.php',
			'vendor/autoload.php',
			'vendor/scoper-autoload.php',
			'composer.json',
			'composer.lock',
			'LICENSE',
			'src/Commerce.php',
			'src/License.php',
			'assets/commerce.js',
			'vendor/paidcommunities/wordpress-sdk/build/components.js',
			'vendor/paidcommunities/wordpress-sdk/build/api.js',
			...fs
				.readdirSync( path.join( root, 'languages' ) )
				.map( ( catalog ) => `languages/${ catalog }` ),
		] ) {
			assert(
				names.includes( prefix + file ),
				`Required file: ${ edition }/${ file }`
			);
		}
		assert(
			! names.some( ( name ) =>
				/(?:^|\/)(?:tests|scripts|tools|node_modules|marketing|\.wordpress-org|\.env)(?:\/|$)/.test(
					name
				)
			)
		);
		assert(
			! names.some( ( name ) =>
				/phpcs|php-scoper|phpcompatibility|development-tool/.test(
					name
				)
			)
		);
		assert(
			! names.some( ( name ) =>
				/PACKAGE_(SECRET|DEV|PRIVATE)_MARKER/.test(
					zip.readAsText( name )
				)
			)
		);
		const main = zip.readAsText( mainFile );
		const readme = zip.readAsText( `${ prefix }readme.txt` );
		assert(
			readme.startsWith(
				edition === 'pro'
					? '=== Simple LDAP Login Pro ==='
					: '=== Simple LDAP Login ==='
			),
			'Each package includes its own readme.'
		);
		assert( main.includes( `define( 'SLL_EDITION', '${ edition }' );` ) );
		assert( ! main.includes( '<?php', 5 ), 'Keep one PHP opening tag.' );
		if ( edition === 'free' ) {
			assert.equal(
				main,
				originalMain.replaceAll(
					'/vendor/autoload.php',
					'/vendor/scoper-autoload.php'
				)
			);
			assert(
				! names.some( ( name ) => name.startsWith( `${ prefix }pro/` ) )
			);
			const php = names
				.filter( ( name ) => name.endsWith( '.php' ) )
				.map( ( name ) => zip.readAsText( name ) )
				.join( '\n' );
			assert(
				! /function\s+(?:authenticate_sso|login_sso|sync_meta)\s*\(/.test(
					php
				)
			);
			assert( ! php.includes( 'update_user_meta(' ) );
			assert( ! php.includes( "$_SERVER['REMOTE_USER']" ) );
		} else {
			for ( const file of [
				'bootstrap.php',
				'composer.json',
				'composer.lock',
				'vendor/autoload.php',
				'src/Metadata.php',
				'src/Roles.php',
				'src/Profile.php',
				'src/Accounts.php',
				'src/Multisite.php',
				'src/Audit.php',
				'src/Settings_Rules.php',
				'src/Server_Sso.php',
				'src/Ldap_Client.php',
				'src/Admin.php',
			] ) {
				assert(
					names.includes( `${ prefix }pro/${ file }` ),
					`Full feature file: ${ edition }/${ file }`
				);
			}
			assert(
				main.includes( "require_once __DIR__ . '/pro/bootstrap.php';" )
			);
		}
		if ( edition === 'pro' ) {
			assert( main.includes( 'Update URI: false' ) );
			assert( main.includes( 'Plugin Name: Simple LDAP Login Pro' ) );
		} else {
			assert( ! main.includes( 'Update URI:' ) );
		}
		const extracted = path.join( source, `extracted-${ edition }` );
		zip.extractAllTo( extracted );
		const plugin = path.join( extracted, prefix );
		execFileSync(
			'php',
			[ '-l', path.join( plugin, 'Simple-LDAP-Login.php' ) ],
			{ stdio: 'pipe', encoding: 'utf8' }
		);
		for ( const scenario of [
			'fresh',
			'existing',
			'network',
			'v1',
			'v1-network',
			...( edition === 'pro' ? [ 'unlicensed', 'expired' ] : [] ),
		] ) {
			execFileSync(
				'php',
				[
					path.join( root, 'tests/package-runtime.php' ),
					plugin,
					edition,
					scenario,
				],
				{ stdio: 'pipe', encoding: 'utf8' }
			);
		}
	}
	fs.writeFileSync( path.join( source, 'composer.json' ), '{invalid' );
	assert.throws( () => buildPackage( 'free', source ), /JSON|parse/i );
	assert.equal(
		fs.readFileSync( path.join( source, 'Simple-LDAP-Login.php' ), 'utf8' ),
		originalMain
	);
	assert.equal(
		fs.readFileSync(
			path.join( source, 'vendor/development-tool.php' ),
			'utf8'
		),
		'PACKAGE_DEV_MARKER'
	);
	assert.equal(
		fs.readFileSync(
			path.join( source, 'pro/vendor/development-tool.php' ),
			'utf8'
		),
		'PACKAGE_DEV_MARKER'
	);
	assert.equal(
		fs.readFileSync(
			path.join( root, 'vendor/composer/installed.json' ),
			'utf8'
		),
		originalVendor
	);
	assert.equal(
		fs.readFileSync( path.join( root, 'Simple-LDAP-Login.php' ), 'utf8' ),
		originalMain
	);
	assert.equal(
		fs.readFileSync( path.join( root, 'pro/composer.lock' ), 'utf8' ),
		originalProLock
	);
	process.stdout.write(
		'Package checks passed: Free and Pro, private dependencies, PSR-4 loading, runtime hooks, migration, exclusions, and source preservation.\n'
	);
} finally {
	fs.rmSync( source, { recursive: true, force: true } );
}
