#!/bin/sh
#

cd tests

phpunit \
    --configuration phpunit.xml \
    --verbose \
    --stop-on-failure \
    --colors \
    --bootstrap phpunit-boot.php \
    -d memory_limit=-1 \
    -d display_startup_errors=0 \
    $1

