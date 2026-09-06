<template>
  <AuthShell title="Регистрация" lead="После этого можно создавать согласования и голосовать.">
    <form class="form-stack" @submit.prevent="submit">
      <div class="field">
        <label for="name">Имя</label>
        <InputText id="name" v-model="name" name="name" autocomplete="name" class="w-full" />
      </div>
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
      <Button type="submit" label="Создать аккаунт" :loading="loading" :disabled="loading" />
    </form>
    <div class="auth-links">
      <router-link to="/login">Уже есть аккаунт</router-link>
    </div>
  </AuthShell>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Message from 'primevue/message'
import AuthShell from '../components/AuthShell.vue'
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
