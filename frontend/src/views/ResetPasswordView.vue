<template>
  <div class="auth-page">
    <Card class="auth-card">
      <template #title>Новый пароль</template>
      <template #content>
        <form class="flex flex-column gap-3" @submit.prevent="submit">
          <InputText v-model="email" type="email" placeholder="Email" class="w-full" />
          <Password v-model="password" :feedback="false" toggleMask placeholder="Пароль" inputClass="w-full" />
          <Password v-model="password_confirmation" :feedback="false" toggleMask placeholder="Повтор" inputClass="w-full" />
          <Message v-if="error" severity="error">{{ error }}</Message>
          <Button type="submit" label="Сохранить" :loading="loading" />
        </form>
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
    error.value = e.response?.data?.message || 'Не удалось сбросить пароль'
  } finally {
    loading.value = false
  }
}
</script>
