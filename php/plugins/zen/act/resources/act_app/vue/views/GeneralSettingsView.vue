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
          <button
            v-for="group in groups"
            :key="group.id"
            type="button"
            class="concord-notifications__group-row concord-notifications__group-row--clickable"
            @click="$emit('edit-group', group.id)"
          >
            <span class="concord-notifications__group-icon-wrap" aria-hidden="true">
              <span class="concord-notifications__group-icon">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none">
                  <circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="1.6"/>
                  <path d="M6 19.5c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
              </span>
              <span class="concord-notifications__group-badge">{{ group.memberCount }}</span>
            </span>
            <span class="concord-notifications__group-title">{{ group.title }}</span>
            <div class="concord-notifications__group-actions">
              <button
                type="button"
                class="concord-notifications__group-action-btn"
                aria-label="Удалить группу"
                @click.stop="askDeleteGroup(group)"
              >
                <ConcordGroupDeleteIcon />
              </button>
            </div>
          </button>

          <button
            type="button"
            class="concord-settings__edit concord-notifications__group-add"
            @click="$emit('create-group')"
          >
            <span class="concord-settings__edit-plus" aria-hidden="true">+</span>
            Создать группу
          </button>
        </div>
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

    <ConcordConfirmSheet
      :open="Boolean(pendingDeleteGroup)"
      title="Удалить группу?"
      :message="deleteConfirmMessage"
      confirm-label="Удалить"
      cancel-label="Отмена"
      @confirm="confirmDeleteGroup"
      @cancel="cancelDeleteGroup"
    />
  </div>
</template>

<script>
import { computed, reactive, ref, toRef } from 'vue'
import {
  DEFAULT_NOTIFICATION_SETTINGS,
  DEFAULT_PROFILE,
} from '../concord/mock-notifications.js'
import ConcordGroupDeleteIcon from '../concord/ConcordGroupDeleteIcon.vue'
import ConcordConfirmSheet from '../concord/ConcordConfirmSheet.vue'

export default {
  name: 'GeneralSettingsView',
  components: { ConcordGroupDeleteIcon, ConcordConfirmSheet },
  props: {
    groups: {
      type: Array,
      required: true,
    },
  },
  emits: ['edit-group', 'delete-group', 'delete-account', 'create-group'],
  setup(props, { emit }) {
    const profile = ref({ ...DEFAULT_PROFILE })
    const notificationSettings = ref({ ...DEFAULT_NOTIFICATION_SETTINGS })
    const avatarInputRef = ref(null)
    const pendingDeleteGroup = ref(null)
    const accountDeleteConfirmOpen = ref(false)
    const expanded = reactive({
      account: false,
      notifications: false,
      groups: false,
    })

    const fullName = computed(() => `${profile.value.firstName} ${profile.value.lastName}`.trim())
    const deleteConfirmMessage = computed(() => {
      if (!pendingDeleteGroup.value) {
        return ''
      }
      return `Группа «${pendingDeleteGroup.value.title}» будет удалена без возможности восстановления.`
    })

    function toggleSection(section) {
      expanded[section] = !expanded[section]
    }

    function askDeleteGroup(group) {
      pendingDeleteGroup.value = group
    }

    function cancelDeleteGroup() {
      pendingDeleteGroup.value = null
    }

    function confirmDeleteGroup() {
      if (!pendingDeleteGroup.value) {
        return
      }
      emit('delete-group', pendingDeleteGroup.value.id)
      pendingDeleteGroup.value = null
    }

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
      notificationSettings,
      groups: toRef(props, 'groups'),
      avatarInputRef,
      expanded,
      fullName,
      pendingDeleteGroup,
      accountDeleteConfirmOpen,
      deleteConfirmMessage,
      toggleSection,
      askDeleteGroup,
      cancelDeleteGroup,
      confirmDeleteGroup,
      askDeleteAccount,
      cancelDeleteAccount,
      confirmDeleteAccount,
      openAvatarPicker,
      onAvatarSelected,
    }
  },
}
</script>
