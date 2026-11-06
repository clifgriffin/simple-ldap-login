const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );
const { execFileSync, spawnSync } = require( 'node:child_process' );

process.chdir( path.join( __dirname, '../..' ) );
for ( const files of [
	[ 'tests/docker/compose.yml' ],
	[ 'tests/docker/compose.yml', 'tests/docker/compose.samba.yml' ],
	[ 'demo/compose.yml' ],
] ) {
	const config = JSON.parse( execFileSync( 'docker', [
		'compose', ...files.flatMap( ( file ) => [ '-f', file ] ),
		'--profile', '*', 'config', '--format', 'json',
	], { encoding: 'utf8' } ) );
	for ( const service of Object.values( config.services ) ) {
		assert.equal( service.healthcheck.interval, '1m0s' );
		assert.equal( service.healthcheck.start_interval, '2s' );
		assert.equal( service.healthcheck.start_period, '1m30s' );
	}
}

// Record Docker commands without starting containers or changing their data.
const temp = fs.mkdtempSync( path.join( os.tmpdir(), 'sll-lifecycle-' ) );
try {
	const log = path.join( temp, 'calls' );
	fs.writeFileSync( path.join( temp, 'docker' ), `#!/bin/sh
printf '%s\\n' "$*" >> "$SLL_CHECK_LOG"
case " $* " in *" exec "*) exit 23 ;; esac
`, { mode: 0o755 } );
	const env = {
		...process.env, PATH: `${ temp }:${ process.env.PATH }`,
		SLL_CHECK_LOG: log, SLL_DIRECTORY: 'ad', SLL_MULTISITE: '1',
		SLL_TEST_PAID: '1', SLL_TEST_PROJECT: 'sll-lifecycle-check',
	};
	execFileSync( 'sh', [ 'tests/docker/run.sh', 'stop' ], { env } );
	assert.equal( fs.readFileSync( log, 'utf8' ),
		'compose -p sll-lifecycle-check -f tests/docker/compose.yml -f tests/docker/compose.samba.yml --profile ad --profile paid stop\n' );
	fs.writeFileSync( log, '' );
	const result = spawnSync( 'sh', [ 'tests/docker/run.sh', 'test' ], {
		env: { ...env, SLL_TEST_PAID: '0' },
	} );
	assert.equal( result.status, 23 );
	assert.match( fs.readFileSync( log, 'utf8' ), / down --volumes --remove-orphans\n$/ );
} finally {
	fs.rmSync( temp, { recursive: true, force: true } );
}
process.stdout.write( 'Docker health check, stop, and failure cleanup checks passed.\n' );
