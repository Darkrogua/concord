<template>
  <nav class="tg-tabbar" aria-label="Навигация">
    <button
      type="button"
      :class="['tg-tabbar__item', { 'tg-tabbar__item--active': active === 'acts' }]"
      @click="goActs"
    >
      <svg class="tg-tabbar__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <rect x="3" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/>
        <rect x="14" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/>
        <rect x="3" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/>
        <rect x="14" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/>
      </svg>
      <span class="tg-tabbar__label">Акты</span>
    </button>
    <button
      type="button"
      :class="['tg-tabbar__item', { 'tg-tabbar__item--active': active === 'profile' }]"
      @click="goProfile"
    >
      <svg class="tg-tabbar__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.8"/>
        <path d="M5 20c1.5-3.5 4.5-5 7-5s5.5 1.5 7 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
      </svg>
      <span class="tg-tabbar__label">Профиль</span>
    </button>
  </nav>
</template>

<script>
import { useRouter } from 'vue-router'
import { useAppState } from '../app-state'

export default {
  name: 'BottomNav',
  props: {
    active: {
      type: String,
      default: 'acts',
    },
  },
  setup() {
    const router = useRouter()
    const { state } = useAppState()

    const goActs = () => router.push({ name: 'acts' })
    const goProfile = () => {
      const login = state.user?.login
      if (login) {
        router.push({ name: 'profile', params: { login } })
      }
    }

    return { goActs, goProfile }
  },
}
</script>
