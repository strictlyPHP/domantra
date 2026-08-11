#!/bin/bash
set -ex
DIRNAME=$(/usr/bin/dirname $0)
DIR=$(/bin/bash -c "cd $DIRNAME/..; /bin/pwd")

WORKDIR=$(mktemp -d)
trap 'rm -rf "$WORKDIR"' EXIT

cd $DIR

rm -fr ~/var/cache/test/*
rm -fr ~/var/cache/prod/*
XDEBUG_MODE=coverage ./vendor/bin/phpunit tests/ --coverage-clover="$WORKDIR/coverage.xml"
./vendor/bin/php-coveralls -x "$WORKDIR/coverage.xml" -o "$WORKDIR/coveralls.json"
