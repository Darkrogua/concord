# OS3

Веб-приложение на [October CMS 4](https://octobercms.com) (Laravel 12, PHP 8.2+) для работы с **контрактами и актами**. Основной пользовательский интерфейс — SPA-плагин **Zen.Act** (Vue 3 + Vite); администрирование — через бэкенд October (`/console`).

Репозиторий организован как монорепозиторий: код приложения в `php/`, локальная инфраструктура — в `docker/`.

**CLI с хоста:** из корня репозитория не вызывайте `php artisan` и `psql` напрямую — используйте прокси [`bin/artisan`](bin/artisan) и [`bin/db`](bin/db) (команды выполняются в Docker-контейнерах). Для AI-агентов в Cursor описаны правила [`.cursor/rules/ai_artisan.mdc`](.cursor/rules/ai_artisan.mdc) и [`.cursor/rules/ai_db.mdc`](.cursor/rules/ai_db.mdc) (`alwaysApply: true`).

## Стек

| Слой | Технологии |
|------|------------|
| CMS / API | October CMS 4, Laravel 12, PHP 8.4 (FPM в Docker) |
| БД | PostgreSQL 16 |
| Фронт (Zen.Act) | Vue 3, Vite 5, SCSS |
| Веб-сервер | Nginx (порт **8083** в compose) |
| Планировщик | `supercronic` + `php artisan schedule:run` |
| Dev-фронт | Node 20 (`os3-vite`), порт **5174** на хосте |

## Структура репозитория

```
os3/
├── bin/
│   ├── artisan             # Прокси: php artisan → os3-php
│   └── db                  # Прокси: psql, dump, restore → os3-db
├── .cursor/rules/
│   ├── ai_artisan.mdc      # Правило Cursor: как пользоваться bin/artisan
│   └── ai_db.mdc           # Правило Cursor: как пользоваться bin/db
├── docker/                 # Docker Compose, Nginx, образ PHP-FPM
│   ├── docker-compose.local.yaml
│   ├── app/Dockerfile
│   └── web/nginx.conf
├── docs/                   # Внутренняя документация проекта
│   └── zen-act-microfrontend.md
└── php/                    # Корень October CMS (artisan, plugins, themes)
    ├── plugins/zen/act/    # SPA «Акт», API, Vite-сборка
    ├── plugins/zen/robots/ # robots.txt
    ├── themes/os3/         # Тема сайта
    └── .scheduler/         # Crontab для os3-scheduler
```

Стандартный README October лежит в [`php/README.md`](php/README.md) — это шаблон дистрибутива, не описание этого проекта.

## Плагины и тема

| Компонент | Назначение |
|-----------|------------|
| **Zen.Act** | Страница `GET /app` (Vue SPA), JSON API ` /act.api/{class}:{method}` |
| **Zen.Robots** | Управление `robots.txt` из бэкенда |
| **RainLab.User** | Пользователи и авторизация |
| **RainLab.Builder** | Генерация схем и плагинов |
| **Тема `os3`** | Фронтенд-тема October CMS |

Миграция плагина Act создаёт таблицу `zen_act_acts` (черновик модели контракта/акта).

## Требования

- Docker и Docker Compose
- Внешняя Docker-сеть **`web`** (общая с окружением Megan / Traefik)
- Запись в `/etc/hosts` (или аналог): `os3.megan` → хост, с которого открываете сайт
- Для фронтенда Zen.Act в dev: Node 20 (локально или в контейнере `os3-vite`)

## Быстрый старт (Docker)

1. Скопируйте и настройте окружение приложения:

   ```bash
   cp php/.env.example php/.env
   # Для compose уже заданы DB_* и APP_URL в docker-compose; синхронизируйте php/.env
   ```

   Минимально для Zen.Act в `php/.env`:

   ```env
   APP_URL=http://os3.megan
   BACKEND_URI=console
   DB_CONNECTION=pgsql
   DB_HOST=os3-db
   DB_PORT=5432
   DB_DATABASE=os3
   DB_USERNAME=os3
   DB_PASSWORD=os3

   ACT_VITE_ORIGIN=http://localhost:5174
   ACT_VITE_DEV_HOST=os3-vite
   ACT_VITE_DEV_PORT=5173
   ```

2. При необходимости задайте UID/GID в `docker/.env` (по умолчанию `1000:1000`).

3. Поднимите стек из **корня репозитория**:

   ```bash
   make up
   ```

   (или вручную: `cd docker && docker compose -f docker-compose.local.yaml up -d --build`)

4. Установите зависимости и инициализируйте October (из корня репозитория, через `./bin/artisan`):

   ```bash
   docker exec -it os3-php composer install
   ./bin/artisan key:generate
   ./bin/artisan october:migrate
   ```

5. Откройте в браузере:

   - Сайт / SPA: [http://os3.megan:8083/app](http://os3.megan:8083/app) (или через прокси Megan, если настроен)
   - Бэкенд: `http://os3.megan:8083/console`

### Сервисы Compose

| Сервис | Контейнер | Назначение |
|--------|-----------|------------|
| `os3-php` | `os3-php` | PHP-FPM, рабочая копия `php/` |
| `os3-nginx` | `os3-nginx` | Nginx, `:8083` |
| `os3-db` | `os3-db` | PostgreSQL |
| `os3-scheduler` | `os3-scheduler` | Cron через supercronic |
| `os3-node` | `os3-vite` | Node для `npm run serve` / `build` |

Том `../php` монтируется в PHP и Node; корень репозитория — в `/var/www/project` (read-only) для виджета деплоя на Dashboard, если появится `scripts/deploy.sh`.

## Разработка Zen.Act (Vite)

Подробности — в [`docs/zen-act-microfrontend.md`](docs/zen-act-microfrontend.md).

Кратко (из **корня репозитория**, через контейнер `os3-vite`):

```bash
make vite-install   # npm install
make vite-serve     # HMR, dev-сервер (порт 5174 на хосте)
make vite-build     # прод: assets/ + Vite manifest + PWA (sw.js, manifest.webmanifest)
make vite-check     # проверить сборку без пересборки
./bin/vite-check --http   # + HTTP 200 для manifest, sw.js, /app
```

Вручную в контейнере:

```bash
docker exec -it -w /var/www/html/plugins/zen/act os3-vite sh
npm ci && npm run serve   # или npm run build
```

**Важно:** маршрут `/app` в Nginx обрабатывается отдельным `location ^~ /app`, иначе запрос уходит в каталог `php/app/` (Laravel) и отдаёт 403 — см. [`docker/web/nginx.conf`](docker/web/nginx.conf).

## API Zen.Act

Динамические маршруты (классы в `Zen\Act\Api\…`):

| Метод | Путь | Описание |
|-------|------|----------|
| `GET` | `/app` | HTML-оболочка SPA |
| `GET`, `POST` | `/act.api/{class}:{method}` | JSON API (точка в `class` → namespace) |

Пример отладки: добавьте `?debug=1` к запросу API.

## Makefile

Из корня репозитория (`make help`):

| Команда | Действие |
|---------|----------|
| `make up` | Запуск Docker-стека (сеть `web`, compose из `docker/`) |
| `make down` | Остановка стека |
| `make restart` | Перезапуск контейнеров без пересборки (`docker compose restart`) |
| `make bash` | Интерактивный bash в `os3-php` (`/var/www/html`, `php artisan …`) |
| `make shell-db` | Интерактивный `psql` (`./bin/db`) |
| `make db-dump` | Дамп PostgreSQL в `backups/*.sql.gz` |
| `make db-restore <файл>` | Восстановление из `backups/` |

Модули: `makefiles/common.mk`, `makefiles/docker.mk`, `makefiles/shell.mk`.

## CLI-прокси (`bin/`)

Обёртки запускают команды **внутри Docker** с хоста (нужны `make up` и `docker` в PATH). В CI без TTY используется `docker exec -i` без `-t`. Референс по паттерну — проект **axis** (`bin/artisan`).

| Скрипт | Контейнер | Назначение |
|--------|-----------|------------|
| [`./bin/artisan`](bin/artisan) | `os3-php` | October / Laravel CLI (`php artisan` в `/var/www/html`) |
| [`./bin/db`](bin/db) | `os3-db` | PostgreSQL: `psql`, дамп, восстановление |
| [`./bin/shakti`](bin/shakti) | Shakti VPS | SSH-прокси: команды, логи, статус (`193.168.48.146`) |

### `./bin/artisan`

```bash
./bin/artisan <command>
./bin/artisan october:migrate
./bin/artisan list
./bin/artisan chub:entity help    # Zen.Chub, если нужен доменный CLI
```

Переменные: `PHP_CONTAINER` (по умолчанию `os3-php`), `APP_DIR` (`/var/www/html`).

### `./bin/db`

```bash
./bin/db                          # интерактивный psql
./bin/db -c "SELECT version();"
./bin/db -Atc "\dt"               # компактный вывод для скриптов / AI
cat query.sql | ./bin/db          # SQL со stdin
./bin/db dump                     # → backups/os3_db_<timestamp>.sql.gz
./bin/db restore os3_db_….sql.gz  # DROP SCHEMA public + загрузка дампа
./bin/db help
```

### `./bin/shakti`

Прокси на тестовый VPS Shakti (креды в `~/bin/shakti-root`, не в git). Скилл: `.cursor/skills/os3-shakti/`.

```bash
./bin/shakti status
./bin/shakti bootstrap          # первый раз
./bin/shakti deploy             # обновление на https://acts.os3.pro
./bin/shakti run 'docker ps'
./bin/shakti logs docker os3-nginx -n 100
```

Make: `make shakti-bootstrap`, `make shakti-deploy`.

Переменные: `DB_CONTAINER` (`os3-db`), `PGUSER`, `PGDATABASE`, `PGPASSWORD` (по умолчанию `os3`), `BACKUPS_DIR`. Дампы — в `backups/` (в `.gitignore`).

Эквиваленты в Makefile: `make shell-db`, `make db-dump`, `make db-restore <файл>`.

## Cursor Rules (AI-агенты)

В [`.cursor/rules/`](.cursor/rules/) лежат постоянные правила для агентов Cursor (`alwaysApply: true` — подключаются в каждой сессии):

| Правило | Файл | О чём |
|---------|------|--------|
| **ai_artisan** | [`.cursor/rules/ai_artisan.mdc`](.cursor/rules/ai_artisan.mdc) | Когда и как вызывать `./bin/artisan`, типовые команды October и `chub:*` |
| **ai_db** | [`.cursor/rules/ai_db.mdc`](.cursor/rules/ai_db.mdc) | Когда и как вызывать `./bin/db`, запросы, дампы, ограничения |

Не дублируйте в промптах длинные инструкции по CLI — достаточно сослаться на эти правила и скрипты в `bin/`. Подробные контракты `chub:* ai` — в репозитории **axis** (`.cursor/commands/*_artisan.md`).

## Полезные команды

```bash
# October CLI и PostgreSQL с хоста
./bin/artisan october:migrate
./bin/db -c "\dt"

# Интерактивная оболочка в контейнере
make bash
make shell-db

# Тесты и линтер (в php/ или в контейнере)
composer test
composer lint

# Права на каталоги БД на хосте (опционально)
./docker/init-db-dirs.sh
```

## База опыта для агентов (Cursor)

Скилл **os3-onboarding** (`.cursor/skills/os3-onboarding/`): накопительная база фактов о проекте, автогенерируемый индекс и скрипты `fact-add.sh` / `index-rebuild.sh`. В чате: упомянуть скилл или задачу по OS3 — агент читает `knowledge/INDEX.md`.

## Документация

- [Zen.Act — микрофронт и Vite](docs/zen-act-microfrontend.md)
- [October CMS](https://docs.octobercms.com)
- CLI-прокси и правила AI: разделы выше, [`bin/`](bin/), [`.cursor/rules/`](.cursor/rules/)
- Референс по Vite/Chub в соседнем проекте: `axis` → `php/plugins/zen/chub`

## Лицензия

Платформа October CMS — [проприетарная лицензия](php/LICENSE.md) (EULA). Собственные плагины `zen/*` — по соглашению с правообладателем репозитория.
