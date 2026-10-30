# Product and release plan

Research date: 2026-10-28. Package plan updated: 2026-10-30.

## Release decision

Build only Free and Pro. Server SSO and custom user fields require Pro. Central network settings stay free.

The 1.x upgrade path is narrow. Preserve saved settings, directory credentials, login rules, administrator recovery, and user data. Sites that use SSO or custom user fields must upgrade directly to Pro. There is no separate Legacy package, Transition package, or eligibility grant.

Multiple directories, role rules, scheduled jobs, audit records, and network templates remain planned work. PaidCommunities licensing and external updates are not implemented. Do not open sales or distribute the Pro preview until its release checks pass.

## Free and Pro features

| Feature | Free | Pro |
| --- | --- | --- |
| Active Directory and OpenLDAP sign-in | Yes | Yes |
| Multiple controllers for one directory | Yes | Yes |
| TLS, custom ports, DNs, and search attributes | Yes | Yes |
| Direct, nested, and primary AD group checks | Yes | Yes |
| OpenLDAP group checks and login restrictions | Yes | Yes |
| Account creation and a fixed new-user role | Yes | Yes |
| LDAP-only login and local administrator recovery | Yes | Yes |
| Standard profile attributes | Yes | Yes |
| Site and central network settings | Yes | Yes |
| Connection and login tests, and security fixes | Yes | Yes |
| Server SSO with a trusted server identity | No | Yes |
| Custom metadata mappings and suffix settings | No | Yes |

Planned Pro additions are multiple independent directories and routing rules; group-to-role rules; scheduled import and profile updates; scheduled account suspension and session removal; audit retention and export; and network templates with controlled rollout. Priority support will also be part of Pro. Do not advertise the planned additions as available.

Multiple controllers for one directory stay free. Pro is planned to support separate directories with their own connection and routing rules.

Other possible Pro work includes integrations and new identity protocols. Decide what to build after customer research, then design and test it separately. These ideas are not part of the first Pro feature list.

Do not make security fixes, TLS, recovery, or basic group restrictions paid features. Do not add user count or login count limits to free installations.

Start with one Pro package and annual access to updates and support. Plans can cover different site counts while using the same code. The initial draft plan is USD 499 per year for one site. Willingness to pay has not been tested.

## Package design

The free package keeps the `simple-ldap-login` directory and `Simple-LDAP-Login.php` entry file. Continue its WordPress.org update path. Use Composer for dependency declarations, autoloading, and development checks. Include the required production files in the release ZIP; site owners must not run Composer.

Maintain one codebase and build two packages:

| Package | Purpose | Update path |
| --- | --- | --- |
| Free | LDAP password login and the shared features listed above | WordPress.org |
| Pro | Free features plus SSO and custom user fields | PaidCommunities; integration not implemented |

Both use the `simple-ldap-login` folder and `Simple-LDAP-Login.php` entry file. Install one package. Pro replaces Free and does not need an add-on.

