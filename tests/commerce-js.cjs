const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const vm = require( 'node:vm' );
const source = fs.readFileSync( 'assets/commerce.js', 'utf8' );
const elements = {
	'sll-license-component': { hidden: true },
	'sll-license-fallback': { hidden: false },
};
const input = {
	setAttribute( key, value ) {
		this[ key ] = value;
	},
};
elements[ 'sll-license-component' ].querySelector = () => input;
let observer;
let props;
let reloaded = 0;
const window = {
	sllCommerce: { i18n: { licenseKey: 'License key' } },
	paidcommunities: { wp: { components: { LicenseActivation() {} } } },
	wp: {
		element: {
			createRoot: () => ( { render() {} } ),
			createElement: ( component, options ) => {
				props = options;
			},
		},
	},
	location: {
		reload() {
			++reloaded;
		},
	},
	fetch() {
		throw new Error( 'Our component must use the SDK API service.' );
	},
	MutationObserver: class {
		constructor( callback ) {
			observer = callback;
		}
		observe() {}
	},
};
const document = { getElementById: ( id ) => elements[ id ] };
vm.runInNewContext( source, { window, document } );
assert.equal( props.config, window.sllCommerce );
assert.equal( Object.hasOwn( props, 'apiService' ), false );
assert.equal( elements[ 'sll-license-component' ].hidden, false );
assert.equal( elements[ 'sll-license-fallback' ].hidden, true );
observer();
assert.equal( input.type, 'password' );
assert.equal( input.autocomplete, 'new-password' );
assert.equal( input[ 'aria-label' ], 'License key' );
props.onActivate();
props.onDeactivate();
assert.equal( reloaded, 2 );
delete elements[ 'sll-license-component' ];
vm.runInNewContext( source, { window, document } );
process.stdout.write(
	'The license component uses the native SDK API service.\n'
);
