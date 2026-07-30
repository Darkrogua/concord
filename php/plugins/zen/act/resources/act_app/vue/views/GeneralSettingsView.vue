<template>
  <div class="concord-page concord-page--notifications">
    <header class="concord-notifications__profile">
      <button
        type="button"
        class="concord-notifications__avatar-btn"
        aria-label="Изменить фото профиля"
        @click="openPhotoSheet"
      >
        <img
          v-if="profile.avatarUrl"
          :src="profile.avatarUrl"
          alt=""
          class="concord-notifications__avatar concord-notifications__avatar--image"
        >
        <span v-else class="concord-notifications__avatar" aria-hidden="true">{{ profile.avatarInitial }}</span>
      </button>
      <h1 class="concord-notifications__name">{{ fullName }}</h1>
    </header>

    <main class="concord-notifications">
      <section class="concord-accordion concord-accordion--static">
        <h2 class="concord-accordion__title">Аккаунт</h2>

        <div class="concord-accordion__body concord-accordion__body--open">
          <button type="button" class="concord-notifications__row concord-notifications__row--photo" @click="openPhotoSheet">
            <span class="concord-notifications__row-label">Фото</span>
            <span class="concord-notifications__photo-action">
              <svg class="concord-notifications__photo-action-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
                <path
                  d="M5 8.5A2.5 2.5 0 0 1 7.5 6H9l1.2-2h3.6L15 6h1.5A2.5 2.5 0 0 1 19 8.5v9A2.5 2.5 0 0 1 16.5 20h-9A2.5 2.5 0 0 1 5 17.5v-9Z"
                  stroke="currentColor"
                  stroke-width="1.6"
                  stroke-linejoin="round"
                />
                <circle cx="12" cy="13" r="3.2" stroke="currentColor" stroke-width="1.6"/>
              </svg>
              <span class="concord-notifications__photo-action-text">{{ photoActionLabel }}</span>
            </span>
          </button>

          <input
            ref="cameraInputRef"
            type="file"
            accept="image/*"
            capture="environment"
            class="concord-notifications__file-input"
            @change="onCameraSelected"
          >
          <input
            ref="galleryInputRef"
            type="file"
            accept="image/*"
            class="concord-notifications__file-input"
            @change="onGallerySelected"
          >

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

      <section v-if="canShowInstall" class="concord-accordion">
        <button
          type="button"
          class="concord-accordion__header concord-accordion__header--nav"
          @click="installApp"
        >
          <span>Установить приложение</span>
        </button>
      </section>
    </main>

    <template v-if="showInstallHint">
      <div class="concord-sheet-backdrop" @click="dismissInstallHint" />
      <div class="concord-sheet" role="dialog" aria-labelledby="concord-pwa-install-title">
        <div class="concord-sheet__handle" aria-hidden="true" />
        <h2 id="concord-pwa-install-title" class="concord-sheet__title">{{ installHintTitle }}</h2>
        <p class="concord-confirm-sheet__message">{{ installHintText }}</p>
        <div class="concord-create-sheet__actions">
          <button
            type="button"
            class="concord-create-sheet__btn concord-create-sheet__btn--save"
            @click="dismissInstallHint"
          >
            Понятно
          </button>
        </div>
      </div>
    </template>

    <ConcordConfirmSheet
      :open="accountDeleteConfirmOpen"
      title="Удалить аккаунт?"
      message="Аккаунт будет удалён без возможности восстановления."
      confirm-label="Удалить"
      cancel-label="Отмена"
      @confirm="confirmDeleteAccount"
      @cancel="cancelDeleteAccount"
    />

    <ConcordAvatarPhotoSheet
      :open="photoSheetOpen"
      :has-avatar="Boolean(profile.avatarUrl)"
      @close="closePhotoSheet"
      @take-photo="openCamera"
      @choose-gallery="openGallery"
      @remove-photo="removeAvatar"
    />

    <ConcordAvatarCropSheet
      :open="cropSheetOpen"
      :image-src="pendingImageSrc"
      @close="closeCrop"
      @save="saveCroppedAvatar"
    />
  </div>
</template>

<script>
import { computed, ref, toRef } from 'vue'
import ConcordGroupDeleteIcon from '../concord/ConcordGroupDeleteIcon.vue'
import ConcordConfirmSheet from '../concord/ConcordConfirmSheet.vue'
import ConcordAvatarPhotoSheet from '../concord/ConcordAvatarPhotoSheet.vue'
import ConcordAvatarCropSheet from '../concord/ConcordAvatarCropSheet.vue'
import { usePwaInstall } from '../pwa-install.js'
import { useConcordProfile } from '../composables/useConcordProfile.js'
import { useAvatarPhotoFlow } from '../composables/useAvatarPhotoFlow.js'

export default {
  name: 'GeneralSettingsView',
  components: {
    ConcordGroupDeleteIcon,
    ConcordConfirmSheet,
    ConcordAvatarPhotoSheet,
    ConcordAvatarCropSheet,
  },
  props: {
    accountId: {
      type: String,
      default: '1',
    },
  },
  emits: ['delete-account', 'open-notification-settings', 'open-groups'],
  setup(props, { emit }) {
    const accountDeleteConfirmOpen = ref(false)
    const { profile, setAvatar, removeAvatar: clearAvatar } = useConcordProfile(toRef(props, 'accountId'))
    const {
      canShowInstall,
      install: installApp,
      showInstallHint,
      installHintTitle,
      installHintText,
      dismissInstallHint,
    } = usePwaInstall()

    const {
      photoSheetOpen,
      cropSheetOpen,
      pendingImageSrc,
      cameraInputRef,
      galleryInputRef,
      openPhotoSheet,
      closePhotoSheet,
      openCamera,
      openGallery,
      onCameraSelected,
      onGallerySelected,
      closeCrop,
      saveCroppedAvatar,
      removeAvatar,
    } = useAvatarPhotoFlow({
      onSave: setAvatar,
      onRemove: clearAvatar,
    })

    const fullName = computed(() => `${profile.value.firstName} ${profile.value.lastName}`.trim())

    const photoActionLabel = computed(() => (
      profile.value.avatarUrl ? 'Заменить фотографию' : 'Добавить фото'
    ))

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

    return {
      profile,
      cameraInputRef,
      galleryInputRef,
      fullName,
      photoActionLabel,
      accountDeleteConfirmOpen,
      canShowInstall,
      installApp,
      showInstallHint,
      installHintTitle,
      installHintText,
      dismissInstallHint,
      askDeleteAccount,
      cancelDeleteAccount,
      confirmDeleteAccount,
      photoSheetOpen,
      cropSheetOpen,
      pendingImageSrc,
      openPhotoSheet,
      closePhotoSheet,
      openCamera,
      openGallery,
      onCameraSelected,
      onGallerySelected,
      closeCrop,
      saveCroppedAvatar,
      removeAvatar,
    }
  },
}
</script>
