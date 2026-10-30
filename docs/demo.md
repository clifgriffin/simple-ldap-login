# Local Herd demo

Use this site for manual tests of the current Free plugin source. Herd serves WordPress from `~/Sites/simple-ldap-demo`. Docker runs OpenLDAP and MariaDB in the separate `sll-demo` project. The automated test sites and their data are separate.

## Open the site

- [WordPress site](http://simple-ldap-demo.test/)
- [WordPress login](http://simple-ldap-demo.test/wp-login.php)
- [Plugin settings](http://simple-ldap-demo.test/wp-admin/options-general.php?page=simple-ldap-login)

These are public test passwords. Use them only on this local demo. Do not share this site through a tunnel.

| Account | Login name or bind DN | Password | Expected result |
| --- | --- | --- | --- |
| WordPress administrator | `testadmin` | `Test-Admin-42!` | Local administrator access, including when LDAP is stopped |
| Alice | `alice` | `Test-Password-42!` | LDAP login succeeds; WordPress role is Subscriber |
| Bob | `bob` | `Test-Password-42!` | LDAP password is correct, but the `staff` group rule denies access |
| LDAP search account | `cn=reader,dc=example,dc=test` | `Reader-Password-42!` | Directory searches; no WordPress login |
| LDAP administrator | `cn=admin,dc=example,dc=test` | `Test-Directory-Root-42!` | Directory changes; no WordPress login |

Alice's DN is `uid=alice,ou=people,dc=example,dc=test`. Bob's DN is `uid=bob,ou=people,dc=example,dc=test`. Alice belongs to `staff`, `editors`, and `unique`. Bob belongs to `denied`.

## Start and stop

Install Docker with Compose v2, Herd with PHP 8.4, WP-CLI, and Composer. Start Docker and Herd. The Herd PHP version must have LDAP and MySQL support. The setup checks the CLI extensions. The site uses Herd's default web PHP version. `~/Sites` must be a Herd parked path. If necessary, run `herd park ~/Sites`.

Run these commands from the plugin repository:

```sh
composer install
sh demo/setup.sh up
```

The setup creates the site, copies the public LDAP CA certificate, links the plugin source, sets the initial LDAP options, and runs login checks. It creates Alice's WordPress account during the checks. Outgoing WordPress email is suppressed. Automatic core updates and scheduled WordPress tasks are disabled. The site asks search engines not to index its pages.

Run `up` again to start stopped services. It keeps database records, LDAP accounts, and changed plugin settings. It copies the current CA certificate and renews it if it expires within one day. A directory certificate is valid for 30 days. Run `up` after renewal so both WP-CLI and web PHP use the current CA.

```sh
# Check the default login rules again. No users are deleted.
sh demo/setup.sh check

# Stop both demo containers. Keep all data.
sh demo/setup.sh stop

# Show service status or logs.
docker compose -f demo/compose.yml ps
docker compose -f demo/compose.yml logs --tail=50 ldap
```

New demo setups select `testadmin` as a local recovery account. Existing demos keep their saved recovery policy.

The checks expect the default settings and passwords in this guide. If you change a group rule or test password, a check can fail as a result.

Docker stores the database, directory, and certificates in the `sll-demo_database`, `sll-demo_ldap-data`, and `sll-demo_ldap-certs` volumes. The containers start again with Docker unless you stop them. The database and LDAP ports bind only to `127.0.0.1`.

## LDAP connection settings

Open **Settings → Simple LDAP Login**. The setup saves these values:

| Setting | Value |
| --- | --- |
| LDAP login | Enabled |
| Directory type | OpenLDAP |
| Directory server | `127.0.0.1` |
| Port | `1389` |
| Transport | StartTLS |
| Allow unencrypted connections | Off |
| Base DN | `dc=example,dc=test` |
| Search sub-OUs | On |
| Search account | `cn=reader,dc=example,dc=test` |
| Search password | `Reader-Password-42!` |
| Login attribute | `uid` |
| Group base DN | `ou=groups,dc=example,dc=test` |
| Required group | `staff` |
| Group name attribute | `cn` |
| Group membership attribute | `memberUid` |
| Create WordPress users | On |
| New user role | Subscriber |
| High security | On |
| Local recovery access | Selected administrators |
| Recovery accounts | `testadmin` |
| Server SSO | Off |

Use `127.0.0.1`. The test certificate contains this IP address. Herd's LDAP library can replace `localhost` with the Mac's host name during certificate checks, which causes a name mismatch. The demo MU plugin at `~/Sites/simple-ldap-demo/wp-content/mu-plugins/sll-demo.php` trusts only the copied demo CA for LDAP. It does not disable certificate checks or change the system trust store.

To test LDAPS, select **LDAPS** and port `1636`. Use the same host, accounts, and CA. Return to StartTLS and port `1389` after the test.

To restore the default plugin settings without deleting users:

```sh
wp --path="$HOME/Sites/simple-ldap-demo" eval-file "$PWD/demo/configure.php"
sh demo/setup.sh check
```

## Manual tests

1. Log in as `testadmin`. Open the plugin settings and use the connection test. It must succeed.
2. Open a private browser window. Log in as `alice` with `Test-Password-42!`. The login must succeed.
3. Open Alice's profile. Check the name **Alice Example**, email `alice@example.test`, and Subscriber role.
4. Log out. Try Alice with an incorrect password. The login must fail.
5. Try `bob` with `Test-Password-42!`. The group rule must deny access. WordPress must not create Bob's account.
6. As the administrator, remove the required `staff` group and save. Bob can then log in. Restore `staff` after this test.
7. Stop only LDAP with the command below. Alice's login must fail. The local `testadmin` login must still succeed. Start LDAP again after the test.

```sh
docker compose -f demo/compose.yml stop ldap
# Test the logins, then restart LDAP.
sh demo/setup.sh up
```

To test first-login account creation again, delete only Alice's WordPress account. Keep her LDAP account. Then log in as Alice through the browser before you run the automated demo check.

```sh
wp --path="$HOME/Sites/simple-ldap-demo" user delete alice --yes
```

To test the other seeded OpenLDAP group types, use `editors` with membership attribute `member`, or `unique` with `uniqueMember`. Both groups contain Alice. Restore `staff` and `memberUid` after each test.

## Inspect the directory and database

Run this directory search from the repository. `-ZZ` requires StartTLS. The command prompts for the search account password from the table above.

```sh
docker compose -f demo/compose.yml exec ldap \
  env LDAPTLS_CACERT=/certs/ca.crt ldapsearch -x -ZZ \
  -H ldap://localhost -D 'cn=reader,dc=example,dc=test' -W \
  -b 'dc=example,dc=test' '(uid=*)' dn uid cn mail
```

For a desktop LDAP client, use `127.0.0.1:1389` with StartTLS, or `127.0.0.1:1636` with LDAPS. Import `~/Sites/simple-ldap-demo/.sll-demo-ca.crt` into that client's CA settings. Use the LDAP administrator bind DN for changes.

| Database setting | Value |
| --- | --- |
| Host | `127.0.0.1` |
| Port | `13306` |
| Database | `wordpress` |
| User | `wordpress` |
| Password | `Test-Database-42!` |
| Root user | `root` |
| Root password | `Test-Root-Database-42!` |

## Source and scope

Plugin Check is installed and active. See the [check results and commands](plugin-check.md). See the [translation guide](translations.md) for catalog checks on this site.

The installed plugin path is a symbolic link to this repository. Source changes apply to the demo. Do not use WordPress to update, replace, or delete this linked plugin. Use the [Docker test sites](docker-tests.md) for Free and Pro ZIP installation tests, Samba AD, and Apache server SSO.

The demo uses OpenLDAP and password login. It does not configure Herd server SSO or Active Directory. Do not run `tests/docker/integration.php` against this demo; that suite changes settings and deletes test users.

To check first-start failures, confirm that Docker and Herd are running and ports `13306`, `1389`, and `1636` are free. The setup refuses to use an existing `~/Sites/simple-ldap-demo` folder unless it has the `.sll-demo` marker.

To remove the demo containers but keep the data, run `docker compose -f demo/compose.yml down`. Adding `--volumes` deletes the demo database, LDAP accounts, and CA. Use that option only when you intend to delete all demo data. The Herd site files remain in `~/Sites/simple-ldap-demo`.
