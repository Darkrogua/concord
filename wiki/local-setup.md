# Локальный запуск

```bash
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan filament:assets
```

SPA поднимается контейнером `frontend` вместе с остальным стеком (`http://localhost:5173`). Grafana в compose нет.

Демо-пользователи (пароль `password`):

- `admin@concord.local`
- `author@concord.local`
- `approver@concord.local`

PrimeVue 5 требует ключ PrimeUI. Для небольших команд он бесплатный: зарегистрируйтесь на [primeui.dev/licenses/community](https://primeui.dev/licenses/community) и вставьте ключ в `frontend/.env` как `VITE_PRIMEUI_LICENSE=...`. Без ключа в углу будет тост «Invalid PrimeUI License».

Адреса:

- SPA: http://localhost:5173
- API и Filament: http://localhost:8080/admin
- Письма: http://localhost:8025
- Loki: http://localhost:3101

Полезные команды: см. корневой `Makefile` (`make up`, `make migrate`, `make logs`).
