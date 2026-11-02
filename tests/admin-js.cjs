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
		querySelectorAll: ( query ) =>
			query === '[data-sll-directory]' ? fields : [],
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

// Keep row values and form names correct through add, move, and remove actions.
let focused;
const makeEditor = ( key ) => {
	const handlers = {};
	const rows = {
		children: [],
		append( row ) {
			row.parent = this;
			this.children.push( row );
		},
		get lastElementChild() {
			return this.children.at( -1 );
		},
	};
	const button = ( action, row ) => ( {
		dataset: { sllRowAction: action },
		disabled: false,
		matches() {
			return this.disabled || editor.locked;
		},
		closest: ( query ) => ( query === 'button' ? null : row ),
		focus() {
			focused = this;
		},
	} );
	const makeRow = ( values ) => {
		const inputs = values.map( ( value, column ) => ( {
			value,
			dataset: { sllColumn: String( column ) },
			focus() {
				focused = this;
			},
		} ) );
		const labels = inputs.map( () => ( {} ) );
		const actions = { hidden: true };
		const row = {
			inputs,
			labels,
			querySelectorAll: () => inputs,
			querySelector( query ) {
				if ( query === 'input' ) {
					return inputs[ 0 ];
				}
				if ( query === '.sll-mapping-actions' ) {
					return actions;
				}
				const column = query.match( /column-label="(\d+)"/ );
				return column
					? labels[ Number( column[ 1 ] ) ]
					: this.buttons[ query.match( /action="(.*?)"/ )[ 1 ] ];
			},
			get previousElementSibling() {
				return this.parent.children[
					this.parent.children.indexOf( this ) - 1
				];
			},
			get nextElementSibling() {
				return this.parent.children[
					this.parent.children.indexOf( this ) + 1
				];
			},
			remove() {
				this.parent.children.splice(
					this.parent.children.indexOf( this ),
					1
				);
			},
			before( moved ) {
				moved.remove();
				this.parent.children.splice(
					this.parent.children.indexOf( this ),
					0,
					moved
				);
			},
			after( moved ) {
				moved.remove();
				this.parent.children.splice(
					this.parent.children.indexOf( this ) + 1,
					0,
					moved
				);
			},
		};
		row.buttons = Object.fromEntries(
			[ 'up', 'down', 'remove' ].map( ( action ) => [
				action,
				button( action, row ),
			] )
		);
		return row;
	};
	const add = button( 'add', null );
	const editor = {
		dataset: { sllMapping: key },
		locked: false,
		addEventListener( event, handler ) {
			handlers[ event ] = handler;
		},
		querySelector( query ) {
			if ( query === '.sll-mapping-rows' ) {
				return rows;
			}
			if ( query === '[data-sll-add-row]' ) {
				return add;
			}
			return { content: { cloneNode: () => makeRow( [ '', '', '' ] ) } };
		},
		click( target ) {
			handlers.click( { target: { closest: () => target } } );
		},
		input: () => handlers.input(),
		rows,
		add,
	};
	rows.append( makeRow( [ 'telephoneNumber', 'phone', 'string' ] ) );
	rows.append( makeRow( [ 'department', 'department', 'string' ] ) );
	return editor;
};
const editors = [ makeEditor( 'user_meta_data' ), makeEditor( 'role_rules' ) ];
vm.runInNewContext( source, {
	document: {
		querySelector: () => ( {
			querySelector: () => null,
			dataset: { sllDirectoryType: 'ol' },
			querySelectorAll: ( query ) =>
				query === '[data-sll-mapping]' ? editors : [],
		} ),
	},
} );
const editor = editors[ 0 ];
const original = editor.rows.children[ 0 ];
assert.equal( original.buttons.up.disabled, true );
assert.equal( editor.rows.lastElementChild.buttons.down.disabled, true );
editor.click( editor.add );
assert.equal( editor.rows.children.length, 3 );
const added = editor.rows.lastElementChild;
assert.equal( focused, added.inputs[ 0 ] );
assert.equal( added.inputs[ 1 ].required, false );
added.inputs[ 0 ].value = 'mail';
editor.input();
assert.equal( added.inputs[ 1 ].required, true );
added.inputs[ 1 ].value = 'contact';
added.inputs[ 2 ].value = 'string';
editor.click( added.buttons.up );
assert.equal( editor.rows.children[ 1 ], added );
editor.click( original.buttons.down );
assert.equal( editor.rows.children[ 0 ], added );
assert.equal( added.inputs[ 0 ].name, 'sll_settings[user_meta_data][0][0]' );
assert.equal( added.labels[ 0 ].htmlFor, added.inputs[ 0 ].id );
assert.equal( original.inputs[ 0 ].value, 'telephoneNumber' );
assert.equal( editors[ 1 ].rows.children.length, 2 );
editor.locked = true;
editor.click( added.buttons.remove );
assert.equal( editor.rows.children.length, 3 );
editor.locked = false;
while ( editor.rows.children.length ) {
	editor.click( editor.rows.children[ 0 ].buttons.remove );
}
assert.equal( focused, editor.add );
editor.click( editor.add );
assert.equal(
	editor.rows.children[ 0 ].inputs[ 0 ].name,
	'sll_settings[user_meta_data][0][0]'
);
assert.equal(
	editors[ 1 ].rows.children[ 0 ].inputs[ 0 ].name,
	'sll_settings[role_rules][0][0]'
);
process.stdout.write(
	'Mapping row add, reorder, remove, validation, and scope checks passed.\n'
);
