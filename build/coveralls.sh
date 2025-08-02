#!/bin/bash
set -ex
DIRNAME=$(/usr/bin/dirname $0)
DIR=$(/bin/bash -c "cd $DIRNAME/..; /bin/pwd")

cd /tmp/
[ -f /tmp/phpcov-8.2.0.phar ] ||  curl https://phar.phpunit.de/phpcov-8.2.0.phar --output /tmp/phpcov-8.2.0.phar

cd $DIR

rm -fr ~/var/cache/test/*
rm -fr ~/var/cache/prod/*
XDEBUG_MODE=coverage ./vendor/bin/phpunit tests/ --coverage-clover=/tmp/coverage.xml
./vendor/bin/php-coveralls -x /tmp/coverage.xml -o /tmp/coveralls.json