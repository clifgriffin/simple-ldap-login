=== Plugin Name ===
Contributors: clifgriffin
Donate link: http://clifgriffin.com/2008/10/28/simple-ldap-login-wordpress-plugin/ 
Tags: LDAP, authentication, login, active directory, adLDAP
Requires at least: 2.5.1
Tested up to: 2.7-rc1
Stable tag: 1.1

Super simple implementation of adLDAP with Wordpress! Authenticates wordpress users accounts against LDAP user accounts with the same username. That's all there is to it!

== Description ==
**If you have used this plugin (successfully or unsuccessfully), please visit the plugin homepage and leave feedback. Or you can e-mail me at webmaster[at]clifgriffin.com. Doing so will contribute to the future of this plugin and help me help others who may be experiencing problems.**

Having a single login for every service is a must in large organizations. This plugin is a *extremely* simple! It authenticates your wordpress username against LDAP using the same username. It does this by redefining the wp_authenticate function and adding a simple hook to adLDAP. The logic goes like this:

* If wordpress login fails, try adLDAP.
* If adLDAP succeeds, login. 
* Else, fail.

**Your WordPress usernames MUST be the same as your LDAP usernames for this to work!**

= Background =

In times past, our organization used a custom hack of wp-login.php that allowed us to implement adLDAP. This was not an upgrade proof solution. In the recent round of upgrades, I tried several purported LDAP plugins. Some of them didn't work at all. Some of them worked but didn't provide the exact functionality I desired.

= Version History =

**Version 1.1:**

* Moved settings to administration pages under settings.
* Upgraded to latest version of adLDAP 2.1.
* Got rid of credentials. (They are not neccessary for the authenticate function in adLDAP!)
* Plugin is now upgrade proof. Settings are stored using Wordpress's setting functions.

**Version 1.0:** 

* Original release.

== Installation ==

1. Upload the directory "simple-ldap-login" to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Immediately update the settings to those that best match your environment by going to Settings -> Simple LDAP Login
1. If you don't get the settings right the first time...don't fret! Just use your wordpress credentials...they will always work!

== Frequently Asked Questions ==

= Can feature x be added? =

Probably! E-mail me: webmaster[at]clifgriffin.com

= It's not working, what am I doing wrong? =

1. Make sure PHP is compiled with LDAP. If it is, running phpinfo should reveal a section entitled LDAP and it should indicate that it is enabled. 
1. Make absolutely sure your setttings are right and that your server can talk to your domain controller. 
1. Make sure your wordpress user accounts are the **same** as the LDAP user accounts you wish to use. This plugin does not bypass wordpress user accounts...just wordpress user account passwords!

= It's still not working! How can I get help? = 
There are two ways. You can post a comment on my blog (http://clifgriffin.com/2008/10/28/simple-ldap-login-wordpress-plugin/) or you can e-mail me: webmaster[at]clifgriffin.com. I will respond as soon as I can.

== Screenshots ==

1. The administration page under Settings -> Simple LDAP Login