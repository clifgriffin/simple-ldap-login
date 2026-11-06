#!/bin/sh
set -eu
cd "$(dirname "$0")/../.."

editions=free
configurations=12
if [ "${SLL_TEST_PAID:-0}" = 1 ]; then editions="free pro enterprise"; configurations=36; fi
for edition in $editions; do
    npm run "build:$edition"
done
for target in minimum current; do
    if [ "$target" = minimum ]; then
        export SLL_PHP_VERSION=8.1 SLL_WP_VERSION=6.5
    else
        export SLL_PHP_VERSION=8.5 SLL_WP_VERSION=
    fi
    for directory in ol ad; do
        for multisite in 0 1; do
            SLL_DIRECTORY=$directory SLL_MULTISITE=$multisite sh tests/docker/run.sh test
        done
    done
done
echo "Release matrix passed: $configurations core configurations."
