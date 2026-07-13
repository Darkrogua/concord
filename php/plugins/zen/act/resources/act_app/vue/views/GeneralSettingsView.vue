<template>
  <div class="concord-page concord-page--notifications">
    <header class="concord-notifications__profile">
      <img
        v-if="profile.avatarUrl"
        :src="profile.avatarUrl"
        alt=""
        class="concord-notifications__avatar concord-notifications__avatar--image"
      >
      <span v-else class="concord-notifications__avatar" aria-hidden="true">{{ profile.avatarInitial }}</span>
      <h1 class="concord-notifications__name">{{ fullName }}</h1>
    </header>

    <main class="concord-notifications">
      <section class="concord-accordion">
        <button
          type="button"
          class="concord-accordion__header"
          :aria-expanded="expanded.account"
          @click="toggleSection('account')"
        >
          <span>Аккаунт</span>
          <svg
            class="concord-accordion__chevron"
            :class="{ 'concord-accordion__chevron--open': expanded.account }"
            viewBox="0 0 24 24"
            width="20"
            height="20"
            fill="none"
            aria-hidden="true"
          >
            <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>

        <div v-if="expanded.account" class="concord-accordion__body">
          <div class="concord-notifications__row">
            <span class="concord-notifications__row-label">фото</span>
            <input
              ref="avatarInputRef"
              type="file"
              accept="image/*"
              class="concord-notifications__file-input"
              @change="onAvatarSelected"
            >
            <button
              type="button"
              class="concord-notifications__photo-btn"
              aria-label="Добавить фото"
              @click="openAvatarPicker"
            >
              <svg viewBox="0 0 24 24" width="24" height="24" fill="none" aria-hidden="true">
                <path
                  d="M5 8.5A2.5 2.5 0 0 1 7.5 6H9l1.2-2h3.6L15 6h1.5A2.5 2.5 0 0 1 19 8.5v9A2.5 2.5 0 0 1 16.5 20h-9A2.5 2.5 0 0 1 5 17.5v-9Z"
                  stroke="currentColor"
                  stroke-width="1.6"
                  stroke-linejoin="round"
                />
                <circle cx="12" cy="13" r="3.2" stroke="currentColor" stroke-width="1.6"/>
                <circle cx="17.5" cy="7.5" r="3.8" fill="currentColor"/>
                <path d="M17.5 5.8v3.4M15.8 7.5h3.4" stroke="#fff" stroke-width="1.3" stroke-linecap="round"/>
              </svg>
            </button>
          </div>

          <label class="concord-notifications__row concord-notifications__row--field">
            <span class="concord-notifications__row-label">имя</span>
            <input v-model="profile.firstName" type="text" class="concord-notifications__input">
          </label>

          <label class="concord-notifications__row concord-notifications__row--field">
            <span class="concord-notifications__row-label">фамилия</span>
            <input v-model="profile.lastName" type="text" class="concord-notifications__input">
          </label>

          <label class="concord-notifications__row concord-notifications__row--field">
            <span class="concord-notifications__row-label">телефон</span>
            <input v-model="profile.phone" type="tel" class="concord-notifications__input">
          </label>

          <label class="concord-notifications__row concord-notifications__row--field">
            <span class="concord-notifications__row-label">дата рождения</span>
            <input
              v-model="profile.birthDate"
              type="text"
              class="concord-notifications__input"
              placeholder="Ведите текст"
            >
          </label>

          <div class="concord-notifications__row">
            <span class="concord-notifications__row-label">удаление</span>
            <button type="button" class="concord-notifications__group-action-btn" aria-label="Удалить аккаунт">
              <ConcordGroupDeleteIcon />
            </button>
          </div>
        </div>
      </section>

      <section class="concord-accordion">
        <button
          type="button"
          class="concord-accordion__header"
          :aria-expanded="expanded.notifications"
          @click="toggleSection('notifications')"
        >
          <span>Уведомления</span>
          <svg
            class="concord-accordion__chevron"
            :class="{ 'concord-accordion__chevron--open': expanded.notifications }"
            viewBox="0 0 24 24"
            width="20"
            height="20"
            fill="none"
            aria-hidden="true"
          >
            <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>

        <div v-if="expanded.notifications" class="concord-accordion__body">
          <label class="concord-notifications__toggle-row">
            <span>общие чаты</span>
            <input v-model="notificationSettings.generalChats" type="checkbox" class="concord-toggle">
          </label>

          <label class="concord-notifications__toggle-row">
            <span>личные чаты</span>
            <input v-model="notificationSettings.personalChats" type="checkbox" class="concord-toggle">
          </label>

          <label class="concord-notifications__toggle-row">
            <span>группы</span>
            <input v-model="notificationSettings.groups" type="checkbox" class="concord-toggle">
          </label>
        </div>
      </section>

      <section class="concord-accordion">
        <button
          type="button"
          class="concord-accordion__header"
          :aria-expanded="expanded.groups"
          @click="toggleSection('groups')"
        >
          <span>Группы</span>
          <svg
            class="concord-accordion__chevron"
            :class="{ 'concord-accordion__chevron--open': expanded.groups }"
            viewBox="0 0 24 24"
            width="20"
            height="20"
            fill="none"
            aria-hidden="true"
          >
            <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>

        <div v-if="expanded.groups" class="concord-accordion__body">
          <div
            v-for="group in groups"
            :key="group.id"
            class="concord-notifications__group-row"
          >
            <span class="concord-notifications__group-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none">
                <circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="1.6"/>
                <path d="M6 19.5c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
              </svg>
            </span>
            <span class="concord-notifications__group-title">{{ group.title }}</span>
            <div class="concord-notifications__group-actions">
              <button type="button" class="concord-notifications__group-action-btn" aria-label="Редактировать группу">
                <ConcordGroupEditIcon />
              </button>
              <button type="button" class="concord-notifications__group-action-btn" aria-label="Удалить группу">
                <ConcordGroupDeleteIcon />
              </button>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>

