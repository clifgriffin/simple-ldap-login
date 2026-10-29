ARG PHP_VERSION=8.4
FROM wordpress:php${PHP_VERSION}-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libldap2-dev \
    && docker-php-ext-configure ldap --with-libdir=lib/$(dpkg-architecture -qDEB_HOST_MULTIARCH) \
    && docker-php-ext-install ldap \
    && rm -rf /var/lib/apt/lists/*

COPY --from=wordpress:cli /usr/local/bin/wp /usr/local/bin/wp
COPY apache-sso.conf /etc/apache2/conf-enabled/sll-test.conf
RUN htpasswd -bc /etc/apache2/sll-test.htpasswd alice 'Test-Password-42!'

ENV WP_CLI_ALLOW_ROOT=1
