#!/bin/sh
set -eu
cd /var/www/html
wp() { command wp --url="$SLL_SITE_URL" "$@"; }
if [ -n "${SLL_WP_VERSION:-}" ]; then
    wp core download --force --skip-content --version="$SLL_WP_VERSION"
fi
if ! wp core is-installed; then
    wp core install --url="$SLL_SITE_URL" --title="LDAP $SLL_EDITION test" --admin_user=testadmin --admin_password='Test-Admin-42!' --admin_email=admin@example.test --skip-email
fi
if [ "${SLL_MULTISITE:-0}" = 1 ] && ! wp eval 'exit(is_multisite() ? 0 : 1);'; then
    wp core multisite-convert --title="LDAP $SLL_EDITION network" --base=/
fi
wp option update home "$SLL_SITE_URL"
wp option update siteurl "$SLL_SITE_URL"
archive=/packages/simple-ldap-login.zip
if [ "$SLL_EDITION" = pro ]; then archive=/packages/simple-ldap-login-pro.zip; fi
wp --skip-plugins --skip-themes plugin install "$archive" --force
if [ "${SLL_MULTISITE:-0}" = 1 ]; then
    wp --skip-plugins --skip-themes plugin activate simple-ldap-login --network
else
    wp --skip-plugins --skip-themes plugin activate simple-ldap-login
fi
cp /tests/http-user.php /var/www/html/sll-test-user.php
cp /tests/http-user.php /var/www/html/sll-test-sso.php
mkdir -p /var/www/html/wp-content/mu-plugins
cp /tests/http-fixture.php /var/www/html/wp-content/mu-plugins/sll-http-fixture.php
wp core version
php -r 'echo "PHP ", PHP_VERSION, "\n";'
