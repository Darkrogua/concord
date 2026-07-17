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
      <section class="concord-accordion concord-accordion--static">
        <h2 class="concord-accordion__title">Аккаунт</h2>

        <div class="concord-accordion__body concord-accordion__body--open">
          <div class="concord-notifications__row">
            <span class="concord-notifications__row-label">Фото</span>
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
            <span class="concord-notifications__row-label">Имя</span>
            <input v-model="profile.firstName" type="text" class="concord-notifications__input">
          </label>

          <label class="concord-notifications__row concord-notifications__row--field">
            <span class="concord-notifications__row-label">Фамилия</span>
            <input v-model="profile.lastName" type="text" class="concord-notifications__input">
          </label>

          <label class="concord-notifications__row concord-notifications__row--field">
            <span class="concord-notifications__row-label">Телефон</span>
            <input v-model="profile.phone" type="tel" class="concord-notifications__input">
          </label>

          <label class="concord-notifications__row concord-notifications__row--field">
            <span class="concord-notifications__row-label">Дата рождения</span>
            <input
              v-model="profile.birthDate"
              type="text"
              class="concord-notifications__input"
              placeholder="Ведите текст"
            >
          </label>

          <div class="concord-notifications__row">
            <span class="concord-notifications__row-label">Удаление</span>
            <button
              type="button"
              class="concord-notifications__group-action-btn"
              aria-label="Удалить аккаунт"
              @click="askDeleteAccount"
            >
              <ConcordGroupDeleteIcon />
            </button>
          </div>
        </div>
      </section>

      <section class="concord-accordion">
        <button
          type="button"
          class="concord-accordion__header concord-accordion__header--nav"
          @click="$emit('open-notification-settings')"
        >
          <span>Уведомления</span>
        </button>
      </section>

      <section class="concord-accordion">
        <button
          type="button"
          class="concord-accordion__header concord-accordion__header--nav"
          @click="$emit('open-groups')"
        >
          <span>Группы</span>
        </button>
      </section>
    </main>

    <ConcordConfirmSheet
      :open="accountDeleteConfirmOpen"
      title="Удалить аккаунт?"
      message="Аккаунт будет удалён без возможности восстановления."
      confirm-label="Удалить"
      cancel-label="Отмена"
      @confirm="confirmDeleteAccount"
      @cancel="cancelDeleteAccount"
    />
  </div>
</template>

<script>
import { computed, ref } from 'vue'
import { DEFAULT_PROFILE } from '../concord/mock-notifications.js'
import ConcordGroupDeleteIcon from '../concord/ConcordGroupDeleteIcon.vue'
import ConcordConfirmSheet from '../concord/ConcordConfirmSheet.vue'

export default {
  name: 'GeneralSettingsView',
  components: { ConcordGroupDeleteIcon, ConcordConfirmSheet },
  emits: ['delete-account', 'open-notification-settings', 'open-groups'],
  setup(props, { emit }) {
    const profile = ref({ ...DEFAULT_PROFILE })
    const avatarInputRef = ref(null)
    const accountDeleteConfirmOpen = ref(false)

    const fullName = computed(() => `${profile.value.firstName} ${profile.value.lastName}`.trim())

    function askDeleteAccount() {
      accountDeleteConfirmOpen.value = true
    }

    function cancelDeleteAccount() {
      accountDeleteConfirmOpen.value = false
    }

    function confirmDeleteAccount() {
      accountDeleteConfirmOpen.value = false
      emit('delete-account')
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
      avatarInputRef,
      fullName,
      accountDeleteConfirmOpen,
      askDeleteAccount,
      cancelDeleteAccount,
      confirmDeleteAccount,
      openAvatarPicker,
      onAvatarSelected,
    }
  },
}
</script>
