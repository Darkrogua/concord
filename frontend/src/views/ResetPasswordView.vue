<template>
  <ConcordPageShell title="Новый пароль">
    <form class="concord-form" @submit.prevent="submit">
      <label class="concord-field"><span>Email</span><InputText v-model="email" type="email" class="w-full" /></label>
      <label class="concord-field"><span>Пароль</span><Password v-model="password" :feedback="false" toggleMask class="w-full" inputClass="w-full" /></label>
      <label class="concord-field"><span>Повтор</span><Password v-model="password_confirmation" :feedback="false" toggleMask class="w-full" inputClass="w-full" /></label>
      <Message v-if="error" severity="error">{{ error }}</Message>
      <Button type="submit" label="Сохранить" :loading="loading" class="w-full" />
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

<style scoped>
.concord-form { display: flex; flex-direction: column; gap: 1rem; }
.concord-field { display: flex; flex-direction: column; gap: 0.35rem; color: var(--concord-text-muted); }
.w-full { width: 100%; }
</style>
