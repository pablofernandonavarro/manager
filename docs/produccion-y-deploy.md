# Producción (GCP), deploy y pendientes

> Extraído de CLAUDE.md el 2026-09-16 para aliviar el context raíz. Ver también CLAUDE.md.

## Producción (Google Cloud) y deploy

- **Servidor**: VM `pos-manager-vps` (GCP, proyecto `pos-manager-508617`, e2-micro 1 GB + 2 GB de swap, Ubuntu 24.04), IP 35.226.112.51, **https://35-226-112-51.sslip.io** (sslip.io resuelve el nombre a la IP; Let's Encrypt renueva solo). Acceso de administración: `ssh pos-manager` (usuario `pablo`, está en los dotfiles).
- **`deploy/provisionar.sh <dominio> <email>`** (una vez, como root, idempotente): nginx + PHP 8.4-FPM (ppa ondrej) + MySQL 8 achicado + certbot + unattended-upgrades; cola como servicio systemd `manager-queue`, scheduler por `/etc/cron.d/manager-scheduler`; usuario `deploy` (clave de GitHub Actions) que por sudo solo puede recargar php-fpm y reiniciar la cola. **El `.env` de producción (APP_KEY, contraseña de MySQL) se genera en el servidor, en `/var/www/manager/shared/.env`, y nunca pasa por GitHub.**
- **Estructura**: `/var/www/manager/releases/<fecha>-<sha>`, `shared/{.env,storage}` enlazados en cada versión, `current` → la activa. Se conservan 5 versiones: volver atrás = apuntar `current` a la anterior y recargar php-fpm (ojo con migraciones ya corridas).
- **`.github/workflows/deploy.yml`**: en cada push/PR corre los tests contra MySQL 8.4; en `main`, si pasan, compila (`composer --no-dev`, `npm run build`) en el runner, sube un tar por SSH y corre `deploy/activar.sh` (migraciones, `optimize`, `storage:link`, cambio de `current`, recarga de php-fpm y cola) y verifica `/login`. Secretos: `DEPLOY_HOST`, `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`; variable `DEPLOY_DOMINIO`.
- **Deploy del Manager = push a `main`.** Seguirlo con `gh run list --branch main --limit 1` y `gh run watch <id> --exit-status` (~3–4 min: job `tests` y después `deploy`). Las migraciones corren solas en el paso "Subir y activar": en el log aparece cada `…_nombre ..... DONE`. El deploy verifica `/login` al final.
- **El POS se publica aparte**, con un tag `escritorio-vX.Y.Z` en su repo (ver `pos-integracion.md`, *Publicar una versión del POS*). Un push al Manager no toca las cajas.
- `opcache.validate_timestamps=0`: un cambio de código en el servidor sin recargar php-fpm no se ve. Nunca editar en `releases/`: todo entra por git.

## Pendiente para producción

Lo hecho: permisos, `throttle:10,1` en `pos/auth` (antes se podía probar el secret por fuerza bruta), `.env.example` del POS con `APP_DEBUG=false`, y la contraseña del admin fuera del repositorio (`AdminUserSeeder` toma `ADMIN_PASSWORD` o genera una al azar y la muestra una sola vez).

Lo que falta y es bloqueante:

- **Backup de la base de producción** (el servidor ya existe, con HTTPS y `APP_DEBUG=false`; falta el respaldo automático fuera de la VM).
- ~~Cajas contra producción~~: hecho. Caja1 (Villa Bosh) y la de Martinez ya sincronizan contra `https://35-226-112-51.sslip.io` (Caja1 corre la app de escritorio desde `C:\POS-Escritorio`, con sus datos en `%APPDATA%\pos-system`).
- `C:\MisLaravel\pos` es el código fuente; la app de escritorio se compila por GitHub Actions desde el repo limpio, así que ya no se cuela el estado de esa carpeta.

## Pendientes acordados (sesión del 2026-09-19/20)

Mejoras que se propusieron y **no** se hicieron, en orden de prioridad:

1. **Falso "sync trabado" al reconectar.** Tras un silencio de la caja, su primer reporte trae el stock viejo y `SaludCaja` la marca crítica ~1 min. Generalizar `reinicioTrasActualizar()` (hoy solo actúa si cambió la versión) con una marca de reconexión: `RegistrarConexionPos` ya conoce el `ultima_conexion_at` anterior.
2. **Historial de conexión por caja** en el Manager (silencios con inicio/fin, reconexiones, cambios de versión, tokens rechazados), visible en Auditoría de sincronización. Hoy la única fuente es el nginx del servidor, que rota a diario.
3. **Cajas a 1.9.12**: reemplazar la carpeta de Caja1 (Villa Bosh) y de Martinez; hasta entonces figuran desactualizadas y sin los logs nuevos.
4. **Caché del Dashboard** (`masVendidos`, `ayer`, `mes_anterior`) solo si con el volumen real se siente pesado. Ver `reportes-y-dashboard.md`.
5. **Cruce contra producción** de las 9 primeras ventas de Caja1 (PDV01-000001 a 000009): que estén todas y que cada una tenga su movimiento de stock con la cantidad y el signo correctos, sin movimientos `venta` duplicados. En la caja local ya cuadran las 9 de 9.
6. **Sin explicación todavía**: qué invalidó el token de Caja1 el 18/09 a las 22:37 (¿Revocar, un canje de código, otra máquina autenticándose?) y qué pasó en la caja el 19/09 de 15:52 a 16:03. Los logs de 1.9.12 en adelante deberían dejar rastro del segundo.
