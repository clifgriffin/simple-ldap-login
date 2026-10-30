( () => {
	const config = window.sllCommerce;
	const root = document.getElementById( 'sll-license-component' );
	if ( ! config || ! root ) {
		return;
	}

	const { LicenseActivation } = window.paidcommunities.wp.components;
	const api = async ( task, data ) => {
		try {
			const response = await window.fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: new URLSearchParams( {
					...data,
					action: 'sll_license',
					sll_scope: config.scope,
					sll_license_task: task,
					sll_license_key: data.license_key || '',
				} ),
			} );
			const result = await response.json();
			if ( result.success === true && result.data?.license ) {
				return result;
			}
			return {
				success: false,
				error: {
					code: 'license_error',
					message: result.data?.message || config.i18n.licenseError,
				},
			};
		} catch {
			return {
				success: false,
				error: {
					code: 'license_error',
					message: config.i18n.licenseError,
				},
			};
		}
	};
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
			apiService: {
				activate: ( basename, data ) => api( 'activate', data ),
				deactivate: ( basename, data ) => api( 'deactivate', data ),
			},
			onActivate: () => window.location.reload(),
			onDeactivate: () => window.location.reload(),
		} )
	);
	root.hidden = false;
	document.getElementById( 'sll-license-fallback' ).hidden = true;
} )();
