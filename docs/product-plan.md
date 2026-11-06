# Product and release plan

Research date: 2026-10-28. Package plan updated: 2026-11-02.

## Release decision

Build Free, Pro, and Enterprise from one codebase. Sell each paid ZIP through its matching product below. Central network settings stay free. The paid package checks verified product access and enables the features in that plan.

The 1.x upgrade path is narrow. Preserve saved settings, directory credentials, login rules, administrator recovery, and user data. Sites that use backup LDAP servers, SSO, or custom user fields must upgrade directly to Pro. There is no separate Legacy package, Transition package, or eligibility grant.

Group-to-role rules, profile updates, scheduled import and access removal, multisite membership rules, and audit history with export are implemented. Multiple directories and network templates remain planned work. PaidCommunities activation and license checks are implemented. Live purchase tests and a published release update test remain required. Do not open sales or distribute the Pro preview until its release checks pass.

## Free, Pro, and Enterprise features

This table defines the implemented plan split. Local checks use product access from the authenticated license response.

| Feature | Free | Pro — USD 99/year | Enterprise — USD 499/year |
| --- | --- | --- | --- |
| Active Directory and OpenLDAP sign-in | Yes | Yes | Yes |
| One directory server | Yes | Yes | Yes |
| Backup servers for one directory | No | Yes | Yes |
| TLS, custom ports, DNs, and search attributes | Yes | Yes | Yes |
| Direct, nested, and primary AD group checks | Yes | Yes | Yes |
| OpenLDAP group checks and login restrictions | Yes | Yes | Yes |
| Account creation and a fixed new-user role | Yes | Yes | Yes |
| LDAP-only login and local administrator recovery | Yes | Yes | Yes |
| Standard profile attributes | Yes | Yes | Yes |
| Site and central network settings | Yes | Yes | Yes |
| Connection and login tests, and security fixes | Yes | Yes | Yes |
| Server SSO with a trusted server identity | No | Yes | Yes |
| Custom metadata mappings and suffix settings | No | Yes | Yes |
| Group-to-role rules | No | Yes | Yes |
| Standard profile updates at login | No | Yes | Yes |
| Scheduled account import | No | No | Yes |
| Scheduled access removal and session removal | No | No | Yes |
| Multisite group membership and role rules | No | No | Yes |
| Audit history, retention, and CSV export | No | No | Yes |

Implemented paid account features are described in [Pro and Enterprise features](pro-features.md). Planned additions are multiple independent directories and routing rules, and network templates with controlled rollout. Priority support remains a possible addition; it is not part of the agreed feature split. Do not advertise planned additions as available.

Backup servers for one directory require an active Pro or Enterprise license. Free uses only the first configured server. Saved backup server addresses remain in place when a license expires or the site uses Free. Support for separate directories with their own connection and routing rules remains planned work.

Other possible Pro work includes integrations and new identity protocols. Decide what to build after customer research, then design and test it separately. These ideas are not part of the first Pro feature list.

Do not make security fixes, TLS, recovery, or basic group restrictions paid features. Do not add user count or login count limits to free installations.

Use a separate ZIP for each tier. Pro costs USD 99 per year for one site. Enterprise costs USD 499 per year for one site. PaidCommunities uses a separate product for each plan, grouped in one product family. Willingness to pay has not been tested.

### Implementation status and checks before sales

- Completed: check each build through its fixed authenticated product request, reject `invalid_product` even when the license status is active, save the verified product in local license state, and keep remote requests outside login. Real test keys passed activation, refresh, deactivation, and Pro denial for Enterprise on 2026-11-02.
- Completed: include backup LDAP servers, SSO, custom user fields, group role rules, and profile updates in Pro. Require Enterprise for scheduled jobs, multisite membership, and audit history, retention, and CSV export.
- Completed: show both feature lists and direct checkout links in Free. Show the verified plan and Enterprise purchase prompt in the paid License tab.
- Completed: test product access, fixed build product IDs, missing and unknown products, renewal, expiry, service errors, and retained account suspensions with controlled responses.
- Pending: live PaidCommunities purchases and product changes, refunds, cancellation, site limits, staging, network activation, and export tests.
- Completed: initialize PluginConfig, connect its update controller to the paid package update URI, and bridge SDK license storage to encrypted site or network records.
- Pending: a published release download and installation test, and the pricing-page feature storage fix. No release is assigned by this task.

