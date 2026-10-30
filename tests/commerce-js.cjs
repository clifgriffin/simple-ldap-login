const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const vm = require( 'node:vm' );
const source = fs.readFileSync( 'assets/commerce.js', 'utf8' );

async function check() {
	const elements = Object.fromEntries(
		[ 'sll-license-component', 'sll-license-fallback' ].map( ( id ) => [
			id,
			{ hidden: false },
		] )
	);
	const input = {
		setAttribute( name, value ) {
			this[ name ] = value;
		},
	};
	elements[ 'sll-license-component' ].querySelector = () => input;
	let observer;
	let props;
	let request;
	let requests = 0;
	const config = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		scope: 'network',
		i18n: {
			licenseKey: 'License key',
			licenseError: 'Could not verify the license.',
		},
	};
	const window = {
		sllCommerce: config,
		paidcommunities: { wp: { components: { LicenseActivation() {} } } },
		wp: {
			element: {
				createRoot: () => ( { render() {} } ),
				createElement: ( component, options ) => {
					assert.equal(
						component,
						window.paidcommunities.wp.components.LicenseActivation
					);
					props = options;
				},
			},
		},
		location: { reload() {} },
		async fetch( url, options ) {
			++requests;
			request = { url, options };
			return {
				json: async () => ( { success: true, data: { license: {} } } ),
			};
		},
	};
	const document = {
		getElementById: ( id ) => elements[ id ],
	};
	vm.runInNewContext( source, {
		window,
		document,
		URLSearchParams,
		MutationObserver: ( window.MutationObserver = class {
			constructor( callback ) {
				observer = callback;
			}
			observe() {}
		} ),
	} );
	assert.equal( requests, 0 );
	assert.equal( elements[ 'sll-license-fallback' ].hidden, true );
	observer();
	assert.equal( input.type, 'password' );
	assert.equal( input.autocomplete, 'new-password' );
	assert.equal( input[ 'aria-label' ], 'License key' );
	await props.apiService.activate( 'plugin', {
		nonce: 'test-nonce',
		license_key: 'private-key',
	} );
	assert.equal( request.url, config.ajaxUrl );
	assert.equal( request.options.credentials, 'same-origin' );
	assert.equal( request.options.body.get( 'action' ), 'sll_license' );
	assert.equal( request.options.body.get( 'sll_scope' ), 'network' );
	assert.equal(
		request.options.body.get( 'sll_license_key' ),
		'private-key'
	);
	await props.apiService.deactivate( 'plugin', { nonce: 'test-nonce' } );
	assert.equal(
		request.options.body.get( 'sll_license_task' ),
		'deactivate'
	);
	window.fetch = async () => ( {
		json: async () => ( {
			success: false,
			data: { message: 'Rejected.' },
		} ),
	} );
	assert.equal(
		( await props.apiService.deactivate( '', {} ) ).error.message,
		'Rejected.'
	);
	window.fetch = async () => {
		throw new Error( 'Test connection error.' );
	};
	assert.equal(
		( await props.apiService.activate( '', {} ) ).error.message,
		config.i18n.licenseError
	);
	delete elements[ 'sll-license-component' ];
	vm.runInNewContext( source, { window, document, URLSearchParams } );
	process.stdout.write(
		'Pro license component and API adapter checks passed.\n'
	);
}
check().catch( ( error ) => {
	throw error;
} );
