<template>
  <div class="auth-page">
    <Card class="auth-card">
      <template #title>Восстановление пароля</template>
      <template #content>
        <form class="flex flex-column gap-3" @submit.prevent="submit">
          <InputText v-model="email" type="email" placeholder="Email" class="w-full" />
          <Message v-if="done" severity="success">Если email существует, письмо отправлено.</Message>
          <Button type="submit" label="Отправить ссылку" :loading="loading" />
        </form>
        <router-link to="/login">Назад ко входу</router-link>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Message from 'primevue/message'
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
