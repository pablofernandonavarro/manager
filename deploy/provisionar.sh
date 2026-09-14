#!/usr/bin/env bash
#
# Deja una VM Ubuntu 24.04 lista para el Manager. Se corre UNA vez, como root:
#
#   sudo bash provisionar.sh <dominio> <email-para-lets-encrypt>
#
# Es idempotente: correrlo de nuevo no rompe nada ni pisa la base ni el .env.
#
# Stack: nginx + PHP 8.4-FPM + MySQL 8 + cola (systemd) + scheduler (cron) + HTTPS
# (certbot). Pensado para una e2-micro (1 GB de RAM): agrega swap y achica MySQL.
#
# Deja creado el usuario `deploy`, que es con el que entra GitHub Actions. Su clave
# pública se pasa en DEPLOY_PUBLIC_KEY (variable de entorno). ESCRITORIO_PUBLIC_KEY es la
# del repo del POS, que solo puede publicar la app de escritorio (recibir-escritorio.sh).
set -euo pipefail

DOMINIO="${1:?Uso: provisionar.sh <dominio> <email>}"
EMAIL="${2:?Uso: provisionar.sh <dominio> <email>}"
APP=/var/www/manager
PHP=8.4

export DEBIAN_FRONTEND=noninteractive
log() { printf '\n== %s\n' "$*"; }

log "Swap de 2 GB (con 1 GB de RAM, composer y MySQL no entran juntos)"
if ! swapon --show | grep -q /swapfile; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi
sysctl -q vm.swappiness=10
echo 'vm.swappiness=10' > /etc/sysctl.d/99-swappiness.conf

log "Zona horaria del sistema en UTC (Laravel guarda UTC y muestra hora argentina)"
timedatectl set-timezone UTC

log "Paquetes"
apt-get update -q
apt-get install -y -q software-properties-common ca-certificates curl unzip git acl
if ! grep -rq ondrej/php /etc/apt/sources.list.d/ 2>/dev/null; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update -q
fi
apt-get install -y -q nginx mysql-server certbot python3-certbot-nginx unattended-upgrades \
    php$PHP-fpm php$PHP-cli php$PHP-mysql php$PHP-mbstring php$PHP-xml php$PHP-curl \
    php$PHP-zip php$PHP-gd php$PHP-intl php$PHP-bcmath php$PHP-soap php$PHP-opcache

if ! command -v composer >/dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

log "Actualizaciones de seguridad automáticas"
dpkg-reconfigure -f noninteractive unattended-upgrades

log "MySQL chico: sin performance_schema y buffer de 128 MB"
cat > /etc/mysql/mysql.conf.d/zz-manager.cnf <<'CNF'
[mysqld]
bind-address = 127.0.0.1
performance_schema = OFF
innodb_buffer_pool_size = 128M
innodb_log_buffer_size = 8M
max_connections = 40
table_open_cache = 400
tmp_table_size = 16M
max_heap_table_size = 16M
CNF
systemctl restart mysql

log "PHP: límites para Excel e instaladores, opcache"
cat > /etc/php/$PHP/fpm/conf.d/99-manager.ini <<'INI'
upload_max_filesize = 50M
post_max_size = 55M
memory_limit = 256M
max_execution_time = 120
opcache.enable = 1
opcache.memory_consumption = 64
opcache.validate_timestamps = 0
expose_php = Off
INI
# Pocos procesos: cada uno ocupa ~40 MB.
sed -i 's/^pm = .*/pm = ondemand/; s/^pm.max_children = .*/pm.max_children = 6/' /etc/php/$PHP/fpm/pool.d/www.conf
systemctl restart php$PHP-fpm

log "Usuario deploy (lo usa GitHub Actions)"
if ! id deploy >/dev/null 2>&1; then
    adduser --disabled-password --gecos '' deploy
fi
usermod -aG www-data deploy
install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
if [ -n "${DEPLOY_PUBLIC_KEY:-}" ]; then
    grep -qF "$DEPLOY_PUBLIC_KEY" /home/deploy/.ssh/authorized_keys 2>/dev/null \
        || echo "$DEPLOY_PUBLIC_KEY" >> /home/deploy/.ssh/authorized_keys
    chown deploy:deploy /home/deploy/.ssh/authorized_keys
    chmod 600 /home/deploy/.ssh/authorized_keys
