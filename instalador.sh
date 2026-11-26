#!/usr/bin/env bash
# Instalador para Raspberry Pi OS / Debian.
# Instala Apache, PHP y MariaDB, descarga el proyecto y elimina el ZIP temporal.
set -Eeuo pipefail

REPO="freedarwuin/POS-System-Pro"
BRANCH="main"
WEB_ROOT="/var/www/html"
APP_DIR="${WEB_ROOT}/POS-System-Pro"
ZIP_FILE="/tmp/pos-system-pro.zip"
EXTRACT_DIR="/tmp/pos-system-pro-extract"
CURL_CONFIG="/tmp/pos-system-pro-curl.conf"

cleanup() {
    rm -f "$ZIP_FILE" "$CURL_CONFIG"
    rm -rf "$EXTRACT_DIR"
}
trap cleanup EXIT

fail() {
    echo "Error: $*" >&2
    exit 1
}

if [[ "${EUID}" -ne 0 ]]; then
    fail "Ejecuta este instalador como administrador: sudo bash instalador.sh"
fi

if ! command -v apt-get >/dev/null 2>&1; then
    fail "Este instalador requiere Raspberry Pi OS o Debian con apt-get."
fi

if [[ -e "$APP_DIR" ]]; then
    fail "Ya existe $APP_DIR. Haz una copia de seguridad y muévelo o elimínalo antes de continuar; no se sobrescribió nada."
fi

command -v curl >/dev/null 2>&1 || {
    apt-get update
    DEBIAN_FRONTEND=noninteractive apt-get install -y curl ca-certificates
}

if [[ -z "${GH_TOKEN:-}" ]]; then
    echo "El repositorio es privado. Se requiere un token de GitHub con permiso de lectura (Contents: read)."
    read -r -s -p "Token de GitHub: " GH_TOKEN
    echo
fi
[[ -n "$GH_TOKEN" ]] || fail "No se proporcionó un token de GitHub."

# Evita poner el token en la URL o en los argumentos visibles de curl.
umask 077
printf 'header = "Authorization: Bearer %s"\n' "$GH_TOKEN" > "$CURL_CONFIG"
unset GH_TOKEN

export DEBIAN_FRONTEND=noninteractive
echo "[1/4] Instalando Apache, PHP y MariaDB..."
apt-get update
apt-get install -y \
    apache2 \
    mariadb-server \
    php \
    libapache2-mod-php \
    php-mysql \
    php-zip \
    php-mbstring \
    php-curl \
    php-gd \
    unzip \
    ca-certificates \
    curl

systemctl enable --now mariadb
systemctl enable --now apache2

mkdir -p "$WEB_ROOT" "$EXTRACT_DIR"
echo "[2/4] Descargando el código del repositorio..."
curl --fail --silent --show-error --location --retry 3 \
    --config "$CURL_CONFIG" \
    --header 'Accept: application/vnd.github+json' \
    --header 'X-GitHub-Api-Version: 2022-11-28' \
    --user-agent 'POS-System-Pro-Raspberry-Pi-Installer' \
    "https://api.github.com/repos/${REPO}/zipball/${BRANCH}" \
    --output "$ZIP_FILE"

[[ -s "$ZIP_FILE" ]] || fail "La descarga del repositorio está vacía."
echo "[3/4] Extrayendo el proyecto en $APP_DIR..."
unzip -q "$ZIP_FILE" -d "$EXTRACT_DIR"
SOURCE_DIR="$(find "$EXTRACT_DIR" -mindepth 1 -maxdepth 1 -type d -print -quit)"
[[ -n "$SOURCE_DIR" && -d "$SOURCE_DIR" ]] || fail "No se encontró la carpeta del proyecto dentro del ZIP."
mkdir -p "$APP_DIR"
cp -a "$SOURCE_DIR"/. "$APP_DIR"/

# El propietario del servidor web necesita escribir para las funciones del sistema
# que guardan archivos y para el actualizador integrado. No se habilitan permisos 777.
echo "[4/4] Configurando permisos y eliminando el ZIP..."
chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} +
find "$APP_DIR" -type f -exec chmod 644 {} +
chmod 755 "$APP_DIR/instalador.sh" 2>/dev/null || true
rm -f "$ZIP_FILE"

systemctl reload apache2

IP_LOCAL="$(hostname -I | awk '{print $1}')"
echo
echo "Instalación completada."
echo "Proyecto: $APP_DIR"
echo "Aplicación: http://${IP_LOCAL:-localhost}/POS-System-Pro/"
echo "MariaDB y Apache quedaron habilitados al iniciar el sistema."
echo
echo "Nota: el instalador no crea ni importa la base de datos. Revisa puntodeventa.sql y configura conexion.php antes de usar la aplicación."
