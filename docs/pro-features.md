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
