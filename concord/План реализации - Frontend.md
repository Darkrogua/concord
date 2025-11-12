# План реализации - Frontend (Vue.js 3)

## Структура проекта

```
frontend/
├── src/
│   ├── components/        # Переиспользуемые компоненты
│   │   ├── blocks/        # Компоненты информационных блоков
│   │   ├── common/        # Общие компоненты
│   │   └── ui/            # UI компоненты
│   ├── views/             # Страницы
│   ├── stores/            # Pinia stores
│   ├── services/          # API сервисы
│   ├── router/            # Vue Router
│   ├── composables/       # Composables
│   ├── utils/             # Утилиты
│   └── App.vue
├── public/
└── package.json
```

## Компоненты

### Общие компоненты

- `AppHeader.vue` - шапка приложения
- `AppFooter.vue` - нижняя навигационная панель
- `AppSidebar.vue` - боковое меню (если нужно)
- `LoadingSpinner.vue` - индикатор загрузки
- `EmptyState.vue` - пустое состояние
- `ConfirmDialog.vue` - диалог подтверждения

### Компоненты информационных блоков

- `TextBlock.vue` - текстовый блок
- `GalleryBlock.vue` - галерея
- `FilesBlock.vue` - блок с файлами
- `LinksBlock.vue` - блок со ссылками
- `CodeBlock.vue` - блок с кодом

### Компоненты согласований

- `AgreementCard.vue` - карточка согласования в списке
- `AgreementList.vue` - список согласований
- `AgreementFilters.vue` - фильтры согласований
- `AgreementForm.vue` - форма создания/редактирования
- `SectionCard.vue` - карточка раздела
- `SectionList.vue` - список разделов
- `VoteBlock.vue` - блок согласования (голосование)
- `VoteResults.vue` - результаты голосования

## Страницы (Views)

### Аутентификация
- `Login.vue` - вход
- `Register.vue` - регистрация
- `ForgotPassword.vue` - восстановление пароля
- `ResetPassword.vue` - сброс пароля

### Главная
- `Dashboard.vue` - главная страница со списком согласований
- `AgreementList.vue` - список согласований (с фильтрами, поиском, сортировкой)
- `AgreementDetail.vue` - детали согласования
- `AgreementCreate.vue` - создание согласования
- `AgreementEdit.vue` - редактирование согласования

### Настройки
- `Settings.vue` - настройки групп фильтров
- `FilterGroupEdit.vue` - редактирование группы фильтров
- `FilterGroupCreate.vue` - создание группы фильтров
- `UserGroups.vue` - управление группами пользователей
- `UserGroupEdit.vue` - редактирование группы пользователей
- `Profile.vue` - профиль пользователя
- `Signatures.vue` - управление подписями

### Уведомления
- `Notifications.vue` - список уведомлений

## Stores (Pinia)

### authStore
- user, token
- login, logout, register
- checkAuth

### agreementStore
- agreements, currentAgreement
- fetchAgreements, fetchAgreement
- createAgreement, updateAgreement, deleteAgreement
- publishAgreement, duplicateAgreement

### sectionStore
- sections, currentSection
- fetchSections, fetchSection
- createSection, updateSection, deleteSection
- duplicateSection

### voteStore
- votes, voteResults
- submitVote, fetchVotes

### filterStore
- filters, activeFilters
- applyFilter, clearFilter
- saveFilterGroup

### notificationStore
- notifications, unreadCount
- fetchNotifications, markAsRead

### signatureStore
- signatures, activeSignature
- fetchSignatures, createSignature, activateSignature

## Сервисы (API)

### api.js
- Базовый axios instance
- Interceptors для токенов и ошибок

### authService.js
- register, login, logout
- forgotPassword, resetPassword
- getCurrentUser

### agreementService.js
- getAgreements, getAgreement
- createAgreement, updateAgreement, deleteAgreement
- publishAgreement, duplicateAgreement
- searchAgreements, filterAgreements

### sectionService.js
- getSections, getSection
- createSection, updateSection, deleteSection
- duplicateSection
- addParticipants, removeParticipant

### voteService.js
- submitVote, getVotes

### fileService.js
- uploadFile, downloadFile, deleteFile

### notificationService.js
- getNotifications, markAsRead

## Роутинг

```javascript
routes: [
  { path: '/login', component: Login },
  { path: '/register', component: Register },
  { path: '/', component: Dashboard },
  { path: '/agreements', component: AgreementList },
  { path: '/agreements/create', component: AgreementCreate },
  { path: '/agreements/:id', component: AgreementDetail },
  { path: '/agreements/:id/edit', component: AgreementEdit },
  { path: '/settings', component: Settings },
  { path: '/notifications', component: Notifications },
  { path: '/profile', component: Profile },
]
```

## Composables

### useAgreement.js
- Логика работы с согласованиями
- Фильтрация, поиск, сортировка

### useVote.js
- Логика голосования
- Проверка прав на голосование

### useFileUpload.js
- Загрузка файлов
- Валидация размера и типа

### useNotifications.js
- Работа с уведомлениями
- WebSocket для real-time обновлений

## Утилиты

### formatters.js
- Форматирование дат
- Форматирование размеров файлов

### validators.js
- Валидация форм
- Валидация файлов

### constants.js
- Константы статусов
- Константы типов блоков

## Стили

- Использовать CSS переменные для темизации
- Поддержка темной темы (v2, но подготовить структуру)
- Адаптивный дизайн (mobile-first)

## Интеграция с API

- Все запросы через axios
- Обработка ошибок через interceptors
- Loading states для всех асинхронных операций
- Оптимистичные обновления где возможно