<script>
import { computed, reactive, ref } from 'vue'
import {
  DEFAULT_NOTIFICATION_SETTINGS,
  DEFAULT_PROFILE,
  DEFAULT_PROFILE_GROUPS,
} from '../concord/mock-notifications.js'
import ConcordGroupDeleteIcon from '../concord/ConcordGroupDeleteIcon.vue'
import ConcordGroupEditIcon from '../concord/ConcordGroupEditIcon.vue'

export default {
  name: 'GeneralSettingsView',
  components: { ConcordGroupEditIcon, ConcordGroupDeleteIcon },
  setup() {
    const profile = ref({ ...DEFAULT_PROFILE })
    const notificationSettings = ref({ ...DEFAULT_NOTIFICATION_SETTINGS })
    const groups = ref(DEFAULT_PROFILE_GROUPS.map((group) => ({ ...group })))
    const avatarInputRef = ref(null)
    const expanded = reactive({
      account: false,
      notifications: false,
      groups: false,
    })

    const fullName = computed(() => `${profile.value.firstName} ${profile.value.lastName}`.trim())

    function toggleSection(section) {
      expanded[section] = !expanded[section]
    }

    function openAvatarPicker() {
      avatarInputRef.value?.click()
    }

    function onAvatarSelected(event) {
      const file = event.target.files?.[0]
      if (!file) {
        return
      }
      const reader = new FileReader()
      reader.onload = () => {
        if (typeof reader.result === 'string') {
          profile.value.avatarUrl = reader.result
        }
      }
      reader.readAsDataURL(file)
      event.target.value = ''
    }

    return {
      profile,
      notificationSettings,
      groups,
      avatarInputRef,
      expanded,
      fullName,
      toggleSection,
      openAvatarPicker,
      onAvatarSelected,
    }
  },
}
</script>
