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

Server single sign-on and custom metadata mappings move to Pro for new installations after the transition release. Existing installations retain all old capabilities through a maintained Legacy package at no charge. The Transition package keeps all old features available to everyone without a license key or payment.

Install only one edition. Free, Pro, Legacy, and Transition use the same plugin folder and entry file. Pro and Legacy are full replacements, not add-ons.

Version 2.0 is in development. Complete migration, server, and external update tests before public use. The reduced Free ZIP must not be published as an immediate update for old installations. Pro and Legacy previews have no automatic external updates; PaidCommunities is not integrated yet.

LDAP requests go to the directory servers that an administrator sets. The free plugin has no license check, external updater, tracking, or sales service.

== Installation ==

1. Install PHP 8.1 or later with the LDAP and OpenSSL extensions.
2. Upload the release ZIP through Plugins > Add New Plugin, or use the WordPress plugin directory.
3. Activate Simple LDAP Login.
4. Open Settings > Simple LDAP Login. For network activation, use Network Admin > Settings.
5. Enter the directory connection details. Use StartTLS or LDAPS and a trusted server certificate.
6. Save the settings and run the connection and login tests.
7. Keep the administrator session open. Enable LDAP login and test it in a second browser.

A release ZIP includes its dependencies. Do not install an unbuilt source archive.

== Frequently Asked Questions ==

= Will my existing settings work? =

The plugin keeps the settings option name and its site or network scope. It backs up and migrates older settings. It records legacy eligibility before migration. Use Legacy to retain SSO, custom mappings, and their settings controls. A local eligibility record cannot restore feature code absent from a ZIP.

Existing unencrypted connections continue to work with a warning. A failed TLS connection stops the login attempt. If the plugin cannot read a saved search password, enter it again.

= Are old features moving to Pro? =

Server SSO and custom metadata mappings are Pro features for new installations after transition. Existing installations retain all old features through Legacy at no charge, including features they had not enabled. The Transition package keeps them available to everyone during migration. Central network settings remain free.

Multiple independent directories, group-to-role rules, scheduled account updates, audit exports, and network templates remain planned work. They are not included yet.

= Does Legacy require a license key? =

No. Legacy keeps the old capabilities available without a key or a remote license check during login. Before distribution, its no-cost update path and the move from old versions must pass release tests.

= What happens if LDAP is unavailable? =

When LDAP-only mode is off, WordPress can check local passwords. When it is on, local administrator accounts retain a recovery login. Group denial does not permit local fallback for other users. LDAP-only mode also blocks local application password fallback for other users.

= How can I recover access? =

Set define( 'SLL_DISABLE_LDAP', true ); in wp-config.php before WordPress loads, then use a local administrator password. Correct the settings and remove the constant. You can also rename the plugin folder to disable it.

= Does SSO trust a browser header? =

No. Pro, Legacy, and Transition use REMOTE_USER set by a server authentication module. Do not copy client request headers into that variable. Block access paths that bypass server authentication. SSO also requires a valid read-only search account. DOMAIN\name identities must match the configured Login domain. The reduced Free package does not include server SSO.

= What happens when I delete the plugin? =

The plugin keeps settings unless an administrator selects Delete settings on uninstall for that site or network. It keeps WordPress users.

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
* Keep other authentication checks and local administrator recovery.
* Remove old IDE files, duplicate documentation, and obsolete server setup commands.

== Upgrade Notice ==

= 2.0.0 =
Test on a site copy first. Requires PHP 8.1 and LDAP v3. Use Transition, then no-cost Legacy, to keep old SSO and metadata features. Do not update old sites directly to reduced Free. TLS failures stop login. Some search passwords need re-entry. Keep a local administrator account for recovery.
