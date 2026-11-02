# Checks completed

Test date: 2026-10-30.

After the license change, source checks, coding standards, assets, translations, package tests, and the full Docker matrix pass. All 24 core configurations passed with license expiry checks and the 1.x settings migration checks. See the [coverage map](test-coverage.md) and [Docker test guide](docker-tests.md).

The two-package change removes Legacy and Transition builds and eligibility records. The current checks cover direct 1.x settings migration in both packages. SSO and custom user field sites must install Pro directly.

## Pro account features

The six Pro additions have source tests in `tests/pro.php` and real WordPress and directory tests in `tests/docker/pro-features.php`.
`composer check` passes 1020 checks: 281 for settings, secrets, login, LDAP, and Pro account features; 597 for admin screens; 26 for package replacement; 112 for licenses; and 4 for uninstall.
The local source checks use PHP 8.4.23. Coding standards now run with the WordPress bootstrap constant set; the previous command exited during Composer autoloading before it checked source files.
Asset checks and browser-script checks pass. Package tests pass for Free and Pro, including a direct call from the private LDAP types into the role callback.

The feature tests cover rule order, role removal, local recovery and network super administrator exclusions, profile conflicts, paged import, repeat jobs, account ownership, stable identities, job locks, access suspension and restoration, and session removal.
A failed group search is a directory error, not confirmed access removal. License expiry does not restore suspended access.
Network tests cover site roles, membership removal, prior membership restoration, manual role edits, and credential scope isolation.
Audit tests cover completed login events, private records with numeric IDs only, scope isolation, retention, CSV pages, capabilities, and nonces.
HTTP tests also download a CSV with a real administrator cookie and reject an invalid nonce and a different scope.

The selected native configurations use OpenLDAP with PHP 8.4.27 and WordPress 7.1.4 on a single site; Samba AD with PHP 8.1.34 and WordPress 6.5 in multisite; and OpenLDAP with PHP 8.5.12 and WordPress 7.1.4 in multisite.
Each multisite run checks network and site-only activation in both editions. These are 10 core configurations, not a new full 24-configuration release run.
The translation update adds 44 messages to all 24 locales. All 241 messages per locale pass completeness, placeholder, and compiled MO checks. All 4,728 existing translations are unchanged. Rule examples and technical identifiers are retained. The native WordPress translation controller passes 11,568 checks across PHP and MO catalogs without a database connection.

## Source and release packages

Before the Pro account additions, `composer check` passed 702 checks: 213 for settings, secrets, recovery policies, authentication, and LDAP; 353 for admin screens; 20 for package replacement; 112 for licenses; and 4 for uninstall. WordPress Coding Standards and PHPCompatibilityWP pass with PHP 8.1 as the minimum. Those earlier source checks ran locally on PHP 8.5. Hosted CI is configured for PHP 8.1 through 8.5, but its jobs have not run yet.

LDAP source tests use LdapRecord's fake transport with its real connection, authentication, and query code. Both application clients must use library APIs. A source check rejects direct PHP LDAP function calls in either client.

`npm run lint` passes with Node 24 and `@wordpress/scripts` 37. Root, Pro, and build-tool Composer manifests pass strict validation. The Composer lock audit reported no known security advisories when LdapRecord 4.1.1 and PaidCommunities 0.0.4 were installed. Production dependencies declare MIT licenses. Their license files remain in the ZIPs.

`npm run test:packages` builds only Free and Pro from the real source. Twelve extracted-package cases cover fresh settings, schema 2 settings, 1.x settings in site and network scope, and Pro with missing or expired licenses. They check PSR-4 loading, feature hooks, metadata, syntax, file exclusions, migration backups, and source preservation. The removed Legacy and Transition build arguments must be rejected. Startup must not make a service request.

Package tests also check private dependency namespaces, helper functions, Composer helper-file records, AD relation classes, SID conversion, and event names. The library must work when another plugin has already defined a helper with the same name. Release ZIPs use the `SimpleLdapLoginVendor` prefix.

## Docker integration checks

The complete matrix passed on these local ARM64 targets:

| PHP | WordPress | Directory servers |
| --- | --- | --- |
| 8.1.34 | 6.5 | OpenLDAP 2.5.13; Samba AD 4.17.12 |
| 8.5.12 | 7.1.4 | OpenLDAP 2.5.13; Samba AD 4.17.12 |

Each target checks Free and Pro with single-site activation, network activation, and site-only activation in multisite. Tests install the built release ZIPs. The kept PHP 8.4 Samba sites also passed with the new packages and remain available on ports 8088 and 8089.

Directory tests cover direct and subtree lookup, anonymous and service binds, controller failover, login forms, disabled AD users, profile attributes, account creation, safe roles, fallback, and administrator recovery. Group tests cover no rule, any allowed group, all absent groups, a different group base, OpenLDAP membership attributes, nested AD groups, primary groups, and primary group parents.

