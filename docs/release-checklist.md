# Release checklist

Prepared: 2026-10-28.

Complete these checks before public release. Record completed tests separately. Tests with simulated WordPress or LDAP responses do not replace tests on real servers.

## Source and build

- [ ] Keep `Simple-LDAP-Login.php` as the plugin entry file and retain the `simple-ldap-login` directory name.
- [ ] Set PHP 8.1 as the minimum in the plugin header, readme, and Composer declaration.
- [ ] Run syntax and regression checks on PHP 8.1 and the latest supported PHP release.
- [ ] Run WordPress Coding Standards checks and resolve their findings.
- [ ] Run Composer validation and dependency security checks. Review all included dependency licenses.
- [ ] Test a clean production install from the lock file with development packages excluded.
- [ ] Confirm that required autoload and runtime files are present in the release ZIP.
- [ ] Run `npm run test:packages`. Check both packages, PSR-4 loading, file exclusions, and source preservation after a build.
- [ ] Run `npm run test:release`. All 24 core configurations and the selected settings, migration, and package replacement cases must pass. See the [coverage map](test-coverage.md).
- [ ] Confirm that `npm run plugin-zip` builds Free and that only Free and Pro ZIPs are produced.
- [ ] Keep the Pro preview out of public distribution until its external update path is implemented and tested. `Update URI: false` prevents WordPress.org replacement but supplies no updates.
- [ ] Include `composer.json`, source files, license text, third-party notices, and any required asset source.
- [ ] Exclude development dependencies, tests, IDE files, old build scripts, obsolete setup guides, and unused libraries from the ZIP.
- [ ] Install the ZIP on a clean site without Composer or a package download step.
- [ ] Update and compile all 24 translation catalogs. Run `npm run test:i18n` and the WordPress catalog check in the [translation guide](translations.md).
- [ ] Run WordPress Plugin Check on the extracted release package and review its warnings and errors. See the [last results and commands](plugin-check.md). Passing the check is not formal WordPress.org approval.

