# Simple LDAP Login

Use the [Herd demo](docs/demo.md) for manual tests with the current plugin source and Docker LDAP accounts.

Use the [Docker test environments](docs/docker-tests.md) to test the Free and Pro release ZIPs with OpenLDAP or Samba AD.

Use Active Directory or OpenLDAP accounts to log in to WordPress. The plugin requires WordPress 6.5 or later and PHP 8.1 or later, with LDAP, OpenSSL, and iconv.

Version 2.0 is in development. Complete the [release checks](docs/release-checklist.md) before production use. Source checks use simulated directory responses. Docker tests use real WordPress, MariaDB, OpenLDAP, Samba AD, and Apache server sign-on. Microsoft AD and Kerberos still need deployment tests.

Free includes password login, account creation, required groups, nested and primary AD groups, standard profile attributes, network settings, and directory server failover. Server SSO and custom metadata mappings belong to Pro.

For a direct upgrade from 1.x, install Pro if the site uses server SSO or custom user fields. Install Free for LDAP password login without those features. Test the upgrade on a site copy and keep a working local administrator account.

## Development

Use Composer 2, PHP 8.2 or later for package builds, and the Node LTS version in `.nvmrc`. The installed plugin requires PHP 8.1 or later.

```sh
composer install
composer install --working-dir=pro
nvm use
npm ci
composer check
npm run lint
npm run test:packages
```

Run `npm run test:release` before a release. It adds the complete Docker configuration matrix, native migrations and package updates, and HTTP SSO checks. See the [coverage map](docs/test-coverage.md).

See the [translation guide](docs/translations.md) for the 24 bundled locales and translation commands.

Composer manages PSR-4 autoloading, WordPress Coding Standards, and PHP compatibility checks. LdapRecord provides the LDAP connection and directory model APIs. The PHP LDAP extension remains a server requirement. Plugin code uses library methods for each LDAP operation. The source checks use the library transport fake without WordPress or a directory server.

The selected directory schema still controls login attributes and bind names. LdapRecord handles AD account filters, nested groups, primary groups, and SID conversion. OpenLDAP group queries use the saved schema attributes. Existing settings and WordPress hooks keep their current format.

The client has a small compatibility step for LdapRecord 4.1 password handling. It encodes ampersands and the literal password `0` before the library decodes them. Real directory tests check that passwords keep their exact bytes. Remove this step when the library preserves those bytes itself.

`@wordpress/scripts` checks the CSS, JavaScript, and package metadata. It also creates the release ZIP. The settings screen uses WordPress forms and CSS.

```sh
npm run build:free
npm run build:pro
```

The ZIPs in `dist/` are:

| Command | ZIP | Contents |
| --- | --- | --- |
| `build:free` | `simple-ldap-login.zip` | Basic LDAP login; excludes SSO and custom metadata code |
| `build:pro` | `simple-ldap-login-pro.zip` | Free features, server SSO, and custom metadata mappings |

Every ZIP contains the `simple-ldap-login` folder and `Simple-LDAP-Login.php` entry file. Install one edition. It replaces the existing package and does not require an add-on. `npm run plugin-zip` builds Free.

Each build copies release files into a temporary folder, installs production dependencies from the Composer lock files, uses PHP-Scoper to put bundled dependencies under `SimpleLdapLoginVendor`, and calls `wp-scripts plugin-zip`. It then removes the temporary folder. The source files and development dependencies stay unchanged. The PHP-Scoper build tool has its own manifest and lock in `tools/scoper/` and is excluded from the ZIP. Site owners install the ZIP without Composer or Node.

The Pro build includes the SSO and metadata classes from `pro/`. Both features need an active PaidCommunities license. Hourly background checks save the license status; login reads only that saved status. Confirmed expiry stops SSO and custom field updates. LDAP password login, recovery accounts, settings, and existing user data remain available. See [licensing](docs/licensing.md).

The Pro preview uses `Update URI: false` to prevent replacement by a WordPress.org update. Automatic external updates and live service acceptance tests remain required before sales.

### Pro source

Keep shared classes in `src/`, under the `SimpleLdapLogin\` PSR-4 namespace. Put Pro classes in `pro/src/`, under `SimpleLdapLogin\Pro\`. The Pro folder needs these release files:

```text
pro/
  bootstrap.php
  composer.json
  composer.lock
  readme.txt
  src/
  assets/          # Optional.
