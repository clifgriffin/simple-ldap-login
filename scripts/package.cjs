const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );
const { spawnSync } = require( 'node:child_process' );

const root = path.resolve( __dirname, '..' );
const zipScript = require.resolve( '@wordpress/scripts/scripts/plugin-zip.js' );
const scoperRoot = path.join( root, 'tools/scoper' );
const proFiles = [ 'bootstrap.php', 'readme.txt', 'src' ];

function run( command, args, cwd ) {
	const result = spawnSync( command, args, { cwd, encoding: 'utf8' } );
	if ( result.error || result.status !== 0 ) {
		throw new Error(
			result.error?.message || result.stderr || result.stdout
		);
	}
}

function install( cwd ) {
	run(
		'composer',
		[
			'install',
			'--no-dev',
			'--prefer-dist',
			'--optimize-autoloader',
			'--no-interaction',
			'--no-plugins',
			'--no-scripts',
		],
		cwd
	);
}

function isolateDependencies( stage ) {
	install( scoperRoot );
	const scoped = fs.mkdtempSync( path.join( os.tmpdir(), 'sll-scoped-' ) );
	try {
		run(
			'php',
			[
				path.join( scoperRoot, 'vendor/bin/php-scoper' ),
				'add-prefix',
				`--config=${ path.join( root, 'scripts/scoper.inc.php' ) }`,
				`--output-dir=${ scoped }`,
				'--force',
				'--stop-on-failure',
				'--no-interaction',
			],
			stage
		);
		fs.cpSync( scoped, stage, { recursive: true } );
		run(
			'composer',
			[
				'dump-autoload',
				'--no-dev',
				'--optimize',
				'--no-interaction',
				'--no-plugins',
				'--no-scripts',
			],
			stage
		);
	} finally {
		fs.rmSync( scoped, { recursive: true, force: true } );
	}
}

function buildPackage(
	edition,
	source = root,
	output = path.join( source, 'dist' )
) {
	if ( ! [ 'free', 'pro', 'enterprise' ].includes( edition ) ) {
		throw new Error( 'Choose free, pro, or enterprise.' );
	}
	const full = edition !== 'free';
	if (
		full &&
		proFiles.some(
			( file ) => ! fs.existsSync( path.join( source, 'pro', file ) )
		)
	) {
		throw new Error(
			'Pro source is missing. Add the paid repository at pro/ before building a paid package. See readme.md.'
		);
	}

	const manifest = JSON.parse(
		fs.readFileSync( path.join( source, 'package.json' ), 'utf8' )
	);
	const mainFile = 'Simple-LDAP-Login.php';
	const main = fs.readFileSync( path.join( source, mainFile ), 'utf8' );
	if ( /^ \* Update URI:/m.test( main ) ) {
		throw new Error(
			'Keep the external Update URI out of the shared plugin header.'
		);
	}
	const stage = fs.mkdtempSync( path.join( os.tmpdir(), 'sll-package-' ) );
	const name = `simple-ldap-login${ edition === 'free' ? '' : '-' + edition }`;
	try {
		const files = manifest.files.filter(
			( file ) => ! /^pro(?:\/|$)/.test( file )
		);
		for ( const file of files ) {
			// Install dependencies from the lock file. Do not copy development vendor files.
			if ( ! /^vendor(?:\/|$)/.test( file ) ) {
				fs.cpSync(
					path.join( source, file ),
					path.join( stage, file ),
					{ recursive: true }
				);
			}
		}
		let entry = main.replace(
			"define( 'SLL_EDITION', 'free' );",
			`define( 'SLL_EDITION', '${ edition }' );`
		);
		if ( full ) {
			for ( const file of [ ...proFiles, 'assets' ] ) {
				const from = path.join( source, 'pro', file );
				if ( fs.existsSync( from ) ) {
					fs.cpSync( from, path.join( stage, 'pro', file ), {
						recursive: true,
					} );
				}
			}
			entry = entry.replace(
				'Plugin Name: Simple LDAP Login',
				`Plugin Name: Simple LDAP Login ${ edition === 'enterprise' ? 'Enterprise' : 'Pro' }`
			);
			entry = entry.replace(
				' * Text Domain:',
				' * Update URI: https://paidcommunities.com\n * Text Domain:'
			);
			fs.copyFileSync(
				path.join( stage, 'pro/readme.txt' ),
				path.join( stage, 'readme.txt' )
			);
			if ( edition === 'enterprise' ) {
				const readme = path.join( stage, 'readme.txt' );
				fs.writeFileSync(
					readme,
					fs
						.readFileSync( readme, 'utf8' )
						.replace(
							'=== Simple LDAP Login Pro ===',
							'=== Simple LDAP Login Enterprise ==='
						)
				);
			}
			fs.unlinkSync( path.join( stage, 'pro/readme.txt' ) );
			entry += "\nrequire_once __DIR__ . '/pro/bootstrap.php';\n";
			files.push( 'pro/' );
		}
		fs.writeFileSync( path.join( stage, mainFile ), entry );
		fs.writeFileSync(
			path.join( stage, 'package.json' ),
			JSON.stringify(
				{
					name,
					version: manifest.version,
					license: manifest.license,
					files,
				},
				null,
				'\t'
			) + '\n'
		);
		install( stage );
		isolateDependencies( stage );
		fs.writeFileSync(
			path.join( stage, mainFile ),
			entry.replaceAll(
				'/vendor/autoload.php',
				'/vendor/scoper-autoload.php'
			)
		);
		run(
			process.execPath,
			[ zipScript, '--root-folder=simple-ldap-login' ],
			stage
		);
		fs.mkdirSync( output, { recursive: true } );
		const archive = path.join( output, `${ name }.zip` );
		fs.copyFileSync( path.join( stage, `${ name }.zip` ), archive );
		return archive;
	} finally {
		fs.rmSync( stage, { recursive: true, force: true } );
	}
}

module.exports = { buildPackage };

if ( require.main === module ) {
	try {
		process.stdout.write( `${ buildPackage( process.argv[ 2 ] ) }\n` );
	} catch ( error ) {
		process.stderr.write( `${ error.message }\n` );
		process.exitCode = 1;
	}
}
