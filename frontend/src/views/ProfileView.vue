<template>
  <div class="page">
    <div class="page-head">
      <div>
        <h1>Профиль</h1>
        <p>Имя, тема и подпись, которой вы голосуете.</p>
      </div>
    </div>
    <div class="sheet">
      <form class="form-stack" @submit.prevent="save">
        <div class="field">
          <label for="profile-name">Имя</label>
          <InputText id="profile-name" v-model="name" name="name" autocomplete="name" class="w-full" />
        </div>
        <label class="field" style="flex-direction: row; align-items: center; gap: 0.6rem">
          <ToggleSwitch v-model="dark" />
          Тёмная тема
        </label>
        <Button type="submit" label="Сохранить профиль" />
      </form>
    </div>
    <div class="sheet">
      <h2>Подписи</h2>
      <ul class="sig-list">
        <li v-for="s in auth.user?.signatures || []" :key="s.id">
          <span>{{ s.name }}</span>
          <span v-if="s.is_active" class="status-pill">активна</span>
          <Button v-else size="small" text label="Сделать активной" @click="auth.activateSignature(s.id)" />
        </li>
      </ul>
      <form class="toolbar" @submit.prevent="addSignature">
        <div class="field" style="flex: 1">
          <label for="new-sig">Новая подпись</label>
          <InputText id="new-sig" v-model="newSignature" name="signature" autocomplete="off" />
        </div>
        <Button type="submit" label="Добавить" style="align-self: end" />
      </form>
    </div>
    <Button label="Выйти" severity="secondary" @click="logout" />
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import InputText from 'primevue/inputtext'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'
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
