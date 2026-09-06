<template>
  <div class="page">
    <h1>Профиль</h1>
    <Card>
      <template #content>
        <div class="flex flex-column gap-3">
          <InputText v-model="name" />
          <div class="flex align-items-center gap-2">
            <ToggleSwitch v-model="dark" />
            <span>Тёмная тема</span>
          </div>
          <Button label="Сохранить" @click="save" />
        </div>
        <Divider />
        <h3>Подписи</h3>
        <ul>
          <li v-for="s in auth.user?.signatures || []" :key="s.id" class="mb-2">
            {{ s.name }}
            <Tag v-if="s.is_active" value="активна" />
            <Button size="small" text label="Сделать активной" @click="auth.activateSignature(s.id)" />
          </li>
        </ul>
        <div class="flex gap-2">
          <InputText v-model="newSignature" placeholder="Новая подпись" />
          <Button label="Добавить" @click="addSignature" />
        </div>
        <Divider />
        <Button label="Выйти" severity="secondary" @click="logout" />
      </template>
    </Card>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'
import Divider from 'primevue/divider'
import Tag from 'primevue/tag'
import { useAuthStore } from '../stores/auth'
import api from '../services/api'

const auth = useAuthStore()
const router = useRouter()
const name = ref(auth.user?.name || '')
const dark = ref(auth.user?.theme === 'dark')
const newSignature = ref('')

watch(() => auth.user, (user) => {
  if (!user) return
  name.value = user.name
  dark.value = user.theme === 'dark'
})

async function save() {
  await auth.updateProfile({ name: name.value, theme: dark.value ? 'dark' : 'light' })
}

async function addSignature() {
  if (!newSignature.value) return
  await api.post('/api/signatures', { name: newSignature.value })
  newSignature.value = ''
  await auth.fetchUser()
}

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>
