<template>
  <div class="concord-page concord-page--notifications">
    <header class="concord-notifications__profile concord-notifications__profile--subpage">
      <button type="button" class="concord-notifications__back" aria-label="Назад" @click="$emit('back')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>

      <div class="concord-notifications__profile-main">
        <img
          v-if="profile.avatarUrl"
          :src="profile.avatarUrl"
          alt=""
          class="concord-notifications__avatar concord-notifications__avatar--image"
        >
        <span v-else class="concord-notifications__avatar" aria-hidden="true">{{ profile.avatarInitial }}</span>
        <h1 class="concord-notifications__name">{{ fullName }}</h1>
      </div>
    </header>

    <main class="concord-notifications">
      <section class="concord-accordion concord-accordion--static">
        <h2 class="concord-accordion__title">Уведомления</h2>

        <div class="concord-accordion__body concord-accordion__body--open">
          <label class="concord-notifications__toggle-row">
            <span>Общие чаты</span>
            <input v-model="notificationSettings.generalChats" type="checkbox" class="concord-toggle">
          </label>

          <label class="concord-notifications__toggle-row">
            <span>Личные чаты</span>
            <input v-model="notificationSettings.personalChats" type="checkbox" class="concord-toggle">
          </label>

          <label class="concord-notifications__toggle-row">
            <span>Группы</span>
            <input v-model="notificationSettings.groups" type="checkbox" class="concord-toggle">
          </label>
        </div>
      </section>
    </main>
  </div>
</template>

<script>
import { computed, ref } from 'vue'
import { DEFAULT_NOTIFICATION_SETTINGS, DEFAULT_PROFILE } from '../concord/mock-notifications.js'

export default {
  name: 'NotificationSettingsView',
  emits: ['back'],
  setup() {
    const profile = ref({ ...DEFAULT_PROFILE })
    const notificationSettings = ref({ ...DEFAULT_NOTIFICATION_SETTINGS })
    const fullName = computed(() => `${profile.value.firstName} ${profile.value.lastName}`.trim())

    return { profile, notificationSettings, fullName }
  },
}
</script>
