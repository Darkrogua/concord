<template>
  <ConcordAuthShell title="Регистрация" subtitle="Создайте аккаунт Concord">
    <form class="concord-auth-form concord-agreement-form" @submit.prevent="submit">
      <label class="concord-agreement-form__field">
        <span class="concord-agreement-form__label">Имя</span>
        <input
          v-model="name"
          class="concord-agreement-form__input"
          type="text"
          name="name"
          autocomplete="name"
          required
        >
      </label>

      <label class="concord-agreement-form__field">
        <span class="concord-agreement-form__label">Email</span>
        <input
          v-model="email"
          class="concord-agreement-form__input"
          type="email"
          name="email"
          autocomplete="email"
          required
        >
      </label>

      <ConcordPasswordField
        v-model="password"
        label="Пароль"
        name="password"
        autocomplete="new-password"
        required
      />

      <ConcordPasswordField
        v-model="password_confirmation"
        label="Повтор пароля"
        name="password_confirmation"
        autocomplete="new-password"
        required
      />

      <p v-if="error" class="concord-auth-error" role="alert">{{ error }}</p>

      <button class="concord-agreement-form__submit" type="submit" :disabled="loading">
        {{ loading ? 'Создаём…' : 'Создать аккаунт' }}
      </button>

      <div class="concord-auth-links">
        <router-link to="/login">Уже есть аккаунт</router-link>
      </div>
    </form>
  </ConcordAuthShell>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import ConcordAuthShell from '../components/concord/ConcordAuthShell.vue'
import ConcordPasswordField from '../components/concord/ConcordPasswordField.vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const name = ref('')
const email = ref('')
const password = ref('')
const password_confirmation = ref('')
const loading = ref(false)
const error = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await auth.register({
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: password_confirmation.value,
    })
    router.push('/agreements')
  } catch (e) {
    error.value = Object.values(e.response?.data?.errors || {}).flat().join(' ') || 'Не удалось зарегистрироваться.'
  } finally {
    loading.value = false
  }
}
</script>
