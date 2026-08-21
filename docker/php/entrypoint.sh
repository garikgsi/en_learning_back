#!/bin/sh
set -eu

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/app/dictionary/audio \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
fi

chmod -R ug+rwX storage bootstrap/cache

exec "$@"
