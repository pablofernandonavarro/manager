# Producción (GCP), deploy y pendientes

> Extraído de CLAUDE.md el 2026-09-16 para aliviar el context raíz. Ver también CLAUDE.md.

## Producción (Google Cloud) y deploy

- **Servidor**: VM `pos-manager-vps` (GCP, proyecto `pos-manager-508617`, e2-micro 1 GB + 2 GB de swap, Ubuntu 24.04), IP 35.226.112.51, **https://35-226-112-51.sslip.io** (sslip.io resuelve el nombre a la IP; Let's Encrypt renueva solo). Acceso de administración: `ssh pos-manager` (usuario `pablo`, está en los dotfiles).
- **`deploy/provisionar.sh <dominio> <email>`** (una vez, como root, idempotente): nginx + PHP 8.4-FPM (ppa ondrej) + MySQL 8 achicado + certbot + unattended-upgrades; cola como servicio systemd `manager-queue`, scheduler por `/etc/cron.d/manager-scheduler`; usuario `deploy` (clave de GitHub Actions) que por sudo solo puede recargar php-fpm y reiniciar la cola. **El `.env` de producción (APP_KEY, contraseña de MySQL) se genera en el servidor, en `/var/www/manager/shared/.env`, y nunca pasa por GitHub.**
- **Estructura**: `/var/www/manager/releases/<fecha>-<sha>`, `shared/{.env,storage}` enlazados en cada versión, `current` → la activa. Se conservan 5 versiones: volver atrás = apuntar `current` a la anterior y recargar php-fpm (ojo con migraciones ya corridas).
- **`.github/workflows/deploy.yml`**: en cada push/PR corre los tests contra MySQL 8.4; en `main`, si pasan, compila (`composer --no-dev`, `npm run build`) en el runner, sube un tar por SSH y corre `deploy/activar.sh` (migraciones, `optimize`, `storage:link`, cambio de `current`, recarga de php-fpm y cola) y verifica `/login`. Secretos: `DEPLOY_HOST`, `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`; variable `DEPLOY_DOMINIO`.
- `opcache.validate_timestamps=0`: un cambio de código en el servidor sin recargar php-fpm no se ve. Nunca editar en `releases/`: todo entra por git.

## Pendiente para producción

Lo hecho: permisos, `throttle:10,1` en `pos/auth` (antes se podía probar el secret por fuerza bruta), `.env.example` del POS con `APP_DEBUG=false`, y la contraseña del admin fuera del repositorio (`AdminUserSeeder` toma `ADMIN_PASSWORD` o genera una al azar y la muestra una sola vez).

Lo que falta y es bloqueante:

- **Backup de la base de producción** (el servidor ya existe, con HTTPS y `APP_DEBUG=false`; falta el respaldo automático fuera de la VM).
- **Cajas contra producción**: hoy apuntan a `manager.test` (sin HTTPS, solo en esta PC); hay que darlas de alta de nuevo contra `https://35-226-112-51.sslip.io`.
- `C:\MisLaravel\pos` es a la vez el código fuente y la instalación de Caja 1. Conviene separarlos: empaquetar captura el estado en que esté esa carpeta.
