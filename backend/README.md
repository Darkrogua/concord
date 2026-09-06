# Concord Backend (Laravel 13 + Filament 4)

API и админка системы согласования.

## Docker

Из корня репозитория:

```bash
cp backend/.env.example backend/.env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan filament:assets
```

- API / Filament: http://localhost:8080 (`/admin`)
- Mailpit: http://localhost:8025

Демо:

- admin@concord.local / password (Filament)
- author@concord.local / password
- approver@concord.local / password
