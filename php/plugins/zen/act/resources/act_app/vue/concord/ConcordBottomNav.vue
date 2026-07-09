<template>
  <nav class="concord-nav" aria-label="Навигация">
    <div class="concord-nav__divider" aria-hidden="true" />
    <button
      type="button"
      class="concord-nav__item concord-nav__item--active"
      aria-current="page"
    >
      <span class="concord-nav__icon-wrap">
        <svg class="concord-nav__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path
            d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5z"
            stroke="currentColor"
            stroke-width="1.6"
            stroke-linejoin="round"
          />
        </svg>
        <span v-if="projectsBadge" class="concord-nav__badge">{{ projectsBadge }}</span>
      </span>
      <span class="concord-nav__label">Все проекты</span>
    </button>

    <button type="button" class="concord-nav__item" @click="$emit('settings')">
      <span class="concord-nav__icon-wrap">
        <svg class="concord-nav__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.5" />
          <path
            d="M12 3.5v2M12 18.5v2M5.2 5.2l1.4 1.4M17.4 17.4l1.4 1.4M3.5 12h2M18.5 12h2M5.2 18.8l1.4-1.4M17.4 6.6l1.4-1.4"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
          />
          <path
            d="M12 6.5a5.5 5.5 0 1 1 0 11 5.5 5.5 0 0 1 0-11z"
            stroke="currentColor"
            stroke-width="1.2"
            stroke-dasharray="2.5 3.5"
            opacity="0.55"
          />
        </svg>
      </span>
      <span class="concord-nav__label">Настройки</span>
    </button>

    <button type="button" class="concord-nav__item" @click="$emit('create')">
      <span class="concord-nav__icon-wrap">
        <svg class="concord-nav__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M12 6v12M6 12h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
        </svg>
      </span>
      <span class="concord-nav__label">Новый проект</span>
    </button>

    <button type="button" class="concord-nav__item" @click="$emit('notifications')">
      <span class="concord-nav__icon-wrap">
        <svg class="concord-nav__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path
            d="M12 3.5c2.6 0 4.5 1.8 4.5 4.5v3.2l1.3 2.2a.8.8 0 0 1-.7 1.2H6.9a.8.8 0 0 1-.7-1.2l1.3-2.2V8c0-2.7 1.9-4.5 4.5-4.5z"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linejoin="round"
          />
          <path d="M10 18.5a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
        </svg>
        <span v-if="notificationsBadge" class="concord-nav__badge">{{ notificationsBadge }}</span>
      </span>
      <span class="concord-nav__label">Уведомления</span>
    </button>

    <button
      type="button"
      class="concord-nav__item concord-nav__item--profile"
      aria-label="Сменить аккаунт"
      @click="$emit('switch-account')"
    >
      <span class="concord-nav__icon-wrap concord-nav__icon-wrap--profile">
        <img v-if="avatarUrl" :src="avatarUrl" alt="" class="concord-nav__avatar">
        <span v-else class="concord-nav__avatar concord-nav__avatar--placeholder" aria-hidden="true">
          {{ avatarInitial }}
        </span>
        <svg class="concord-nav__caret" viewBox="0 0 12 8" fill="none" aria-hidden="true">
          <path d="M1 1.5 6 6.5 11 1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </span>
    </button>
  </nav>
</template>

<script>
export default {
  name: 'ConcordBottomNav',
  props: {
    projectsBadge: {
      type: [Number, String],
      default: 2,
    },
    notificationsBadge: {
      type: [Number, String],
      default: 2,
    },
    avatarUrl: {
      type: String,
      default: '',
    },
    avatarInitial: {
      type: String,
      default: 'А',
    },
  },
  emits: ['create', 'settings', 'notifications', 'switch-account'],
}
</script>
