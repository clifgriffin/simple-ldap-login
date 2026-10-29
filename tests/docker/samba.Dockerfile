FROM debian:bookworm-slim

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        ca-certificates ldap-utils openssl samba-ad-dc samba-ad-provision samba-vfs-modules winbind \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/samba/smb.conf

COPY samba-entrypoint.sh /usr/local/bin/samba-entrypoint
RUN chmod +x /usr/local/bin/samba-entrypoint

ENV LDAPTLS_CACERT=/certs/ca.crt LDAPTLS_REQCERT=demand

ENTRYPOINT ["/usr/local/bin/samba-entrypoint"]
