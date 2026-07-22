/**
 * Локальное превью Concord без Docker и бэкенда.
 * Открыть: npm run preview:concord → http://localhost:5173/concord-preview.html
 */
import { createApp } from 'vue'
import ConcordApp from '../vue/ConcordApp.vue'
import { initAppEnvironment } from '../vue/app-environment.js'
import { initPwaInstall, registerServiceWorker, watchAppBuild } from '../vue/pwa-install.js'
import '../scss/concord.scss'

initAppEnvironment()
initPwaInstall()
registerServiceWorker()
watchAppBuild()

createApp(ConcordApp).mount('#concord-app')
