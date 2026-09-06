#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REMOTE_DIR="docker/concord"

cd "$ROOT"
chmod +x scripts/remote-bootstrap.sh scripts/deploy-hdocker.sh

echo "==> Копирую проект на hdocker"
tar czf - \
  --exclude='./backend/vendor' \
  --exclude='./frontend/node_modules' \
  --exclude='./.git' \
  --exclude='./backend/.env' \
  --exclude='./frontend/.env' \
  --exclude='./backend/storage' \
  --exclude='./backend/bootstrap/cache' \
  --exclude='./frontend/dist' \
  --exclude='./concord' \
  --exclude='./.obsidian' \
  --exclude='./.cursor' \
  . | hdocker "mkdir -p ${REMOTE_DIR} && tar xzf - -C ${REMOTE_DIR}"

echo "==> Собираю и поднимаю на сервере"
hdocker "chmod +x ${REMOTE_DIR}/scripts/remote-bootstrap.sh && bash ${REMOTE_DIR}/scripts/remote-bootstrap.sh"
