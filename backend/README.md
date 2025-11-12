# Concord Backend (Laravel 11)

API для системы согласования документов.

## Установка

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

## Запуск

```bash
php artisan serve
php artisan queue:work
```

## Структура

- `app/` - приложение
- `database/` - миграции, модели, сидеры
- `routes/api.php` - API маршруты
- `config/` - конфигурация

