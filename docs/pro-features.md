# Pro and Enterprise features

Both plans use the Pro ZIP. Pro costs USD 99 per year for one site and includes backup LDAP servers, server SSO, custom user fields, group role rules, and profile updates. Enterprise costs USD 499 per year for one site and adds scheduled account import, scheduled access removal, multisite membership rules, and audit history with retention and CSV export.

The plugin reads verified product access from saved local license state. Login makes no license service request. An activation plan choice does not grant features. Unknown or missing product access keeps paid features off until a license check verifies the plan. A service error keeps the last verified state. See [licensing](licensing.md) for activation and purchase steps.

Pro includes [server SSO](server-sso.md) and custom user field mappings on User data. The following sections describe the account features and their required plan.

## Backup LDAP servers

Requires Pro or Enterprise. On Connection, enter the primary server first, then one backup hostname or IP address per line. All servers must use the same directory. The next server is tried if the previous server is unavailable. Rejected credentials or group access do not trigger another attempt. Free and expired or inactive licenses use only the first server. Saved backup addresses are kept, including during upgrades and package changes. This rule applies to password login, connection tests, SSO, and account jobs. License checks use local state only.

## Custom user fields

Requires Pro or Enterprise. On User data, select **Add row** under Custom user fields. Enter the LDAP attribute and WordPress meta key. Select Text or Number. Select **Remove row** to delete a mapping, then save the settings. Existing mappings appear as rows.

## Group roles

Requires Pro or Enterprise.

On Access, select **Add row** under Group roles. Enter an LDAP group and a WordPress role. For example, enter `Editors` and `editor`. The role field suggests installed roles.
The first matching rule sets the role at each LDAP password login or server SSO.
If no rule matches, the default role applies. The rule replaces current roles.
Remove all rule rows to keep current roles. Each rule must use an existing role.
Recovery accounts and super administrators are excluded.
AD rules include nested and primary groups. OpenLDAP uses the configured group attributes.
Group names follow the same rules as the free login restriction.

## Profile updates

Requires Pro or Enterprise.

On User data, select **Update profiles at login**. This option is off by default.
Each login copies the configured first name, last name, email, and website.
The display name uses the first and last name, or the account name if both are empty.
Empty directory values clear fields, except email. A missing or invalid email stops the update.
A WordPress update error, including a duplicate email, retains the profile.
Passwords and account names do not change. Recovery accounts and super administrators are excluded.

## Scheduled import

Requires Enterprise. Pro users see an upgrade link on Accounts. The saved settings remain, but the controls are disabled and the job does not run.

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

Requires Enterprise. Existing suspensions and directory identity checks remain enforced if the license expires or changes to Pro.

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

Requires Enterprise. Activate the Pro ZIP for the network with an Enterprise license. On Accounts, select **Add row** under Multisite membership. Enter the LDAP group, Site ID, and WordPress role in separate fields.
For example, enter `Editors`, `2`, and `editor`. Each target site must belong to the current network and have the selected role.
The first matching rule for each site applies at login and import.
Network site rules replace ordinary group role rules. New users receive only the memberships selected by site rules.
When a group no longer matches, prior roles return. Memberships created by the rules are removed.
A manual role change made after assignment is kept when the group no longer matches.
Unrelated site memberships stay unchanged. Remove all rule rows to stop membership updates and keep existing memberships.
Recovery accounts and super administrators are excluded. Site-only activation cannot change other sites.

## Audit history and CSV export

Requires Enterprise. Pro users see an upgrade link on Audit. The saved settings and existing records remain, but collection, retention tasks, and CSV export do not run.

On Audit, select **Enable audit history**. Collection is off by default.
The screen shows the latest 50 records. Export CSV downloads all retained records in the current scope.
Export requires the settings administrator capability, the current scope, a valid nonce, and an active Enterprise license.
Records contain a UTC time, a fixed event name, and actor, user, and site IDs.
Events include completed WordPress logins, failed login or directory checks, role changes, profile updates, imports, access changes, membership changes, and settings changes.
A directory check that passes before a later login denial does not produce a successful login record.
Passwords, credentials, directory field values, request headers, and attempted account names are excluded.
Private WordPress posts store the records. They have no public page or REST API.
Network records stay on the main site and retain the source site ID. Site records remain separate.
Retention defaults to 30 days. Set 1 to 3650 days. A daily WordPress task removes expired records in batches.
Turning off collection or losing a license keeps existing records. Deactivation clears scheduled tasks.
Uninstall keeps audit records, user data, and content.
