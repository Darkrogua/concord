<template>
  <ConcordAuthShell title="Вход" subtitle="Войдите, чтобы открыть согласования">
    <form class="concord-auth-form concord-agreement-form" @submit.prevent="submit">
      <label class="concord-agreement-form__field">
        <span class="concord-agreement-form__label">Email</span>
        <input
          v-model="email"
          class="concord-agreement-form__input"
          type="email"
          name="email"
          placeholder="you@example.com"
          autocomplete="username"
          required
        >
      </label>

      <ConcordPasswordField
        v-model="password"
        label="Пароль"
        name="password"
        autocomplete="current-password"
        required
      />

      <p v-if="error" class="concord-auth-error" role="alert">{{ error }}</p>

      <button class="concord-agreement-form__submit" type="submit" :disabled="loading">
        {{ loading ? 'Входим…' : 'Войти' }}
      </button>

      <div class="concord-auth-links">
        <router-link to="/register">Регистрация</router-link>
        <router-link to="/forgot-password">Забыли пароль?</router-link>
      </div>
    </form>
  </ConcordAuthShell>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ConcordAuthShell from '../components/concord/ConcordAuthShell.vue'
import ConcordPasswordField from '../components/concord/ConcordPasswordField.vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await auth.login({ email: email.value, password: password.value })
    router.push(route.query.redirect || '/agreements')
  } catch (e) {
    error.value = e.response?.data?.message || 'Не удалось войти.'
  } finally {
    loading.value = false
  }
}
</script>
