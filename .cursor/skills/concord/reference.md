# Concord — справочник API и БД

Источник: `vc/concord/План реализации - Backend.md`, `План реализации - База данных.md`.

## Database

### Таблицы (порядок миграций)

1. `users` — id, name, email, email_verified_at, password, remember_token
2. `signatures` — user_id, name, is_active (мульти-подпись)
3. `projects` — name, user_id (категории/папки)
4. `agreements` — title, description, author_id, signature_id, project_id, deadline, publish_date, status (draft|awaiting|completed|expired|archived|deleted), is_approved, completed_at, deleted_at
5. `sections` — agreement_id, name, order, completion_condition (all), show_results_before_vote, participants_see_each_other, is_approved, approved_at
6. `section_participants` — section_id, user_id, signature_id (unique: section+user+signature)
7. `information_blocks` — section_id, type (text|gallery|files|links|code), title, content (json), order
8. `files` — information_block_id, name, path, size, mime_type
9. `votes` — section_id, user_id, signature_id, vote (yes|no), comment (unique: section+user+signature)
10. `user_groups` / `user_group_members`
11. `agreement_groups` — name, user_id, filters (json), is_default
12. `favorites` — user_id, agreement_id
13. `notifications` — user_id, type, title, message, data (json), read_at
14. `activity_logs` — user_id, signature_id, action, model_type, model_id, changes (json), ip, user_agent

### Индексы

- agreements: author_id, status, deadline, publish_date, project_id
- sections: agreement_id, order
- votes: section_id, user_id, signature_id
- notifications: user_id, read_at

## API

Базовый путь: `/api`. Auth: Laravel Sanctum.

### Auth

| Method | Path | Описание |
|--------|------|----------|
| POST | `/register` | Регистрация |
| POST | `/login` | Вход |
| POST | `/logout` | Выход |
| POST | `/forgot-password` | Восстановление |
| POST | `/reset-password` | Сброс пароля |
| GET | `/user` | Текущий пользователь |

### Signatures (мульти-подпись)

| Method | Path |
|--------|------|
| GET | `/signatures` |
| POST | `/signatures` |
| PUT | `/signatures/{id}` |
| DELETE | `/signatures/{id}` |
| POST | `/signatures/{id}/activate` |

### Projects

| Method | Path |
|--------|------|
| GET/POST | `/projects` |
| PUT/DELETE | `/projects/{id}` |

### Agreements

| Method | Path | Примечание |
|--------|------|------------|
| GET | `/agreements` | Фильтрация, поиск, сортировка |
| GET | `/agreements/{id}` | Детали |
| POST | `/agreements` | Создание |
| PUT | `/agreements/{id}` | Только до запуска |
| DELETE | `/agreements/{id}` | Мягкое удаление |
| POST | `/agreements/{id}/publish` | Запуск |
| POST | `/agreements/{id}/duplicate` | Дублирование |
| POST | `/agreements/{id}/archive` | Архивация |
| POST | `/agreements/{id}/restore` | Из корзины |
| GET | `/agreements/search?q=` | Поиск |
| POST/DELETE | `/agreements/{id}/favorite` | Избранное |

### Sections

| Method | Path | Примечание |
|--------|------|------------|
| GET | `/agreements/{agreementId}/sections` | Список |
| GET/PUT/DELETE | `/sections/{id}` | PUT сбрасывает голоса при активном согласовании |
| POST | `/agreements/{agreementId}/sections` | Создание |
| POST | `/sections/{id}/duplicate` | Дублирование |

### Participants

| Method | Path |
|--------|------|
| GET | `/sections/{id}/participants` |
| POST | `/sections/{id}/participants` |
| DELETE | `/sections/{id}/participants/{userId}` |
| POST | `/sections/{id}/participants/bulk` |

### Information blocks & files

| Method | Path |
|--------|------|
| GET/POST | `/sections/{sectionId}/blocks` |
| PUT/DELETE | `/blocks/{id}` |
| POST | `/blocks/{blockId}/files` |
| GET/DELETE | `/files/{id}` |

### Voting

| Method | Path |
|--------|------|
| POST | `/sections/{id}/vote` |
| GET | `/sections/{id}/votes` |

### User groups

| Method | Path |
|--------|------|
| GET/POST | `/user-groups` |
| PUT/DELETE | `/user-groups/{id}` |
| GET | `/user-groups/{id}/members` |

### Agreement groups

| Method | Path |
|--------|------|
| GET/POST | `/agreement-groups` |
| PUT/DELETE | `/agreement-groups/{id}` |

### Notifications

| Method | Path |
|--------|------|
| GET | `/notifications` |
| PUT | `/notifications/{id}/read` |
| PUT | `/notifications/read-all` |

## Background jobs

| Job | Назначение |
|-----|------------|
| `SendAgreementNotificationJob` | Новое согласование |
| `SendVoteNotificationJob` | Голосование |
| `SendDeadlineReminderJob` | Напоминание о дедлайне |
| `SendSectionUpdatedNotificationJob` | Корректировка раздела |
| `PublishScheduledAgreementsJob` | Отложенный запуск |
| `ArchiveCompletedAgreementsJob` | Архивация (2 недели) |
| `CheckAgreementCompletionJob` | Проверка завершения |

## Artisan commands

```bash
php artisan agreements:publish-scheduled
php artisan agreements:archive-completed
php artisan agreements:check-completion
```

## Policies

- `AgreementPolicy` — create, edit, delete
- `SectionPolicy` — edit sections
- `VotePolicy` — who can vote

## Frontend stores (Pinia)

| Store | Ответственность |
|-------|-----------------|
| `authStore` | user, token, login/logout/register |
| `agreementStore` | CRUD, publish, duplicate |
| `sectionStore` | CRUD sections, participants |
| `voteStore` | submitVote, results |
| `filterStore` | filters, save filter groups |
| `notificationStore` | list, mark read |
| `signatureStore` | signatures, activate |

## Типы информационных блоков

| type | Описание |
|------|----------|
| `text` | Форматированный текст |
| `links` | Ссылки |
| `files` | Файлы (до 50 МБ) |
| `gallery` | Картинки |
| `code` | Код |

Блок согласования — не information_block, всегда последний элемент раздела (отдельная логика голосования).

## Группы согласований (системные по умолчанию)

1. Входящие
2. Исходящие
3. Запущенные
4. Все (кроме корзины)
5. Избранное
6. Корзина
7. Завершённые
