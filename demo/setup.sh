#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
repo=$(pwd)
site="$HOME/Sites/simple-ldap-demo"
url=http://simple-ldap-demo.test
compose() { docker compose -f "$repo/demo/compose.yml" "$@"; }
wp() { command wp --path="$site" --url="$url" "$@"; }

case "${1:-up}" in
    stop) compose stop; exit ;;
    check) wp eval-file "$repo/demo/check.php"; exit ;;
    up) ;;
    *) echo 'Use up, check, or stop.' >&2; exit 1 ;;
esac

for tool in docker herd wp php; do command -v "$tool" >/dev/null; done
if ! php -r 'exit(extension_loaded("ldap") && extension_loaded("mysqli") ? 0 : 1);'; then
    echo 'Enable the LDAP and mysqli extensions in Herd PHP first.' >&2
    exit 1
fi
if [ ! -f "$repo/vendor/autoload.php" ]; then
    echo 'Run composer install in the plugin repository first.' >&2
    exit 1
fi
if [ -d "$site" ] && [ ! -f "$site/.sll-demo" ]; then
    echo "The site folder exists without the demo marker: $site" >&2
    exit 1
fi
mkdir -p "$site"
touch "$site/.sll-demo"
compose up -d --build --wait --wait-timeout 180
# Renew the short-lived fixture CA before it expires. Keep all LDAP accounts.
if ! compose exec -T ldap openssl x509 -checkend 86400 -noout -in /certs/ca.crt; then
    compose exec -T ldap rm /certs/ca.crt
    compose restart ldap
    compose up -d --wait --wait-timeout 180
fi
compose cp ldap:/certs/ca.crt "$site/.sll-demo-ca.crt"
if [ ! -f "$site/wp-load.php" ]; then wp core download; fi
if [ ! -f "$site/wp-config.php" ]; then
    wp config create --dbname=wordpress --dbuser=wordpress --dbpass='Test-Database-42!' --dbhost=127.0.0.1:13306 --extra-php <<'PHP'
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_AUTO_UPDATE_CORE', false );
define( 'DISABLE_WP_CRON', true );
PHP
fi
mkdir -p "$site/wp-content/mu-plugins" "$site/wp-content/plugins"
cp "$repo/demo/local.php" "$site/wp-content/mu-plugins/sll-demo.php"
if ! wp core is-installed; then
    wp core install --url="$url" --title='Simple LDAP Login Demo' --admin_user=testadmin --admin_password='Test-Admin-42!' --admin_email=admin@example.test --skip-email
    wp option update blog_public 0
fi
plugin="$site/wp-content/plugins/simple-ldap-login"
if [ ! -e "$plugin" ] && [ ! -L "$plugin" ]; then ln -s "$repo" "$plugin"; fi
if [ "$(readlink "$plugin")" != "$repo" ]; then
    echo "The plugin path must link to $repo. Check $plugin." >&2
    exit 1
fi
wp plugin activate simple-ldap-login
if ! wp option get sll_demo_configured >/dev/null 2>&1; then
    wp eval-file "$repo/demo/configure.php"
fi
cd "$site"
herd link simple-ldap-demo
wp eval-file "$repo/demo/check.php"
echo "Demo: $url/wp-admin/"
echo 'Administrator: testadmin / Test-Admin-42!'
echo 'LDAP account: alice / Test-Password-42!'
