/**
 * Локальное превью Concord без Docker и бэкенда.
 * Открыть: npm run preview:concord → http://localhost:5173/concord-preview.html
 */
import { createApp } from 'vue'
import ConcordApp from '../vue/ConcordApp.vue'
import '../scss/concord.scss'

createApp(ConcordApp).mount('#concord-app')
