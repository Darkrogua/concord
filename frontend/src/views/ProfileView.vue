<template>
  <ConcordPageShell title="Профиль">
    <form class="concord-form" @submit.prevent="save">
      <label class="concord-field">
        <span>Имя</span>
        <InputText v-model="name" name="name" class="w-full" />
      </label>
      <label class="concord-field concord-field--row">
        <ToggleSwitch v-model="dark" />
        <span>Тёмная тема</span>
      </label>
      <Button type="submit" label="Сохранить" />
    </form>

    <h2 class="concord-section-title">Подписи</h2>
    <ul class="concord-settings-list">
      <li v-for="s in auth.user?.signatures || []" :key="s.id">
        {{ s.name }}
        <Tag v-if="s.is_active" value="активна" />
        <Button v-else size="small" text label="Сделать активной" @click="auth.activateSignature(s.id)" />
      </li>
    </ul>
    <form class="concord-form" @submit.prevent="addSignature">
      <InputText v-model="newSignature" placeholder="Новая подпись" class="w-full" />
      <Button type="submit" label="Добавить" />
    </form>
    <Button label="Выйти" severity="secondary" class="mt-3" @click="logout" />
  </ConcordPageShell>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import InputText from 'primevue/inputtext'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import ConcordPageShell from '../components/ConcordPageShell.vue'
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

<style scoped>
.concord-form { display: flex; flex-direction: column; gap: 0.85rem; margin-bottom: 1.5rem; }
.concord-field { display: flex; flex-direction: column; gap: 0.35rem; color: var(--concord-settings-field-label-color); }
.concord-field--row { flex-direction: row; align-items: center; gap: 0.6rem; }
.concord-section-title { font-size: var(--concord-settings-section-title-size); margin: 1rem 0 0.5rem; }
.concord-settings-list { list-style: none; padding: 0; margin: 0 0 1rem; }
.concord-settings-list li { padding: 0.55rem 0; border-bottom: 1px solid var(--concord-divider); display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
.w-full { width: 100%; }
.mt-3 { margin-top: 1rem; }
</style>
