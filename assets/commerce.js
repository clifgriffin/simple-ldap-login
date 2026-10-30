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

	const button = document.getElementById( 'sll-load-pricing' );
	if ( ! button ) {
		return;
	}
	const container = document.getElementById( 'sll-pricing' );
	const status = document.getElementById( 'sll-pricing-status' );
	const checkoutOrigin = 'https://checkout.paidcommunities.com';
	let frame;
	// The purchase SDK checks the frame name. Also check its sender and redirect.
	window.addEventListener(
		'message',
		( event ) => {
			if ( ! frame || event.data?.id !== frame.name ) {
				return;
			}
			let trusted =
				event.origin === checkoutOrigin &&
				event.source === frame.contentWindow;
			if ( event.data.event === 'redirect' ) {
				try {
					trusted =
						trusted &&
						new URL( event.data.payload?.url ).origin ===
							checkoutOrigin;
				} catch {
					trusted = false;
				}
			}
			if ( ! trusted ) {
				event.stopImmediatePropagation();
			}
		},
		true
	);
	button.addEventListener( 'click', () => {
		button.disabled = true;
		status.hidden = false;
		status.textContent = config.i18n.pricingLoading;
		const previous = window.paidcommunities;
		const script = document.createElement( 'script' );
		// Service code loads only after the administrator selects View plans.
		script.src = 'https://js.paidcommunities.com/v1';
		script.referrerPolicy = 'no-referrer';
		const failed = () => {
			status.hidden = false;
			status.textContent = config.i18n.pricingError;
		};
		script.onerror = failed;
		script.onload = () => {
			try {
				window.paidcommunities = {
					...previous,
					...window.paidcommunities,
				};
				const pricing = window.paidcommunities.Pricing(
					config.product
				);
				const timeout = window.setTimeout( failed, 15000 );
				pricing.on( 'onReady', () => {
					window.clearTimeout( timeout );
					status.hidden = true;
					button.hidden = true;
				} );
				pricing.render( container );
				frame = container.querySelector( 'iframe' );
				frame.title = config.i18n.pricingTitle;
			} catch {
				failed();
			}
		};
		document.head.appendChild( script );
	} );
} )();
