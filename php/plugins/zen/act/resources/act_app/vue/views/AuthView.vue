<template>
  <div class="tg-auth">
    <div>
      <h1 class="tg-auth__title">{{ mode === 'login' ? 'Вход' : 'Регистрация' }}</h1>
      <p class="tg-auth__sub">Zen.Act — логин и пароль</p>
    </div>

    <div class="tg-auth__card">
      <form @submit.prevent="submit">
        <label class="tg-field">
          <span class="tg-field__label">Логин</span>
          <input
            v-model="form.login"
            class="tg-input"
            type="text"
            autocomplete="username"
            autocapitalize="none"
            required
          >
        </label>
        <label class="tg-field">
          <span class="tg-field__label">Пароль</span>
          <input
            v-model="form.password"
            class="tg-input"
            type="password"
            autocomplete="current-password"
            required
          >
        </label>
        <label v-if="mode === 'register'" class="tg-field">
          <span class="tg-field__label">Подтверждение пароля</span>
          <input
            v-model="form.password_confirmation"
            class="tg-input"
            type="password"
            autocomplete="new-password"
            required
          >
        </label>
        <label class="tg-checkbox">
          <input v-model="remember" type="checkbox">
          <span>Запомнить меня</span>
        </label>
        <p v-if="error" class="tg-error">{{ error }}</p>
        <button class="tg-btn tg-btn--block" type="submit" :disabled="pending">
          {{ pending ? '…' : (mode === 'login' ? 'Войти' : 'Зарегистрироваться') }}
        </button>
      </form>
      <p class="tg-auth-switch">
        <button type="button" class="tg-link-btn" @click="toggleMode">
          {{ mode === 'login' ? 'Создать аккаунт' : 'Уже есть аккаунт' }}
        </button>
      </p>
    </div>
  </div>
</template>

<script>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAppState } from '../app-state'

const REMEMBER_KEY = 'act_remember_me'
const SAVED_LOGIN_KEY = 'act_saved_login'

export default {
  name: 'AuthView',
  setup() {
    const router = useRouter()
    const { api, setUser } = useAppState()
    const mode = ref('login')
    const pending = ref(false)
    const error = ref('')
    const remember = ref(localStorage.getItem(REMEMBER_KEY) === '1')
    const form = reactive({
      login: '',
      password: '',
      password_confirmation: '',
    })

    onMounted(() => {
      if (remember.value) {
        const savedLogin = localStorage.getItem(SAVED_LOGIN_KEY)
        if (savedLogin) {
          form.login = savedLogin
        }
      }
    })

    const persistRememberPrefs = () => {
      if (remember.value) {
        localStorage.setItem(REMEMBER_KEY, '1')
        localStorage.setItem(SAVED_LOGIN_KEY, form.login)
      } else {
        localStorage.setItem(REMEMBER_KEY, '0')
        localStorage.removeItem(SAVED_LOGIN_KEY)
      }
    }

    const submit = () => {
      pending.value = true
      error.value = ''
      const action = mode.value === 'login' ? 'Auth:login' : 'Auth:register'
      const data = {
        login: form.login,
        password: form.password,
        remember: remember.value,
      }
      if (mode.value === 'register') {
        data.password_confirmation = form.password_confirmation
      }

      api({
        api: action,
        data,
        then: (res) => {
          pending.value = false
          if (res?.ok && res.data?.user) {
            persistRememberPrefs()
            setUser(res.data.user)
            if (res.data.token) {
              localStorage.setItem('act_token', res.data.token)
            }
            form.password = ''
            form.password_confirmation = ''
            router.replace({ name: 'acts' })
            return
          }
          error.value = res?.errors?.[0]?.message || 'Ошибка авторизации'
        },
        catch: () => {
          pending.value = false
          error.value = 'Ошибка сети'
        },
      })
    }

    const toggleMode = () => {
      mode.value = mode.value === 'login' ? 'register' : 'login'
      error.value = ''
    }

    return {
      mode,
      pending,
      error,
      remember,
      form,
      submit,
      toggleMode,
    }
  },
}
</script>
