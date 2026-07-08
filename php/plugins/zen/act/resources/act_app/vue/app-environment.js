import { computed, reactive, readonly } from 'vue'

const MOBILE_UA_PATTERN = /android|iphone|ipad|ipod|mobile|telegram/i
const TELEGRAM_UA_PATTERN = /telegram/i
const TELEGRAM_PARAM_PREFIX = 'tgWebApp'

const state = reactive({
  isMobile: false,
  isDesktop: true,
  isStandalone: false,
  isTelegram: false,
  isBrowser: true,
  isIos: false,
  isAndroid: false,
  isChrome: false,
  isSafari: false,
  hasCoarsePointer: false,
  viewportWidth: 0,
  viewportHeight: 0,
})

let initialized = false
let cleanup = null

function getNavigator() {
  return typeof window === 'undefined' ? null : window.navigator
}

function getUserAgent() {
  return getNavigator()?.userAgent ?? ''
}

function getTelegramWebApp() {
  return typeof window === 'undefined' ? null : window.Telegram?.WebApp ?? null
}

function getLaunchParams() {
  if (typeof window === 'undefined') {
    return new URLSearchParams()
  }

  const params = new URLSearchParams(window.location.search)
  const hash = window.location.hash.startsWith('#')
    ? window.location.hash.slice(1)
    : window.location.hash
  const hashParams = new URLSearchParams(hash)

  hashParams.forEach((value, key) => {
    if (!params.has(key)) {
      params.set(key, value)
    }
  })

  return params
}

function hasTelegramLaunchParams() {
  const params = getLaunchParams()
  return Array.from(params.keys()).some((key) => key.startsWith(TELEGRAM_PARAM_PREFIX))
}

export function shouldLoadTelegramSdk() {
  const tg = getTelegramWebApp()
  if (tg) {
    return true
  }

  return hasTelegramLaunchParams() || TELEGRAM_UA_PATTERN.test(getUserAgent())
}

export function detectTelegramWebApp() {
  const tg = getTelegramWebApp()
  if (tg?.platform && tg.platform !== 'unknown') {
    return true
  }

  return hasTelegramLaunchParams()
}

function setRootClass(name, enabled) {
  document.documentElement.classList.toggle(name, enabled)
}

function applyRootClasses() {
  if (typeof document === 'undefined') {
    return
  }

  setRootClass('app-env--browser', state.isBrowser)
  setRootClass('app-env--mobile', state.isMobile)
  setRootClass('app-env--desktop', state.isDesktop)
  setRootClass('app-env--standalone', state.isStandalone)
  setRootClass('app-env--telegram', state.isTelegram)
  setRootClass('telegram-webapp', state.isTelegram)
}

export function updateAppEnvironment() {
  if (typeof window === 'undefined') {
    return state
  }

  const ua = getUserAgent()
  const coarsePointer = window.matchMedia?.('(pointer: coarse)').matches ?? false
  const narrowViewport = window.innerWidth <= 767
  const standalone =
    window.matchMedia?.('(display-mode: standalone)').matches ||
    getNavigator()?.standalone === true

  state.viewportWidth = window.innerWidth
  state.viewportHeight = window.innerHeight
  state.hasCoarsePointer = coarsePointer
  state.isIos = /iphone|ipad|ipod/i.test(ua)
  state.isAndroid = /android/i.test(ua)
  state.isChrome = /chrome|crios/i.test(ua) && !/edg|opr|firefox/i.test(ua)
  state.isSafari = /safari/i.test(ua) && !/chrome|crios|fxios|edgios|android/i.test(ua)
  state.isStandalone = Boolean(standalone)
  state.isTelegram = detectTelegramWebApp() || TELEGRAM_UA_PATTERN.test(ua)
  state.isMobile = state.isTelegram || coarsePointer || narrowViewport || MOBILE_UA_PATTERN.test(ua)
  state.isDesktop = !state.isMobile
  state.isBrowser = !state.isTelegram

  applyRootClasses()

  return state
}

export function initAppEnvironment() {
  if (initialized || typeof window === 'undefined') {
    return state
  }

  initialized = true
  updateAppEnvironment()

  const onChange = () => updateAppEnvironment()
  window.addEventListener('resize', onChange)
  window.addEventListener('orientationchange', onChange)

  const standaloneQuery = window.matchMedia?.('(display-mode: standalone)')
  standaloneQuery?.addEventListener?.('change', onChange)

  cleanup = () => {
    window.removeEventListener('resize', onChange)
    window.removeEventListener('orientationchange', onChange)
    standaloneQuery?.removeEventListener?.('change', onChange)
  }

  return state
}

export function disposeAppEnvironment() {
  cleanup?.()
  cleanup = null
  initialized = false
}

export function useAppEnvironment() {
  if (!initialized) {
    initAppEnvironment()
  }

  return {
    environment: readonly(state),
    isPhone: computed(() => state.isMobile),
    isTelegram: computed(() => state.isTelegram),
    isStandalone: computed(() => state.isStandalone),
  }
}
