<template>
  <AuthShell title="Вход" lead="Почта и пароль, которыми вы регистрировались.">
    <form class="form-stack" @submit.prevent="submit">
      <div class="field">
        <label for="email">Email</label>
        <InputText
          id="email"
          v-model="email"
          type="email"
          name="email"
          autocomplete="username"
          spellcheck="false"
          class="w-full"
        />
      </div>
      <div class="field">
        <label for="password">Пароль</label>
        <Password
          inputId="password"
          v-model="password"
          :feedback="false"
          toggleMask
          class="w-full"
          inputClass="w-full"
          autocomplete="current-password"
        />
      </div>
      <Message v-if="error" severity="error">{{ error }}</Message>
      <Button type="submit" label="Войти" :loading="loading" :disabled="loading" />
    </form>
    <div class="auth-links">
      <router-link to="/register">Регистрация</router-link>
      <router-link to="/forgot-password">Забыли пароль?</router-link>
    </div>
  </AuthShell>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Message from 'primevue/message'
import AuthShell from '../components/AuthShell.vue'
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
    error.value = e.response?.data?.message || 'Не удалось войти. Проверьте почту и пароль.'
  } finally {
    loading.value = false
  }
}
</script>
