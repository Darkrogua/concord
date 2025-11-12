# План реализации - Backend (Laravel)

## Структура API

### Аутентификация (Sanctum)

**Endpoints:**
- `POST /api/register` - регистрация
- `POST /api/login` - вход
- `POST /api/logout` - выход
- `POST /api/forgot-password` - восстановление пароля
- `POST /api/reset-password` - сброс пароля
- `GET /api/user` - текущий пользователь

**Модели:**
- User
- PasswordResetToken (для восстановления)

### Мульти-подпись

**Endpoints:**
- `GET /api/signatures` - список подписей пользователя
- `POST /api/signatures` - создание подписи
- `PUT /api/signatures/{id}` - обновление подписи
- `DELETE /api/signatures/{id}` - удаление подписи
- `POST /api/signatures/{id}/activate` - активация подписи

**Модели:**
- Signature

### Проекты

**Endpoints:**
- `GET /api/projects` - список проектов пользователя
- `POST /api/projects` - создание проекта
- `PUT /api/projects/{id}` - обновление проекта
- `DELETE /api/projects/{id}` - удаление проекта

**Модели:**
- Project

### Согласования

**Endpoints:**
- `GET /api/agreements` - список согласований (с фильтрацией, поиском, сортировкой)
- `GET /api/agreements/{id}` - детали согласования
- `POST /api/agreements` - создание согласования
- `PUT /api/agreements/{id}` - обновление согласования (только до запуска)
- `DELETE /api/agreements/{id}` - удаление согласования (мягкое)
- `POST /api/agreements/{id}/publish` - запуск согласования
- `POST /api/agreements/{id}/duplicate` - дублирование согласования
- `POST /api/agreements/{id}/archive` - архивация
- `POST /api/agreements/{id}/restore` - восстановление из корзины

**Модели:**
- Agreement

**Сервисы:**
- AgreementService - бизнес-логика согласований

### Разделы

**Endpoints:**
- `GET /api/agreements/{agreementId}/sections` - список разделов
- `GET /api/sections/{id}` - детали раздела
- `POST /api/agreements/{agreementId}/sections` - создание раздела
- `PUT /api/sections/{id}` - обновление раздела (с сбросом голосов при активном согласовании)
- `DELETE /api/sections/{id}` - удаление раздела
- `POST /api/sections/{id}/duplicate` - дублирование раздела

**Модели:**
- Section

**Сервисы:**
- SectionService - бизнес-логика разделов

### Участники разделов

**Endpoints:**
- `GET /api/sections/{id}/participants` - список участников
- `POST /api/sections/{id}/participants` - добавление участников
- `DELETE /api/sections/{id}/participants/{userId}` - удаление участника
- `POST /api/sections/{id}/participants/bulk` - массовое добавление (из группы)

**Модели:**
- SectionParticipant

### Информационные блоки

**Endpoints:**
- `GET /api/sections/{sectionId}/blocks` - список блоков
- `POST /api/sections/{sectionId}/blocks` - создание блока
- `PUT /api/blocks/{id}` - обновление блока
- `DELETE /api/blocks/{id}` - удаление блока

**Модели:**
- InformationBlock

### Файлы

**Endpoints:**
- `POST /api/blocks/{blockId}/files` - загрузка файла
- `GET /api/files/{id}` - скачивание файла
- `DELETE /api/files/{id}` - удаление файла

**Модели:**
- File

**Сервисы:**
- FileService - обработка файлов

### Голосование

**Endpoints:**
- `POST /api/sections/{id}/vote` - голосование
- `GET /api/sections/{id}/votes` - результаты голосования (если доступно)

**Модели:**
- Vote

**Сервисы:**
- VoteService - логика голосования и проверки завершения

### Группы пользователей

**Endpoints:**
- `GET /api/user-groups` - список групп
- `POST /api/user-groups` - создание группы
- `PUT /api/user-groups/{id}` - обновление группы
- `DELETE /api/user-groups/{id}` - удаление группы
- `GET /api/user-groups/{id}/members` - участники группы

**Модели:**
- UserGroup
- UserGroupMember

### Группы согласований

**Endpoints:**
- `GET /api/agreement-groups` - список групп
- `POST /api/agreement-groups` - создание группы
- `PUT /api/agreement-groups/{id}` - обновление группы (фильтры)
- `DELETE /api/agreement-groups/{id}` - удаление группы

**Модели:**
- AgreementGroup

### Избранное

**Endpoints:**
- `GET /api/favorites` - список избранных
- `POST /api/agreements/{id}/favorite` - добавить в избранное
- `DELETE /api/agreements/{id}/favorite` - убрать из избранного

**Модели:**
- Favorite

### Уведомления

**Endpoints:**
- `GET /api/notifications` - список уведомлений
- `PUT /api/notifications/{id}/read` - отметить как прочитанное
- `PUT /api/notifications/read-all` - отметить все как прочитанные

**Модели:**
- Notification

**Сервисы:**
- NotificationService - отправка уведомлений

### Поиск и фильтрация

**Endpoints:**
- `GET /api/agreements/search?q={query}` - поиск согласований
- `GET /api/agreements?filter={json}` - фильтрация согласований

**Сервисы:**
- SearchService - поиск
- FilterService - фильтрация

## Фоновые задачи (Jobs)

### Уведомления
- `SendAgreementNotificationJob` - отправка уведомления о новом согласовании
- `SendVoteNotificationJob` - уведомление о голосовании
- `SendDeadlineReminderJob` - напоминание о дедлайне
- `SendSectionUpdatedNotificationJob` - уведомление о корректировке раздела

### Автоматизация
- `PublishScheduledAgreementsJob` - запуск отложенных согласований
- `ArchiveCompletedAgreementsJob` - архивация завершенных согласований
- `CheckAgreementCompletionJob` - проверка завершения согласования

## Команды (Commands)

- `php artisan agreements:publish-scheduled` - запуск отложенных согласований
- `php artisan agreements:archive-completed` - архивация завершенных
- `php artisan agreements:check-completion` - проверка завершения

## Политики доступа (Policies)

- AgreementPolicy - кто может создавать, редактировать, удалять согласования
- SectionPolicy - кто может редактировать разделы
- VotePolicy - кто может голосовать

## Валидация

- Request классы для валидации всех endpoints
- Custom validation rules для бизнес-логики

## Логирование

- ActivityLogService - логирование всех действий
- Middleware для автоматического логирования

