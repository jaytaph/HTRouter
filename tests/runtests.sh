#!/bin/sh
#

if [ -z "$1" ] ; then
    dir=.
else
    dir=$1
fi

phpunit \
    --testdox-html ../public/coverage/testdox.html \
    --coverage-html ../public/coverage \
    --configuration phpunit.xml \
    --verbose \
    --stop-on-failure \
    --colors \
    --bootstrap phpunit-boot.php \
    -d memory_limit=-1 \
    -d display_startup_errors=0 \
    $dir


#   --process-isolation \

