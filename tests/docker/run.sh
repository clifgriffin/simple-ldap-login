#!/bin/sh
set -eu
cd "$(dirname "$0")/../.."

action=${1:-test}
directory=${SLL_DIRECTORY:-ol}
multisite=${SLL_MULTISITE:-0}
paid=${SLL_TEST_PAID:-0}
case "$paid" in 0|1) ;; *) echo 'Use SLL_TEST_PAID=0 or 1.' >&2; exit 1 ;; esac
editions=free
if [ "$paid" = 1 ]; then
    if [ "$action" != down ] && [ "$action" != stop ] && [ ! -f pro/bootstrap.php ]; then
        echo 'Install the paid repo at pro/ before running paid Docker checks.' >&2
        exit 1
    fi
    editions="free pro enterprise"
fi
case "$directory" in ol|ad) ;; *) echo 'Use SLL_DIRECTORY=ol or ad.' >&2; exit 1 ;; esac
case "$multisite" in 0|1) ;; *) echo 'Use SLL_MULTISITE=0 or 1.' >&2; exit 1 ;; esac
case "$action" in test|up|stop|down) ;; *) echo 'Use test, up, stop, or down.' >&2; exit 1 ;; esac
activation=site
if [ "$multisite" = 1 ]; then activation=network; fi

compose() {
    if [ "$paid" = 1 ]; then set -- --profile paid "$@"; fi
    if [ "$directory" = ad ]; then
        docker compose -p "$project" -f tests/docker/compose.yml -f tests/docker/compose.samba.yml --profile ad "$@"
    else
        docker compose -p "$project" -f tests/docker/compose.yml "$@"
    fi
}

wp_exec() {
    service=$1
    shift
    compose exec -T -e SLL_ACTIVATION_SCOPE="$activation" "$service" sh -c 'exec wp --url="$SLL_SITE_URL" "$@"' sh "$@"
}

default_project=sll-lab-$directory
if [ "$multisite" = 1 ]; then default_project=$default_project-network; fi
project=${SLL_TEST_PROJECT:-$default_project}
if [ "$action" = stop ]; then
    compose stop
    exit
fi
if [ "$action" = down ]; then
    compose down --volumes --remove-orphans
    exit
fi
if [ "$action" = test ]; then
    project=${SLL_TEST_PROJECT:-sll-test-$directory-$multisite-$$}
    # Test runs use their own volumes and random localhost ports.
    export SLL_FREE_PORT=0 SLL_PRO_PORT=0 SLL_ENTERPRISE_PORT=0 SLL_TEST_URL=http://localhost
    cleanup() {
        result=$?
        trap - EXIT
        if [ "$result" -ne 0 ]; then compose logs --tail=100; fi
        compose down --volumes --remove-orphans
        exit "$result"
    }
    trap cleanup EXIT
    trap 'exit 130' INT
    trap 'exit 143' TERM
fi

compose up -d --build --wait --wait-timeout 180
for edition in $editions; do
    compose exec -T "$edition" sh /tests/site.sh
done
configure() {
    compose exec -T -e SLL_ACTIVATION_SCOPE="$activation" -e SLL_CONFIGURE_ONLY=1 "$1" sh -c 'exec wp --url="$SLL_SITE_URL" eval-file /tests/integration.php'
    wp_exec "$1" eval-file /tests/licenses.php setup
}
check_edition() {
    edition=$1
    configure "$edition"
    wp_exec "$edition" eval-file /tests/migration.php
    if [ "$paid" = 1 ]; then
        wp_exec "$edition" eval-file /tests/upgrades.php prepare
        wp_exec "$edition" eval-file /tests/upgrades.php seed-v1
        wp_exec "$edition" eval-file /tests/upgrades.php verify-v1
        full=pro
        if [ "$edition" = enterprise ]; then full=enterprise; fi
        for operation in reject-install reject-update update replace; do
            wp_exec "$edition" eval-file /tests/upgrades.php guard "$full" "$full" "$operation"
        done
        wp_exec "$edition" eval-file /tests/upgrades.php deactivate
        wp_exec "$edition" eval-file /tests/upgrades.php choose-free
        wp_exec "$edition" eval-file /tests/upgrades.php restore
        wp_exec "$edition" eval-file /tests/upgrades.php verify
    fi
    wp_exec "$edition" eval-file /tests/integration.php
    if [ "$edition" = enterprise ]; then wp_exec "$edition" eval-file /pro-tests/pro-features.php; fi
    wp_exec "$edition" eval-file /tests/licenses.php
    compose exec -T -e SLL_ACTIVATION_SCOPE="$activation" -e SLL_BAD_CA=1 "$edition" sh -c 'exec wp --url="$SLL_SITE_URL" eval-file /tests/integration.php'
    compose exec -T "$edition" php /tests/http-check.php
    compose exec -T "$edition" sh /tests/dependencies.sh
    echo "Configuration checks passed: $edition, $directory, $activation activation, multisite=$multisite."
}
for edition in $editions; do
    check_edition "$edition"
done
if [ "$multisite" = 1 ]; then
    for edition in $editions; do
        activation=site
        wp_exec "$edition" plugin deactivate simple-ldap-login --network
        wp_exec "$edition" plugin activate simple-ldap-login
        check_edition "$edition"
        wp_exec "$edition" plugin deactivate simple-ldap-login
        wp_exec "$edition" plugin activate simple-ldap-login --network
        activation=network
        configure "$edition"
    done
fi
echo "$editions Docker tests passed ($directory, multisite=$multisite)."
if [ "$action" = up ]; then
    echo "Free: http://localhost:${SLL_FREE_PORT:-8088}/wp-admin/"
    if [ "$paid" = 1 ]; then
        echo "Pro: http://localhost:${SLL_PRO_PORT:-8089}/wp-admin/"
        echo "Enterprise: http://localhost:${SLL_ENTERPRISE_PORT:-8090}/wp-admin/"
    fi
    echo 'Test admin: testadmin / Test-Admin-42!'
    echo 'Run env:stop with the same SLL_DIRECTORY value to stop the containers and keep their data.'
    echo 'Run env:down with the same SLL_DIRECTORY value to remove this test data.'
fi