```

The shared Composer manifest includes the PaidCommunities SDK so Free can show plans and activate a Pro license. Both editions use the same checked handlers and stored license record. Pro feature code stays in `pro/`. Its separate manifest uses PSR-4:

```json
{
  "name": "clifgriffin/simple-ldap-login-pro",
  "license": "GPL-2.0-or-later",
  "require": {
    "php": ">=8.1"
  },
  "autoload": {
    "psr-4": {
      "SimpleLdapLogin\\Pro\\": "src/"
    }
  },
  "config": {
    "platform": {
      "php": "8.1.0"
    }
  }
}
```

The Pro manifest needs its own PHP platform setting. The shared manifest's setting does not apply to it. This keeps dependency selection compatible with PHP 8.1.

Generate and commit `pro/composer.lock` when adding its dependencies. `pro/bootstrap.php` checks `ABSPATH`, loads `pro/vendor/autoload.php`, and registers the SSO and metadata features. Use shared services and the `sll_loaded` hook where needed. Do not check a remote license during login.

The Pro build includes the bootstrap, Composer files, classes, assets, and production dependencies. The reduced Free ZIP excludes the entire Pro folder and keeps WordPress.org updates.

`npm run test:packages` checks the package contents, stable plugin path, PSR-4 class loading, production autoloaders, file exclusions, and source preservation. Test classes must not enter product ZIPs.

## Settings and migration

The plugin keeps its entry file, `Simple-LDAP-Login.php`, and settings option, `sll_settings`. It uses the original site or network scope. Migration reads the older `simpleldap_*` options, makes one `sll_settings_backup_v1` backup, and keeps the old options and unknown settings keys. A save changes only the submitted fields.

There are only two packages: Free and Pro. No eligibility flag or separate migration package is required. A 1.x site that uses SSO or custom user fields must upgrade directly to Pro to retain those features. Free keeps the saved values and shows a notice if they are configured, but it cannot run code that is absent from its ZIP.

An active Pro installation rejects an incoming ZIP that lacks the Pro files. If WordPress deactivated the plugin before this check, the guard restores its original activation scope. To choose Free, deactivate Pro before installing Free.

Existing plain LDAP settings continue to work and show a warning. New installs require TLS by default. TLS failure stops the connection. Install the correct certificate authority on the PHP server; the plugin has no control to disable certificate checks.

Migration converts the search password from hostname-based encryption to AES-256-GCM. If the plugin cannot read the old password, it keeps the stored value. Enter the password again in the settings. Keep WordPress salts stable or set a long, random `SLL_ENCRYPTION_KEY` in `wp-config.php` before setting the search password. Keep this key outside the database. A key change requires password re-entry.

The backup can contain old weakly encrypted credentials. Restrict database backup access. Remove the migration backup after the upgrade and recovery checks are complete. Do not restore it to an active system without a tested recovery plan.

## Login behavior

LDAP checks run before the core password handlers. LDAP-only mode blocks core username, email, and application password fallback for other users. Local administrators retain local password recovery unless an existing `sll_force_ldap` filter explicitly disables it. Other WordPress authentication and user checks remain installed. Group denial blocks local fallback for other users.

For Active Directory, a verified UPN or domain login uses the directory account name when no WordPress account matches the entered name. Existing exact login names are kept. New accounts receive a random local password, never the LDAP password. Standard profile values are set during account creation. In editions with custom metadata, the two original suffix keys remain. Local edits are kept until the LDAP source value changes. Mappings cannot change protected WordPress account metadata.

Server SSO is included in Pro. It requires a web server that authenticates the user and sets `REMOTE_USER`. It also requires a nonempty search account password and a unique LDAP user result. `DOMAIN\name` identities must match the configured Login domain. See [server SSO](docs/server-sso.md).

The connection and login tests require an administrator action and a nonce. They do not create users or sessions. Save connection settings before running a test.

## Recovery and removal

Keep a separate local administrator account. To stop LDAP password and SSO hooks, add this before WordPress loads in `wp-config.php`:

```php
define( 'SLL_DISABLE_LDAP', true );
```

Log in with a local password, correct the settings, and remove the constant. You can also rename the plugin folder through your host's file manager.

Deactivation keeps your settings. Uninstall removes them only if an administrator selected **Delete settings on uninstall** for that scope. It keeps WordPress users and does not load the plugin or connect to LDAP.

## Extension hooks

The legacy filters remain: `sll_get_setting`, `sll_force_ldap`, `sll_remove_default_authentication_hook`, `sll_ldap_auth`, `sll_user_has_groups`, and `sll_user_data`. The custom metadata feature retains `sll_user_meta_data` in Pro. The actions `sll_auth_success` and `sll_auth_failure` remain. A compatibility class provides the old global object and its settings methods. The old `adLDAP` object and its directory administration methods are no longer available.

`false` from `sll_force_ldap` means that the administrator must use LDAP. This inverted legacy meaning is retained. The hook named `sll_remove_default_authentication_hook` now controls fallback policy without removing core or third-party filters. `sll_ldap_auth` can reject a successful bind; it cannot turn a failed bind into a valid identity. `sll_auth_success` now runs for both new and existing LDAP accounts.

Extensions can register through `sll_loaded`, which supplies the settings and authentication services. Do not call a licensing service during login.

## Product plan

Pro includes server SSO and custom user fields. A 1.x site that uses either feature must upgrade directly to Pro. Central network settings remain free. Multiple independent directories, group-to-role rules, scheduled account updates, audit exports, and network setup tools remain planned features.

PaidCommunities manages purchases, license activation, and status checks. Free has a Get Pro tab with the official pricing and activation components. Both editions use the SDK services with a checked HTTP client and license handlers. It does not register the SDK's unsafe admin handlers. Automatic updates and live account checks remain open in the [product plan](docs/product-plan.md).

All editions use GPL-2.0-or-later. Pro features require an active subscription. A service error keeps the last verified status; it does not activate an expired license. Expiry does not end existing WordPress sessions or delete settings.

The purchase service loads only after an administrator selects **View plans**. Checkout runs on PaidCommunities. After purchase, install the Pro ZIP from the receipt at the same plugin path. A license activated in Free stays active after replacement. Activation does not install or enable paid feature code in Free.

See [Pro account features](docs/pro-features.md) for group role settings and behavior.
