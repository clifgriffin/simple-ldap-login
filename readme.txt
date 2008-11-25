=== Plugin Name ===
Contributors: clifgriffin
Donate link: http://clifgriffin.com/2008/10/28/simple-ldap-login-wordpress-plugin/ 
Tags: LDAP, authentication, login, active directory
Requires at least: 2.5.1
Tested up to: 2.7-beta3
Stable tag: 1.0.0.1

Authenticates wordpress users against LDAP. That's all there is to it.

== Description ==
Having a single login for every service is a must in large organizations. This plugin is a very simple. It redefines the wp_authenticate function and adds a simple hook to adLDAP. The logic goes like this:
If wordpress login fails, try adLDAP.
If adLDAP succeeds, login. 
Else, fail.

Version History:
1.0 - Original release.

Background:

In times past, our organization used a custom hack of wp-login.php that allowed us to implement adLDAP. This was not an upgrade proof solution. In the recent round of upgrades, I tried several purported LDAP plugins. Some of them didn't work at all. Some of them worked but didn't provide the exact functionality I desired.

== Installation ==

1. Upload the directory "simple-ldap-login" to the `/wp-content/plugins/` directory
2. Customize settings by modifying adLDAP.php in /plugins/simple-ldap-login/ 
3. Activate the plugin through the 'Plugins' menu in WordPress

Note: You may wish to create a backup of adLDAP.php once you're done. Automatic plugin updates might overwrite this file and make upgrades more difficult. 

== Frequently Asked Questions ==

= Will you be moving the settings to the admin panel? =

At some point. These settings are not frequently changed however and a competent system administrator should not have any issues with configuration.

= I noticed you're using adLDAP 1.4. Why?  =
This is the version my organization was using previously and  it was very easy to simply copy over. It should work fine. If there are issues with this version that affect you, let me know and I'll investigate.

= Can feature x be added? =

Probably! E-mail me: webmaster[at]clifgriffin.com
