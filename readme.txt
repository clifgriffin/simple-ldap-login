=== Plugin Name ===
Contributors: clifgriffin
Donate link: http://cgd.io
Tags: LDAP, authentication, login, active directory, adLDAP
Requires at least: 3.4
Tested up to: 3.6
Stable tag: 1.5.5
License: GPLv2 or later

Integrating WordPress with LDAP shouldn't be difficult. Now it isn't. Simple LDAP Login provides all of the features, none of the hassles.

== Description ==
Having a single login for every service is a must in large organizations. This plugin allows you to integrate WordPress with LDAP quickly and easily. Like, really really easy.

**Contributing**
The easiest way to contribute to this plugin is to submit a GitHub pull request. Here's the repo:
https://github.com/clifgriffin/simple-ldap-login

**NEW VERSION -- 1.5**

Just when you thought this project was dead, it sprang to life. I have spent some time completely rewriting Simple LDAP Login from the ground up. Proceed with caution as it's possible I have broken something in the process, but I'm reasonably certain it's fundamentally stable.

If you have any problems with 1.5, please let me know: clifgriffin@gmail.com  

**Support**

If you need support, I recommend you leave a comment on the appropriate post on my blog:
http://clifgriffin.com/2009/05/13/simple-ldap-login-13-for-wordpress/

**Special Requests**

If you need a customization or change specific to your install, I am available for hire. Shoot me an e-mail: clifgriffin[at]gmail.com

= Features =

* Supports Active Directory and OpenLDAP (and other directory systems which comply to the LDAP standard, such as OpenDS)
* Supports TLS
* Uses up-to-date methods for WordPress authentication routines.
* Authenticates existing WordPress usernames against LDAP.
* Can be configured to automatically create WordPress users for valid LDAP logins.
* You can restrict logins based on one or more LDAP groups.
* Intuitive control panel.

= Architecture =
Simple LDAP Login adds an authentication filter to WordPress that authentication requests must pass. In doing so, it makes several decisions.

* Can the provided credentials be authenticated against LDAP?
* * If so, is the LDAP user a member of the required LDAP groups (if any)?
* * * Does a matching WordPress user exist?
* * * * If so, log the user in.
* * * * If not, is user creation enabled?
* * * * * Create the user and log them in.

