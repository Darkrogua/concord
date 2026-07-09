# Concord — движок (референс в репозитории)

Пример движка уже в корне проекта. Concord собирается **на его основе**, а не как отдельный `backend/` + `frontend/`.

## Стек движка

| Слой | Технология |
|------|------------|
| CMS / API | October CMS 4 (Laravel 12), PHP 8.4 |
| БД | PostgreSQL 16 |
| SPA | Zen.Act — Vue 3, Vite 5, SCSS |
| Инфра | Docker Compose, Nginx :8083 |
| Auth | RainLab.User |
| CLI | `bin/artisan`, `bin/db` (прокси в контейнеры) |

## Структура репозитория

```
concord/
├── bin/artisan, bin/db          # CLI с хоста → Docker
├── docker/                      # compose, nginx, Dockerfile
├── docs/
│   ├── zen-act-concept.md       # модель актов, storage, CLI
│   └── zen-act-microfrontend.md # Vite, /app, act.api
├── php/                         # корень October CMS
│   └── plugins/zen/act/         # плагин Zen.Act (SPA + API)
├── vc/concord/                  # спека Concord (источник истины)
└── Makefile                     # make up, make down, …
```

## Zen.Act — ключевые точки

| Что | Где |
|-----|-----|
| SPA | `GET /app` → `AppController` + `views/app.blade.php` |
| API | `/act.api/{Class}:{method}` → `php/plugins/zen/act/api/` |
| Vue entry | `resources/act_app/js/act_app.js` → `ActApp.vue` |
| Vite config | `php/plugins/zen/act/vite.config.js` |
| Vite helper | `classes/support/Vite.php` |
| Маршруты | `php/plugins/zen/act/routes.php` |
| Блоки UI | `resources/act_app/vue/components/blocks/` |

## Dev-окружение

```bash
make up                                    # из корня
./bin/artisan october:migrate
cd php/plugins/zen/act && npm run serve   # HMR, порт 5174 на хосте
```

`.env` (фрагмент):

```env
ACT_VITE_ORIGIN=http://localhost:5174
ACT_VITE_DEV_HOST=os3-vite
ACT_VITE_DEV_PORT=5173
```

**Правила Cursor:** `.cursor/rules/ai_artisan.mdc`, `.cursor/rules/ai_db.mdc` — не вызывать `php artisan` / `psql` напрямую с хоста.

## Модель данных движка (текущая)

Zen.Act сейчас — контейнеры «актов» с блоками:

- PostgreSQL: `zen_act_acts` (uuid, name, description, owner_id, activate_at, stop_at)
- Диск: `storage/acts/{uuid}/current.json` + `act.sqlite` (блоки, log, snapshots)

См. `docs/zen-act-concept.md`, CLI: `./bin/artisan act:acts ai schema`.

## Маппинг Concord → движок

| Concord (спека) | Реализация на движке |
|-----------------|----------------------|
| Согласование | Расширить/заменить модель актов или новые таблицы `concord_*` |
| Раздел + блоки | Блоки Zen.Act + блок согласования |
| REST `/api/*` из спеки | Адаптировать в `act.api/*` или новые API-классы плагина |
| Vue SPA | `resources/act_app/` — страницы, stores, компоненты |
| Jobs (архивация, уведомления) | October/Laravel queue + scheduler (`os3-scheduler`) |
| Файлы | `storage/` + FileService в плагине |

Доменная логика Concord — из `vc/concord/`. Паттерны кода, Vite, Docker, API — из Zen.Act.

## Сборка Concord на движке

1. Поднять инфра (`make up`), миграции (`./bin/artisan october:migrate`)
2. Схема БД Concord — миграции в `php/plugins/zen/act/updates/` (или отдельный плагин `zen/concord`)
3. API — классы в `api/`, маршруты через `act.api`
4. Frontend — Vue в `resources/act_app/`, сборка `npm run build`
5. Фоновые задачи — Jobs + scheduler

Подробнее: [README.md](../../../README.md) в корне репозитория.
