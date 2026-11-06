( () => {
	const config = window.sllCommerce;
	const root = document.getElementById( 'sll-license-component' );
	if ( ! config || ! root ) {
		return;
	}

	const { LicenseActivation } = window.paidcommunities.wp.components;
	// Keep the SDK input private and give it an accessible label.
	new window.MutationObserver( () => {
		const input = root.querySelector( 'input' );
		if ( input ) {
			input.type = 'password';
			input.autocomplete = 'new-password';
			input.setAttribute( 'aria-label', config.i18n.licenseKey );
		}
	} ).observe( root, { childList: true, subtree: true } );
	window.wp.element.createRoot( root ).render(
		window.wp.element.createElement( LicenseActivation, {
			config,
			onActivate: () => window.location.reload(),
			onDeactivate: () => window.location.reload(),
		} )
	);
	root.hidden = false;
	document.getElementById( 'sll-license-fallback' ).hidden = true;
} )();
