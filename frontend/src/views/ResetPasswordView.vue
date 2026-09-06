<template>
  <ConcordAuthShell title="Новый пароль" subtitle="Задайте новый пароль для входа">
    <form class="concord-auth-form concord-agreement-form" @submit.prevent="submit">
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
        {{ loading ? 'Сохраняем…' : 'Сохранить' }}
      </button>

      <div class="concord-auth-links">
        <router-link to="/login">Назад ко входу</router-link>
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

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const email = ref(route.query.email || '')
const password = ref('')
const password_confirmation = ref('')
const loading = ref(false)
const error = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await auth.resetPassword({
      token: route.query.token,
      email: email.value,
      password: password.value,
      password_confirmation: password_confirmation.value,
    })
    router.push('/login')
  } catch (e) {
    error.value = e.response?.data?.message || 'Не удалось сбросить пароль.'
  } finally {
    loading.value = false
  }
}
</script>
