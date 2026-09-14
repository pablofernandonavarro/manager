#!/usr/bin/env bash
#
# Activa una versión ya subida a releases/<nombre>. Lo corre GitHub Actions como `deploy`:
#
#   bash activar.sh <nombre-del-release>
#
# Sin tiempo fuera de servicio: la versión nueva se prepara al costado (enlaces a shared/,
# migraciones, cachés) y recién al final `current` pasa a apuntarle. Si algo falla antes
# de ese paso, `current` sigue en la versión anterior.
set -euo pipefail

APP=/var/www/manager
RELEASE="$APP/releases/${1:?Uso: activar.sh <release>}"
PHP=8.4
CONSERVAR=5

cd "$RELEASE"

echo "== Enlazando .env y storage compartidos"
rm -rf storage
ln -sfn "$APP/shared/storage" storage
ln -sfn "$APP/shared/.env" .env
chgrp -R www-data bootstrap/cache
chmod -R g+w bootstrap/cache

echo "== Migraciones"
php artisan migrate --force --no-interaction

echo "== Cachés de config, rutas, vistas y eventos"
php artisan optimize
php artisan storage:link --force >/dev/null 2>&1 || true

echo "== Cambiando current a esta versión"
ln -sfn "$RELEASE" "$APP/current.nuevo"
mv -Tf "$APP/current.nuevo" "$APP/current"

sudo /usr/bin/systemctl reload php$PHP-fpm
sudo /usr/bin/systemctl restart manager-queue

echo "== Borrando versiones viejas (quedan $CONSERVAR)"
ls -1dt "$APP"/releases/*/ | tail -n +$((CONSERVAR + 1)) | xargs -r rm -rf

echo "== Activa: $(basename "$RELEASE")"
