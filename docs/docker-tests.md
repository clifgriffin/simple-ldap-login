# Docker test environments

The default test stack has Free, MariaDB, and a directory server. The paid command adds separate Pro and Enterprise sites and requires the paid repo at `pro/`. It installs the release ZIPs. It does not mount the plugin source into WordPress. Use Docker Desktop or Docker Engine with Compose v2, Node 24, and Composer. Run `npm ci` once before the first run.

## Run the tests

```sh
# Free release checks: source, packages, and the configuration matrix.
npm run test:release

# Free with OpenLDAP.
npm run test:docker

# All editions with OpenLDAP. Requires the paid checkout.
npm run test:docker:paid

# All-edition release matrix.
npm run test:release:paid

# Free with a Samba AD domain.
SLL_DIRECTORY=ad npm run test:docker

# Minimum PHP and WordPress versions, with Samba AD.
SLL_PHP_VERSION=8.1 SLL_WP_VERSION=6.5 SLL_DIRECTORY=ad npm run test:docker

# Network activation and site-only activation in multisite.
SLL_MULTISITE=1 SLL_DIRECTORY=ad npm run test:docker
```

The default Docker command builds the Free ZIP. The `:paid` command builds all three ZIPs. Each command starts a separate test project, waits for healthy services, and runs the checks. A failed check returns a nonzero exit code and prints service logs. The command removes its test containers and volumes when it ends. It uses random ports bound to localhost. Images stay cached for later runs.

Both release matrices and their GitHub Actions workflows use PHP 8.1 with WordPress 6.5, and PHP 8.5 with the current WordPress image. The public matrix tests Free. The paid matrix tests all three editions. Both use both directory servers and run in single-site, network, and site-only multisite configurations. The default individual environment uses PHP 8.4. See the [coverage map](test-coverage.md) for the selected settings and limits.

## Keep the sites open

```sh
# Build, test, and keep the OpenLDAP sites running.
npm run env:up

# Keep all three sites open. Requires the paid checkout.
npm run env:up:paid

# Or use Samba AD.
SLL_DIRECTORY=ad npm run env:up
```

Open [Free](http://localhost:8088/wp-admin/). Paid mode also starts [Pro](http://localhost:8089/wp-admin/), or [Enterprise](http://localhost:8090/wp-admin/). The test administrator is `testadmin`, with password `Test-Admin-42!`. LDAP user `alice` has password `Test-Password-42!`. Only Alice belongs to the required `staff` group. Bob has the same password but is denied access by that group rule.

The setup saves working test settings. Both paid sites have a phone metadata mapping and server SSO enabled. To check server SSO, open `/sll-test-sso.php` and enter Alice's test credentials in the browser authentication prompt. Apache checks the password and sets `REMOTE_USER`. Both paid sites return Alice's WordPress login. Free returns an empty login. Samba domain data and its test CA remain in named volumes between starts.

The test path uses Apache Basic authentication. It checks the plugin's trusted server identity path. It does not test Kerberos negotiation or a customer's proxy configuration.

Run only one kept environment at a time with the default ports. Set `SLL_FREE_PORT`, `SLL_PRO_PORT`, and `SLL_ENTERPRISE_PORT` if those ports are in use. `SLL_TEST_PROJECT` can set a separate Compose project name. Keep these values the same for startup and shutdown. Direct calls to `tests/docker/run.sh` need `SLL_TEST_PAID=1` to include paid editions.

Health checks run every two seconds during startup, then once per minute after each service becomes healthy. The demo uses the same database and LDAP check settings. These settings require Docker Engine 25 or later and Compose 2.20.2 or later.

Run `node tests/docker/lifecycle.cjs` to check the health check settings, stop command, and cleanup after a failed test. This check does not start containers.

Stop the containers when you finish manual tests. This keeps their data:

```sh
npm run env:stop
# Or, for the Samba environment:
SLL_DIRECTORY=ad npm run env:stop
# Stop the separate Herd demo services:
sh demo/setup.sh stop
```

Use the same `SLL_MULTISITE` and `SLL_TEST_PROJECT` values as startup. Stopped containers use no CPU or RAM. Docker Desktop can retain memory for its virtual machine and cache.

To remove the kept environment and its test data:

```sh
npm run env:down
# Or, for the Samba environment:
SLL_DIRECTORY=ad npm run env:down
```

If the kept environment used `SLL_MULTISITE=1`, set that value for `env:down` too. Its project is separate from the single-site project.

Test runs reset the fixture WordPress users and plugin settings. Do not put useful data in these sites. All passwords are public test values. Database and directory ports are not published. Web ports bind only to localhost. No container needs privileged mode, extra capabilities, or the Docker socket. Test CA private keys stay in the directory container. Clients receive only the public test certificates. The test scripts and HTTP probes are excluded from product ZIPs.

## What the tests cover

- The expected Free, Pro, or Enterprise package loads in real WordPress and MariaDB.
- Valid LDAP login, wrong passwords, group denial, account creation, profile values, and the configured WordPress role.
- LDAP filter and DN injection attempts, local password fallback denial, and WordPress user checks.
- StartTLS and LDAPS with a trusted CA; rejection with an untrusted CA.
- OpenLDAP sub-OU lookup and the three supported group membership attributes.
- Samba account names, UPNs, domain names, direct groups, nested groups, primary groups, and disabled accounts.
- Pro custom metadata and SSO lookup; absence of those features in Free.
- Pro role rules and profile updates; Enterprise paged imports, managed account suspension and restoration, and real session removal.
- Enterprise network membership rules, target site roles, membership removal, and credential scope isolation.
- Private audit storage, CSV output, and retention with real WordPress posts.
- HTTP sign-on through Apache, and rejection of a forged user header.

## Active Directory limits

Samba provides an AD-compatible domain controller and LDAP service. It uses its own directory database. It is a useful automated test target for the AD queries in this plugin. These containers run on ARM64 and AMD64 without a Windows host. The test image uses Samba's `xattr_tdb` test configuration so it needs no privileged mode. Use normal filesystem ACLs for a production domain controller. [Samba test configuration](https://lists.samba.org/archive/samba-technical/2022-June/137433.html)

Microsoft AD DS cannot run as a Windows container role. Use a Windows Server virtual machine for a final Microsoft AD test. Keep that check for domain policy, trust relationships, and real Kerberos server SSO. [Microsoft container limits](https://learn.microsoft.com/en-us/virtualization/windowscontainers/quick-start/lift-shift-to-containers)

License tests run the packaged PaidCommunities SDK through the WordPress HTTP API with controlled service responses. They cover activation, expiry, renewal, outages, password login, recovery, and retained settings. HTTP SSO probes cover expired, inactive, missing, and last-known active licenses. Live PaidCommunities accounts and external updates still need acceptance tests.
