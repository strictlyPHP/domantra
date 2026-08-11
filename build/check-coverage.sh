#!/bin/bash
set -ex
DIRNAME=$(/usr/bin/dirname $0)
DIR=$(/bin/bash -c "cd $DIRNAME/..; /bin/pwd")

PHPCOV_VERSION=10.0.0
# SHA-256 of phpcov-10.0.0.phar, GPG-verified against https://phar.phpunit.de/phpcov-10.0.0.phar.asc
# (key fingerprint D840 6D0D 8294 7747 2937 7831 4AA3 9408 6372 C20A, see https://phpunit.de/verify.html)
PHPCOV_SHA256=6c470cb155cb4765c83d76325f05b62f4377bbac96d22356d9f9d900743590da

WORKDIR=$(mktemp -d)
trap 'rm -rf "$WORKDIR"' EXIT

PHPCOV_PHAR="$WORKDIR/phpcov-$PHPCOV_VERSION.phar"
curl -fsSL "https://phar.phpunit.de/phpcov-$PHPCOV_VERSION.phar" --output "$PHPCOV_PHAR"
echo "$PHPCOV_SHA256  $PHPCOV_PHAR" | sha256sum --check --quiet -

cd $DIR

rm -fr ~/var/cache/test/*
rm -fr ~/var/cache/prod/*
XDEBUG_MODE=coverage ./vendor/bin/phpunit tests/ --display-all-issues --coverage-php="$WORKDIR/coverage.cov" --coverage-html="$WORKDIR/coverage.html"
XDEBUG_MODE=coverage php -d auto_prepend_file=vendor/autoload.php "$PHPCOV_PHAR" patch-coverage --path-prefix /usr/src/myapp/ "$WORKDIR/coverage.cov" ./diff.txt | php ./build/check-coverage.php
