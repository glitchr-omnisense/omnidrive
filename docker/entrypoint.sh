#!/bin/sh
# Composes the harness (this core + every provider), installs it when it
# changed, waits for the servers the OMNIDRIVE_* variables name, then runs the
# command: "bare" (the default) for the script that uses the packages with no
# framework, "test" for PHPUnit, "composer ...", "php ..." or "sh" as they are.
set -e
php /omnidrive/core/docker/harness/setup.php
cd /harness
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --no-progress
elif [ composer.json -nt composer.lock ]; then
    composer update --no-interaction --no-progress
fi
php /omnidrive/core/docker/harness/wait.php
case "${1:-bare}" in
    test) shift; exec vendor/bin/phpunit "$@" ;;
    bare) shift; exec php /omnidrive/core/docker/harness/bin/bare "$@" ;;
    composer|sh|php) exec "$@" ;;
    *) echo "bare, test, composer, php or sh" >&2; exit 2 ;;
esac
