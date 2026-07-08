<template>
  <div :class="appClasses">
    <div v-if="state.booting" class="tg-boot-splash" aria-busy="true" aria-live="polite">
      <AsyncRegionSkeleton pattern="default" />
    </div>
    <router-view v-else v-slot="{ Component, route: viewRoute }">
      <Transition name="tg-view" mode="out-in">
        <component :is="Component" :key="viewRoute.fullPath" />
      </Transition>
    </router-view>
  </div>
</template>

<script>
import { computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAppState } from './app-state'
import { useAppEnvironment } from './app-environment'
import AsyncRegionSkeleton from './components/AsyncRegionSkeleton.vue'

export default {
  name: 'ActApp',
  components: { AsyncRegionSkeleton },
  setup() {
    const route = useRoute()
    const router = useRouter()
    const { state, loadUser } = useAppState()
    const { environment } = useAppEnvironment()

    const appClasses = computed(() => [
      'tg-app',
      {
        'tg-app--standalone': Boolean(route.meta.standalone) || environment.isStandalone,
        'tg-app--telegram': environment.isTelegram,
        'tg-app--browser': environment.isBrowser,
        'tg-app--mobile': environment.isMobile,
        'tg-app--desktop': environment.isDesktop,
      },
    ])

    const guardRoutes = async () => {
      if (state.booting) {
        return
      }

      if (route.meta.requiresAuth && !state.user) {
        await router.replace({ name: 'auth' })
        return
      }

      if (route.meta.guest && state.user) {
        await router.replace({ name: 'acts' })
      }
    }

    onMounted(async () => {
      await loadUser()
      await guardRoutes()
    })

    watch(
      () => [route.fullPath, state.user, state.booting],
      () => {
        guardRoutes()
      }
    )

    return {
      state,
      appClasses,
    }
  },
}
</script>