Activation uses one fixed product ID for the installed build. A mismatched key stops activation. There is no retry with another product. Only a verified active entitlement enables a feature. Missing or unknown products keep paid features off and show a license-check prompt. A service error keeps the last verified state. Enterprise controls are disabled for Pro and link to the License tab; saved values remain. The pricing-page editor still does not retain its saved feature cards. See the catalog issue below.

## Package design

The free package keeps the `simple-ldap-login` directory and `Simple-LDAP-Login.php` entry file. Continue its WordPress.org update path. Use Composer for dependency declarations, autoloading, and development checks. Include the required production files in the release ZIP; site owners must not run Composer.

Maintain one codebase and build three packages:

| Package | Purpose | Update path |
| --- | --- | --- |
| Free | LDAP password login and the shared features listed above | WordPress.org |
| Pro | Pro features with a Pro product license | PaidCommunities SDK; published release update test pending |
| Enterprise | Pro and Enterprise features with an Enterprise product license | PaidCommunities SDK; published release update test pending |

All three use the `simple-ldap-login` folder and `Simple-LDAP-Login.php` entry file. Install one package. Pro replaces Free and does not need an add-on.

Keep Pro code in `pro/`, outside the Free ZIP. `build:free`, `build:pro`, and `build:enterprise` use temporary folders and `wp-scripts plugin-zip`. The default `npm run plugin-zip` builds Free. Shared classes use the `SimpleLdapLogin\` PSR-4 namespace; Pro classes use `SimpleLdapLogin\Pro\`. Pro has its own Composer manifest and lock file.

Use PaidCommunities for Pro licensing, downloads, and updates. Each paid package uses `Update URI: https://paidcommunities.com` and initializes the SDK update controller. Free retains its WordPress.org update path. Test an actual published release download and installation before public distribution.

## Upgrades from 1.x

For LDAP password login, upgrade to any edition. If SSO is enabled or custom user fields are configured, install the matching Pro or Enterprise ZIP directly. Do not first install Free and assume those features will keep working. Free retains their settings and shows an administrator notice, but does not include their implementation.

Test the direct upgrade on a site copy. Keep the old settings and a migration backup in their original scope. Check LDAP password login, SSO when used, metadata values and local edits, required groups, and local administrator recovery. The emergency `SLL_DISABLE_LDAP` switch remains available.

An active Pro installation rejects replacement ZIPs that lack its feature files and restores activation if WordPress deactivated it before the check. Administrators can choose Free by deactivating Pro first. This guard does not exist in 1.x and cannot protect a direct 1.x-to-Free replacement.

## Settings and updates

Keep `sll_settings` as the settings key. Read and write it in the same site or network scope that the existing activation uses. Use older `simpleldap_*` options to fill settings keys that are missing from the current array. Never treat a new installation as an old enabled installation.

Before a migration writes data, retain the original values in `sll_settings_backup_v1` in the same scope. Make the migration safe to repeat. Keep unknown keys so that custom integrations do not lose data. Keep old options for rollback. Preserve false values, custom attributes, group restrictions, user roles, and metadata suffixes. Do not copy network credentials into each site's options.

Local recovery access is a shared Free feature with three modes: Selected administrators, All administrators (legacy), and Off. Selected accounts use WordPress authentication directly and cannot be used by LDAP password login or server SSO. A network selection is limited to super administrators; a site selection is limited to administrators of that site. New setups need a selected account or an explicit Off choice before LDAP or SSO is enabled. Existing installations retain the legacy mode. Schema version 3 adds this policy without changing existing backups or secrets.

For emergency access, set `define( 'SLL_DISABLE_LDAP', true );` in `wp-config.php` before WordPress loads. This skips LDAP password and SSO hooks so a local WordPress administrator can sign in. It does not recover an unknown local password. Remove the constant after the directory setup is safe.

Keep the two custom metadata keys: one for the LDAP source value and one for the WordPress value. Update the WordPress value only when the LDAP source changes. Keep local edits between source changes.

Keep existing public hooks where they remain safe. Document each changed or removed method, filter, and global API before release. Keep the current settings format unless a feature requires a change.

Security changes can require administrator action. Examples include failed TLS validation, an unsafe metadata target, an unreadable stored secret, or an untrusted SSO identity. Show a clear settings error and a recovery action. Do not silently remove group rules, allow local password fallback, or authenticate an unknown identity to make an old configuration appear to work.

