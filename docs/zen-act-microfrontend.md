# Zen.Act — микрофронт и Vite (референс: axis → zen.chub)

## Как устроено на axis (Chub)

Референс: `/Atman/projects/axis/php/plugins/zen/chub/`.

1. **`vite.config.js`** — один корень `resources/`, несколько **целей** (`flow_app`, `process_app`, …). У каждой цели: `js/*.js` и опционально `scss/*.scss` попадают в `rollupOptions.input` как ключи вида `act_app/js/act_app.js`. Сборка: `outDir` = `assets/`, **`manifest: true`**, в проде `base` = `/plugins/zen/chub/assets/`.
2. **`package.json`** — `serve` = dev-сервер Vite, `build` = прод.
3. **`Classes/Support/Vite.php`** — из PHP решает:
   - если с порта доступен Vite → в разметку идут URL с `ACT_VITE_ORIGIN` (`/@vite/client` + entry);
   - иначе читает `assets/.vite/manifest.json` и подставляет хешированные файлы из `/plugins/zen/act/assets/`.
4. **Встраивание** — в Chub частички вида `controllers/flows/flow_app.php` выводят корневой `div` и `Vite::tags(['flow_app/js/flow_app.js'])`.

У zen.act тот же принцип, но одна цель **`act_app`**, фронт отдаётся целой страницей по **`GET /app`** (`AppController` + Blade).

## Переменные окружения (.env)

Имеет смысл в `php/.env` задать (хост/порт под ваш docker compose):

```env
ACT_VITE_ORIGIN=http://localhost:5174
ACT_VITE_DEV_HOST=os3-vite
ACT_VITE_DEV_PORT=5173
```

- **`ACT_VITE_ORIGIN`** — с чего **браузер** загружает модули в dev (у os3 на хосте часто **5174**, внутри контейнера Vite слушает **5173**).
- **`ACT_VITE_DEV_HOST` / `ACT_VITE_DEV_PORT`** — куда **PHP** стучится `fsockopen`, чтобы понять, жив ли dev-сервер (из контейнера `os3-php` это обычно имя сервиса Node, например `os3-vite:5173`).

Для `vite serve` можно экспортировать тот же публичный origin:

```bash
cd php/plugins/zen/act && ACT_VITE_ORIGIN=http://localhost:5174 npm run serve
```

## Сервис `os3-vite` (аналог `axis-vite`)

В `docker/docker-compose.local.yaml` сервис **`os3-node`** с **`container_name: os3-vite`**: образ `node:20-alpine`, том `../php:/var/www/html`, пор **`5174:5173`**, сети `os3_internal` + `web`. По умолчанию команда `sleep infinity` — как на axis: вручную `npm run serve` в `plugins/zen/act` внутри контейнера. Для **прод-подложки** достаточно `npm run build` на хосте или в контейнере; dev-сервер не обязателен.

## Маршрут `/app` и Nginx

В корне October лежит каталог **`app/`** (Laravel). В типовом `location /` с `try_files ... $uri/` запрос **`/app`** сначала попадает в этот каталог → **403**. Нужен отдельный блок **до** общего `location /` (см. `docker/web/nginx.conf`: `location ^~ /app` → `index.php`).

## Команды

```bash
cd /Atman/projects/os3/php/plugins/zen/act
npm ci   # или npm install
npm run serve   # разработка, HMR
npm run build   # manifest + файлы в assets/
```

В Docker dev обычно: `docker exec -it -w /var/www/html/plugins/zen/act os3-vite sh` → `npm run serve` (зависимости ставить в этом контейнере один раз).

## Маршруты плагина

| Метод | Путь        | Назначение        |
|-------|-------------|-------------------|
| GET   | `/app`      | SPA «Акт» (Vue 3) |
| *     | `/act.api/…` | JSON API плагина |

## План работ (что важнее всего дальше)

1. **Стабилизировать dev-поток** — зафиксировать в Makefile/docker-команде запуск `npm run serve` для Node-контейнера и значения `ACT_VITE_*` под команду (как в Chub + axis).
2. **Модель контракта и API** — сущность «контракт / акт», связь с пользователем или сессией; эндпоинты `act.api` или отдельные маршруты для CRUD и списка событий.
3. **Конструктор событий во Vue** — форма: тип события, время (абсолютное/относительное), порядок; валидация; черновик в `localStorage` до сохранения.
4. **Миниапп / встраивание** — отдельный лёгкий layout или query-флаг `?embed=1`; при необходимости второй entry в `vite.config.js` (как несколько приложений в Chub).
5. **Прод-сборка в CI** — `npm run build` до деплоя; проверка наличия `manifest.json`.

## Файлы zen.act (текущая подложка)

- `vite`, фронт: `resources/act_app/`, `vite.config.js`, `package.json`
- PHP: `controllers/AppController.php`, `views/app.blade.php`, `classes/support/Vite.php`
- Точка входа фронта: `resources/act_app/js/act_app.js` → `vue/ActApp.vue`
