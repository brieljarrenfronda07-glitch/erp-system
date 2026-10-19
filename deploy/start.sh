#!/bin/sh
set -eu
case "${PORT:-10000}" in
  ''|*[!0-9]*) echo 'PORT must be numeric' >&2; exit 1 ;;
esac
mkdir -p "${DATA_DIR:-/var/data}"
chown www-data:www-data "${DATA_DIR:-/var/data}"
chmod 700 "${DATA_DIR:-/var/data}"
sed -i "s/^Listen .*/Listen ${PORT:-10000}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT:-10000}>/" /etc/apache2/sites-available/000-default.conf
exec apache2-foreground
