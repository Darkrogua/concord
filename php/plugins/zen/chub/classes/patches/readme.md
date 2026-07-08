# Механизм патчей
Для того чтобы запустить патч Patch170220261527 нужно воспользоваться командой
```bash
php artisan chub:patch Patch170220261527
```

Для патча уникальности `uid` в базе `uon-payments`:
```bash
php artisan chub:patch Patch110320261200
```

Для нормализации `uid` у `uon-payments` (из `request-payment-created_at` в `request-payment`):
```bash
php artisan chub:patch Patch190320261500
```