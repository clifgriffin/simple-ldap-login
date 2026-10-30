# Pro licenses

SSO, custom LDAP user fields, group role rules, profile updates, scheduled account jobs, multisite membership rules, and audit collection and export require an active PaidCommunities license. Free includes the purchase and license client, but excludes the paid feature code. LDAP password login in Free does not require a license.

| License state | Pro behavior |
| --- | --- |
| Not activated | Pro features are off. |
| Active | Pro features run when enabled in settings. |
| Expired or inactive | Pro updates, jobs, and audit collection and export stop. Saved settings, data, and suspensions remain. |
| Renewed | A successful check restores both features. |
| Service error | Keep the last verified state. An expired license stays expired. |

LDAP password login, account creation, group checks, and local recovery accounts remain available in every state. Expiry stops Pro account changes and audit collection and export. Existing account suspensions and directory identity checks remain enforced. Existing user data and audit records stay in place. Expiry does not end existing WordPress sessions. Settings remain editable.

## Activation and checks

Use the License tab to activate, check, or deactivate a license. Activation sends the key, the configured site domain, and the plugin version to PaidCommunities. The service also receives the server IP address. The original key is not stored. The site activation secret is encrypted with the same key source used for LDAP credentials. License data uses `sll_pro_license`, separate from the public LDAP settings API.

The shared Composer dependency is `paidcommunities/wordpress-sdk` at `0.0.4`. The build places the SDK under `SimpleLdapLoginVendor`. Both editions use its local `LicenseActivation` React component and domain and plugin services. They do not initialize `PluginConfig`, the SDK admin handlers, or its updater. The component calls the checked `wp_ajax_sll_license` handler through its `apiService` prop. A normal form remains available if JavaScript fails. The shared HTTP client checks HTTP status and JSON data, blocks redirects, and uses a ten-second timeout. Keys, secrets, remote error bodies, LDAP settings, and directory records are not logged.

The SDK's authenticated `plugins/update_check` endpoint returns `license.status`. Only `active`, `inactive`, and `expired` are accepted. This endpoint checks a license even though the preview does not yet install external updates. See the [SDK source](https://github.com/paidcommunities/paidcommunities-wordpress-sdk/blob/4bfaef0967c01daf720fd5182d87fe91451ca20d/src/UpdateController.php) and [documented license states](https://paidcommunities.com/documentation/objects/license/).

Activation schedules `sll_pro_license_check` hourly through WP-Cron. Login reads the saved state and never calls PaidCommunities. Confirmed expiry takes effect after a successful check. WP-Cron needs site traffic or a server cron task. A delayed task delays expiry detection. Administrators can select **Check license** after renewal.

License data uses site options for a site activation and network options for a network activation. Network checks run on the main site. A copy at a different configured site address needs its own activation. The service controls activation limits; live staging and multisite terms still need acceptance tests.

The admin handler requires POST, `manage_options` or `manage_network_options`, a valid nonce, and the correct settings scope. Failed permission checks do not read keys, call the service, or clear local activation data. Failed remote deactivation also keeps local data.

## Checks and release limits

`php tests/licenses.php` tests the real SDK with controlled HTTP responses. It covers activation, expiry, renewal, inactive licenses, timeouts, invalid JSON, HTTP errors, unknown states, storage failure, scope, secret storage, and copied activations. Admin tests cover permissions, nonces, and form output. Docker tests run the installed ZIP with WordPress, OpenLDAP, and Samba. They check password login, recovery, metadata, the private SDK, and HTTP SSO after expiry.

The tests do not use a live customer license. Before release, check a real purchase, expiry, renewal, cancellation, refund, site limits, staging, and network activation with PaidCommunities. Complete the external updater separately. Keep the product in Draft until those checks pass.

## Purchases from Free

The Go Pro tab lists all eight Pro feature groups. Without an active license, opening the tab loads the official purchase SDK at `https://js.paidcommunities.com/v1`. The SDK renders `Pricing(License::PRODUCT)` in the page without a separate load button. One small, gray notice below the license controls explains the data shared with PaidCommunities. An active license hides plans and keeps the Pro installation instructions.

The purchase SDK checks message IDs. The plugin also checks the message origin and source, and permits redirects only to `https://checkout.paidcommunities.com`. The checkout then handles payment on that domain. A direct hosted pricing link remains available if the SDK cannot load. The purchase SDK and WordPress component SDK share a global name; the wrapper keeps the WordPress component namespace when the purchase SDK loads.

After purchase, install the Pro ZIP from the receipt through WordPress. Free does not download or install executable code. Activating a license in Free preserves the same encrypted record for Pro. Base LDAP login remains free in every license state.

Browser checks confirmed that the official component shows the $499 annual plan and that Buy Now opens its checkout. The live checkout currently returns `This product is not published.` The product is Draft, its release is Pending, and payment methods are disabled. Publish a tested release, enable a payment method, and complete live account checks before sales. Automatic Pro updates remain separate unfinished work.
