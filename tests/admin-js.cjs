const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const vm = require( 'node:vm' );
const source = fs.readFileSync( 'assets/admin.js', 'utf8' );

for ( const hasSelector of [ true, false ] ) {
	const fields = [ 'ad', 'ol' ].map( ( type ) => ( {
		dataset: { sllDirectory: type },
		hidden: false,
		input: { value: 'saved-value', disabled: false },
		querySelectorAll() {
			return [ this.input ];
		},
	} ) );
	const selector = {
		value: 'ad',
		addEventListener( name, callback ) {
			assert.equal( name, 'change' );
			this.change = callback;
		},
	};
	const settings = {
		dataset: { sllDirectoryType: 'ol' },
		querySelector: () => ( hasSelector ? selector : null ),
		querySelectorAll: () => fields,
	};
	vm.runInNewContext( source, {
		document: { querySelector: () => settings },
	} );
	const check = ( type ) => {
		for ( const field of fields ) {
			assert.equal( field.hidden, field.dataset.sllDirectory !== type );
			assert.equal( field.input.disabled, field.hidden );
			assert.equal( field.input.value, 'saved-value' );
		}
	};
	check( hasSelector ? 'ad' : 'ol' );
	if ( hasSelector ) {
		selector.value = 'ol';
		selector.change();
		check( 'ol' );
		selector.value = 'ad';
		selector.change();
		check( 'ad' );
	}
}
vm.runInNewContext( source, { document: { querySelector: () => null } } );
process.stdout.write( 'Directory field visibility checks passed.\n' );
