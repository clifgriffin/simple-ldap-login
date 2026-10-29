#!/bin/sh
set -eu

# These accounts and keys are for the isolated test service only.
if [ ! -f /var/lib/samba/.sll-seeded ]; then
    umask 077
    mkdir -p /var/lib/samba/private/tls /certs
    tls=/var/lib/samba/private/tls
    openssl req -x509 -newkey rsa:2048 -nodes -sha256 -days 7 \
        -subj '/CN=Simple LDAP Login Samba Test CA' \
        -addext 'basicConstraints=critical,CA:TRUE' \
        -addext 'keyUsage=critical,keyCertSign,cRLSign' \
        -keyout "$tls/ca.key" -out "$tls/ca.crt"
    openssl req -new -newkey rsa:2048 -nodes -sha256 \
        -subj '/CN=ad.example.test' \
        -keyout "$tls/server.key" -out "$tls/server.csr"
    cat > "$tls/server.ext" <<'EOF'
basicConstraints=critical,CA:FALSE
keyUsage=critical,digitalSignature,keyEncipherment
extendedKeyUsage=serverAuth
subjectAltName=DNS:ad,DNS:ad.example.test
EOF
    openssl x509 -req -sha256 -days 7 -in "$tls/server.csr" \
        -CA "$tls/ca.crt" -CAkey "$tls/ca.key" -CAcreateserial \
        -extfile "$tls/server.ext" -out "$tls/server.crt"
    openssl req -x509 -newkey rsa:2048 -nodes -sha256 -days 7 \
        -subj '/CN=Untrusted Samba Test CA' \
        -addext 'basicConstraints=critical,CA:TRUE' \
        -keyout "$tls/wrong-ca.key" -out "$tls/wrong-ca.crt"

    # ponytail: xattr_tdb stores test ACLs without extra container privileges.
    # Use native file system ACLs for a production domain controller.
    # https://lists.samba.org/archive/samba-technical/2022-June/137433.html
    samba-tool domain provision \
        --server-role=dc --realm=EXAMPLE.TEST --domain=EXAMPLE \
        --host-name=ad --dns-backend=SAMBA_INTERNAL \
        --adminpass='Admin-Password-42!' --use-rfc2307 \
        --option='vfs objects = dfs_samba4 acl_xattr xattr_tdb' \
        --option='xattr_tdb:file = /var/lib/samba/xattr.tdb' \
        --option='dns forwarder = 127.0.0.11' \
        --option='ldap server require strong auth = yes' \
        --option='tls enabled = yes' \
        --option="tls cafile = $tls/ca.crt" \
        --option="tls certfile = $tls/server.crt" \
        --option="tls keyfile = $tls/server.key"

    samba-tool user create reader 'Reader-Password-42!'
    samba-tool user create alice 'Test-Password-42!' \
        --use-username-as-cn --given-name=Alice --surname=Example \
        --mail-address=alice@example.test --telephone-number=+15550100
    samba-tool user create bob 'Test-Password-42!' \
        --use-username-as-cn --given-name=Bob --surname=Example \
        --mail-address=bob@example.test
    samba-tool user create disabled 'Test-Password-42!'
    samba-tool user disable disabled
    samba-tool group add staff
    samba-tool group add parentstaff
    samba-tool group add primaryparents
    samba-tool group add denied
    samba-tool group addmembers staff alice
    samba-tool group addmembers parentstaff staff
    samba-tool group addmembers primaryparents 'Domain Users'
    samba-tool group addmembers denied bob
    touch /var/lib/samba/.sll-seeded
fi

# Share public test CA certificates. Keep all private keys in this container.
cp /var/lib/samba/private/tls/ca.crt /certs/ca.crt
cp /var/lib/samba/private/tls/wrong-ca.crt /certs/wrong-ca.crt
chmod 755 /certs
chmod 644 /certs/ca.crt /certs/wrong-ca.crt
cp /var/lib/samba/private/krb5.conf /etc/krb5.conf
printf 'nameserver 127.0.0.1\nsearch example.test\n' > /etc/resolv.conf
exec samba --interactive --no-process-group
