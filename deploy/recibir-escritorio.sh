#!/usr/bin/env bash
#
# Recibe una compilación de la app de escritorio del POS y la publica para descargar desde
# Puntos de venta. Es el ÚNICO comando que puede correr la clave del repo del POS: está
# forzado en authorized_keys (command="...",restrict), así que esa clave no abre una
# terminal ni puede desplegar código.
#
#   ssh deploy@servidor "<version> <sha256>" < pos-escritorio.zip          (Windows)
#   ssh deploy@servidor "<version> <sha256> mac" < pos-escritorio-mac.zip  (Mac)
#
# Antes de publicar verifica que el archivo llegó entero (sha256), que es un zip sin datos de
# ninguna caja y que la versión es mayor que la publicada para esa plataforma (las cajas
# corren las migraciones solo cuando la versión sube). El reemplazo es atómico: nunca queda
# un zip a medio subir.
set -euo pipefail

DIR=/var/www/manager/shared/storage/app/private/pos-escritorio
MAX_BYTES=$((1024 * 1024 * 1024))

falla() { echo "ERROR: $*" >&2; exit 1; }

read -r VERSION SHA PLATAFORMA EXTRA <<<"${SSH_ORIGINAL_COMMAND:-}"
[[ -z "${EXTRA:-}" ]] || falla 'Uso: "<version> <sha256> [mac]" con el zip por stdin.'
[[ "${VERSION:-}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || falla "Versión inválida: ${VERSION:-vacía}"
[[ "${SHA:-}" =~ ^[0-9a-f]{64}$ ]] || falla 'sha256 inválido.'

case "${PLATAFORMA:-}" in
    '')  BASE=pos-escritorio ;;
    mac) BASE=pos-escritorio-mac ;;
    *)   falla "Plataforma inválida: $PLATAFORMA (vacío para Windows, o mac)." ;;
esac

install -d -m 2775 "$DIR"
ACTUAL=$(sed -n 's/.*"version": *"\([^"]*\)".*/\1/p' "$DIR/$BASE.json" 2>/dev/null || true)
if [[ -n "$ACTUAL" && "$ACTUAL" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    MAYOR=$(printf '%s\n%s\n' "$ACTUAL" "$VERSION" | sort -V | tail -1)
    [[ "$VERSION" != "$ACTUAL" && "$MAYOR" == "$VERSION" ]] \
        || falla "La versión $VERSION no es mayor que la publicada ($ACTUAL)."
fi

TMP=$(mktemp -p "$DIR" .subida.XXXXXX)
trap 'rm -f "$TMP"' EXIT

head -c $((MAX_BYTES + 1)) > "$TMP"
TAMANO=$(stat -c %s "$TMP")
(( TAMANO <= MAX_BYTES )) || falla 'El archivo supera 1 GB.'
echo "$SHA  $TMP" | sha256sum -c --status || falla 'El sha256 no coincide: el archivo llegó incompleto o distinto.'

LISTA=$(unzip -Z1 "$TMP" 2>/dev/null) || falla 'No es un zip válido.'
if [[ -z "${PLATAFORMA:-}" ]]; then
    grep -q '^POS-Escritorio/pos-system\.exe$' <<<"$LISTA" || falla 'El zip no trae POS-Escritorio/pos-system.exe.'
else
    grep -Eq '^[^/]+\.app/Contents/Info\.plist$' <<<"$LISTA" || falla 'El zip no trae una app de Mac (<nombre>.app/Contents/Info.plist).'
fi
if grep -Eiq '\.(sqlite|sqlite-wal|sqlite-shm)$|(^|/)\.pos-info$|(^|/)\.env\.escritorio$' <<<"$LISTA"; then
    falla 'La compilación trae datos de una caja. No se publica.'
fi

chmod 664 "$TMP"
mv -f "$TMP" "$DIR/$BASE.zip"
trap - EXIT
[[ -n "${PLATAFORMA:-}" ]] || rm -f "$DIR/pos-escritorio.exe"

printf '{\n    "version": "%s",\n    "formato": "zip",\n    "tamano": %s,\n    "sha256": "%s",\n    "generado_at": "%s"\n}\n' \
    "$VERSION" "$TAMANO" "$SHA" "$(date -u +%Y-%m-%dT%H:%M:%S+00:00)" > "$DIR/$BASE.json.tmp"
chmod 664 "$DIR/$BASE.json.tmp"
mv -f "$DIR/$BASE.json.tmp" "$DIR/$BASE.json"

echo "Publicada la app de escritorio ${PLATAFORMA:-windows} $VERSION ($((TAMANO / 1024 / 1024)) MB)."
