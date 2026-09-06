# Контейнеры Docker Compose

Стек описывается в `docker-compose.yml`. Grafana **не** входит в compose: используется ваш отдельный инстанс.

## postgres (`concord-postgres`)

Реляционная база PostgreSQL 16. Хранит пользователей, согласования, разделы, голоса, файлы, уведомления.

- Порт хоста: `5432`
- БД / пользователь / пароль (dev): `concord` / `concord` / `concord`
- Данные: том `postgres_data`

Без этого контейнера Laravel не стартует (healthcheck `pg_isready`).

## redis (`concord-redis`)

In-memory хранилище. Нужен для очередей Laravel, кэша и сессий (если настроены на Redis).

- Порт хоста: `6379`
- Данные в контейнере не персистятся отдельным томом (после `compose down -v` очередь очищается)

## app (`concord-app`)

PHP-FPM 8.4 с кодом Laravel (`backend/` монтируется в `/var/www/html`). Сам по себе **не** слушает HTTP: принимает FastCGI от Nginx на порту `9000` внутри сети Docker.

Здесь крутится приложение: API, Filament `/admin`, Sanctum.

## nginx (`concord-nginx`)

Обратный прокси и веб-сервер. Принимает HTTP с хоста и отдаёт PHP через `app:9000`.

- Порт хоста: `8080` → контейнер `80`
- JSON access-логи: том `nginx_logs` (`/var/log/nginx/access.json`)

Точка входа: API и админка `http://localhost:8080`.

## queue (`concord-queue`)

Тот же PHP-образ, что и `app`, но команда `php artisan queue:work`. Забирает jobs из Redis: письма, отложенная публикация, архивация, напоминания о дедлайне.

Если контейнер стоит, письма и фоновые задачи не уходят, хотя сайт может открываться.

## scheduler (`concord-scheduler`)

Тот же PHP-образ, команда `php artisan schedule:work`. Каждую минуту запускает расписание Laravel (публикация по дате, проверка завершения, архивация).

Аналог cron внутри Docker, без crontab на хосте.

## mailpit (`concord-mailpit`)

Почтовая «заглушка» для разработки. Laravel шлёт SMTP на порт `1025`, письма смотрятся в веб-UI.

- SMTP: `localhost:1025`
- UI: `http://localhost:8025`

В проде этот контейнер не нужен.

## loki (`concord-loki`)

Хранилище логов (Grafana Loki). Promtail пушит сюда строки, Grafana читает LogQL.

- Порт хоста: `3101` → внутри контейнера `3100`  
  (на хосте `3100` часто занят другим Loki)
- Данные: том `loki_data`

Datasource во внешней Grafana: `http://127.0.0.1:3101` или `http://host.docker.internal:3101`.

## promtail (`concord-promtail`)

Агент сбора логов. Сам логи не хранит, только читает и отправляет в Loki:

- stdout/stderr контейнеров с именем `concord-*`
- JSON-файлы Laravel `backend/storage/logs/laravel.json*.log`
- access-лог Nginx из тома `nginx_logs`

Нужен доступ к Docker socket (`/var/run/docker.sock`).

## Что не в compose

| Компонент | Где живёт |
|-----------|-----------|
| Vue SPA (Vite) | Хост, `frontend/`, порт `5173` |
| Grafana | Ваш отдельный контейнер/сервер |
| PHP на хосте | Не требуется, только Docker |
