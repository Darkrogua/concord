<template>
  <div class="concord-page concord-page--notifications">
    <ConcordPageHeader title="Настройки" />

    <main class="concord-notifications">
      <ConcordProfileHero
        :name="fullName"
        :handle="profile.login"
        :avatar-url="profile.avatarUrl"
        :avatar-initial="profile.avatarInitial"
        @avatar-click="openPhotoSheet"
      />
      <section class="concord-settings-group">
        <h2 class="concord-settings-group__title">Аккаунт</h2>
        <div class="concord-profile-card">
          <ConcordProfileFieldRow
            label="Логин"
            :value="loginLabel"
            strong
          />
          <ConcordProfileFieldRow
            label="Имя"
            :value="fullName"
            interactive
            @click="openMenu('name')"
          />
          <ConcordProfileFieldRow
            label="Email"
            :value="profile.email"
            interactive
            @click="openMenu('email')"
          />
          <ConcordProfileFieldRow
            label="Телефон"
            :value="profile.phone"
            interactive
            @click="openMenu('phone')"
          />
          <ConcordProfileFieldRow
            label="Пароль"
            value="••••••"
            interactive
            @click="openMenu('password')"
          />
          <ConcordProfileFieldRow
            label="Часовой пояс"
            :value="timezoneLabel"
            interactive
            @click="openMenu('timezone')"
          />
        </div>

        <button
          type="button"
          class="concord-profile-delete"
          @click="askDeleteAccount"
        >
          Удалить аккаунт
        </button>

        <input
          ref="cameraInputRef"
          type="file"
          accept="image/*"
          capture="environment"
          class="concord-notifications__file-input"
          @change="onCameraSelected"
        />
        <input
          ref="galleryInputRef"
          type="file"
          accept="image/*"
          class="concord-notifications__file-input"
          @change="onGallerySelected"
        />
      </section>

      <nav class="concord-settings-group" aria-label="Разделы профиля">
        <div class="concord-profile-card">
          <button
            type="button"
            class="concord-settings-link"
            @click="$emit('open-notification-settings')"
          >
            <span>Уведомления</span>
            <svg class="concord-settings-link__chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
          <button
            type="button"
            class="concord-settings-link"
            @click="$emit('open-groups')"
          >
            <span>Группы</span>
            <svg class="concord-settings-link__chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
          <button
            v-if="canShowInstall"
            type="button"
            class="concord-settings-link"
            @click="installApp"
          >
            <span>Установить приложение</span>
            <svg class="concord-settings-link__chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
        </div>
      </nav>
    </main>

    <ConcordProfileFieldMenu
      :open="menuOpen"
      :title="menuTitle"
      :label="menuLabel"
      :value="menuValue"
      :actions="menuActions"
      @close="closeMenu"
      @select="onMenuSelect"
    />

    <ConcordProfileFieldSheet
      :open="activeField === 'name'"
      title="Имя"
      title-id="concord-profile-edit-name"
      @close="closeField"
      @save="saveName"
    >
      <label class="concord-profile-field-sheet__field">
        <span class="concord-profile-field-sheet__label">Имя</span>
        <input v-model="draftFirstName" type="text" class="concord-profile-field-sheet__input" autocomplete="given-name">
      </label>
      <label class="concord-profile-field-sheet__field">
        <span class="concord-profile-field-sheet__label">Фамилия</span>
        <input v-model="draftLastName" type="text" class="concord-profile-field-sheet__input" autocomplete="family-name">
      </label>
    </ConcordProfileFieldSheet>

    <ConcordProfileFieldSheet
      :open="activeField === 'email'"
      title="Email"
      title-id="concord-profile-edit-email"
      :save-disabled="!draftEmail.trim()"
      @close="closeField"
      @save="saveEmail"
    >
      <label class="concord-profile-field-sheet__field">
        <span class="concord-profile-field-sheet__label">Email</span>
        <input v-model="draftEmail" type="email" class="concord-profile-field-sheet__input" autocomplete="email">
      </label>
    </ConcordProfileFieldSheet>

    <ConcordProfileFieldSheet
      :open="activeField === 'phone'"
      title="Телефон"
      title-id="concord-profile-edit-phone"
      @close="closeField"
      @save="savePhone"
    >
      <label class="concord-profile-field-sheet__field">
        <span class="concord-profile-field-sheet__label">Телефон</span>
        <input v-model="draftPhone" type="tel" class="concord-profile-field-sheet__input" autocomplete="tel">
      </label>
    </ConcordProfileFieldSheet>

    <ConcordProfileFieldSheet
      :open="activeField === 'password'"
      title="Пароль"
      title-id="concord-profile-edit-password"
      :save-disabled="!draftOldPassword.trim() || !draftNewPassword.trim()"
      @close="closeField"
      @save="savePassword"
    >
      <label class="concord-profile-field-sheet__field">
        <span class="concord-profile-field-sheet__label">Старый пароль</span>
        <input v-model="draftOldPassword" type="password" class="concord-profile-field-sheet__input" autocomplete="current-password">
      </label>
      <label class="concord-profile-field-sheet__field">
        <span class="concord-profile-field-sheet__label">Новый пароль</span>
        <input v-model="draftNewPassword" type="password" class="concord-profile-field-sheet__input" autocomplete="new-password">
      </label>
    </ConcordProfileFieldSheet>

    <ConcordProfileFieldSheet
      :open="activeField === 'timezone'"
      title="Часовой пояс"
      title-id="concord-profile-edit-timezone"
      @close="closeField"
      @save="saveTimezone"
    >
      <label class="concord-profile-field-sheet__field">
        <span class="concord-profile-field-sheet__label">Часовой пояс</span>
        <select v-model="draftTimezone" class="concord-profile-field-sheet__select">
          <option value="">Авто ({{ browserTimezoneLabel }})</option>
          <option
            v-for="option in timezoneOptions"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </label>
    </ConcordProfileFieldSheet>

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
import ConcordConfirmSheet from '../concord/ConcordConfirmSheet.vue'
import ConcordAvatarPhotoSheet from '../concord/ConcordAvatarPhotoSheet.vue'
import ConcordAvatarCropSheet from '../concord/ConcordAvatarCropSheet.vue'
import ConcordProfileFieldRow from '../concord/ConcordProfileFieldRow.vue'
import ConcordProfileFieldSheet from '../concord/ConcordProfileFieldSheet.vue'
import ConcordProfileFieldMenu from '../concord/ConcordProfileFieldMenu.vue'
import ConcordPageHeader from '../concord/ConcordPageHeader.vue'
import ConcordProfileHero from '../concord/ConcordProfileHero.vue'
import { usePwaInstall } from '../pwa-install.js'
import { useConcordProfile } from '../composables/useConcordProfile.js'
import { useAvatarPhotoFlow } from '../composables/useAvatarPhotoFlow.js'
import { formatTimezoneLabel, formatTimezoneSetting, listTimezoneOptions } from '../timezone.js'

