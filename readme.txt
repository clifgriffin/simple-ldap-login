=== Plugin Name ===
Contributors: clifgriffin
Donate link: http://clifgriffin.com/donate/
Tags: LDAP, authentication, login, active directory, adLDAP
Requires at least: 2.8
Tested up to: 3.2.1
Stable tag: 1.4.0.5.1

Integrating Wordpress with LDAP shouldn't be difficult. Now it isn't. Simple LDAP Login provides all of the features, none of the hassles.

== Description ==
Having a single login for every service is a must in large organizations. This plugin allows you to integrate Wordpress with LDAP quickly and easily. Like, really really easy.

**Donate?**

If you use this plugin, and find that it serves your organization's needs, please consider a donation. I have spent many unpaid hours supporting and updating this plugin. A plugin like this would easily retail for $200 or more. I offer it for free, with only the request that you consider giving back if you are financially capable of doing so.

**Support**

If you need support, I recommend you leave a comment on the appropriate post on my blog:
http://clifgriffin.com/2009/05/13/simple-ldap-login-13-for-wordpress/

**Special Requests**

If you need a customization or change specific to your install, I am available for hire. Shoot me an e-mail: clifgriffin[at]gmail.com

= Features =

* Supports Active Directory and OpenLDAP (and other directory systems which comply to the LDAP standard, such as OpenDS)
* Supports TLS
* Uses up-to-date methods for Wordpress authentication routines.
* Includes three login modes:
* * Normal Mode: Authenticates existing wordpress usernames against LDAP. This requires you to create all Wordpress accounts manually using the same user names as those in your LDAP directory.
* * Account Creation Mode 1: Creates Wordpress accounts automatically for any LDAP user.
* * Account Creation Mode 2: Creates Wordpress accounts automatically for LDAP users in a specific Group you specify.
* Intuitive control panel.

= Architecture =
Simple LDAP Login adds an authentication filter to Wordpress that authentication requests must pass. In doing so, it makes several decisions.

* Can the provided credentials be authenticated against LDAP?
* * If so, is the username a valid WP username?
* * * If not, can we create a WP user?
* * * * If we can, does the user belong to the right (if any) group?
* * * * * If the user does, create the Wordpress user and log the user in.
* * If the username is already valid wordpress user, are they in the right group?
* * * Is so, log the user in.

This is simply a high level overview. The actual logic the plugin employs is more complex, but hopefully this gives you an idea, philosophically, about how the plugin accomplishes what it does. If the plugin is unable to authenticate the user, it passes it down the chain to Wordpress. (Unless security mode is set to high, which will disable this functionality.)

== Changelog ==
**Version 1.4.0.5.1** 
* I broke it. Sorry guys! :(
* Downgraded adLDAP as some referenced functions no longer exist. 

**Version 1.4.0.5**
* Updated adLDAP to version 4.x
* Fixed error in OpenLDAP group membership check
* As always TEST this first. Don't assume it worksÉI don't have a testing environment to ensure it will work correctly. 

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
* Shows actual error message from Wordpress upon failure.

**Version 1.4**

* First update in about a year. Thanks for your patience. 
* Completely rewritten to support changes in Wordpress 2.8+.  Now fully supports Wordpress 3.0.
* Much more manageable and efficient code structure. Less code repetition.
* Includes TLS support. 
* Allows OpenLDAP users to specify an alternate LDAP attribute to use for logins for those not using UID.

**Version 1.3.0.3**

* Test form now implements wp_authenticate and uses the same routines as the actual login. This also means account creation and group membership are tested. 
* Implemented stripslashes() to correct issue with some special characters such as a single quote and backslash. 
* Wordpress account "admin" is now allowed to login using local password even when security mode is set to high. For safety.
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
* The ability to create wordpress users automatically upon login based on LDAP group membership OR by LDAP authentication alone.
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
* Plugin is now upgrade proof. Settings are stored using Wordpress's setting functions.

**Version 1.0** 

* Original release.

== Installation ==

1. Use the WordPress plugin directory to install the plugin or upload the directory “simple-ldap-login” to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Immediately update the settings to those that best match your environment by going to Settings -> Simple LDAP Login
1. If you don’t get the settings right the first time…don’t fret! Just use your wordpress credentials…they will always work in low security mode.  
1. Once you have the settings correct, you can change the security mode to High Security (if you so desire).
1. To make your life easier, consider using two different browsers (e.g., IE and Firefox) to do testing.  Change settings in one. Test in the other. This will prevent any chance of being locked out.

== Frequently Asked Questions ==

= Other than Wordpress, what does my system require? =

If you are using Active Directory, you will probably need PHP 5. This is because I'm using adLDAP 3.0 to do my Active Directory integration. As far as I know, the rest of the code should work with PHP 4. If you are unable to upgrade to PHP 5, find an older version of adLDAP (on sourceforge) and replace adLDAP.php with it. This should bypass the PHP 5 requirement. 

Additionally, PHP must be configured/compiled with ldap support!

= How do I know what the correct settings are? =

I have tried to make the settings as self-explanatory as possible. If you are struggling figuring them out, you may need to speak with your LDAP administrator. I realize this is an obnoxious response, but there is no good, fail proof way to help you discover these settings. A good place to start, if you're feeling daring, might be to use ADSIEdit for Windows and Active Directory, or GQ for Linux and OpenLDAP.

= It's still not working, what other things can I try? =

If you are confident your settings are correct and it still does not work, it may be time to check for port or firewall issues. If your LDAP server is running on a non-standard port or an obsolete version of the LDAP protocol you are going to have issues. Port 389 is the port this plugin, and nearly every other LDAP enabled software expects. They are also expecting protocol version 3. If you are using an old version of LDAP or running a non-standard port you may need to modify the code that the plugin runs or update your LDAP installation.

Unfortunately I can't be relied upon to assist with these types of requests. I chose not to support these scenarios because they are infrequent and because they confuse everyone else.

= It's still not working! How can I get help? = 
There are two ways. You can post a comment on my blog (http://clifgriffin.com/2009/05/13/simple-ldap-login-13-for-wordpress/) or you can e-mail me: me[at]clifgriffin.com. I'll do my best to get you up and running!

== Screenshots ==

1. Easy to use admin panel. 