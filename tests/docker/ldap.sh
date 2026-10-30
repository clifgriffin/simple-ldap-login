#!/bin/sh
set -eu
mkdir -p /var/lib/ldap/tls /certs /var/run/slapd
if [ ! -f /certs/ca.crt ]; then
    openssl req -x509 -newkey rsa:2048 -nodes -days 30 -subj '/CN=Plugin test CA' -keyout /var/lib/ldap/tls/ca.key -out /certs/ca.crt
    openssl req -newkey rsa:2048 -nodes -subj '/CN=ldap' -keyout /var/lib/ldap/tls/server.key -out /var/lib/ldap/tls/server.csr
    printf 'subjectAltName=DNS:ldap,DNS:localhost,IP:127.0.0.1\nextendedKeyUsage=serverAuth\n' > /var/lib/ldap/tls/extensions
    openssl x509 -req -in /var/lib/ldap/tls/server.csr -CA /certs/ca.crt -CAkey /var/lib/ldap/tls/ca.key -CAcreateserial -days 30 -extfile /var/lib/ldap/tls/extensions -out /var/lib/ldap/tls/server.crt
    openssl req -x509 -newkey rsa:2048 -nodes -days 30 -subj '/CN=Untrusted test CA' -keyout /var/lib/ldap/tls/wrong-ca.key -out /certs/wrong-ca.crt
fi
if [ ! -f /var/lib/ldap/data.mdb ]; then
    slapadd -f /etc/ldap/slapd-test.conf -l /seed.ldif
fi
chown -R openldap:openldap /var/lib/ldap /var/run/slapd
chmod 600 /var/lib/ldap/tls/*.key
exec slapd -d 256 -u openldap -g openldap -f /etc/ldap/slapd-test.conf -h 'ldap:/// ldaps:///'
