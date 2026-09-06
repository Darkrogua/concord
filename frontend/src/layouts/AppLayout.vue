<template>
  <div class="concord-app">
    <div class="concord-app__main">
      <router-view />
    </div>
    <ConcordBottomNav
      :active="navActive"
      :avatar-initial="avatarInitial"
      :profile-label="auth.user?.name || 'Профиль'"
      :agreements-badge="undefined"
      :notifications-badge="undefined"
      @navigate="onNavigate"
      @create="router.push('/agreements/create')"
      @notifications="router.push('/notifications')"
      @switch-account="router.push('/profile')"
    />
  </div>
</template>

<script setup>
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ConcordBottomNav from '../components/concord/ConcordBottomNav.vue'
import { useAuthStore } from '../stores/auth'
import { getPersonInitials } from '../components/concord/agreement-card-utils.js'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const navActive = computed(() => {
  if (route.path.startsWith('/profile') || route.path.startsWith('/groups')) return 'settings'
  return 'list'
})

const avatarInitial = computed(() => getPersonInitials(auth.user?.name || 'П'))

function onNavigate(view) {
  if (view === 'list') router.push('/agreements')
  if (view === 'settings') router.push('/profile')
}

watch(
  () => auth.user?.theme,
  (theme) => {
    document.documentElement.classList.toggle('app-dark', theme === 'dark')
  },
  { immediate: true },
)
</script>
