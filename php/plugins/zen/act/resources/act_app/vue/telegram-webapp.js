import { ref } from 'vue'
import { detectTelegramWebApp, shouldLoadTelegramSdk, updateAppEnvironment } from './app-environment.js'

const TELEGRAM_SDK_URL = 'https://telegram.org/js/telegram-web-app.js'
const SDK_TIMEOUT_MS = 3000

const inTelegram = ref(false)
let sdkPromise = null
let backButtonBound = false

function getWebApp() {
  return window.Telegram?.WebApp ?? null
}

/**
 * Подгружает SDK Telegram без блокировки страницы (важно для локалки без доступа к telegram.org).
 * В Mini App клиент часто уже инжектит window.Telegram до загрузки скрипта.
 */
export function ensureTelegramSdk() {
  if (typeof window === 'undefined') {
    return Promise.resolve(false)
  }
  if (getWebApp()) {
    return Promise.resolve(true)
  }
  if (!shouldLoadTelegramSdk()) {
    return Promise.resolve(false)
  }
  if (sdkPromise) {
    return sdkPromise
  }

  sdkPromise = new Promise((resolve) => {
    let settled = false
    const finish = (ok) => {
      if (settled) {
        return
      }
      settled = true
      resolve(Boolean(ok))
    }

    const script = document.createElement('script')
    script.src = TELEGRAM_SDK_URL
    script.async = true

    const timer = window.setTimeout(() => {
      script.remove()
      finish(getWebApp())
    }, SDK_TIMEOUT_MS)

    script.onload = () => {
      window.clearTimeout(timer)
      finish(getWebApp())
    }
    script.onerror = () => {
      window.clearTimeout(timer)
      script.remove()
      finish(false)
    }

    document.head.appendChild(script)
  })

  return sdkPromise
}

/**
 * Открыто внутри Telegram (Mini App). В обычном браузере — false.
 */
export function isTelegramWebApp() {
  if (inTelegram.value) {
    return true
  }

  return detectTelegramWebApp() || shouldLoadTelegramSdk()
}

function applyTelegramTheme(tg) {
  const root = document.documentElement
  const params = tg.themeParams ?? {}

  if (params.bg_color) {
    root.style.setProperty('--tg-bg-page', params.bg_color)
  }
  if (params.secondary_bg_color) {
    root.style.setProperty('--tg-bg', params.secondary_bg_color)
    root.style.setProperty('--tg-bg-secondary', params.secondary_bg_color)
  }
  if (params.text_color) {
    root.style.setProperty('--tg-text', params.text_color)
  }
  if (params.hint_color) {
    root.style.setProperty('--tg-hint', params.hint_color)
  }
  if (params.link_color) {
    root.style.setProperty('--tg-accent', params.link_color)
    root.style.setProperty('--tg-tab-active', params.link_color)
  }
  if (params.button_color) {
    root.style.setProperty('--tg-header', params.button_color)
  }

  root.classList.toggle('telegram-webapp--dark', tg.colorScheme === 'dark')

  const themeColor = params.button_color || params.bg_color
  if (themeColor) {
    const meta = document.querySelector('meta[name="theme-color"]')
    meta?.setAttribute('content', themeColor)
  }
}

/**
 * Инициализация Mini App: ready, expand, тема Telegram.
 * Вызывать один раз при старте (до mount Vue).
 */
export function initTelegramWebApp() {
  const tg = getWebApp()
  if (!tg?.platform || tg.platform === 'unknown') {
    updateAppEnvironment()
    return null
  }

  inTelegram.value = true
  updateAppEnvironment()

  tg.ready?.()
  tg.expand?.()

  if (typeof tg.disableVerticalSwipes === 'function') {
    tg.disableVerticalSwipes()
  }

  applyTelegramTheme(tg)
  tg.onEvent?.('themeChanged', () => applyTelegramTheme(tg))

  return tg
}

function routeShowsBackButton(route) {
  const name = route.name
  return name === 'act' || name === 'auth'
}

/**
 * Нативная кнопка «Назад» Telegram на вложенных экранах.
 */
export function bindTelegramBackButton(router) {
  const tg = getWebApp()
  if (backButtonBound || !tg?.BackButton || !isTelegramWebApp()) {
    return
  }

  backButtonBound = true

  const sync = () => {
    if (routeShowsBackButton(router.currentRoute.value)) {
      tg.BackButton.show()
    } else {
      tg.BackButton.hide()
    }
  }

  tg.BackButton.onClick(() => {
    if (window.history.length > 1) {
      router.back()
    } else {
      router.push({ name: 'acts' })
    }
  })

  router.afterEach(sync)
  sync()
}

export function useTelegramWebApp() {
  return {
    isTelegram: inTelegram,
    webApp: getWebApp,
  }
}
