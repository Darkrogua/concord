#!/bin/bash
# Скрипт для создания папок базы данных с правильными правами доступа
# Устанавливает права UID=1000 GID=1000 для хостового пользователя

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
DB_DIR="$PROJECT_ROOT/db"

# Получаем UID и GID из переменных окружения или используем значения по умолчанию
# UID - это read-only переменная в bash, поэтому используем DOCKER_UID или id -u
DOCKER_UID=${DOCKER_UID:-${UID:-1000}}
DOCKER_GID=${DOCKER_GID:-${GID:-1000}}

# Если UID/GID не установлены, пытаемся получить из системы
if [ "$DOCKER_UID" = "1000" ] && command -v id >/dev/null 2>&1; then
    DOCKER_UID=$(id -u 2>/dev/null || echo "1000")
    DOCKER_GID=$(id -g 2>/dev/null || echo "1000")
fi

UID_VAL=$DOCKER_UID
GID_VAL=$DOCKER_GID

echo "🔧 Создание папок базы данных..."
echo "   Директория: $DB_DIR"
echo "   UID: $UID_VAL, GID: $GID_VAL"

mkdir -p "$DB_DIR"

mkdir -p "$DB_DIR/os3_db_data"

if [ -d "$DB_DIR/os3_db_data" ] && [ -z "$(ls -A "$DB_DIR/os3_db_data" 2>/dev/null)" ]; then
    chown -R "$UID_VAL:$GID_VAL" "$DB_DIR/os3_db_data" 2>/dev/null || true
    chmod 700 "$DB_DIR/os3_db_data" 2>/dev/null || true
fi

echo "✅ Папки базы данных созданы"