The WordPress.org package must include the files needed to run. Its review guidance also covers external updaters, unused files, and dependency declarations. [Common issues](https://developer.wordpress.org/plugins/wordpress-org/common-issues/)

## Settings migration

- [ ] Test a fresh site. LDAP and SSO remain off until configured.
- [ ] Test real `sll_settings` examples from the last public release and the unreleased repository version.
- [ ] Test oldest `simpleldap_*` options when no current settings array exists.
- [ ] Test site activation, network activation, and separate site settings on multisite.
- [ ] Retain the `sll_settings_backup_v1` backup in the original scope before migration changes saved data.
- [ ] Run migration twice and compare the result. The second run must not overwrite the backup or change working settings.
- [ ] Preserve explicit false values, unknown extension keys, groups, controllers, custom ports, profile attributes, and metadata suffixes.
- [ ] Keep old options for rollback and test it with a database copy. Do not use an old insecure build as a permanent fix.
- [ ] Test malformed, partial, and unexpected option values. A bad option must not cause a fatal error or grant access.
- [ ] Test old encrypted service passwords. If recovery fails, require re-entry; do not overwrite the stored value with an empty password.
- [ ] Save one settings section and confirm that other sections retain their values.
- [ ] Confirm that uninstall does not delete users and does not load or run the plugin's sign-in code.

## Authentication and directory servers

- [ ] Use a real supported WordPress release, with the actual core authentication filters active.
- [ ] Test the minimum WordPress release declared in the package and the latest stable release. Only then set `Tested up to`.
- [ ] Test valid and invalid passwords with Active Directory and OpenLDAP.
- [ ] Reject empty passwords before bind. Test quotes, backslashes, a password equal to `0`, and non-ASCII text without changing the password bytes.
- [ ] Test special characters in usernames, DNs, and group names. Check LDAP filter and DN escaping separately.
- [ ] Test AD account suffix and domain login formats, including the formats existing sites use.
- [ ] Test direct OpenLDAP bind and subtree user lookup. Reject an ambiguous lookup.
- [ ] Test direct, nested, and primary AD groups. Test OpenLDAP `memberUid`, `member`, and `uniqueMember` groups.
- [ ] Test an empty group list, an allowed group, a denied group, an unavailable group search, and a different group base DN.
- [ ] Denied group checks and failed directory searches must not become a local-password bypass.
- [ ] Test backup servers with active Pro and Enterprise licenses. Free and expired or inactive licenses must use only the first server. Preserve the full saved server list. Test password login, connection tests, SSO, and account jobs.
- [ ] Test timeouts, no route, DNS failure, bind failure, and missing PHP LDAP support. Fail with a controlled error.
- [ ] Test plain LDAP only under the documented legacy policy. Test STARTTLS and LDAPS with a trusted CA.
- [ ] Test an expired certificate, a wrong hostname, and an untrusted CA. Do not continue after failed TLS.
- [ ] Test existing WordPress users, new user creation, disabled creation, duplicate email addresses, and denied or invalid roles.
- [ ] Confirm that no LDAP password is stored as a WordPress password or included in an error, URL, log, or test result.
- [ ] Test custom metadata and both old suffix keys. Keep a local edit when the LDAP source has not changed.
- [ ] Prevent metadata writes to capability, role, session, and other protected account keys.
- [ ] Test selected recovery accounts, the legacy administrator rule, Off, LDAP-only mode, and permitted local fallback through both username and email login.
- [ ] Require a valid selection or explicit Off choice on new LDAP/SSO setup. Keep the legacy rule when schema 2 settings migrate to schema 3, without changing backups or secrets.
- [ ] Reject directory login and server SSO aliases that resolve to a selected recovery account. Keep WordPress security checks active. Test demotion, deletion, site membership, and network superadmin scope.
- [ ] Set `define( 'SLL_DISABLE_LDAP', true );` in `wp-config.php` before WordPress loads. Confirm that LDAP password and SSO hooks are skipped and a local administrator can sign in. Remove the constant and repeat the normal login test.
- [ ] Confirm that authentication hooks still run on later login attempts in the same request and that other plugins retain their filters.
- [ ] Test an application password, a second-factor plugin, and any supported external login plugin. Document each supported interaction.

## Server SSO

- [ ] Test with a real server authentication module and its supported deployment setup.
- [ ] Confirm that only the trusted server sets `REMOTE_USER`. Block clients from supplying or replacing it through a proxy header.
- [ ] Test an unknown user, disabled user, failed group check, failed service bind, and missing server identity.
- [ ] Test sign-in success and check that the WordPress user and cookie are set only after a valid result.
- [ ] Test logout, the login page, password recovery, REST requests, cron, and command-line requests. SSO must not cause an immediate login loop.
- [ ] Check SSO identity normalization against existing user names. Do not merge distinct users silently.
- [ ] Verify that SSO errors do not expose service credentials or raw directory messages.

## Settings screen and security

- [ ] Test site administrators, network administrators, and users with no settings permission.
- [ ] Require the correct capability, POST, and a valid nonce for each settings save or directory test.
- [ ] Test malformed inputs, unexpected fields, invalid hosts, attribute names, ports, roles, and metadata mappings.
- [ ] Confirm that secret fields are blank in the page source. A blank submitted secret keeps the current secret unless an explicit clear control is used.
- [ ] Test stored secret encryption, tampered data, key changes, and recovery. A decryption failure must return an error.
- [ ] Escape every saved or server-supplied value when it is shown.
- [ ] Test the settings screen with a keyboard, visible focus, screen-reader labels, narrow screens, and a long translated label.
- [ ] Make errors and success states clear without color alone. Keep the form usable when JavaScript is off.
- [ ] Check that a diagnostic test does not create users, change roles, set cookies, or save a test user's password.
- [ ] Test settings without LDAP or OpenSSL support. The administrator must still be able to open the page and disable the plugin.
- [ ] Confirm that merely viewing settings does not send a bind request or contact a sales or license service.
- [ ] Have a second reviewer check the final authentication, migration, secret handling, and access-control changes.

## Free package and product claims

- [ ] Keep basic LDAP login, TLS, recovery, group restrictions, account creation, standard profiles, network settings, and one directory server free. Require Pro or Enterprise for backup servers.
- [ ] Exclude SSO and custom metadata feature code from reduced Free. Include those features without locks in Pro.
- [ ] State that backup LDAP servers, SSO, and custom user fields require Pro, including on 1.x upgrades.
- [ ] Exclude Pro feature code and the external self-updater from the free ZIP. An optional upgrade connection must not restrict free features or replace WordPress.org updates.
- [ ] Keep promotion brief and within the plugin's own settings. Do not use repeated dashboard notices.
- [ ] Document any external service and obtain the needed consent before optional data transmission.
- [ ] Review the current directory rules again before upload. A notice about breaking changes does not replace this review.

SSO and custom user fields are Pro features. Keep their code outside the WordPress.org ZIP. Review the final packages under the [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/).

## Upgrades from 1.x

- [ ] Test direct 1.x-to-Free upgrades with LDAP password login, required groups, and administrator recovery.
- [ ] Test direct 1.x-to-Pro upgrades with backup LDAP servers, SSO, custom user fields, stored credentials, and custom metadata suffixes.
- [ ] Tell sites that use backup LDAP servers, SSO, or custom user fields to install Pro directly. Free cannot preserve functionality whose code is absent.
- [ ] Preserve activation, settings scope, credentials, migration backup, and user data during replacement.
- [ ] Verify the Free settings notice for saved backup servers, SSO, or metadata configuration. Do not delete those settings.
- [ ] Verify that the Pro update guard rejects incomplete packages and restores the original activation scope.

## Planned Pro release

- [ ] Build and test the planned Pro features before advertising them as available or opening sales.
- [ ] Generate Free and Pro ZIPs from one codebase. Keep the plugin folder and entry filename stable. Test replacement upgrades and voluntary package changes without losing settings or silently changing access.
- [ ] Verify whether PaidCommunities generates the required packages and provides authorized Pro installation. Use its tools where available. Test the package contents and update source for each build.
- [ ] Require explicit user authorization through the connected service before any automatic Pro installation. Free currently uses manual Pro ZIP installation after checkout. Any future service installation flow must test invalid authorization, cancellation, interrupted installation, and recovery. A license activation alone must not start an install.
- [ ] Verify the pinned PaidCommunities SDK on PHP 8.1 and the latest supported PHP release. Keep the SDK's built-in admin handlers disabled; use the checked shared handlers.
- [ ] Verify that failed nonce and permission checks cannot clear or change local license data. Repeat this check on activation and deactivation.
- [ ] Test new purchases, activation, site limits, site moves, staging sites, network installations, refunds, cancellation, renewal, and expiry.
- [ ] Test invalid responses, rate limits, service outages, and stale license data.
- [ ] Verify that confirmed expiry stops backup servers, SSO, and custom field updates. Keep LDAP password login, group rules, local recovery, saved settings, and user data. Verify that login never calls the license server.
- [ ] Verify that renewal restores Pro features and that a service error keeps the last verified status. Check hourly scheduling on a live site and network.
- [ ] Confirm that keys and update tokens are not sent in browser URLs or retained in logs.
- [ ] Test package download authorization and ensure that a license for a different product cannot download Pro.
- [ ] Export and restore customer, subscription, license, and activation records before relying on the service.
- [ ] Confirm payment, refund, tax, privacy, and service terms. Do not assume that either provider remits tax for the seller.
- [ ] Include GPL-2.0-or-later source licensing and separate terms for update and support access.

Installation policy: [Plugins Team clarification](https://make.wordpress.org/plugins/2017/03/16/clarification-of-guideline-8-executable-code-and-installs/).

Service references: [EDD API](https://easydigitaldownloads.com/docs/software-licensing-api/), [PaidCommunities SDK](https://paidcommunities.com/documentation/sdks/wordpress-sdk/), [WordPress license](https://wordpress.org/about/license/).

## Beta and public release

- [ ] Keep a tested local administrator account and a database backup before every real upgrade test.
- [ ] Test an upgrade on copies of active legacy sites before a fresh install alone.
- [ ] Publish beta instructions that list supported servers, known limits, changed security requirements, and recovery steps.
- [ ] Get beta results for Active Directory, OpenLDAP, network settings, and server SSO.
- [ ] Record exact WordPress, PHP, LDAP server, and operating system versions for each result.
- [ ] Resolve all authentication and migration failures before public release.
- [ ] Update plugin headers, readme, stable tag, changelog, screenshots, and upgrade notice together.
- [ ] State which features remain in Free and which need Pro for new installations. State that 1.x sites using backup LDAP servers, SSO, or custom user fields must install Pro directly. List security changes that can require administrator action.
- [ ] Publish a recovery guide with the tested `SLL_DISABLE_LDAP` method and `sll_settings_backup_v1` backup key. Do not publish secrets or real customer settings.
- [ ] Use the existing WordPress.org listing for the rewrite, then verify the generated package from that listing. [Plugin Developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/)
