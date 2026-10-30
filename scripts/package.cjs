const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );
const { spawnSync } = require( 'node:child_process' );

const root = path.resolve( __dirname, '..' );
const zipScript = require.resolve( '@wordpress/scripts/scripts/plugin-zip.js' );
const scoperRoot = path.join( root, 'tools/scoper' );
const proFiles = [
	'bootstrap.php',
	'composer.json',
	'composer.lock',
	'readme.txt',
];

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
	if ( ! [ 'free', 'pro' ].includes( edition ) ) {
		throw new Error( 'Choose free or pro.' );
	}
	const full = edition !== 'free';
	if (
		full &&
		proFiles.some(
			( file ) => ! fs.existsSync( path.join( source, 'pro', file ) )
		)
	) {
		throw new Error(
			'Pro source is missing. Add pro/bootstrap.php, pro/composer.json, pro/composer.lock, and pro/readme.txt before building a full package. See readme.md.'
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
			for ( const file of [ ...proFiles, 'src', 'assets' ] ) {
				const from = path.join( source, 'pro', file );
				if ( fs.existsSync( from ) ) {
					fs.cpSync( from, path.join( stage, 'pro', file ), {
						recursive: true,
					} );
				}
			}
			entry = entry.replace(
				'Plugin Name: Simple LDAP Login',
				'Plugin Name: Simple LDAP Login Pro'
			);
			// This is a local preview until the external updater is configured.
			entry = entry.replace(
				' * Text Domain:',
				' * Update URI: false\n * Text Domain:'
			);
			fs.copyFileSync(
				path.join( stage, 'pro/readme.txt' ),
				path.join( stage, 'readme.txt' )
			);
			fs.unlinkSync( path.join( stage, 'pro/readme.txt' ) );
			entry += "\nrequire_once __DIR__ . '/pro/bootstrap.php';\n";
			install( path.join( stage, 'pro' ) );
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
