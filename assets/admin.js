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
}
