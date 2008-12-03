=== Plugin Name ===
Contributors: clifgriffin
Donate link: http://clifgriffin.com/2008/10/28/simple-ldap-login-wordpress-plugin/ 
Tags: LDAP, authentication, login, active directory, adLDAP
Requires at least: 2.5.1
Tested up to: 2.7-rc1
Stable tag: 1.1

Super simple implementation of adLDAP with Wordpress! Authenticates wordpress users against LDAP. That's all there is to it.

== Description ==
Having a single login for every service is a must in large organizations. This plugin is a *extremely* simple! It redefines the wp_authenticate function and adds a simple hook to adLDAP. The logic goes like this:
*   If wordpress login fails, try adLDAP.
*   If adLDAP succeeds, login. 
*   Else, fail.

Background

In times past, our organization used a custom hack of wp-login.php that allowed us to implement adLDAP. This was not an upgrade proof solution. In the recent round of upgrades, I tried several purported LDAP plugins. Some of them didn't work at all. Some of them worked but didn't provide the exact functionality I desired.

Version History

Version 1.1
*   Moved settings to administration pages under settings.
*   Upgraded to latest version of adLDAP 2.1.
*   Got rid of credentials. (They are not neccessary for the authenticate function in adLDAP!)
*   Plugin is now upgrade proof. Settings are stored using Wordpress's setting functions.

Version 1.0 
*   Original release.

== Installation ==

1. Upload the directory "simple-ldap-login" to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Immediately update the settings to those that best match your environment by going to Settings -> Simple LDAP Login
1. If you don't get the settings right the first time...don't fret! Just use your wordpress credentials...they will always work!

== Frequently Asked Questions ==

= Can feature x be added? =

Probably! E-mail me: webmaster[at]clifgriffin.com

== Screenshots ==

1. The administration page under Settings -> Simple LDAP Login
