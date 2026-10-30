const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const vm = require( 'node:vm' );
const source = fs.readFileSync( 'assets/commerce.js', 'utf8' );

async function check() {
	const elements = Object.fromEntries(
		[
			'sll-license-component',
			'sll-license-fallback',
			'sll-load-pricing',
			'sll-pricing',
			'sll-pricing-status',
		].map( ( id ) => [ id, { hidden: false } ] )
	);
	const input = {
		setAttribute( name, value ) {
			this[ name ] = value;
		},
	};
	const frame = { name: 'test-frame', contentWindow: {} };
	elements[ 'sll-license-component' ].querySelector = () => input;
	elements[ 'sll-pricing' ].querySelector = () => frame;
	elements[ 'sll-load-pricing' ].addEventListener = ( name, callback ) => {
		assert.equal( name, 'click' );
		elements[ 'sll-load-pricing' ].click = callback;
	};
	let script;
	let observer;
	let guard;
	let props;
	let request;
	let ready;
	let product;
	let requests = 0;
	const config = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		scope: 'network',
		product: 'prd_9QyrKxQpvZGrcCW8',
		i18n: {
			licenseKey: 'License key',
			licenseError: 'Could not verify the license.',
			pricingLoading: 'Loading plans.',
			pricingError: 'Open checkout in a new tab.',
			pricingTitle: 'Pro plans',
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
		addEventListener( event, listener, capture ) {
			assert.equal( event, 'message' );
			assert.equal( capture, true );
			guard = listener;
		},
		setTimeout: () => 1,
		clearTimeout() {},
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
		createElement: () => ( {} ),
		head: {
			appendChild: ( element ) => {
				script = element;
			},
		},
	};
	vm.runInNewContext( source, {
		window,
		document,
		URL,
		URLSearchParams,
		MutationObserver: ( window.MutationObserver = class {
			constructor( callback ) {
				observer = callback;
			}
			observe() {}
		} ),
	} );
	assert.equal( requests, 0 );
	assert.equal(
		script,
		undefined,
		'Opening settings does not load the purchase service.'
	);
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
	elements[ 'sll-load-pricing' ].click();
	assert.equal( script.src, 'https://js.paidcommunities.com/v1' );
	script.onerror();
	assert.equal(
		elements[ 'sll-pricing-status' ].textContent,
		config.i18n.pricingError
	);
	window.paidcommunities = {
		Pricing( id ) {
			product = id;
			return {
				on: ( name, callback ) => {
					ready = callback;
				},
				render: ( container ) =>
					assert.equal( container, elements[ 'sll-pricing' ] ),
			};
		},
	};
	script.onload();
	assert.equal( product, config.product );
	assert(
		window.paidcommunities.wp,
		'The purchase SDK preserves the WordPress SDK components.'
	);
	assert.equal( frame.title, config.i18n.pricingTitle );
	for ( const [ origin, sender, url, blocked ] of [
		[ 'https://attacker.test', frame.contentWindow, undefined, true ],
		[ 'https://checkout.paidcommunities.com', {}, undefined, true ],
		[
			'https://checkout.paidcommunities.com',
			frame.contentWindow,
			'https://attacker.test/',
			true,
		],
		[
			'https://checkout.paidcommunities.com',
			frame.contentWindow,
			'https://checkout.paidcommunities.com/checkout/test',
			false,
		],
	] ) {
		let stopped = false;
		guard( {
			data: {
				id: frame.name,
				event: url ? 'redirect' : 'resize',
				payload: { url },
			},
			origin,
			source: sender,
			stopImmediatePropagation() {
				stopped = true;
			},
		} );
		assert.equal( stopped, blocked );
	}
	ready();
	assert.equal( elements[ 'sll-pricing-status' ].hidden, true );
	process.stdout.write(
		'SDK component, purchase consent, API adapter, and message checks passed.\n'
	);
}
check().catch( ( error ) => {
	throw error;
} );
