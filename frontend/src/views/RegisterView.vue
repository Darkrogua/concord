<template>
  <ConcordPageShell title="Регистрация">
    <form class="concord-form" @submit.prevent="submit">
      <label class="concord-field"><span>Имя</span><InputText v-model="name" class="w-full" /></label>
      <label class="concord-field"><span>Email</span><InputText v-model="email" type="email" class="w-full" /></label>
      <label class="concord-field"><span>Пароль</span><Password v-model="password" :feedback="false" toggleMask class="w-full" inputClass="w-full" /></label>
      <label class="concord-field"><span>Повтор пароля</span><Password v-model="password_confirmation" :feedback="false" toggleMask class="w-full" inputClass="w-full" /></label>
      <Message v-if="error" severity="error">{{ error }}</Message>
      <Button type="submit" label="Создать аккаунт" :loading="loading" class="w-full" />
      <router-link to="/login">Уже есть аккаунт</router-link>
    </form>
  </ConcordPageShell>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Message from 'primevue/message'
import ConcordPageShell from '../components/ConcordPageShell.vue'
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
    await auth.register({ name: name.value, email: email.value, password: password.value, password_confirmation: password_confirmation.value })
    router.push('/agreements')
  } catch (e) {
    error.value = Object.values(e.response?.data?.errors || {}).flat().join(' ') || 'Не удалось зарегистрироваться.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.concord-form { display: flex; flex-direction: column; gap: 1rem; }
.concord-field { display: flex; flex-direction: column; gap: 0.35rem; color: var(--concord-text-muted); }
.w-full { width: 100%; }
</style>
