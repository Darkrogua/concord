<template>
  <AuthShell title="Новый пароль">
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
          autocomplete="new-password"
        />
      </div>
      <div class="field">
        <label for="password_confirmation">Повтор пароля</label>
        <Password
          inputId="password_confirmation"
          v-model="password_confirmation"
          :feedback="false"
          toggleMask
          class="w-full"
          inputClass="w-full"
          autocomplete="new-password"
        />
      </div>
      <Message v-if="error" severity="error">{{ error }}</Message>
      <Button type="submit" label="Сохранить пароль" :loading="loading" :disabled="loading" />
    </form>
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
    error.value = e.response?.data?.message || 'Не удалось сбросить пароль. Запросите ссылку ещё раз.'
  } finally {
    loading.value = false
  }
}
</script>
