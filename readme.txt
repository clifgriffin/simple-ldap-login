=== Simple LDAP Login ===
Contributors: clifgriffin
Tags: ldap, authentication, login, active directory
Requires at least: 6.5
Tested up to: 7.1
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

Pro adds single sign-on (SSO) so staff can open WordPress without another login prompt after your web server authenticates them. It also copies custom LDAP fields, such as phone number or department, to WordPress at login. A 1.x site that uses either feature must upgrade directly to Pro.

Install either Free or Pro. Both use the same plugin folder and entry file. Pro replaces Free and does not need an add-on.

Version 2.0 is in development. Test upgrades on a site copy before public use. Pro previews have no automatic external updates; live license service tests remain required.

LDAP requests go to the directory servers that an administrator sets. Free LDAP login does not require a PaidCommunities account or license. Pro feature code is not included in Free.

= External Services =

The optional Go Pro tab links to [PaidCommunities](https://paidcommunities.com/) checkout. It does not load external scripts or show a license form. Pro uses PaidCommunities to manage licenses after installation. Free LDAP login does not require this service. Pro requires a license key from a purchase through PaidCommunities.

The service receives data in these cases:

* Buy Pro: This link opens checkout for the single annual plan on https://checkout.paidcommunities.com without a referrer. PaidCommunities receives the product and plan IDs, your IP address, and browser details. Payment takes place on PaidCommunities. It receives the purchase and payment details that you enter there.
* Activate license in Pro: This action sends your license key, configured site domain, plugin version, and product ID to https://api.paidcommunities.com/wordpress/v1. The plugin saves an encrypted site activation secret instead of the original license key.
* Check license in Pro: After activation, WordPress schedules a background check once per hour. You can also select Check license. Each check sends the site activation secret, plugin version, and product ID to the same API.
* Deactivate license in Pro: This action sends the site activation ID and secret to the same API. A successful deactivation removes the local activation record and stops background checks.

API requests also share your server IP address and standard WordPress HTTP headers. These headers can include your WordPress version and site URL. Login uses the saved license status and does not make a PaidCommunities request. LDAP hosts, bind credentials, passwords, and user records are not sent to PaidCommunities.

Free keeps WordPress.org updates. After purchase, replace Free with the Pro ZIP from your receipt, then activate your license in Pro. Your LDAP settings stay in place. Activating a key does not install Pro.

* [Service documentation](https://paidcommunities.com/documentation/sdks/wordpress-sdk/)
* [Checkout documentation](https://paidcommunities.com/documentation/checkout/)

<!-- Before release, add public PaidCommunities terms of use and/or privacy policy links, as required by the WordPress Plugin Directory. The service website currently shows policy labels without links. -->

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

Your saved settings stay in place. The plugin backs up older settings before migration. If your 1.x site uses SSO or custom user fields, install Pro directly. Free supports LDAP password login but does not run these two features.

Existing unencrypted connections continue to work with a warning. A failed TLS connection stops the login attempt. If the plugin cannot read a saved search password, enter it again.

= Are old features moving to Pro? =

Single sign-on and custom user fields require Pro, including on sites that upgrade from 1.x. Standard profile attributes, group restrictions, recovery access, and central network settings remain free.

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

No. Pro uses REMOTE_USER set by a server authentication module. Do not copy client request headers into that variable. Block access paths that bypass server authentication. SSO also requires a valid read-only search account. DOMAIN\name logins must match the Windows domain setting. The Free package does not include server SSO.

= What happens when I delete the plugin? =

User accounts are always kept. Settings are kept unless you enable Delete settings when removing the plugin on the Advanced tab.

== Screenshots ==

1. Connect to OpenLDAP and choose the LDAP connection security.
2. Set group access rules, account creation, and local recovery accounts.
3. Set directory attributes, timeouts, and data retention.
4. Test the LDAP connection and a user login.

== Changelog ==

= 2.0.0 =

* Refactor the code for PHP 8.1 and WordPress coding standards.
* Add Composer autoloading and standard WordPress development tools.
* Retain existing settings, migration backups, and safe extension hooks.
* Build Free and Pro packages. Pro includes single sign-on and custom user fields.
* Back up 1.x settings before migration and keep their site or network scope.
* Use LdapRecord for LDAP connections, searches, and directory models.
* Add settings screens for desktop and mobile, with permission checks and separate connection and login tests.
* Stop after failed TLS, require verified SSO lookup, and protect account metadata.
* Add selected local recovery accounts and an Off choice. Keep the old administrator rule on existing installations.
* Keep other WordPress authentication checks active.
* Remove old IDE files, duplicate documentation, and obsolete server setup commands.

== Upgrade Notice ==

= 2.0.0 =
Requires PHP 8.1. Existing sites keep administrator password recovery. New sites select local recovery accounts or explicitly turn recovery off. If 1.x uses SSO or custom user fields, install Pro directly to keep them. TLS failures stop login. Test on a site copy first.
