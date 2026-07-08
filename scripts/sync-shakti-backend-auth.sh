#!/usr/bin/env bash
# Перенос таблиц backend-авторизации October CMS: локальная БД → Shakti.
# Пароль zen тот же, что на Megan (хеш из дампа).

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck disable=SC1091
source "$REPO_ROOT/.cursor/skills/os3-shakti/scripts/lib.sh"

BACKEND_TABLES=(
  backend_user_roles
  backend_user_groups
  backend_users
  backend_users_groups
  backend_user_preferences
  backend_user_throttle
  backend_access_log
)

DUMP_FILE="${1:-$REPO_ROOT/backups/backend_auth_$(date +%d%m%Y%H%M).sql}"

echo "==> Экспорт backend auth из локальной БД"
mkdir -p "$(dirname "$DUMP_FILE")"

table_args=()
for t in "${BACKEND_TABLES[@]}"; do
  table_args+=(-t "$t")
done

docker exec os3-db pg_dump -U os3 -d os3 --data-only --no-owner --no-acl \
  "${table_args[@]}" >"$DUMP_FILE"

echo "    $DUMP_FILE ($(wc -l <"$DUMP_FILE") строк)"

echo "==> Загрузка на Shakti"
shakti_scp_to "$DUMP_FILE" "/tmp/backend_auth.sql"

echo "==> Импорт на Shakti (TRUNCATE + COPY)"
shakti_ssh "bash -s" <<'REMOTE'
set -euo pipefail
cd /opt/os3/docker
COMPOSE="docker compose --env-file .env.shakti -f docker-compose.shakti.yaml"
$COMPOSE exec -T os3-db psql -U os3 -d os3 -v ON_ERROR_STOP=1 <<'SQL'
TRUNCATE TABLE
  backend_access_log,
  backend_user_throttle,
  backend_user_preferences,
  backend_users_groups,
  backend_users,
  backend_user_groups,
  backend_user_roles
RESTART IDENTITY CASCADE;
SQL
cat /tmp/backend_auth.sql | $COMPOSE exec -T os3-db psql -U os3 -d os3 -v ON_ERROR_STOP=1
$COMPOSE exec -T os3-db psql -U os3 -d os3 -Atc "SELECT login, email FROM backend_users ORDER BY id;"
$COMPOSE exec -T os3-php php artisan config:clear
rm -f /tmp/backend_auth.sql
REMOTE

echo ""
echo "Готово. Вход: https://acts.os3.pro/console (логин как на Megan)"
