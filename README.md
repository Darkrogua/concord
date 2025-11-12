# Concord - Система согласования документов

Система для согласования документов через голосование.

## Технологический стек

- **Backend**: Laravel 11
- **Frontend**: Vue.js 3 (SPA)
- **База данных**: PostgreSQL
- **Очереди**: Redis
- **Кэш**: Redis

## Структура проекта

```
concord/
├── backend/          # Laravel API
├── frontend/         # Vue.js SPA
└── concord/          # Документация проекта
```

## Быстрый старт

### Требования

- PHP 8.2+
- Composer
- Node.js 18+
- PostgreSQL 15+
- Redis 7+

### Установка

1. Клонировать репозиторий
2. Запустить Docker контейнеры:
   ```bash
   docker-compose up -d
   ```

3. Настроить Backend:
   ```bash
   cd backend
   composer install
   cp .env.example .env
   php artisan key:generate
   php artisan migrate
   ```

4. Настроить Frontend:
   ```bash
   cd frontend
   npm install
   cp .env.example .env
   ```

### Запуск

Backend:
```bash
cd backend
php artisan serve
php artisan queue:work
```

Frontend:
```bash
cd frontend
npm run dev
```

## Документация

Полная документация проекта находится в папке `concord/`:
- `Бизнес-логика проекта.md` - полное описание бизнес-логики
- `План реализации - Общий.md` - общий план реализации
- `План реализации - Backend.md` - план бэкенда
- `План реализации - Frontend.md` - план фронтенда
- `План реализации - База данных.md` - схема базы данных
- `План реализации - Инфраструктура.md` - настройка инфраструктуры

## Лицензия

Proprietary
