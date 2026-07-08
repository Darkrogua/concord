<template>
  <div>
    <header :class="['tg-header', { 'tg-header--light': !isSelf }]">
      <button v-if="!isSelf || !state.user" type="button" class="tg-header__back" aria-label="Назад" @click="goBack">
        ←
      </button>
      <h1 class="tg-header__title">{{ headerTitle }}</h1>
    </header>

    <main :class="['tg-main', { 'tg-main--no-tab': !showTabbar }]">
      <AsyncRegion :loading="loading" variant="page" pattern="profile">
        <p v-if="error" class="tg-error">{{ error }}</p>
        <template v-else-if="profile">
        <div class="tg-profile-hero">
          <span class="tg-avatar">{{ initials }}</span>
          <h2 class="tg-profile-hero__name">{{ profile.display_name }}</h2>
          <p class="tg-profile-hero__login">@{{ profile.login }}</p>
          <span v-if="!isSelf" class="tg-badge">Чужой профиль</span>
        </div>

        <div class="tg-section">
          <div class="tg-list">
            <div class="tg-list__row">
              <span class="tg-list__label">Логин</span>
              <span class="tg-list__value tg-list__value--strong">@{{ profile.login }}</span>
            </div>
            <ProfileEditableRow
              label="Имя"
              :value="profile.name"
              :editable="isSelf"
              strong
              edit-label="Редактировать имя"
              @edit="openModal('name')"
            />
            <ProfileEditableRow
              v-if="isSelf"
              label="Email"
              :value="profile.email"
              editable
              strong
              edit-label="Редактировать email"
              @edit="openModal('email')"
            />
            <ProfileEditableRow
              v-if="isSelf"
              label="Пароль"
              value="••••••"
              editable
              edit-label="Сменить пароль"
              @edit="openModal('password')"
            />
            <ProfileEditableRow
              v-if="isSelf"
              label="Часовой пояс"
              :value="timezoneLabel"
              editable
              edit-label="Изменить часовой пояс"
              @edit="openModal('timezone')"
            />
          </div>
        </div>

        <div v-if="isSelf && canShowInstall" class="tg-section">
          <div class="tg-list">
            <button class="tg-list__row tg-list__row--action" type="button" @click="installApp">
              <span class="tg-list__action-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                  <path d="M12 3v10m0 0l4-4m-4 4L8 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M5 17v2a2 2 0 002 2h10a2 2 0 002-2v-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
              </span>
              <span class="tg-list__label tg-list__label--accent">Установить приложение</span>
            </button>
          </div>
        </div>

        <div v-if="isSelf" class="tg-section">
          <div class="tg-list">
            <div class="tg-list__row">
              <button class="tg-btn tg-btn--danger tg-btn--block" type="button" @click="doLogout">
                Выйти
              </button>
            </div>
          </div>
        </div>
        </template>
      </AsyncRegion>
    </main>

    <AppShell v-if="showTabbar" active-tab="profile" />

    <EditFieldModal
      :open="activeModal === 'name'"
      title="Имя"
      title-id="profile-edit-name"
      @close="closeModal"
    >
      <label class="tg-field">
        <span class="tg-field__label">Отображаемое имя</span>
        <input
          ref="nameInput"
          v-model="modalForm.name"
          class="tg-input"
          type="text"
          maxlength="255"
          autocomplete="name"
        >
      </label>
      <p v-if="modalError" class="tg-error tg-error--inline">{{ modalError }}</p>
      <button class="tg-btn tg-btn--block" type="button" :disabled="modalSaving" @click="saveName">
        {{ modalSaving ? '…' : 'Сохранить' }}
      </button>
    </EditFieldModal>

    <EditFieldModal
      :open="activeModal === 'email'"
      title="Email"
      title-id="profile-edit-email"
      @close="closeModal"
    >
      <label class="tg-field">
        <span class="tg-field__label">Адрес электронной почты</span>
        <input
          ref="emailInput"
          v-model="modalForm.email"
          class="tg-input"
          type="email"
          autocomplete="email"
        >
      </label>
      <p v-if="modalError" class="tg-error tg-error--inline">{{ modalError }}</p>
      <button class="tg-btn tg-btn--block" type="button" :disabled="modalSaving" @click="saveEmail">
        {{ modalSaving ? '…' : 'Сохранить' }}
      </button>
    </EditFieldModal>

    <EditFieldModal
      :open="activeModal === 'password'"
      title="Смена пароля"
      title-id="profile-edit-password"
      @close="closeModal"
    >
      <label class="tg-field">
        <span class="tg-field__label">Новый пароль</span>
        <input
          ref="passwordInput"
          v-model="modalForm.password"
          class="tg-input"
          type="password"
          autocomplete="new-password"
        >
      </label>
      <label class="tg-field">
        <span class="tg-field__label">Текущий пароль</span>
        <input
          v-model="modalForm.current_password"
          class="tg-input"
          type="password"
          autocomplete="current-password"
        >
      </label>
      <p v-if="modalError" class="tg-error tg-error--inline">{{ modalError }}</p>
      <button class="tg-btn tg-btn--block" type="button" :disabled="modalSaving" @click="savePassword">
        {{ modalSaving ? '…' : 'Сохранить' }}
      </button>
    </EditFieldModal>

    <EditFieldModal
      :open="activeModal === 'timezone'"
      title="Часовой пояс"
      title-id="profile-edit-timezone"
      @close="closeModal"
    >
      <label class="tg-field">
        <span class="tg-field__label">Отображение дат и времени</span>
        <select
          ref="timezoneInput"
          v-model="modalForm.timezone"
          class="tg-input"
        >
          <option value="">Авто (по устройству)</option>
          <option
            v-for="option in timezoneOptions"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </label>
      <p class="tg-field__hint">Время версий актов и других меток будет показано в выбранном поясе.</p>
      <p v-if="modalError" class="tg-error tg-error--inline">{{ modalError }}</p>
      <button class="tg-btn tg-btn--block" type="button" :disabled="modalSaving" @click="saveTimezone">
        {{ modalSaving ? '…' : 'Сохранить' }}
      </button>
    </EditFieldModal>

    <div v-if="showInstallHint" class="tg-sheet-backdrop" @click="dismissInstallHint" />
    <div v-if="showInstallHint" class="tg-sheet" role="dialog" aria-labelledby="pwa-install-title">
      <h3 id="pwa-install-title" class="tg-sheet__title">{{ installHintTitle }}</h3>
      <p class="tg-stub__text">{{ installHintText }}</p>
      <div class="tg-sheet__actions">
        <button class="tg-btn" type="button" @click="dismissInstallHint">Понятно</button>
      </div>
    </div>
  </div>
