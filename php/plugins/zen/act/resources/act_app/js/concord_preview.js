/**
 * Локальное превью Concord без Docker и бэкенда.
 * Открыть: npm run preview:concord → http://localhost:5173/concord-preview.html
 */
import { createApp } from 'vue'
import ConcordApp from '../vue/ConcordApp.vue'
import { initAppEnvironment } from '../vue/app-environment.js'
import { initPwaInstall, registerServiceWorker, watchAppBuild } from '../vue/pwa-install.js'
import { resetConcordScrollPosition } from '../vue/concord/scroll-top.js'
import '../scss/concord.scss'

if ('scrollRestoration' in window.history) {
  window.history.scrollRestoration = 'manual'
}

resetConcordScrollPosition()
window.addEventListener('pageshow', () => {
  resetConcordScrollPosition()
  window.requestAnimationFrame(resetConcordScrollPosition)
  window.setTimeout(resetConcordScrollPosition, 0)
})

initAppEnvironment()
initPwaInstall()
registerServiceWorker()
watchAppBuild()

createApp(ConcordApp).mount('#concord-app')
