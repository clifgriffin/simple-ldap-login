# Pro account features

These features require the Pro package and an active license.

## Group roles

On Access, enter one `group:role` rule per line. Example: `Editors:editor`.
The first matching rule sets the role at each LDAP password login or server SSO.
If no rule matches, the default role applies. The rule replaces current roles.
Leave the rules blank to keep current roles. Each rule must use an existing role.
Recovery accounts and super administrators are excluded.
AD rules include nested and primary groups. OpenLDAP uses the configured group attributes.
Group names follow the same rules as the free login restriction.

## Profile updates

On User data, select **Update profiles at login**. This option is off by default.
Each login copies the configured first name, last name, email, and website.
The display name uses the first and last name, or the account name if both are empty.
Empty directory values clear fields, except email. A missing or invalid email stops the update.
A WordPress update error, including a duplicate email, retains the profile.
Passwords and account names do not change. Recovery accounts and super administrators are excluded.

## Scheduled import

On Accounts, select **Enable scheduled import**. Set a search account on Connection.
The hourly WordPress task imports users below the Import Base DN, or the connection Base DN if blank.
Import groups restrict the selection. Login group restrictions also apply.
New accounts use a random local password and the default role. Group role rules also apply.
Existing local accounts are excluded. A successful LDAP login marks an existing account for later imports.
Repeat imports keep the same accounts. They run optional profile and custom field updates.
No login session is created. Import works when account creation at login is off.
WordPress cron needs site traffic or a system task. Network activation runs the job on the main site only.
Directory errors do not remove accounts. Deactivation clears the schedule.
