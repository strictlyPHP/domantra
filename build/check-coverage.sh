#!/bin/bash
set -ex
DIRNAME=$(/usr/bin/dirname $0)
DIR=$(/bin/bash -c "cd $DIRNAME/..; /bin/pwd")

cd /tmp/
if [ ! -f /tmp/phpcov-10.0.0.phar ]; then
    curl -fsSL https://phar.phpunit.de/phpcov-10.0.0.phar --output /tmp/phpcov-10.0.0.phar.tmp
    mv /tmp/phpcov-10.0.0.phar.tmp /tmp/phpcov-10.0.0.phar
fi

cd $DIR

rm -fr ~/var/cache/test/*
rm -fr ~/var/cache/prod/*
XDEBUG_MODE=coverage ./vendor/bin/phpunit tests/ --display-all-issues --coverage-php=/tmp/coverage.cov --coverage-html=/tmp/coverage.html
XDEBUG_MODE=coverage php -d auto_prepend_file=vendor/autoload.php /tmp/phpcov-10.0.0.phar patch-coverage --path-prefix /usr/src/myapp/ /tmp/coverage.cov ./diff.txt | php ./build/check-coverage.php