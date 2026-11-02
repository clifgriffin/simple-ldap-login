const settings = document.querySelector( '.sll-admin' );
if ( settings ) {
	const directory = settings.querySelector( '#sll-directory' );
	const updateFields = () => {
		const type = directory
			? directory.value
			: settings.dataset.sllDirectoryType;
		settings
			.querySelectorAll( '[data-sll-directory]' )
			.forEach( ( field ) => {
				field.hidden = field.dataset.sllDirectory !== type;
				field
					.querySelectorAll( 'input, select, textarea' )
					.forEach( ( input ) => {
						input.disabled = field.hidden;
					} );
			} );
	};
	if ( directory ) {
		directory.addEventListener( 'change', updateFields );
	}
	updateFields();

	settings.querySelectorAll( '[data-sll-mapping]' ).forEach( ( editor ) => {
		const rows = editor.querySelector( '.sll-mapping-rows' );
		const add = editor.querySelector( '[data-sll-add-row]' );
		const updateRows = () => {
			Array.from( rows.children ).forEach( ( row, index ) => {
				const inputs = row.querySelectorAll( '[data-sll-column]' );
				const required = Array.from( inputs ).some(
					( input ) => input.value.trim() !== ''
				);
				inputs.forEach( ( input ) => {
					const column = input.dataset.sllColumn;
					input.name = `sll_settings[${ editor.dataset.sllMapping }][${ index }][${ column }]`;
					input.id = `sll-${ editor.dataset.sllMapping }-${ index }-${ column }`;
					input.required = required;
					row.querySelector(
						`[data-sll-column-label="${ column }"]`
					).htmlFor = input.id;
				} );
				row.querySelector( '.sll-mapping-actions' ).hidden = false;
			} );
		};
		editor.addEventListener( 'click', ( event ) => {
			const button = event.target.closest( 'button' );
			if ( ! button || button.matches( ':disabled' ) ) {
				return;
			}
			if ( button === add ) {
				rows.append(
					editor.querySelector( 'template' ).content.cloneNode( true )
				);
				updateRows();
				rows.lastElementChild.querySelector( 'input' ).focus();
				return;
			}
			const row = button.closest( '.sll-mapping-row' );
			if ( ! row ) {
				return;
			}
			const focus = row.nextElementSibling || row.previousElementSibling;
			row.remove();
			updateRows();
			( focus ? focus.querySelector( 'input' ) : add ).focus();
		} );
		editor.addEventListener( 'input', updateRows );
		editor.addEventListener( 'change', updateRows );
		add.hidden = false;
		updateRows();
	} );
}
