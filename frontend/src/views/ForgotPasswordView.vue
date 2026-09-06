<template>
  <ConcordPageShell title="Сброс пароля">
    <form class="concord-form" @submit.prevent="submit">
      <label class="concord-field"><span>Email</span><InputText v-model="email" type="email" class="w-full" /></label>
      <Message v-if="done" severity="success">Если email есть в системе, письмо отправлено.</Message>
      <Button type="submit" label="Отправить ссылку" :loading="loading" class="w-full" />
      <router-link to="/login">Назад ко входу</router-link>
    </form>
  </ConcordPageShell>
</template>

<script setup>
import { ref } from 'vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Message from 'primevue/message'
import ConcordPageShell from '../components/ConcordPageShell.vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const email = ref('')
const loading = ref(false)
const done = ref(false)

async function submit() {
  loading.value = true
  try {
    await auth.forgotPassword(email.value)
    done.value = true
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
