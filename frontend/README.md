# Concord Frontend (Vue 3 + PrimeVue)

SPA для системы согласования документов.

## Установка

```bash
npm install
cp .env.example .env
```

## Запуск

```bash
npm run dev
```

Приложение: http://localhost:5173  
API (Docker Nginx): http://localhost:8080  
Админка Filament: http://localhost:8080/admin  

Демо-пользователи (после `php artisan db:seed`):

- `admin@concord.local` / `password` — Filament
- `author@concord.local` / `password`
- `approver@concord.local` / `password`
