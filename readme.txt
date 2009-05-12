=== Plugin Name ===
Contributors: clifgriffin
Donate link: http://clifgriffin.com/code/donate/
Tags: LDAP, authentication, login, active directory, adLDAP
Requires at least: 2.5.1
Tested up to: 2.7.1
Stable tag: 1.2.0.1

Super simple implementation of LDAP with Wordpress! Authenticates wordpress users accounts against LDAP user accounts with the same username. That's all there is to it!

== Description ==
**BETA Version Available**
I released Simple LDAP Login 1.3 Beta on 05/12/2009.  The new version supports integrated user creation based on LDAP authentication, group membership, or the original mode. Additionally, I have implemented Olivier Fontes' MXC LDAP plugin into my plugin. In theory, this should add support for OpenLDAP. It is important to note, however, that **I have NOT been able to test any OpenLDAP scenarios**. The code logic makes sense to me, but I am 100% confident there will be issues that come up. **I'm counting on you guys to help me out in testing this.** 

The new version seems to work flawlessly in Active Directory. I will be posting a complete description of how the new plugin works philosophically soon. Until now, I believe it should be fairly self-explanatory. 

The new version is available under the versions link on the right. I have not made it the primary release because it is in beta at this point and many features have not been tested adequately.

Please comment on this post with issues, questions: http://clifgriffin.com/2009/05/12/simple-ldap-login-13-beta-released

Or, you can e-mail me: me[at]clifgriffin.com

**Old description for =< 1.2.0.1**
*NOTE: It appears that all versions before 1.3 do not work with OpenLDAP. If you need OpenLDAP, please see the early beta release of 1.3.*

Having a single login for every service is a must in large organizations. This plugin is a *extremely* simple! It authenticates your wordpress username against LDAP using the same username. It does this by redefining the wp_authenticate function and adding a simple hook to adLDAP. The logic goes like this:

* If wordpress login fails, try adLDAP.
* If adLDAP succeeds, login. 
* Else, fail.

**Your WordPress usernames MUST be the same as your LDAP usernames for this to work!**

= Background =

In times past, our organization used a custom hack of wp-login.php that allowed us to implement adLDAP. This was not an upgrade proof solution. In the recent round of upgrades, I tried several purported LDAP plugins. Some of them didn't work at all. Some of them worked but didn't provide the exact functionality I desired.

= Version History =
**Version 1.3 Beta**

*Support for both Active Directory and OpenLDAP.
*The ability to create wordpress users automatically upon login based on LDAP group membership OR by LDAP authentication alone.
*The ability to test domain settings straight from admin panel.
*Announcements pane that allows me to update you with fixes, cautions, new beta versions, or other important information.

**Version 1.2.0.1:**

* Changed required user level for admin page to 10, Administrators only.

**Version 1.2:**

* Implemented multiple domain controllers.
* Changed field sizes on admin page to be more user friendly.

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
2. Version 1.3 Beta administration page highlighting new features