fi
# Clave del repo del POS: solo puede publicar la app de escritorio (comando forzado).
install -m 755 "$(dirname "$0")/recibir-escritorio.sh" /usr/local/bin/recibir-escritorio 2>/dev/null \
    || echo "AVISO: copiá deploy/recibir-escritorio.sh junto a este script para publicar la app de escritorio"
if [ -n "${ESCRITORIO_PUBLIC_KEY:-}" ]; then
    LINEA="command=\"/usr/local/bin/recibir-escritorio\",restrict $ESCRITORIO_PUBLIC_KEY"
    grep -qF "$ESCRITORIO_PUBLIC_KEY" /home/deploy/.ssh/authorized_keys 2>/dev/null \
        || echo "$LINEA" >> /home/deploy/.ssh/authorized_keys
fi
# Lo único que deploy puede hacer como root: recargar PHP y reiniciar la cola.
cat > /etc/sudoers.d/deploy-manager <<SUDO
deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php$PHP-fpm, /usr/bin/systemctl restart manager-queue
SUDO
chmod 440 /etc/sudoers.d/deploy-manager
visudo -cf /etc/sudoers.d/deploy-manager

log "Carpetas: releases/ + shared/ (storage y .env sobreviven a cada deploy)"
install -d -o deploy -g www-data -m 2775 $APP $APP/releases $APP/shared
install -d -o deploy -g www-data -m 2775 $APP/shared/storage
for d in app/public app/private framework/cache/data framework/sessions framework/views logs; do
    install -d -o deploy -g www-data -m 2775 "$APP/shared/storage/$d"
done
setfacl -R -m g:www-data:rwx -m d:g:www-data:rwx $APP/shared/storage

log "Base de datos y .env (solo la primera vez: nunca se pisan)"
ENV=$APP/shared/.env
if [ ! -f "$ENV" ]; then
    DB_PASS=$(openssl rand -base64 24 | tr -d '/+=')
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'manager'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER 'manager'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON manager.* TO 'manager'@'localhost';
FLUSH PRIVILEGES;
SQL
    cat > "$ENV" <<DOTENV
APP_NAME=Manager
APP_ENV=production
APP_KEY=base64:$(openssl rand -base64 32)
APP_DEBUG=false
APP_URL=https://$DOMINIO
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_DISPLAY_TIMEZONE=America/Argentina/Buenos_Aires

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=manager
DB_USERNAME=manager
DB_PASSWORD=$DB_PASS

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database
MAIL_MAILER=log
DOTENV
fi
chown deploy:www-data "$ENV"
chmod 640 "$ENV"

log "nginx"
cat > /etc/nginx/sites-available/manager <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name $DOMINIO;
    root $APP/current/public;
    index index.php;
    client_max_body_size 55M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/php$PHP-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
}
NGINX
ln -sf /etc/nginx/sites-available/manager /etc/nginx/sites-enabled/manager
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

log "Cola de trabajos (facturación AFIP) como servicio"
cat > /etc/systemd/system/manager-queue.service <<UNIT
[Unit]
Description=Manager - cola de trabajos
After=network.target mysql.service

[Service]
User=deploy
Group=www-data
WorkingDirectory=$APP/current
ExecStart=/usr/bin/php $APP/current/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --memory=128
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable manager-queue >/dev/null
# Arranca en el primer deploy, cuando exista current/.

log "Scheduler (reintento de facturas cada 5 minutos)"
echo "* * * * * deploy cd $APP/current && /usr/bin/php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/manager-scheduler
chmod 644 /etc/cron.d/manager-scheduler

log "HTTPS con Let's Encrypt"
if [ ! -d "/etc/letsencrypt/live/$DOMINIO" ]; then
    certbot --nginx -d "$DOMINIO" -m "$EMAIL" --agree-tos --non-interactive --redirect
fi

log "Listo. Falta el primer deploy (GitHub Actions) y después: php artisan db:seed --class=RolesAndPermissionsSeeder"
