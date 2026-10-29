#!/bin/sh
set -eu
mkdir -p /tmp/sll-no-ldap
for ini in /usr/local/etc/php/conf.d/*.ini; do
    case "$ini" in *ldap.ini) ;; *) ln -sf "$ini" /tmp/sll-no-ldap/ ;; esac
done
PHP_INI_SCAN_DIR=/tmp/sll-no-ldap php /usr/local/bin/wp --url="$SLL_SITE_URL" eval-file /tests/dependencies.php ldap
php -d disable_functions=openssl_encrypt,openssl_decrypt /usr/local/bin/wp --url="$SLL_SITE_URL" eval-file /tests/dependencies.php openssl
