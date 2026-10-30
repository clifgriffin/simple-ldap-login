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

## Scheduled access removal

On Accounts, select **Enable scheduled access removal**. Set a search account on Connection.
The hourly task checks only accounts marked by a verified LDAP login or import in the current scope.
A confirmed missing account, disabled AD account, changed directory identity, or failed login group rule suspends access.
WordPress sessions end. User data and content stay in place. Cookie and application password identities are blocked.
Each lookup first checks the directory Base DN. Connection and search errors keep the current access state.
A later check restores access only when the same directory account passes all login group rules.
Recovery accounts and super administrators are excluded.
License expiry and disabling the task keep current suspensions. The emergency recovery constant stops the suspension filters.
Directory renames can cause suspension until a verified login updates the saved DN and the next scheduled check passes.

## Multisite membership

Activate Pro for the network. On Accounts, enter one `group:site ID:role` rule per line.
Example: `Editors:2:editor`. Each target site must belong to the current network and have the selected role.
The first matching rule for each site applies at login and import.
Network site rules replace ordinary group role rules. New users receive only the memberships selected by site rules.
When a group no longer matches, prior roles return. Memberships created by the rules are removed.
A manual role change made after assignment is kept when the group no longer matches.
Unrelated site memberships stay unchanged. Blank rules stop membership updates and keep existing memberships.
Recovery accounts and super administrators are excluded. Site-only activation cannot change other sites.
