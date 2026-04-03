#!/bin/sh
set -e

WORKDIR="/var/www/html"
cd "$WORKDIR"

# ─── PHP ini ─────────────────────────────────────────────────────────────────
cp "$WORKDIR/docker/php/php.ini" "$PHP_INI_DIR/conf.d/espocrm.ini"

# ─── Writable directories & permissions (always, every start) ────────────────
mkdir -p \
    "$WORKDIR/data/logs" \
    "$WORKDIR/data/cache" \
    "$WORKDIR/data/upload" \
    "$WORKDIR/data/tmp"

for dir in data custom; do
    [ -d "$WORKDIR/$dir" ] && chown -R www-data:www-data "$WORKDIR/$dir"
done

# ─── Skip install steps for secondary containers (cron, daemon…) ─────────────
if [ "${SKIP_INSTALL:-false}" = "true" ]; then
    echo "[entrypoint] SKIP_INSTALL=true, skipping install steps."
    exec "$@"
fi

# ─── PHP dependencies ────────────────────────────────────────────────────────
if [ ! -f "$WORKDIR/vendor/autoload.php" ]; then
    echo "[entrypoint] Installing PHP dependencies (composer install)..."
    composer install --no-dev --optimize-autoloader --no-interaction
else
    echo "[entrypoint] vendor/autoload.php already present, skipping composer install."
fi

# ─── Frontend assets ─────────────────────────────────────────────────────────
if [ ! -d "$WORKDIR/client/lib" ] || [ -z "$(ls -A "$WORKDIR/client/lib" 2>/dev/null)" ]; then
    echo "[entrypoint] Installing NPM dependencies..."
    npm ci

    echo "[entrypoint] Building frontend assets (grunt internal)..."
    npm run build-frontend
else
    echo "[entrypoint] client/lib/ already present, skipping frontend build."
fi

# ─── Permissions for generated assets ────────────────────────────────────────
for dir in vendor client/lib client/css client/modules; do
    [ -d "$WORKDIR/$dir" ] && chown -R www-data:www-data "$WORKDIR/$dir"
done

echo "[entrypoint] Ready. Starting: $*"
exec "$@"
