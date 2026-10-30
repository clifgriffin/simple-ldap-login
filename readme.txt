=== Simple LDAP Login ===
Contributors: clifgriffin
Tags: ldap, authentication, login, active directory, sso
Requires at least: 6.5
Tested up to: 7.1.3
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WordPress accounts to Active Directory or OpenLDAP, with group access rules and automatic account creation.

== Description ==

Use Active Directory or OpenLDAP accounts to log in to WordPress. You can require membership in an LDAP group and create a WordPress account after a valid login. The plugin also copies standard directory attributes to new user profiles.

Free features include:

* Active Directory and OpenLDAP login.
* StartTLS and LDAPS with certificate checks.
* Automatic account creation and a default WordPress role.
* Required groups, including nested and primary Active Directory groups.
* Multiple controllers in the same directory.
* Custom LDAP attributes for standard profile fields.
* Site and network settings.
* Connection and login tests that do not create users or sessions.

After the transition release, new sites need Pro for single sign-on (SSO) and custom user fields. Existing sites can use the free Legacy package to keep these features. Transition includes them without a license key.

Install only one edition. Free, Pro, Legacy, and Transition use the same plugin folder and entry file. Pro and Legacy are full replacements, not add-ons.

Version 2.0 is in development. Complete migration, server, and external update tests before public use. The reduced Free ZIP must not be published as an immediate update for old installations. Pro and Legacy previews have no automatic external updates; PaidCommunities is not integrated yet.

LDAP requests go to the directory servers that an administrator sets. The free plugin has no license check, external updater, tracking, or sales service.

== Installation ==

1. Install PHP 8.1 or later with the LDAP, OpenSSL, and iconv extensions.
2. Upload the release ZIP through Plugins > Add New Plugin, or use the WordPress plugin directory.
3. Activate Simple LDAP Login.
4. Open Settings > Simple LDAP Login. For network activation, use Network Admin > Settings.
5. Enter the directory connection details. Use StartTLS or LDAPS and a trusted server certificate.
6. Save the settings and run the connection and login tests.
7. Choose local recovery access on the Access tab. Keep your administrator session open, enable LDAP login, and test it in another browser.

A release ZIP includes its dependencies. Do not install an unbuilt source archive.

== Frequently Asked Questions ==

= Where are the advanced settings? =

The Advanced tab contains the timeout, LDAP login and group attributes, group search base, profile attributes, plain LDAP permission, and uninstall data setting. Full packages also show metadata suffixes there. Common connection and access settings stay on their main tabs. Saved values are kept when you save another tab.

= Will my existing settings work? =

Your saved settings stay in place. The plugin backs up older settings before migration. Existing sites qualify for free Legacy access. Install Legacy to keep SSO and custom user fields.

Existing unencrypted connections continue to work with a warning. A failed TLS connection stops the login attempt. If the plugin cannot read a saved search password, enter it again.

= Are old features moving to Pro? =

Server SSO and custom metadata mappings are Pro features for new installations after transition. Existing installations retain all old features through Legacy at no charge, including features they had not enabled. The Transition package keeps them available to everyone during migration. Central network settings remain free.

= Does Legacy require a license key? =

No. Legacy includes the old features without a license key. Its update service still needs to be completed before public release.

= What happens if LDAP is unavailable? =

Local recovery access on the Access tab has three choices:

* Selected administrators: the chosen accounts use WordPress authentication directly. LDAP and server SSO cannot sign in as these accounts or update their directory metadata.
* All administrators (legacy): allow administrator WordPress passwords. On multisite, this applies to super administrators only.
* Off: remove the administrator exception. Every account follows the normal LDAP login policy.

New installations default to Selected administrators. Select at least one eligible local account, or explicitly choose Off, before enabling LDAP or server SSO. Site settings accept administrators of that site. Network settings accept only super administrators. Existing installations keep the legacy rule until an administrator changes it. Local recovery access is included in Free.

When LDAP-only mode is off, WordPress can check local passwords for other accounts. A denied group still blocks those accounts. LDAP-only mode also blocks their local application password fallback.

= How can I recover access? =

Set define( 'SLL_DISABLE_LDAP', true ); in wp-config.php before WordPress loads, then use a local administrator password. Correct the settings and remove the constant. You can also rename the plugin folder to disable it.

= Does SSO trust a browser header? =

No. Pro, Legacy, and Transition use REMOTE_USER set by a server authentication module. Do not copy client request headers into that variable. Block access paths that bypass server authentication. SSO also requires a valid read-only search account. DOMAIN\name logins must match the Windows domain setting. The reduced Free package does not include server SSO.

= What happens when I delete the plugin? =

User accounts are always kept. Settings are kept unless you enable Delete settings when removing the plugin on the Advanced tab.

== Changelog ==

= 2.0.0 =

* Refactor the code for PHP 8.1 and WordPress coding standards.
* Add Composer autoloading and standard WordPress development tools.
* Retain existing settings, migration backups, and safe extension hooks.
* Move server SSO and custom metadata code into full Pro, Legacy, and Transition packages.
* Record legacy eligibility before migration. Keep all old capabilities available at no charge through Legacy.
* Add a Transition build that keeps old features available to everyone before a later reduced Free release.
* Use LdapRecord for LDAP connections, searches, and directory models.
* Add settings screens for desktop and mobile, with permission checks and separate connection and login tests.
* Stop after failed TLS, require verified SSO lookup, and protect account metadata.
* Add selected local recovery accounts and an Off choice. Keep the old administrator rule on existing installations.
* Keep other WordPress authentication checks active.
* Remove old IDE files, duplicate documentation, and obsolete server setup commands.

== Upgrade Notice ==

= 2.0.0 =
Requires PHP 8.1. Existing sites keep administrator password recovery. New sites select local recovery accounts or explicitly turn recovery off. Use Transition, then Legacy, to keep old SSO and metadata features. TLS failures stop login. Test on a site copy first.
