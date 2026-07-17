<template>
  <nav class="concord-nav" aria-label="Навигация">
    <div class="concord-nav__divider" aria-hidden="true" />
    <button
      type="button"
      :class="['concord-nav__item', { 'concord-nav__item--active': active === 'list' }]"
      :aria-current="active === 'list' ? 'page' : undefined"
      @click="$emit('navigate', 'list')"
    >
      <span class="concord-nav__icon-wrap">
        <svg class="concord-nav__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path
            d="M4 6.5h16M4 12h16M4 17.5h10"
            stroke="currentColor"
            stroke-width="1.6"
            stroke-linecap="round"
          />
        </svg>
        <span v-if="agreementsBadge" class="concord-nav__badge">{{ agreementsBadge }}</span>
      </span>
      <span class="concord-nav__label">Согласования</span>
    </button>

    <button
      type="button"
      :class="['concord-nav__item', { 'concord-nav__item--active': active === 'settings' }]"
      :aria-current="active === 'settings' ? 'page' : undefined"
      @click="$emit('navigate', 'settings')"
    >
      <span class="concord-nav__icon-wrap">
        <ConcordGearIcon :size="22" />
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

    <button
      type="button"
      :class="['concord-nav__item', { 'concord-nav__item--active': active === 'notifications' }]"
      :aria-current="active === 'notifications' ? 'page' : undefined"
      @click="$emit('notifications')"
    >
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
      <span class="concord-nav__label">{{ profileLabel }}</span>
    </button>
  </nav>
</template>

<script>
import ConcordGearIcon from './ConcordGearIcon.vue'

export default {
  name: 'ConcordBottomNav',
  components: { ConcordGearIcon },
  props: {
    active: {
      type: String,
      default: 'list',
    },
    agreementsBadge: {
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
    profileLabel: {
      type: String,
      default: 'Профиль',
    },
  },
  emits: ['navigate', 'create', 'notifications', 'switch-account'],
}
</script>
