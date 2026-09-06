import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import ConcordTheme from './theme'
import App from './App.vue'
import router from './router'
import 'primeicons/primeicons.css'
import './style.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(PrimeVue, {
  license: import.meta.env.VITE_PRIMEUI_LICENSE,
  theme: {
    preset: ConcordTheme,
    options: {
      darkModeSelector: '.app-dark',
      cssLayer: false,
    },
  },
})
app.use(ToastService)
app.use(ConfirmationService)

app.mount('#app')