const EDIT_ICON = 'M4 20h4l10-10-4-4L4 16v4z M13 7l4 4'
const COPY_ICON = 'M8 4v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-4l-2-2H8a2 2 0 0 0-2 2z M16 8v10H8'
const PASSWORD_ICON = 'M12 4.5c-5 0-9 3.5-9 7.5s4 7.5 9 7.5 9-3.5 9-7.5-4-7.5-9-7.5z M12 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4z'

export default {
  name: 'GeneralSettingsView',
  components: {
    ConcordConfirmSheet,
    ConcordAvatarPhotoSheet,
    ConcordAvatarCropSheet,
    ConcordProfileFieldRow,
    ConcordProfileFieldSheet,
    ConcordProfileFieldMenu,
    ConcordPageHeader,
    ConcordProfileHero,
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
    const activeField = ref(null)
    const menuField = ref(null)
    const draftFirstName = ref('')
    const draftLastName = ref('')
    const draftEmail = ref('')
    const draftPhone = ref('')
    const draftOldPassword = ref('')
    const draftNewPassword = ref('')
    const draftTimezone = ref('')
    const timezoneOptions = listTimezoneOptions()

    const { profile, updateProfile, setAvatar, removeAvatar: clearAvatar } = useConcordProfile(toRef(props, 'accountId'))
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
    const loginLabel = computed(() => `@${profile.value.login || ''}`)
    const timezoneLabel = computed(() => formatTimezoneSetting(profile.value.timezone))
    const browserTimezoneLabel = computed(() => formatTimezoneLabel(null))

    const menuOpen = computed(() => Boolean(menuField.value))
    const menuTitle = computed(() => {
      const titles = {
        name: 'Имя',
        email: 'Email',
        phone: 'Телефон',
        password: 'Пароль',
        timezone: 'Часовой пояс',
      }
      return titles[menuField.value] || ''
    })
    const menuLabel = computed(() => {
      const labels = {
        name: 'имя',
        email: 'email',
        phone: 'мобильный',
        password: 'пароль',
        timezone: 'часовой пояс',
      }
      return labels[menuField.value] || ''
    })
    const menuValue = computed(() => {
      const values = {
        name: fullName.value,
        email: profile.value.email,
        phone: profile.value.phone,
        password: '••••••',
        timezone: timezoneLabel.value,
      }
      return values[menuField.value] || ''
    })
    const menuActions = computed(() => {
      const actions = []
      if (menuField.value === 'name') {
        actions.push({ id: 'edit', label: 'Изменить имя', iconPath: EDIT_ICON })
      }
      if (menuField.value === 'email') {
        actions.push({ id: 'edit', label: 'Изменить email', iconPath: EDIT_ICON })
        if (profile.value.email?.trim()) {
          actions.push({ id: 'copy', label: 'Копировать email', iconPath: COPY_ICON })
        }
      }
      if (menuField.value === 'phone') {
        actions.push({ id: 'edit', label: 'Изменить номер', iconPath: EDIT_ICON })
        if (profile.value.phone?.trim()) {
          actions.push({ id: 'copy', label: 'Копировать номер', iconPath: COPY_ICON })
        }
      }
      if (menuField.value === 'password') {
        actions.push({ id: 'edit', label: 'Изменить пароль', iconPath: EDIT_ICON })
      }
      if (menuField.value === 'timezone') {
        actions.push({ id: 'edit', label: 'Изменить часовой пояс', iconPath: EDIT_ICON })
      }
      return actions
    })

    function openMenu(field) {
      requestAnimationFrame(() => {
        menuField.value = field
      })
    }

    function closeMenu() {
      menuField.value = null
    }

    function onMenuSelect(actionId) {
      const field = menuField.value
      closeMenu()
      if (actionId === 'edit') {
        window.setTimeout(() => openField(field), 120)
        return
      }
      if (actionId === 'copy') {
        const value = field === 'email' ? profile.value.email : profile.value.phone
        if (value) {
          navigator.clipboard?.writeText(value).catch(() => {})
        }
      }
    }

    function openField(field) {
      activeField.value = field
      if (field === 'name') {
        draftFirstName.value = profile.value.firstName
        draftLastName.value = profile.value.lastName
      }
      if (field === 'email') {
        draftEmail.value = profile.value.email
      }
      if (field === 'phone') {
        draftPhone.value = profile.value.phone
      }
      if (field === 'password') {
        draftOldPassword.value = ''
        draftNewPassword.value = ''
      }
      if (field === 'timezone') {
        draftTimezone.value = profile.value.timezone || ''
      }
    }

    function closeField() {
      activeField.value = null
    }

    function saveName() {
      updateProfile({
        firstName: draftFirstName.value.trim(),
        lastName: draftLastName.value.trim(),
      })
      closeField()
    }

    function saveEmail() {
      updateProfile({ email: draftEmail.value.trim() })
      closeField()
    }

    function savePhone() {
      updateProfile({ phone: draftPhone.value.trim() })
      closeField()
    }

    function savePassword() {
      closeField()
    }

    function saveTimezone() {
      updateProfile({ timezone: draftTimezone.value.trim() })
      closeField()
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

    return {
      profile,
      cameraInputRef,
      galleryInputRef,
      fullName,
      loginLabel,
      timezoneLabel,
      browserTimezoneLabel,
      timezoneOptions,
      activeField,
      menuOpen,
      menuTitle,
      menuLabel,
      menuValue,
      menuActions,
      draftFirstName,
      draftLastName,
      draftEmail,
      draftPhone,
      draftOldPassword,
      draftNewPassword,
      draftTimezone,
      accountDeleteConfirmOpen,
      canShowInstall,
      installApp,
      showInstallHint,
      installHintTitle,
      installHintText,
      dismissInstallHint,
      openMenu,
      closeMenu,
      onMenuSelect,
      openField,
      closeField,
      saveName,
      saveEmail,
      savePhone,
      savePassword,
      saveTimezone,
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
