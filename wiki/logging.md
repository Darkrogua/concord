# Логирование

Приложение пишет JSON в stderr контейнеров и в `backend/storage/logs/laravel.json-YYYY-MM-DD.log` (`LOG_STACK=stderr,json`).

Покрыто: HTTP (`http.request`, `X-Request-Id`), исключения, auth, audit, файлы, уведомления, разделы, голоса, jobs.

## Сбор

Promtail → Loki. Grafana в этот репозиторий не входит.

Во внешней Grafana:

1. Datasource Loki: `http://127.0.0.1:3101` (хост) или `http://host.docker.internal:3101` (Grafana в Docker).
2. Импорт дашборда `docker/grafana/dashboards/concord-logs.json`.

Примеры LogQL:

```logql
{project="concord"}
{job="laravel"}
{job="nginx"}
{service="app"} |= "auth.login"
```
