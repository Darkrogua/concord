<template>
  <div class="auth-page">
    <Card class="auth-card">
      <template #title>Регистрация</template>
      <template #content>
        <form class="flex flex-column gap-3" @submit.prevent="submit">
          <InputText v-model="name" placeholder="Имя" class="w-full" />
          <InputText v-model="email" type="email" placeholder="Email" class="w-full" />
          <Password v-model="password" :feedback="false" toggleMask placeholder="Пароль" inputClass="w-full" />
          <Password v-model="password_confirmation" :feedback="false" toggleMask placeholder="Повтор пароля" inputClass="w-full" />
          <Message v-if="error" severity="error">{{ error }}</Message>
          <Button type="submit" label="Создать аккаунт" :loading="loading" />
        </form>
        <router-link to="/login">Уже есть аккаунт</router-link>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Message from 'primevue/message'
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
    router.push('/')
  } catch (e) {
    error.value = Object.values(e.response?.data?.errors || {}).flat().join(' ') || 'Ошибка регистрации'
  } finally {
    loading.value = false
  }
}
</script>
