# Docker test environments

The test stack has separate WordPress sites for Free and Pro, MariaDB, and a directory server. It installs the release ZIPs. It does not mount the plugin source into WordPress. Use Docker Desktop or Docker Engine with Compose v2, Node 24, and Composer. Run `npm ci` once before the first run.

## Run the tests

```sh
# Full release check: source, packages, and the configuration matrix.
npm run test:release

# Free and Pro with OpenLDAP.
npm run test:docker

# Free and Pro with a Samba AD domain.
SLL_DIRECTORY=ad npm run test:docker

# Minimum PHP and WordPress versions, with Samba AD.
SLL_PHP_VERSION=8.1 SLL_WP_VERSION=6.5 SLL_DIRECTORY=ad npm run test:docker

# Network activation and site-only activation in multisite.
SLL_MULTISITE=1 SLL_DIRECTORY=ad npm run test:docker
```

Each Docker test command builds both package ZIPs, starts a separate test project, waits for healthy services, and runs the checks. A failed check returns a nonzero exit code and prints service logs. The command removes its test containers and volumes when it ends. It uses random ports bound to localhost. Images stay cached for later runs.

The release matrix and GitHub Actions use PHP 8.1 with WordPress 6.5, and PHP 8.5 with the current WordPress image. Both directory servers and both packages run in single-site, network, and site-only multisite configurations. The default individual environment uses PHP 8.4. See the [coverage map](test-coverage.md) for the selected settings and limits.

## Keep the sites open

```sh
# Build, test, and keep the OpenLDAP sites running.
npm run env:up

# Or use Samba AD.
SLL_DIRECTORY=ad npm run env:up
```

Open [Free](http://localhost:8088/wp-admin/) or [Pro](http://localhost:8089/wp-admin/). The test administrator is `testadmin`, with password `Test-Admin-42!`. LDAP user `alice` has password `Test-Password-42!`. Only Alice belongs to the required `staff` group. Bob has the same password but is denied access by that group rule.

The setup saves working test settings. Pro also has a phone metadata mapping and server SSO enabled. To check server SSO, open `/sll-test-sso.php` and enter Alice's test credentials in the browser authentication prompt. Apache checks the password and sets `REMOTE_USER`. Pro returns Alice's WordPress login. Free returns an empty login. Samba domain data and its test CA remain in named volumes between starts.

The test path uses Apache Basic authentication. It checks the plugin's trusted server identity path. It does not test Kerberos negotiation or a customer's proxy configuration.

Run only one kept environment at a time with the default ports. Set `SLL_FREE_PORT` and `SLL_PRO_PORT` if those ports are in use. `SLL_TEST_PROJECT` can set a separate Compose project name. Keep these values the same for startup and shutdown.

To remove the kept environment and its test data:

```sh
npm run env:down
# Or, for the Samba environment:
SLL_DIRECTORY=ad npm run env:down
```

If the kept environment used `SLL_MULTISITE=1`, set that value for `env:down` too. Its project is separate from the single-site project.

Test runs reset the fixture WordPress users and plugin settings. Do not put useful data in these sites. All passwords are public test values. Database and directory ports are not published. Web ports bind only to localhost. No container needs privileged mode, extra capabilities, or the Docker socket. Test CA private keys stay in the directory container. Clients receive only the public test certificates. The test scripts and HTTP probes are excluded from product ZIPs.

## What the tests cover

- The expected Free or Pro package loads in real WordPress and MariaDB.
- Valid LDAP login, wrong passwords, group denial, account creation, profile values, and the configured WordPress role.
- LDAP filter and DN injection attempts, local password fallback denial, and WordPress user checks.
- StartTLS and LDAPS with a trusted CA; rejection with an untrusted CA.
- OpenLDAP sub-OU lookup and the three supported group membership attributes.
- Samba account names, UPNs, domain names, direct groups, nested groups, primary groups, and disabled accounts.
- Pro custom metadata and SSO lookup; absence of those features in Free.
- HTTP sign-on through Apache, and rejection of a forged user header.

## Active Directory limits

Samba provides an AD-compatible domain controller and LDAP service. It uses its own directory database. It is a useful automated test target for the AD queries in this plugin. These containers run on ARM64 and AMD64 without a Windows host. The test image uses Samba's `xattr_tdb` test configuration so it needs no privileged mode. Use normal filesystem ACLs for a production domain controller. [Samba test configuration](https://lists.samba.org/archive/samba-technical/2022-June/137433.html)

Microsoft AD DS cannot run as a Windows container role. Use a Windows Server virtual machine for a final Microsoft AD test. Keep that check for domain policy, trust relationships, and real Kerberos server SSO. [Microsoft container limits](https://learn.microsoft.com/en-us/virtualization/windowscontainers/quick-start/lift-shift-to-containers)

PaidCommunities licensing and external updates are not connected yet, so these tests do not claim to cover them.