This is high level overview. This should answer the philosophical questions about how the plugin works. If the plugin is unable to authenticate the user, it should pass it down the chain to WordPress. (Unless LDAP Exclusive is turned on, in which case it won't.)

== Upgrade Notice ==
I have spent some time completely rewriting Simple LDAP Login from the ground up. Proceed with caution as it's possible I have broken something in the process, but I'm reasonably certain it's fundamentally stable.

If you have any problems with 1.5, please let me know: clifgriffin@gmail.com  

== Changelog ==
**Version 1.5.5**

* Fix syntax error.
* Don’t sanitize user info.

**Version 1.5.4**

* Local admins will always fall back to local WP password. 
* Fixes bug where new users do not have name or other information from LDAP directory 

**Version 1.5.3**

* Fixing apparent security problem with blank passwords. (!)
* Fixing typo in filter name (did not affect any functionality)
* Local admin exception coming soon, as well as more bug fixes. 
* Possible fix for login error upon arriving at login page when LDAP exclusive enabled.

**Version 1.5.2**

* Fixed bug with groups setting.
* Removed delete_option references in upgrade code to allow for easier rollbacks (sorry about that!)
* Fixed a few bugs in the user creation code. 
* Fixed bug with storing default user role. 

**Version 1.5.1**

* Fixed a bug where the domain controllers are passed as a string. 

**Version 1.5**

* Complete rewritten from the ground up.
* It's Object Oriented, DRY and Singleton. 
* The options have been overhauled to make configuration much easier. Focuses on individual features rather than "modes" that encapsulate several behaviors. 
* Admin pages now use WordPress admin styles and behaviors. 
* Tested with Active Directory. I recommend OpenLDAP users test carefully before implementing in their production environments. 
* Added global on off switch so you can easily disable LDAP authentication without deactivating.  

**Version 1.4.0.5.1** 

* I broke it. Sorry guys! :(
* Downgraded adLDAP as some referenced functions no longer exist. 

**Version 1.4.0.5**

* Updated adLDAP to version 4.x
* Fixed error in OpenLDAP group membership check
* As always TEST this first. Don't assume it works...I don't have a testing environment to ensure it will work correctly. 

**Version 1.4.0.4**

* Fixes nickname bug accidentally put back in in last version. (My bad!)

**Version 1.4.0.3**
* Reverts bug introduced in 1.4.0.2
* If you installed 1.4.0.2 and use OpenLDAP, please update as soon as possible and verify users cannot login with incorrect passwords (and vice versa).

**Version 1.4.0.2 - Patches submitted by Jonas Genannt and Ilya Kozlov**
* Updates adLDAP to 3.3.2
* Fixes issue with users in recursive OUs not being found. 
* Fixes issues with different Base DN formats.
* NOTE: Please be catious in updating. As I don't have an OpenLDAP install, I am unable to independently confirm these fix the problems. If you have issues, revert to 1.4.0.1 and e-mail me: clifgriffin[at]gmail.com.  Likewise, If you can confirm these changes are effective, also let me know. :)

**Version 1.4.0.1**

* Fix for e-mail exists issue with WP 3.0+ for LDAP installations that don't populate the e-mail address attribute.
* Shows actual error message from WordPress upon failure.

**Version 1.4**

* First update in about a year. Thanks for your patience. 
* Completely rewritten to support changes in WordPress 2.8+.  Now fully supports WordPress 3.0.
* Much more manageable and efficient code structure. Less code repetition.
* Includes TLS support. 
* Allows OpenLDAP users to specify an alternate LDAP attribute to use for logins for those not using UID.

**Version 1.3.0.3**

* Test form now implements wp_authenticate and uses the same routines as the actual login. This also means account creation and group membership are tested. 
* Implemented stripslashes() to correct issue with some special characters such as a single quote and backslash. 
* WordPress account "admin" is now allowed to login using local password even when security mode is set to high. For safety.
* Made some minor wording changes to the admin panel. 

**Version 1.3.0.2.1**

* Fixed case sensitivity issue that could result in multiple accounts. There may be lingering case insensitivity issues due to the get_userdatabylogin function being case-sensitive. We'll figure this out in due time. 
* Sorry for posting two updates on the same day!

**Version 1.3.0.2**

* Fixes several tickets including role assignment, case sensitivity, and potential compatibility issues with other themes/plugins.
* Added security mode setting to allow security to be tightened. 
* Changed auto created accounts to use a random password rather than the LDAP password given. 
* Fixed error with the way announcements are displayed in the admin panel. 
* More code clean up.

**Version 1.3.0.1**

* Never officially released. 
* Contained code cleanup and some attempted fixes. 

**Version 1.3 Beta**

* Support for both Active Directory and OpenLDAP.
* The ability to create WordPress users automatically upon login based on LDAP group membership OR by LDAP authentication alone.
* The ability to test domain settings straight from admin panel.
* Announcements pane that allows me to update you with fixes, cautions, new beta versions, or other important information.

**Version 1.2.0.1**

* Changed required user level for admin page to 10, Administrators only.

**Version 1.2**

* Implemented multiple domain controllers.
* Changed field sizes on admin page to be more user friendly.

**Version 1.1**

* Moved settings to administration pages under settings.
* Upgraded to latest version of adLDAP 2.1.
* Got rid of credentials. (They are not neccessary for the authenticate function in adLDAP!)
* Plugin is now upgrade proof. Settings are stored using WordPress's setting functions.

**Version 1.0** 

* Original release.

== Installation ==

1. Use the WordPress plugin directory to install the plugin or upload the directory `simple-ldap-login` to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Update the settings to those that best match your environment by going to Settings -> Simple LDAP Login
1. If you don't get the settings right the first time, don't fret! Just use your WordPress credentials. They should always work 
1. Once you have the settings correct, you can toggle LDAP Exclusive mode (if you like).
1. To make your life easier, consider using two different browsers (e.g., Chrome and Firefox) to do testing.  Change settings in one. Test in the other. This will prevent any chance of being locked out.

== Frequently Asked Questions ==

= Other than WordPress, what does my system require? =

Your install of PHP must be configured/compiled with LDAP support.

= How do I know what the correct settings are? =

I have tried to make the settings as self-explanatory as possible. If you are struggling figuring them out, you may need to speak with your LDAP administrator. I realize this is an obnoxious response, but there is no good, fool proof way to help you discover these settings. A good place to start, if you're feeling daring, might be to use ADSIEdit for Windows and Active Directory, or GQ for Linux and OpenLDAP.

= It's still not working, what other things can I try? =

If you are confident your settings are correct and it still does not work, it may be time to check for port or firewall issues. If your LDAP server is running on a non-standard port or an obsolete version of the LDAP protocol you are going to have issues. Port 389 is the port this plugin, and nearly every other LDAP enabled software expects. They are also expecting protocol version 3. If you are using an old version of LDAP or running a non-standard port you may need to modify the code that the plugin runs or update your LDAP installation.

Unfortunately I can't be relied upon to assist with these types of requests. I chose not to support these scenarios because they are infrequent and because they confuse everyone else.

= It's still not working! How can I get help? = 
The easiest way to get help is to post a comment on my blog: http://clifgriffin.com/simple-ldap-login/. I'll do my best to get you up and running!

== Screenshots ==

1. Easy to use admin panel. 
2. Advanced options for power users.