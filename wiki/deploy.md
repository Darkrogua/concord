# Деплой на hdocker (c.2ds.ru)

Сервер: `hdocker.2ds.ru`, каталог `~/docker/concord`.
Вход: команда `hdocker` на рабочей машине.
Публичный адрес: https://c.2ds.ru  
Админка: https://c.2ds.ru/admin

Traefik уже стоит на хосте (сеть `proxy`, сертификат `*.2ds.ru`). В production-compose порты Postgres/Redis наружу не публикуются.

```bash
./scripts/deploy-hdocker.sh
```

Скрипт копирует код, собирает PHP-образ и SPA, ставит Composer, гоняет миграции.
