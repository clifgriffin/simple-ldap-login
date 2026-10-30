# Release test coverage

Run this before a release:

```sh
npm run test:release
```

This command runs source checks, coding standards, asset checks, package checks, and the Docker matrix. A failed check returns a nonzero exit code. GitHub Actions runs the same suites on pushes and pull requests.

## Configuration selection

The release matrix checks 24 core configurations:

| Setting | Values |
| --- | --- |
| Package | Free, Pro |
| PHP and WordPress | PHP 8.1 with WordPress 6.5; PHP 8.5 with the current WordPress image |
| Directory | OpenLDAP, Samba AD |
| Installation | Single site; network activation; site-only activation in multisite |

The source CI job also runs on each PHP minor version from 8.1 through 8.5. Package tests check Free and Pro with fresh, schema 2, and 1.x settings in site and network scope.

Within each core configuration, the tests change selected settings and check their results. They cover both outcomes for important access controls and combine settings where one can affect another. They do not run every possible combination of fields.

## Required behavior

| Area | Covered cases | Tests |
| --- | --- | --- |
| Package startup | Production ZIP, PSR-4 loading, private dependency classes and helpers, correct feature hooks, no development files, no service request at startup | `tests/package.cjs`, `tests/package-runtime.php` |
| Settings scope | Site and network activation, explicit scope, child site reads and saves, isolation between site and network options | `tests/docker/migration.php`, `tests/docker/integration.php` |
| Connection policy | StartTLS, LDAPS, explicit LDAPS URI, wrong CA, wrong certificate host, failed first controller, stop after a credential denial | `tests/docker/integration.php` |
| Legacy plain LDAP | Explicit permission required, runtime guard, permitted OpenLDAP connection, Samba rejection of an unencrypted bind | `tests/docker/integration.php` |
| Directory lookup | Direct DN, anonymous and service-account subtree lookup, invalid search account, generic OpenLDAP schema, AD account name, UPN, and domain name | `tests/docker/integration.php` |
| Access groups | No group rule, any allowed group, all denied groups, group base, OpenLDAP membership attributes, direct and nested AD groups, primary groups and their parents | `tests/docker/integration.php` |
| Password bytes | Literal `0`, quotes, backslashes, entity text, and rejection of changed passwords | `tests/docker/integration.php` |
| Library API | No direct PHP LDAP function calls in either client, bounded queries, escaped values, and explicit LDAPS port 389 | `tests/ldap.php`, `tests/run.php` |
| Account creation | Creation on and off, existing account, safe configured role, invalid role, random local password, disabled AD account | `tests/docker/integration.php` |
| Recovery and fallback | Selected, legacy, and Off modes; local username/email login without LDAP; normal fallback; invalid local passwords; account removal and demotion; LDAP and SSO alias denial; WordPress security checks; recovery constant | `tests/auth.php`, `tests/docker/integration.php`, `tests/docker/http-check.php` |
| Pro account management | Ordered group roles, role removal, profile update conflicts, paged import, local account protection, stable identities, job locks, suspension and restoration, session removal, multisite membership restoration, and license expiry | `tests/pro.php`, `tests/docker/pro-features.php` |
| Audit | Completed login events, fixed event names, numeric IDs only, private storage, scope isolation, retention, paged CSV export, capability and nonce checks | `tests/pro.php`, `tests/admin.php`, `tests/docker/pro-features.php` |
| Profile and metadata | Default and custom attributes, Pro string and number values, custom suffixes, local edit retention, changed mapped values, protected WordPress keys, Free feature absence | `tests/run.php`, `tests/auth.php`, `tests/docker/integration.php` |
| Server SSO | SSO on/off, password login off, missing or invalid identity/service account, group denial, user and later login filter denial, existing session, password whitespace normalization | `tests/docker/http-check.php` |
| Pro licenses | Activation, expiry, renewal, inactive and missing licenses, failed checks, encrypted secrets, site and network scope, copied sites, product families, permissions, and retained LDAP password login | `tests/licenses.php`, `tests/admin.php`, `tests/package-runtime.php`, `tests/docker/licenses.php`, `tests/docker/http-check.php` |
| SSO request context | GET, POST, JSON, login page, AJAX, cron, XML-RPC, CLI, forged identity headers, auth cookies set only after success | `tests/docker/http-check.php` |
| Migration | Both old option formats, schema 2 to 3 recovery policy, false and custom values, old encrypted passwords, backup, repeat migration, malformed data | `tests/run.php`, `tests/docker/migration.php` |
| Package replacement | 1.x settings to Pro with LDAP login, SSO lookup, and metadata updates; Free to Pro, full package updates and replacements, blocked reduced Free update, deliberate inactive downgrade, activation and user data retention after shutdown | `tests/upgrades.php`, `tests/docker/upgrades.php` |
| Missing dependencies | LDAP absent; OpenSSL encryption functions absent; controlled errors, unchanged secrets, usable admin controls | `tests/docker/dependencies.php` |
| Recovery settings | Fresh selected policy, existing legacy policy, explicit Off, required selection before setup, eligible IDs, site membership, network superadmin scope, atomic saves | `tests/recovery-settings.php`, `tests/admin.php`, `tests/docker/migration.php` |
| Admin and input security | Capabilities, nonce, POST, scope, tab saves, Advanced field placement, old Free profile links, blank secret handling, malformed fields, escaping, protected metadata, no LDAP request during render | `tests/admin.php`, `tests/run.php` |

Each native update runs in a separate WordPress request. The following request checks the installed edition, activation, and saved data after WordPress finishes its shutdown work. Update checks also compare the user password hash, role, profile, and metadata.

The HTTP suite uses real Apache authentication and checks the returned auth cookies. Request fixtures affect only the test probes. They do not change saved product settings. The dependency suite starts separate PHP processes with LDAP or the encryption functions unavailable.

## Checks outside this matrix

Keep the [release checklist](release-checklist.md) for Microsoft AD DS, domain trusts, Kerberos negotiation, expired certificates, customer configurations, and named second-factor integrations. Samba and a test user filter do not establish compatibility with every AD policy or second-factor plugin.

Source and package tests cover PaidCommunities activation and status checks with controlled HTTP responses. They cover expiry, renewal, outages, and local feature checks. Docker checks cover the installed SDK, retained LDAP password login and recovery, and browser SSO restrictions. Live PaidCommunities account tests and external updates remain required before release.

When a defect is fixed, add a test that fails with the old behavior. Update this table when support is added or removed. Keep configuration changes in the appropriate native test, rather than adding a full matrix dimension for each field.
