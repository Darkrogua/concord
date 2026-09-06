<template>
  <div class="layout-shell">
    <a class="skip-link" href="#content">К содержанию</a>
    <header class="topbar">
      <router-link class="brand" to="/agreements" translate="no">Concord</router-link>
      <nav class="desktop-nav" aria-label="Основное">
        <router-link class="nav-link" to="/agreements">Согласования</router-link>
        <router-link class="nav-link" to="/agreements/create">Создать</router-link>
        <router-link class="nav-link" to="/groups">Группы</router-link>
        <router-link class="nav-link" to="/notifications">Уведомления</router-link>
      </nav>
      <div class="topbar-end">
        <Select
          v-if="auth.user?.signatures?.length"
          :options="auth.user.signatures"
          optionLabel="name"
          optionValue="id"
          :modelValue="auth.activeSignature?.id"
          aria-label="Активная подпись"
          @update:modelValue="auth.activateSignature"
        />
        <router-link class="nav-link user-name" to="/profile">{{ auth.user?.name }}</router-link>
      </div>
    </header>

    <div id="content" class="layout-body">
      <router-view />
    </div>

    <nav class="mobile-nav" aria-label="Мобильное">
      <router-link to="/agreements" aria-label="Согласования"><i class="pi pi-inbox" aria-hidden="true" /></router-link>
      <router-link to="/groups" aria-label="Группы"><i class="pi pi-users" aria-hidden="true" /></router-link>
      <router-link to="/agreements/create" aria-label="Создать"><i class="pi pi-plus" aria-hidden="true" /></router-link>
      <router-link to="/notifications" aria-label="Уведомления"><i class="pi pi-bell" aria-hidden="true" /></router-link>
      <router-link to="/profile" aria-label="Профиль"><i class="pi pi-user" aria-hidden="true" /></router-link>
    </nav>
  </div>
</template>

<script setup>
import { watch } from 'vue'
import Select from 'primevue/select'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()

watch(
  () => auth.user?.theme,
  (theme) => {
    document.documentElement.classList.toggle('app-dark', theme === 'dark')
  },
  { immediate: true },
)
</script>
