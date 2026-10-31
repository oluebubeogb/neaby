#!/bin/sh
# neaby boot hook — runs inside serversideup/php's entrypoint
# (copied to /etc/entrypoint.d/60-neaby.sh) before nginx/php-fpm start.
set -e
cd /var/www/html

echo "[neaby] preparing storage"
mkdir -p storage/app/public storage/app/private storage/framework/cache/data \
         storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
[ -e public/storage ] || php artisan storage:link || true

if [ -z "${APP_KEY}" ]; then
  echo "[neaby] ERROR: APP_KEY is empty. Generate one with: php artisan key:generate --show" >&2
  exit 1
fi

# Wait for Postgres (Coolify may start the app before the DB is ready)
tries=0
until php artisan db:show --json >/dev/null 2>&1; do
  tries=$((tries+1))
  if [ "$tries" -ge "${NEABY_DB_WAIT_TRIES:-30}" ]; then
    echo "[neaby] database not reachable after $tries tries — continuing; migrate will report the error" >&2
    break
  fi
  echo "[neaby] waiting for database ($tries)…"
  sleep 2
done

echo "[neaby] php artisan migrate --force"
php artisan migrate --force

if [ "${NEABY_SEED_CATEGORIES:-true}" = "true" ]; then
  echo "[neaby] seeding default categories (idempotent)"
  php artisan db:seed --class='Database\Seeders\CategorySeeder' --force
  php artisan db:seed --class='Database\Seeders\MembershipPlanSeeder' --force
fi

echo "[neaby] caching config/routes/views"
php artisan optimize:clear >/dev/null 2>&1 || true
php artisan optimize
echo "[neaby] ready"
