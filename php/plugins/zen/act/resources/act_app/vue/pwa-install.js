import { computed, ref } from 'vue'
import { registerSW } from 'virtual:pwa-register'
import { isTelegramWebApp } from './telegram-webapp.js'
import { useAppEnvironment } from './app-environment.js'

const ACT_BUILD_INFO_URL = '/plugins/zen/act/assets/build-info.json'
const BUILD_CHECK_INTERVAL_MS = 5 * 60 * 1000
const SW_UPDATE_INTERVAL_MS = 60 * 60 * 1000

function getCurrentBuildId() {
  if (typeof __CONCORD_BUILD_ID__ !== 'undefined' && __CONCORD_BUILD_ID__) {
    return __CONCORD_BUILD_ID__
  }

  if (typeof __ACT_BUILD_ID__ !== 'undefined' && __ACT_BUILD_ID__) {
    return __ACT_BUILD_ID__
  }

  return ''
}

function isConcordPreviewBuild() {
  return typeof __CONCORD_BUILD_ID__ !== 'undefined' && __CONCORD_BUILD_ID__ !== 'dev'
}

function getBuildInfoUrl() {
  if (isConcordPreviewBuild()) {
    const base = import.meta.env.BASE_URL || '/'
    return `${base}build-id.txt`
  }

  return ACT_BUILD_INFO_URL
}

const deferredPrompt = ref(null)
const isInstalled = ref(false)
let initialized = false
let swRegistration = null

function reloadForNewBuild() {
  window.location.reload()
}

function checkInstalled() {
  isInstalled.value =
    window.matchMedia('(display-mode: standalone)').matches ||
    window.navigator.standalone === true
}

export function registerServiceWorker() {
  if (isTelegramWebApp() || !('serviceWorker' in navigator) || import.meta.env.DEV) {
    return
  }

  registerSW({
    immediate: true,
    onRegisteredSW(_swUrl, registration) {
      if (!registration) {
        return
      }

      swRegistration = registration
      registration.update().catch(() => {})

      window.setInterval(() => {
        registration.update().catch(() => {})
      }, SW_UPDATE_INTERVAL_MS)
    },
  })
}

export function watchAppBuild() {
  if (isTelegramWebApp() || import.meta.env.DEV) {
    return
  }

  const currentBuildId = getCurrentBuildId()
  if (!currentBuildId || currentBuildId === 'dev') {
    return
  }

  const buildInfoUrl = getBuildInfoUrl()
  const concordPreview = isConcordPreviewBuild()

  const checkBuild = async () => {
    try {
      const response = await fetch(`${buildInfoUrl}?v=${Date.now()}`, {
        cache: 'no-store',
        credentials: 'same-origin',
      })
      if (!response.ok) {
        return
      }

      if (concordPreview) {
        const remoteBuildId = (await response.text()).trim()
        if (remoteBuildId && remoteBuildId !== currentBuildId) {
          reloadForNewBuild()
        }
        return
      }

      const info = await response.json()
      if (info?.buildId && info.buildId !== currentBuildId) {
        reloadForNewBuild()
      }
    } catch {
      // сеть недоступна — повторим позже
    }
  }

  window.setTimeout(checkBuild, 30_000)
  window.setInterval(checkBuild, BUILD_CHECK_INTERVAL_MS)

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState !== 'visible') {
      return
    }

    checkBuild()
    swRegistration?.update().catch(() => {})
  })
}

export function initPwaInstall() {
  if (initialized) {
    return
  }
  initialized = true

  checkInstalled()

  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault()
    deferredPrompt.value = event
  })

  window.addEventListener('appinstalled', () => {
    isInstalled.value = true
    deferredPrompt.value = null
  })
}

export function usePwaInstall() {
  if (!initialized) {
    initPwaInstall()
  }

  const { environment } = useAppEnvironment()
  const showInstallHint = ref(false)
  const installHintTitle = ref('')
  const installHintText = ref('')

  const isIosSafari = computed(() => environment.isIos && environment.isSafari)
  const isAndroidChrome = computed(() => environment.isAndroid && environment.isChrome)

  const canShowInstall = computed(() => {
    if (environment.isTelegram || isInstalled.value) {
      return false
    }
    return Boolean(deferredPrompt.value) || isIosSafari.value || isAndroidChrome.value
  })

  const canNativeInstall = computed(() => Boolean(deferredPrompt.value))

  const openHint = (title, text) => {
    installHintTitle.value = title
    installHintText.value = text
    showInstallHint.value = true
  }

  const install = async () => {
    if (deferredPrompt.value) {
      await deferredPrompt.value.prompt()
      await deferredPrompt.value.userChoice
      deferredPrompt.value = null
      checkInstalled()
      return
    }

    if (isIosSafari.value) {
      openHint(
        'Установить на главный экран',
        'Нажмите «Поделиться», затем «На экран Домой».'
      )
      return
    }

    if (!window.isSecureContext) {
      openHint(
        'Установка недоступна по HTTP',
        'Android Chrome показывает установку PWA только на HTTPS или localhost. Сейчас открыт обычный HTTP-адрес, поэтому системный диалог установки не появляется.'
      )
      return
    }

    if (isAndroidChrome.value) {
      openHint(
        'Установка ещё недоступна',
        'Chrome ещё не прислал событие установки. Обновите страницу, подождите несколько секунд или проверьте, что приложение ещё не установлено.'
      )
    }
  }

  const dismissInstallHint = () => {
    showInstallHint.value = false
  }

  return {
    canShowInstall,
    canNativeInstall,
    install,
    showInstallHint,
    installHintTitle,
    installHintText,
    dismissInstallHint,
  }
}
