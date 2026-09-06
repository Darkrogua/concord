<template>
  <div class="auth-page">
    <Card class="auth-card">
      <template #title>Вход в Concord</template>
      <template #content>
        <form class="flex flex-column gap-3" @submit.prevent="submit">
          <div>
            <label>Email</label>
            <InputText v-model="email" type="email" class="w-full" />
          </div>
          <div>
            <label>Пароль</label>
            <Password v-model="password" :feedback="false" toggleMask class="w-full" inputClass="w-full" />
          </div>
          <Message v-if="error" severity="error">{{ error }}</Message>
          <Button type="submit" label="Войти" :loading="loading" />
        </form>
        <div class="mt-3 flex justify-content-between">
          <router-link to="/register">Регистрация</router-link>
          <router-link to="/forgot-password">Забыли пароль?</router-link>
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Message from 'primevue/message'
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
    router.push(route.query.redirect || '/')
  } catch (e) {
    error.value = e.response?.data?.message || 'Не удалось войти'
  } finally {
    loading.value = false
  }
}
</script>
