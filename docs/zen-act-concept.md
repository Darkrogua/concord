# Zen.Act - Концепт

Акты в этом проекте — контейнеры блоков с фиксированными полями в PostgreSQL.

**В базе данных** (`zen_act_acts`): uuid PK, `name`, `description`, `owner_id` (bigint → RainLab.User), `activate_at`, `stop_at`, timestamps. Колонки `data` нет.

**На диске** (`storage/acts/{uuid}/`):

```
storage/acts/{uuid}/
├── current.json     ← полное текущее состояние (format_version: 2)
└── act.sqlite       ← блоки, log, access, user_tags, snapshots (история версий)
```

- **current.json** — канонический экспорт для DR и `act:restore-from-storage`
- **snapshots** (SQLite) — история версий: diff + указатель на `log_head_id`; слияние диапазона в UI
- **log** — цепочка изменений блоков (hash + chain); replay при restore

**CLI (AI):** `./bin/artisan act:acts ai schema` — CRUD актов и версий.

**Миграция legacy:** `./bin/artisan act:snapshots-migrate [--act=UUID] [--delete-legacy]`

**Auth (SPA):** `/act.api/Auth:login|register|logout|me` — login + password; см. факт `act-auth-rainlab-plan`.
