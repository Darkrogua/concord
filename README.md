# Concord - Система согласования документов

Система для согласования документов через голосование.

## Стек

- **Backend**: Laravel 13, Filament 4, Sanctum
- **Frontend**: Vue 3, Vite, PrimeVue
- **База данных**: PostgreSQL 16
- **Очереди / кэш**: Redis 7
- **Логи**: Loki + Promtail (Grafana — отдельный инстанс)

## Быстрый старт

```bash
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan filament:assets
cd frontend && npm install && npm run dev
```

- SPA: http://localhost:5173
- API и админка: http://localhost:8080/admin
- Письма: http://localhost:8025
- Loki API: http://localhost:3101

Grafana в этот compose не входит. В существующем инстансе добавьте datasource Loki:

- Grafana на хосте: `http://127.0.0.1:3101`
- Grafana в Docker на том же хосте: `http://host.docker.internal:3101`

Дашборд: импорт `docker/grafana/dashboards/concord-logs.json`.

Логи приложения пишутся в JSON (`backend/storage/logs/laravel.json.log` и stderr контейнеров). Promtail собирает логи Docker, Laravel и Nginx и отправляет в Loki.

Документация: [wiki/](wiki/) (инфраструктура), папка `concord/` (продукт).
