<template>
  <AuthShell title="Сброс пароля" lead="Пришлём ссылку, если такой адрес есть в системе.">
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
      <Message v-if="done" severity="success">Если этот email есть в системе, письмо уже отправлено.</Message>
      <Button type="submit" label="Отправить ссылку" :loading="loading" :disabled="loading" />
    </form>
    <div class="auth-links">
      <router-link to="/login">Назад ко входу</router-link>
    </div>
  </AuthShell>
</template>

<script setup>
import { ref } from 'vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Message from 'primevue/message'
import AuthShell from '../components/AuthShell.vue'
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
