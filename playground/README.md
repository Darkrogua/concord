# Playground — локальная разработка webapp-блоков

Каталог для HTML-фрагментов блоков типа `webapp`. Файлы **версионируются в git** — демо (тетрис и др.) можно развивать вместе с агентом в IDE.

## Структура

```
playground/
  {act_uuid}/
    {block_uuid}/
      fragment.html   # содержимое block.data.html (без doctype/head/body)
      meta.json       # act_id, block_id, block_hash, content_sha256
```

## Команды

```bash
# выгрузить блок из SQLite акта
./bin/artisan act:playground pull \
  --act=0c82be07-355a-4ca3-8b8f-9dca587b38bd \
  --block=1544cc0b-0e36-4298-8f04-85f88f536751

# статус: совпадает ли meta с БД
./bin/artisan act:playground status --act=… --block=…

# загрузить правки (log + snapshot + restore.json через BlockApp::update)
./bin/artisan act:playground push --act=… --block=…

# если блок меняли в SPA — pull заново или push --force
./bin/artisan act:playground push --act=… --block=… --force

# список каталогов с meta.json
./bin/artisan act:playground list
```

Только **local dev** (`APP_ENV=local`). На production команда откажется без `--allow-prod`.

## Workflow

1. `pull` → правка `fragment.html` в Cursor
2. `push` → проверка http://os3.megan/act_{uuid}
3. Коммит в git
4. На прод: скопировать HTML из блока вручную (или pull на проде не используется)

## Контракт HTML-фрагмента

Фрагмент вставляется в страницу Act через `HtmlFragment` — это **не** полная HTML-страница.

- Один корневой контейнер, например `<div class="my-app">…</div>`
- **Не** стилизовать `html` / `body`
- Адаптивность: `width: 100%`, `min-height: 100dvh`, `overflow-x: hidden`
- `<style>` и `<script>` внутри фрагмента — допустимы
- Скрипты с `DOMContentLoaded` / `load` — патчатся при монтировании (см. `html-fragment.js`)

Пример после pull: тетрис в `0c82be07-…/1544cc0b-…/`.

## Push и версионирование

`push` вызывает `BlockApp::update` — в SQLite пишутся:

- обновление `blocks` + новый hash
- запись в `log` (цепочка chain)
- снимок акта (`recordStateSnapshot`) и `restore.json`

Если `meta.block_hash` не совпадает с текущим hash в БД (блок меняли в SPA), push отклоняется — сделайте `pull` или `push --force`.

Переопределение пути: `ACT_PLAYGROUND_PATH` в `php/.env` (по умолчанию `../playground` от `php/`).
