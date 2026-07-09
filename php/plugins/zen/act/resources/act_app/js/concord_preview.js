/**
 * Локальное превью Concord без Docker и бэкенда.
 * Открыть: npm run preview:concord → http://localhost:5173/concord-preview.html
 */
import { createApp } from 'vue'
import ProjectsHomeView from '../vue/views/ProjectsHomeView.vue'
import '../scss/concord.scss'

createApp(ProjectsHomeView).mount('#concord-app')