Trusted StartTLS and LDAPS pass. Both transports reject an untrusted CA and a certificate for a different host. Plain LDAP requires explicit permission. Samba still rejects an unencrypted bind. A source test checks that the production adapter retains an explicit LDAPS port of 389 without making a network request.

Real OpenLDAP password changes check the literal password `0`, quotes, backslashes, and entity text. Login accepts each exact value and rejects a changed value. LdapRecord 4.1 decodes HTML entities and treats `0` as null during bind. The adapter compensates for those two cases. The original fixture password is restored after each run.

The first migration run also found that OpenLDAP's enabled-account filter needs a password policy schema. A generic server can return no entries for its undefined attribute. The client now applies the enabled-account filter only to the AD model. The generic OpenLDAP test server and a source assertion cover this behavior.

The 1.x tests seed the old settings format and load the 2.0 packages. They do not run the old 1.x PHP code. The native Pro check loads those settings in a new WordPress request, then checks LDAP password login, SSO lookup, and custom metadata suffixes. Native migrations also check both old settings formats, stored false values, encryption migration, backups, repeated migration, malformed data, and child-site scope isolation. Each package update runs in a separate WordPress request. Later requests check activation, settings, credentials, user password hashes, roles, profiles, and metadata after core shutdown work.

Recovery tests cover Selected administrators, All administrators (legacy), and Off. New LDAP and SSO setup needs a valid selection in selected mode. Existing schema 2 and older settings keep the legacy rule; migration preserves raw settings, backups, secrets, and explicit new policies. Site selections require current site administrator access; network selections require super administrator access.

Selected accounts accept local username and email login without a directory request. Wrong local passwords and WordPress security filter denials still fail. LDAP and server SSO cannot use direct names, UPNs, or domain aliases to select a recovery account or run its profile and metadata hooks. Removing, deleting, or demoting an account removes its exception. These checks pass across all 24 core configurations.

The HTTP suite has 69 checks per edition and activation mode. Apache Basic authentication supplies a trusted `REMOTE_USER`; forged headers cannot create a session. Tests cover SSO on and off, license states, password login off, recovery, invalid identities, group denials, user and later login filter denials, request contexts, and auth cookies. Separate PHP requests remove LDAP or disable encryption functions, then check controlled errors, unchanged secrets, and usable admin controls.

Earlier tests found that an SSO proof made with `wp_generate_password()` could contain whitespace that core trims. The proof now uses 32 random bytes encoded as hex. Source and HTTP tests retain that regression check and verify that later authentication filters can still deny access.

Temporary test containers and volumes were removed after each automated run. The kept Samba domain, configuration, and CA remain in named volumes.

## Settings screens

The Advanced tab contains connection timeouts, LDAP attribute and group search overrides, profile attributes, plain LDAP permission, and uninstall data retention. Full packages also show metadata suffixes there. Custom metadata mappings stay on the full package User data tab. Free has no empty User data tab; its old profile links open Advanced. Screen checks cover field placement in Free and Pro, site and network scope, saved-value rendering, and Advanced form submissions. Missing fields are retained by the existing settings save path.

The copy review shortened labels, help text, notices, and login errors. Active Directory-only fields are hidden for OpenLDAP, and OpenLDAP-only fields are hidden for AD. Browser checks on the Herd demo verified both directory choices, the Access and Advanced screens, and the unchanged saved OpenLDAP configuration. `npm run test:admin` checks live field switching, disabled hidden controls, and retained values. Source, asset, and both package checks pass. Authentication behavior is unchanged.

## Earlier settings-screen checks

Before the LDAP dependency change, a WordPress 7.1.3 site passed site and network saves, section retention, nonce and scope checks, and subscriber permission denial. Desktop and 390-pixel mobile checks showed aligned controls, wrapped tabs, no horizontal overflow, and no JavaScript errors. Current admin source tests continue to cover the controls and migration notices.

See the current [Plugin Check report](plugin-check.md) for the Free and Pro packages. Plugin Check does not provide formal WordPress.org approval.

## Checks still needed

Complete the [release checklist](release-checklist.md). Microsoft AD DS, domain trusts, Kerberos negotiation, expired certificates, customer settings, and named second-factor integrations still need acceptance tests. Samba and Apache Basic authentication do not establish support for every AD policy or SSO setup.

PaidCommunities activation and license checks are implemented. Source and Docker tests use controlled service responses. Live account tests and automatic external updates remain required. Pro remains a local preview. Sites that use SSO or custom user fields in 1.x must upgrade directly to Pro and activate a license. The previously uploaded draft ZIP also needs replacement before release.

The Free Go Pro screen lists all nine paid feature groups and links directly to the single annual plan checkout. It has no license form and loads no commerce scripts. Pro has license controls without purchase offers. Screen tests cover both editions in site and network scope, including retained license records. JavaScript tests cover the Pro activation component, API adapter, and failure responses. Browser checks verify the direct checkout destination and the Free screen. Live checkout is blocked because the product is Draft; release and payment setup are still required.
