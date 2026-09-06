<template>
  <ConcordPageShell title="Вход">
    <form class="concord-form" @submit.prevent="submit">
      <label class="concord-field">
        <span>Email</span>
        <InputText v-model="email" type="email" name="email" autocomplete="username" class="w-full" />
      </label>
      <label class="concord-field">
        <span>Пароль</span>
        <Password v-model="password" :feedback="false" toggleMask class="w-full" inputClass="w-full" autocomplete="current-password" />
      </label>
      <Message v-if="error" severity="error">{{ error }}</Message>
      <Button type="submit" label="Войти" :loading="loading" class="w-full" />
      <div class="concord-form-links">
        <router-link to="/register">Регистрация</router-link>
        <router-link to="/forgot-password">Забыли пароль?</router-link>
      </div>
    </form>
  </ConcordPageShell>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Message from 'primevue/message'
import ConcordPageShell from '../components/ConcordPageShell.vue'
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

<style scoped>
.concord-form { display: flex; flex-direction: column; gap: 1rem; }
.concord-field { display: flex; flex-direction: column; gap: 0.35rem; color: var(--concord-text-muted); }
.concord-form-links { display: flex; justify-content: space-between; font-size: var(--concord-text-body); }
.w-full { width: 100%; }
</style>
