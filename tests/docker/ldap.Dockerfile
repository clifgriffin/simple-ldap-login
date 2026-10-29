FROM debian:bookworm-slim
RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends slapd ldap-utils openssl ca-certificates \
    && rm -rf /var/lib/apt/lists/* /var/lib/ldap/*
COPY ldap.ldif /seed.ldif
COPY ldap.conf /etc/ldap/slapd-test.conf
COPY ldap.sh /usr/local/bin/sll-ldap
CMD ["sh", "/usr/local/bin/sll-ldap"]