## WordPress.org rules

The directory package must work without payment. It must not include paid code that a key unlocks. A remote service used only to check a license is not a permitted SaaS workaround. Keep Pro code outside that package. [Detailed Plugin Guidelines, rules 5 and 6](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)

External tracking needs consent. Describe service calls and their data. Keep promotion within the plugin's own settings and make it restrained. The free plugin must not act as an independent downloader of paid code. [Detailed Plugin Guidelines, rules 7, 8, and 11](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)

A connected service can install a purchased package at the user's explicit request. The service must make its role and the external package source clear. An upgrade flow needs those controls; a license check alone does not authorize installation. [Plugins Team clarification](https://make.wordpress.org/plugins/2017/03/16/clarification-of-guideline-8-executable-code-and-installs/)

The directory package excludes SSO and custom field code. Sites that need those features, including 1.x upgrades, use Pro. Review the final packages against the directory rules before distribution; an upgrade notice alone does not establish compliance.

Do not include an external updater in the directory package. Include `composer.json` and required runtime files. Exclude development tools, test folders, and unused files from the distributed ZIP. [Common issues](https://developer.wordpress.org/plugins/wordpress-org/common-issues/)

Keep the existing directory listing for the rewrite. Do not submit a duplicate free plugin. [Plugin Developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/)

## Source license and commercial access

Use GPL-2.0-or-later for all editions. Include the license text and third-party notices. The source license and the paid service term are separate: a commercial key gives access to updates, support, and account services. It does not remove the user's GPL rights or prevent copying. [WordPress license](https://wordpress.org/about/license/)

Paid features require an active license for a recognized product. Pro enables SSO, field and role updates, and profile updates. Enterprise also enables scheduled jobs, membership rules, and audit collection, retention, and export. A confirmed expired or inactive license stops these paid features. Existing suspensions and directory identity checks remain enforced. LDAP password login, login group restrictions, and local recovery accounts still work. Keep saved settings, user data, audit records, and existing sessions. A verified renewal restores the licensed features. Explain this policy before purchase.

The SDK refreshes licenses through WordPress plugin-update checks, normally twice daily. There is no separate plugin license schedule. `License::plan()` and login read only local state. A service error keeps the last verified status and product access, including an expired status. Missing and unknown product access keeps paid features off. See the [license implementation and remaining service checks](licensing.md).

This expiry policy is a product decision, not a general WordPress.org rule.

## License service choice

Use PaidCommunities for Pro. Complete the SDK, outage, and data export checks below before sales start. Use EDD only if a confirmed problem prevents safe use of PaidCommunities. Do not build a licensing backend for this release.

### PaidCommunities catalog setup

Updated on 2026-10-30:

- Pro product: [Simple LDAP Login Pro](https://admin.paidcommunities.com/products/prd_9QyrKxQpvZGrcCW8), ID `prd_9QyrKxQpvZGrcCW8`, slug `simple-ldap-login-pro`.
- Pro annual plan: `plan_9R8xkMlq1nmCekmG`, USD 99 per year, one site. Pro remains the default purchase choice, as the user requested.
- Enterprise product: [Simple LDAP Login Enterprise](https://admin.paidcommunities.com/products/prd_9R92jB1prxMfJR36), ID `prd_9R92jB1prxMfJR36`. Status: Draft, without a release.
- Enterprise annual plan: `plan_9R937DcDDy4wMH4x`, USD 499 per year, one site.
- Product family: **Simple LDAP Login**, ID `prgrp_9R8wuq4vA3Dtc9b8`, with exactly the Pro and Enterprise products. The name and membership were verified after reload. Separate products carry separate feature entitlements. See [product families](https://paidcommunities.com/documentation/product-families/).
- The previous USD 499 plan, `plan_9QysG8ogD3yHM8sO`, remains under the Pro product for existing records. Its Inactive status was verified in the dashboard.
- No release is assigned by this task. The earlier release record was 2.0.0, Pending, named `Version 2.0.0 — internal preview`, with file `simple-ldap-login-pro-2.0.0.zip`. Recheck that record before use.
- The earlier preview record listed WordPress 6.5, PHP 8.0, and `Tested up to` 7.1.3. Verify the current package requirements and update the release fields before sale.
- The prior record said Stripe was not connected. Check current payment methods and complete live service tests before sales. License activation, product access checks, and feature restrictions are implemented. A published release update test remains required.
- The hosted preview ZIP predates the SSO proof correction and product access checks. Replace it with a rebuilt paid ZIP after the release and live service checks pass.
- On 2026-11-02, backup LDAP servers moved to Pro and Enterprise. The Pro product description was updated in PaidCommunities and verified after reload. Free uses only the first saved server; it keeps backup addresses for later use with a paid license.

Checkout URLs:

- Pro: `https://checkout.paidcommunities.com/checkout/prd_9QyrKxQpvZGrcCW8?plan=plan_9R8xkMlq1nmCekmG`
- Enterprise: `https://checkout.paidcommunities.com/checkout/prd_9R92jB1prxMfJR36?plan=plan_9R937DcDDy4wMH4x`

`Commerce::checkout_url()` uses Pro by default. Passing `enterprise` selects Enterprise. Each link specifies its product and annual plan. The paid License tab offers **Get Enterprise** only to active Pro users. It opens checkout for a new license and does not change or cancel the Pro subscription. Manage that subscription separately through PaidCommunities.

### Pricing-page editor issue

The visible browser Network panel confirmed that saving to `/v1/price_page_designs/prd_9QyrKxQpvZGrcCW8` returns HTTP 200. The response includes the entered text in `json_content`, but `account_id`, `parent_id`, and `theme` are null. The editor and customer page lose the feature cards after reload. A successful save response is not proof that these cards are retained.

The issue was also reproduced in the product family editor on 2026-10-30. After Save and reload, both the title and feature fields were blank. The separate Pro and Enterprise product descriptions retained their feature lists and were verified after reload. Enterprise includes Pro's five feature groups plus scheduled import and access removal, multisite membership, and audit history with CSV export. Both plans cover one site.

**PaidCommunities TODO:** Correct the pricing-page design storage, then save and verify the title and both feature lists in the family editor and on the customer page. The saved product descriptions provide the feature lists while the storage issue remains open. A provider support review may be required. No support message has been sent.

### Service comparison

| Choice | Confirmed support | Cost and work |
| --- | --- | --- |
| PaidCommunities | Hosted sales, subscriptions, licenses, customer management, and updates | Its pricing states no platform fee or revenue share. Stripe processing fees remain. Verify the operating and exit terms below. |
| EDD Software Licensing | Keys, activation, validation, renewals, updates, beta versions | Maintain the WordPress store, backups, security, and hosting. The current Professional price is $299.50 for the first year, with a stated normal price of $599 and full-price renewals. Payment fees are extra. |
| New backend | Only what we build | Maintain billing events, key recovery, site counts, refunds, renewals, downloads, backups, abuse controls, and support tools. This work does not improve LDAP sign-in. |

Sources: [EDD Software Licensing](https://easydigitaldownloads.com/downloads/software-licensing/), [EDD pricing](https://easydigitaldownloads.com/pricing/), [PaidCommunities pricing](https://paidcommunities.com/pricing/), [PaidCommunities service description](https://paidcommunities.com/).

EDD provides `activate_license`, `deactivate_license`, `check_license`, and `get_version`. The developer controls the effect of a key's state on installed software. Expired or disabled keys cannot download updates. Use HTTPS POST requests for keys, and keep them out of logs. [EDD API](https://easydigitaldownloads.com/docs/software-licensing-api/)

EDD documents local and staging exceptions. It can export keys, status, customer details, dates, and activations. Check that an export and restore work before launch. [Staging sites](https://easydigitaldownloads.com/docs/wordpress-staging-site-support/), [license export](https://easydigitaldownloads.com/docs/software-licensing-usage-instructions/)

PaidCommunities documents the Composer package `paidcommunities/wordpress-sdk`, activation controls, and update checks. Each paid ZIP uses its services for activation, status, and product access checks. It reads the saved verified product to enable the correct features. It never makes a service request during login. Its domain example includes site, version, IP address, and check times. [PaidCommunities WordPress SDK](https://paidcommunities.com/documentation/sdks/wordpress-sdk/)

The upgrade from Free is purchase through the Go Pro tab, receipt download, and installation of the matching paid ZIP at the existing plugin path, without a separate add-on. Free uses direct checkout links. The paid ZIP uses the official activation component with fixed build product IDs. First-time installation uses the WordPress ZIP upload screen; license activation does not install code. The reviewed SDK paths handle license activation and updates; their release scripts build the SDK itself. Check the hosted deployment tools before adding equivalent build or installation code here. This does not change the choice of PaidCommunities.

The SDK review used commit `4bfaef0967c01daf720fd5182d87fe91451ca20d`. Its `VERSION` file says `0.0.4`. The manifest declares MIT and no minimum PHP version. Test that version on PHP 8.1. The GitHub releases list was empty when checked. [SDK source](https://github.com/paidcommunities/paidcommunities-wordpress-sdk/tree/4bfaef0967c01daf720fd5182d87fe91451ca20d), [manifest](https://github.com/paidcommunities/paidcommunities-wordpress-sdk/blob/4bfaef0967c01daf720fd5182d87fe91451ca20d/composer.json)

The SDK deactivation handler deletes local license data after an exception, including a failed nonce or permission check. A local test confirmed deletion with a missing nonce. The paid package initializes the SDK and retains that deletion behavior by design. An SDK license adapter applies its writes and deletions to the encrypted record in the current site or network scope. The activation component and PHP fallback use the native SDK handlers for the installed product. Status writes use the SDK update controller without duplicate requests. Free has no activation form. The SDK dependency is pinned at `0.0.4`. [Inspected handler](https://github.com/paidcommunities/paidcommunities-wordpress-sdk/blob/4bfaef0967c01daf720fd5182d87fe91451ca20d/src/Admin/AdminAjaxController.php)

The update controller catches API exceptions without deleting stored license data. Its default response cache lasts 60 seconds, and its HTTP client has a 30-second timeout. The plugin limits SDK HTTP requests to ten seconds through WordPress filters. Controlled outage tests cover the native handlers and updater. SDK 0.0.4 has no separate license scheduler; it uses WordPress plugin-update checks. Keep license requests outside login and keep login working during remote errors. [Update controller](https://github.com/paidcommunities/paidcommunities-wordpress-sdk/blob/4bfaef0967c01daf720fd5182d87fe91451ca20d/src/UpdateController.php), [cache](https://github.com/paidcommunities/paidcommunities-wordpress-sdk/blob/4bfaef0967c01daf720fd5182d87fe91451ca20d/src/Cache.php), [HTTP client](https://github.com/paidcommunities/paidcommunities-wordpress-sdk/blob/4bfaef0967c01daf720fd5182d87fe91451ca20d/src/HttpClient/WordPressClient.php)

Before the PaidCommunities integration ships, confirm these points with the provider:

- Can all keys, activations, subscriptions, and customer records be exported and restored elsewhere?
- Can current keys and payment subscriptions move without customer action?
- What happens during an API outage, and how long does the client retain a known status?
- How are multisite, staging sites, local sites, and domain changes counted?
- What are the service, data storage, privacy, support, and shutdown terms?
- Does the current SDK support PHP 8.1, and what does it send before activation?
- Who calculates, collects, and remits sales tax? Do not assume merchant-of-record service.

PaidCommunities states that customer data can be exported. The export fields and transfer process still need a test. The source review covered selected SDK files; it was not a full audit or a live service test. The remaining questions are open checks, not confirmed faults. [PaidCommunities data statement](https://paidcommunities.com/)

Request only the data needed for license validation and updates. Never send LDAP hosts, bind credentials, user passwords, or directory records. Show the external service and data sent before the administrator activates it. Obtain any required service terms and privacy details before sales start.

## Release order

1. Complete the refactor, settings migration, Pro extraction, and security checks.
2. Test Free, Pro, and Enterprise with OpenLDAP, Samba AD, multisite, and trusted server SSO. Test direct 1.x upgrades to the correct package.
3. Complete live PaidCommunities purchase tests and a published Pro release update test. Keep license calls outside login.
4. Publish Free and start Pro sales after the release and service checks pass. State clearly that 1.x sites using backup LDAP servers, SSO, or custom user fields need Pro.
5. Build and test later Pro features separately. Advertise each only after it is available.

## Final API decision: 2026-11-02

Live client API probes found no usable product group or billing plan route. Group registration fails, and update checks ignore plan fields. Use separate Free, Pro, and Enterprise ZIPs with one fixed product ID per paid build. Keep the shared paid code in `pro/`. See the [request results](licensing.md#product-group-and-billing-plan-api-test-2026-11-02).
