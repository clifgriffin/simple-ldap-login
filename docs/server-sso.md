# Server single sign-on

Server SSO is part of Pro for new installations after the transition release. Existing installations retain it through the maintained Legacy package at no charge. The Transition package also includes SSO without a key or payment. The reduced Free package does not include SSO code.

Install and test Legacy before replacing Transition on a site that uses SSO. A saved legacy grant cannot restore SSO code absent from a ZIP. The external package update path and the full migration process need release tests before public distribution.

A server administrator must configure Kerberos, NTLM, Apache, Nginx, or the reverse proxy used for authentication. The plugin does not configure these services.

1. Configure a supported server authentication module to check the user.
2. Let the authenticated server set `REMOTE_USER`. Never copy an HTTP request header into this variable.
3. Block public paths to the WordPress origin that bypass that authentication. Check proxy and FastCGI settings.
4. Keep the WordPress login page and recovery path available. The plugin skips automatic SSO on `wp-login.php`.
5. Set a read-only directory search account and a nonempty password in the plugin settings. Use a trusted TLS connection.
6. For `DOMAIN\name` identities, set Login domain to the exact trusted domain. Other domains are rejected. UPN identities such as `name@example.org` are looked up in the configured directory base.
7. Test a known user, an unknown user, a denied user, missing `REMOTE_USER`, and a failed search account. Only the known user with permitted access must receive a WordPress session.
8. Test logout, password recovery, second-factor checks, and normal local administrator recovery before production use.

SSO binds with the search account, finds one matching user, and checks the required groups. It then runs the normal WordPress authentication filters before starting a session. An empty password bind cannot verify a user.

Server SSO and LDAP password login have separate switches. Both are off on new installations. `SLL_DISABLE_LDAP` stops both login paths.

The old Debian and CentOS guides contained obsolete package and server commands and have been removed. Use current instructions for the selected server module. Test the complete setup on a separate site.
