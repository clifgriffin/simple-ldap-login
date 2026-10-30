# Plugin Check

Plugin Check 2.1.0 is installed and active on the Herd demo. On 30 October 2026, all default checks ran against the extracted release packages with WordPress 7.1.3 and PHP 8.4.23. This included all five runtime checks.

| Package | Errors | Warnings |
| --- | ---: | ---: |
| Free | 0 | 18 |
| Pro | 1 | 21 |

Free is the WordPress.org package. Pro has one `plugin_updater_detected` error because its preview header has `Update URI: false`. Pro uses external distribution. Do not submit it to WordPress.org. The header prevents WordPress.org from replacing it; an external update service is still required before release.

The remaining warnings are:

- Short prefixes on existing `sll_*` hooks, `SLL_FEATURES_LOADED`, and the old `str_true()` helper. These names remain for compatibility.
- WordPress core hooks `wp_authenticate_user` and `wp_login`. Their names must match WordPress.
- `load_plugin_textdomain()`. WordPress.org language packs can load without this call. The plugin keeps it to load its bundled catalogs, including in the external packages.

No warnings were hidden. The checks also found an invalid `Tested up to` format and private uninstall variable prefixes. These are fixed. Plugin Check does not grant formal WordPress.org approval.

## Run the checks again

On the demo site:

```sh
wp plugin install plugin-check --activate --path="$HOME/Sites/simple-ldap-demo"
wp plugin check simple-ldap-login \
  --path="$HOME/Sites/simple-ldap-demo" \
  --require="$HOME/Sites/simple-ldap-demo/wp-content/plugins/plugin-check/cli.php"
```

Keep `plugin check` before the options. Plugin Check 2.1.0 needs this order to set up its runtime environment. Without early loading through `--require`, WP-CLI runs only static checks.

The demo links to the full source repository. Its checks can report development files that are excluded from releases. Check the release contents separately:

```sh
npm run build:free
check_dir=$(mktemp -d)
unzip -q dist/simple-ldap-login.zip -d "$check_dir"
wp plugin check "$check_dir/simple-ldap-login" \
  --path="$HOME/Sites/simple-ldap-demo" \
  --require="$HOME/Sites/simple-ldap-demo/wp-content/plugins/plugin-check/cli.php"
```

Check Pro for code issues, with the external update header finding noted above. Read the findings; this Plugin Check version can return exit code zero even when it reports errors. Do not install a ZIP over the demo's source link.