</template>

<script>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAppState } from '../app-state'
import { usePwaInstall } from '../pwa-install'
import AppShell from '../components/AppShell.vue'
import AsyncRegion from '../components/AsyncRegion.vue'
import EditFieldModal from '../components/EditFieldModal.vue'
import ProfileEditableRow from '../components/ProfileEditableRow.vue'
import { formatTimezoneSetting, listTimezoneOptions } from '../timezone'

export default {
  name: 'ProfileView',
  components: { AppShell, AsyncRegion, EditFieldModal, ProfileEditableRow },
  props: {
    login: {
      type: String,
      required: true,
    },
  },
  setup(props) {
    const router = useRouter()
    const { state, api, setUser, logout } = useAppState()
    const { canShowInstall, install: installApp, showInstallHint, installHintTitle, installHintText, dismissInstallHint } = usePwaInstall()
    const profile = ref(null)
    const isSelf = ref(false)
    const loading = ref(true)
    const error = ref('')
    const activeModal = ref(null)
    const modalSaving = ref(false)
    const modalError = ref('')
    const nameInput = ref(null)
    const emailInput = ref(null)
    const passwordInput = ref(null)
    const timezoneInput = ref(null)
    const timezoneOptions = listTimezoneOptions()
    const modalForm = reactive({
      name: '',
      email: '',
      password: '',
      current_password: '',
      timezone: '',
    })

    const showTabbar = computed(() => isSelf.value && Boolean(state.user))
    const headerTitle = computed(() => (isSelf.value ? 'Профиль' : 'Пользователь'))
    const initials = computed(() => {
      const name = profile.value?.display_name || profile.value?.login || '?'
      return name.trim().charAt(0).toUpperCase()
    })
    const timezoneLabel = computed(() => formatTimezoneSetting(profile.value?.timezone))

    const applyUser = (user) => {
      if (!user) {
        return
      }
      setUser(user)
      profile.value = {
        login: user.login,
        name: user.name,
        email: user.email,
        timezone: user.timezone,
        display_name: user.display_name,
      }
    }

    const loadProfile = () => {
      loading.value = true
      error.value = ''
      api({
        api: `Auth:profile?login=${encodeURIComponent(props.login)}`,
        then: (res) => {
          loading.value = false
          if (res?.ok && res.data?.profile) {
            profile.value = res.data.profile
            isSelf.value = Boolean(res.data.is_self)
            return
          }
          error.value = res?.errors?.[0]?.message || 'Профиль не найден'
        },
        catch: () => {
          loading.value = false
          error.value = 'Ошибка сети'
        },
      })
    }

    const focusModalInput = async () => {
      await nextTick()
      const input = activeModal.value === 'name'
        ? nameInput.value
        : activeModal.value === 'email'
          ? emailInput.value
          : activeModal.value === 'timezone'
            ? timezoneInput.value
            : passwordInput.value
      input?.focus()
    }

    const openModal = (field) => {
      modalError.value = ''
      if (field === 'name') {
        modalForm.name = profile.value?.name || ''
      } else if (field === 'email') {
        modalForm.email = profile.value?.email || ''
      } else if (field === 'password') {
        modalForm.password = ''
        modalForm.current_password = ''
      } else if (field === 'timezone') {
        modalForm.timezone = profile.value?.timezone || ''
      }
      activeModal.value = field
      focusModalInput()
    }

    const closeModal = () => {
      activeModal.value = null
      modalError.value = ''
    }

    const apiCall = (apiName, data) => new Promise((resolve) => {
      api({
        api: apiName,
        data,
        then: resolve,
        catch: () => resolve(null),
      })
    })

    const saveName = async () => {
      modalSaving.value = true
      modalError.value = ''
      const res = await apiCall('Auth:updateProfile', { name: modalForm.name })
      modalSaving.value = false
      if (!res?.ok) {
        modalError.value = res?.errors?.[0]?.message || 'Не удалось сохранить имя'
        return
      }
      applyUser(res.data?.user)
      closeModal()
    }

    const saveEmail = async () => {
      modalSaving.value = true
      modalError.value = ''
      const res = await apiCall('Auth:updateProfile', { email: modalForm.email })
      modalSaving.value = false
      if (!res?.ok) {
        modalError.value = res?.errors?.[0]?.message || 'Не удалось сохранить email'
        return
      }
      applyUser(res.data?.user)
      closeModal()
    }

    const savePassword = async () => {
      if (!modalForm.password.trim()) {
        modalError.value = 'Введите новый пароль'
        return
      }
      modalSaving.value = true
      modalError.value = ''
      const res = await apiCall('Auth:changePassword', {
        current_password: modalForm.current_password,
        password: modalForm.password,
        password_confirmation: modalForm.password,
      })
      modalSaving.value = false
      if (!res?.ok) {
        modalError.value = res?.errors?.[0]?.message || 'Не удалось сменить пароль'
        return
      }
      applyUser(res.data?.user)
      closeModal()
    }

    const saveTimezone = async () => {
      modalSaving.value = true
      modalError.value = ''
      const res = await apiCall('Auth:updateProfile', { timezone: modalForm.timezone })
      modalSaving.value = false
      if (!res?.ok) {
        modalError.value = res?.errors?.[0]?.message || 'Не удалось сохранить часовой пояс'
        return
      }
      applyUser(res.data?.user)
      closeModal()
    }

    const doLogout = async () => {
      await logout()
      router.replace({ name: 'auth' })
    }

    const goBack = () => {
      if (state.user) {
        router.push({ name: 'acts' })
      } else {
        router.push({ name: 'auth' })
      }
    }

    watch(activeModal, (value) => {
      document.body.style.overflow = value ? 'hidden' : ''
      if (value) {
        focusModalInput()
      }
    })

    onMounted(loadProfile)
    watch(() => props.login, loadProfile)

    return {
      state,
      profile,
      isSelf,
      loading,
      error,
      activeModal,
      modalSaving,
      modalError,
      modalForm,
      nameInput,
      emailInput,
      passwordInput,
      timezoneInput,
      timezoneOptions,
      timezoneLabel,
      showTabbar,
      headerTitle,
      initials,
      openModal,
      closeModal,
      saveName,
      saveEmail,
      savePassword,
      saveTimezone,
      doLogout,
      goBack,
      canShowInstall,
      installApp,
      showInstallHint,
      installHintTitle,
      installHintText,
      dismissInstallHint,
    }
  },
}
</script>
