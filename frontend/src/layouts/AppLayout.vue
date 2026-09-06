<template>
  <div class="layout-shell">
    <Menubar class="desktop-nav" :model="items">
      <template #start>
        <strong class="px-2">Concord</strong>
      </template>
      <template #end>
        <div class="flex align-items-center gap-2 px-2">
          <Select
            v-if="auth.user?.signatures?.length"
            :options="auth.user.signatures"
            optionLabel="name"
            optionValue="id"
            :modelValue="auth.activeSignature?.id"
            placeholder="Подпись"
            @update:modelValue="auth.activateSignature"
          />
          <Button :label="auth.user?.name" text @click="$router.push('/profile')" />
        </div>
      </template>
    </Menubar>

    <div class="layout-body">
      <router-view />
    </div>

    <div class="mobile-nav" style="position:fixed;bottom:0;left:0;right:0;background:var(--p-surface-0);border-top:1px solid var(--p-content-border-color);display:flex;justify-content:space-around;padding:.5rem;z-index:20;">
      <Button icon="pi pi-inbox" text rounded @click="$router.push('/agreements')" />
      <Button icon="pi pi-users" text rounded @click="$router.push('/groups')" />
      <Button icon="pi pi-plus" rounded @click="$router.push('/agreements/create')" />
      <Button icon="pi pi-bell" text rounded @click="$router.push('/notifications')" />
      <Button icon="pi pi-user" text rounded @click="$router.push('/profile')" />
    </div>
  </div>
</template>

<script setup>
import { computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import Menubar from 'primevue/menubar'
import Button from 'primevue/button'
import Select from 'primevue/select'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()

const items = computed(() => [
  { label: 'Согласования', icon: 'pi pi-inbox', command: () => router.push('/agreements') },
  { label: 'Создать', icon: 'pi pi-plus', command: () => router.push('/agreements/create') },
  { label: 'Группы', icon: 'pi pi-users', command: () => router.push('/groups') },
  { label: 'Уведомления', icon: 'pi pi-bell', command: () => router.push('/notifications') },
])

watch(
  () => auth.user?.theme,
  (theme) => {
    document.documentElement.classList.toggle('app-dark', theme === 'dark')
  },
  { immediate: true },
)
</script>