Keep Pro code in `pro/`, outside the Free ZIP. `build:free` and `build:pro` use temporary folders and `wp-scripts plugin-zip`. The default `npm run plugin-zip` builds Free. Shared classes use the `SimpleLdapLogin\` PSR-4 namespace; Pro classes use `SimpleLdapLogin\Pro\`. Pro has its own Composer manifest and lock file.

Use PaidCommunities for Pro licensing, downloads, and updates. The Pro preview uses `Update URI: false` to prevent replacement by a WordPress.org update. It has no automatic external updates. Complete and test the external updater before public distribution.

## Upgrades from 1.x

For LDAP password login, upgrade to Free or Pro. If SSO is enabled or custom user fields are configured, install Pro directly. Do not first install Free and assume those features will keep working. Free retains their settings and shows an administrator notice, but does not include their implementation.

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

For this product, an expired key or a license service outage must not stop sign-in, remove active access rules, or change saved settings. Do not put license calls in the login path. Allow installed Pro features to continue. Expiry can stop new Pro downloads and support. Explain this policy before purchase.

This expiry policy is a product decision, not a general WordPress.org rule.

## License service choice

Use PaidCommunities for Pro. Complete the SDK, outage, and data export checks below before sales start. Use EDD only if a confirmed problem prevents safe use of PaidCommunities. Do not build a licensing backend for this release.

### Draft product setup

Confirmed on 2026-10-29:

- Product: [Simple LDAP Login Pro](https://admin.paidcommunities.com/products/prd_9QyrKxQpvZGrcCW8), ID `prd_9QyrKxQpvZGrcCW8`.
- Slug: `simple-ldap-login-pro`. Type: Plugin. Status: Draft.
- Plan: Single site, USD 499 per year for one site, no trial, no sale price.
- Release 2.0.0: Pending, named `Version 2.0.0 — internal preview`, with file `simple-ldap-login-pro-2.0.0.zip`. It has not been released or assigned for sale.
- The preview requires WordPress 6.5 and PHP 8.0. Its `Tested up to` field is 7.1.3, the local test version recorded in `docs/testing.md`.
- Stripe is not connected. Licensing and automatic updates still need implementation and tests before public release.
- The hosted preview ZIP predates the SSO proof correction found by the expanded tests. Replace it with a rebuilt Pro ZIP before release.

### Service comparison

| Choice | Confirmed support | Cost and work |
| --- | --- | --- |
| PaidCommunities | Hosted sales, subscriptions, licenses, customer management, and updates | Its pricing states no platform fee or revenue share. Stripe processing fees remain. Verify the operating and exit terms below. |
| EDD Software Licensing | Keys, activation, validation, renewals, updates, beta versions | Maintain the WordPress store, backups, security, and hosting. The current Professional price is $299.50 for the first year, with a stated normal price of $599 and full-price renewals. Payment fees are extra. |
| New backend | Only what we build | Maintain billing events, key recovery, site counts, refunds, renewals, downloads, backups, abuse controls, and support tools. This work does not improve LDAP sign-in. |

Sources: [EDD Software Licensing](https://easydigitaldownloads.com/downloads/software-licensing/), [EDD pricing](https://easydigitaldownloads.com/pricing/), [PaidCommunities pricing](https://paidcommunities.com/pricing/), [PaidCommunities service description](https://paidcommunities.com/).

EDD provides `activate_license`, `deactivate_license`, `check_license`, and `get_version`. The developer controls the effect of a key's state on installed software. Expired or disabled keys cannot download updates. Use HTTPS POST requests for keys, and keep them out of logs. [EDD API](https://easydigitaldownloads.com/docs/software-licensing-api/)

EDD documents local and staging exceptions. It can export keys, status, customer details, dates, and activations. Check that an export and restore work before launch. [Staging sites](https://easydigitaldownloads.com/docs/wordpress-staging-site-support/), [license export](https://easydigitaldownloads.com/docs/software-licensing-usage-instructions/)

PaidCommunities documents the Composer package `paidcommunities/wordpress-sdk`, activation controls, and update checks. Its sample uses `isActive()` to enable features. Do not apply that sample to sign-in. Its domain example includes site, version, IP address, and check times. Inspect outgoing data before use. [PaidCommunities WordPress SDK](https://paidcommunities.com/documentation/sdks/wordpress-sdk/)

The target upgrade is purchase, authorization, and installation of the Pro package without a separate add-on. PaidCommunities package generation and automatic first-time Pro installation remain unverified. The reviewed SDK paths handle license activation and updates; their release scripts build the SDK itself. Check the hosted deployment tools before adding equivalent build or installation code here. This does not change the choice of PaidCommunities.

The SDK review used commit `4bfaef0967c01daf720fd5182d87fe91451ca20d`. Its `VERSION` file says `0.0.4`. The manifest declares MIT and no minimum PHP version. Test that version on PHP 8.1. The GitHub releases list was empty when checked. [SDK source](https://github.com/paidcommunities/paidcommunities-wordpress-sdk/tree/4bfaef0967c01daf720fd5182d87fe91451ca20d), [manifest](https://github.com/paidcommunities/paidcommunities-wordpress-sdk/blob/4bfaef0967c01daf720fd5182d87fe91451ca20d/composer.json)

Fix the SDK deactivation handler before use. It deletes local license data after an exception, including a failed nonce or permission check. A local test confirmed deletion with a missing nonce. Use a corrected SDK release, or replace its admin handlers with handlers that check permissions and nonces. The test covered the SDK handler, not the hosted service. [Inspected handler](https://github.com/paidcommunities/paidcommunities-wordpress-sdk/blob/4bfaef0967c01daf720fd5182d87fe91451ca20d/src/Admin/AdminAjaxController.php)

The update controller catches API exceptions without deleting stored license data. Its default response cache lasts 60 seconds, and its HTTP client has a 30-second timeout. These values come from the source; outage tests are still required. Keep license requests outside login and keep login working during remote errors. [Update controller](https://github.com/paidcommunities/paidcommunities-wordpress-sdk/blob/4bfaef0967c01daf720fd5182d87fe91451ca20d/src/UpdateController.php), [cache](https://github.com/paidcommunities/paidcommunities-wordpress-sdk/blob/4bfaef0967c01daf720fd5182d87fe91451ca20d/src/Cache.php), [HTTP client](https://github.com/paidcommunities/paidcommunities-wordpress-sdk/blob/4bfaef0967c01daf720fd5182d87fe91451ca20d/src/HttpClient/WordPressClient.php)

Before the PaidCommunities integration ships, confirm these points with the provider:

- Can all keys, activations, subscriptions, and customer records be exported and restored elsewhere?
- Can current keys and payment subscriptions move without customer action?
- What happens during an API outage, and how long does the client retain a known status?
- How are multisite, staging sites, local sites, and domain changes counted?
- What are the service, data storage, privacy, support, and shutdown terms?
- Does the current SDK support PHP 8.1, and what does it send before activation?
- Who calculates, collects, and remits sales tax? Do not assume merchant-of-record service.

PaidCommunities states that customer data can be exported. The export fields and transfer process still need a test. The source review covered selected SDK files; it was not a full audit or a live service test. The remaining questions are open checks, not confirmed faults. [PaidCommunities data statement](https://paidcommunities.com/)

For either service, request only the license data needed for updates. Never send LDAP hosts, bind credentials, user passwords, or directory records. Show the external service and data sent before the administrator activates it. Obtain any required service terms and privacy details before sales start.

## Release order

1. Complete the refactor, settings migration, Pro extraction, and security checks.
2. Test Free and Pro with OpenLDAP, Samba AD, multisite, and trusted server SSO. Test direct 1.x upgrades to the correct package.
3. Correct the PaidCommunities client defect and test licensing and external Pro updates. Keep license calls outside login.
4. Publish Free and start Pro sales after the release and service checks pass. State clearly that 1.x sites using SSO or custom user fields need Pro.
5. Build and test later Pro features separately. Advertise each only after it is available.
