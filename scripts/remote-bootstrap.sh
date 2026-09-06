#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."
DOMAIN="${APP_DOMAIN:-c.2ds.ru}"

if [ ! -f .env ]; then
  DB_PASSWORD="$(openssl rand -hex 16)"
  printf 'APP_DOMAIN=%s\nDB_PASSWORD=%s\n' "$DOMAIN" "$DB_PASSWORD" > .env
fi

# shellcheck disable=SC1091
set -a
source .env
set +a

if [ ! -f backend/.env ]; then
  APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
  cat > backend/.env <<EOF
APP_NAME=Concord
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=https://${DOMAIN}

APP_LOCALE=ru
APP_FALLBACK_LOCALE=ru
APP_FAKER_LOCALE=ru_RU
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=stderr,json
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=concord
DB_USERNAME=concord
DB_PASSWORD=${DB_PASSWORD}

SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
CACHE_STORE=redis

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@${DOMAIN}
MAIL_FROM_NAME="Concord"

SANCTUM_STATEFUL_DOMAINS=${DOMAIN}
CORS_ALLOWED_ORIGINS=https://${DOMAIN}
FRONTEND_URL=https://${DOMAIN}
EOF
fi

if [ ! -f frontend/.env ]; then
  cat > frontend/.env <<EOF
VITE_PRIMEUI_LICENSE=${VITE_PRIMEUI_LICENSE:-}
EOF
elif [ -n "${VITE_PRIMEUI_LICENSE:-}" ] && ! grep -q '^VITE_PRIMEUI_LICENSE=' frontend/.env; then
  printf '\nVITE_PRIMEUI_LICENSE=%s\n' "$VITE_PRIMEUI_LICENSE" >> frontend/.env
fi

mkdir -p backend/storage/logs backend/bootstrap/cache frontend/dist
chmod -R ug+rwx backend/storage backend/bootstrap/cache || true

echo "==> Собираю образы"
docker compose -f docker-compose.prod.yml --env-file .env build

echo "==> Собираю SPA"
docker compose -f docker-compose.prod.yml --profile build run --rm frontend

echo "==> Composer"
docker compose -f docker-compose.prod.yml --env-file .env run --rm --no-deps app composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Поднимаю стек"
docker compose -f docker-compose.prod.yml --env-file .env up -d --remove-orphans

echo "==> Права и Laravel"
docker compose -f docker-compose.prod.yml --env-file .env exec -T app chown -R www-data:www-data storage bootstrap/cache || true
docker compose -f docker-compose.prod.yml --env-file .env exec -T app php artisan storage:link --force || true
docker compose -f docker-compose.prod.yml --env-file .env exec -T app php artisan migrate --force
docker compose -f docker-compose.prod.yml --env-file .env exec -T app php artisan db:seed --force || true
docker compose -f docker-compose.prod.yml --env-file .env exec -T app php artisan filament:assets
docker compose -f docker-compose.prod.yml --env-file .env exec -T app php artisan config:clear
docker compose -f docker-compose.prod.yml --env-file .env exec -T app php artisan config:cache
docker compose -f docker-compose.prod.yml --env-file .env exec -T app php artisan route:cache
docker compose -f docker-compose.prod.yml --env-file .env exec -T app php artisan view:cache

echo "==> Готово: https://${DOMAIN}"
docker compose -f docker-compose.prod.yml --env-file .env ps
