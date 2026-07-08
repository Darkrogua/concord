import { createApp } from 'vue'
import ActApp from '../vue/ActApp.vue'
import router from '../vue/router.js'
import { initAppEnvironment } from '../vue/app-environment.js'
import { initPwaInstall, registerServiceWorker, watchAppBuild } from '../vue/pwa-install.js'
import { bindTelegramBackButton, ensureTelegramSdk, initTelegramWebApp, isTelegramWebApp } from '../vue/telegram-webapp.js'
import '../scss/act_app.scss'

function bootstrap() {
  initAppEnvironment()
  initTelegramWebApp()

  if (!isTelegramWebApp()) {
    initPwaInstall()
    registerServiceWorker()
    watchAppBuild()
  }

  bindTelegramBackButton(router)
  createApp(ActApp).use(router).mount('#act-app')

  ensureTelegramSdk().then((loaded) => {
    if (!loaded) {
      return
    }
    initTelegramWebApp()
    bindTelegramBackButton(router)
  })
}

bootstrap()
