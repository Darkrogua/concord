import { createApp } from 'vue'
import ProcessApp from '../vue/ProcessApp.vue'
import { reactive } from 'vue'
import vueClickOutsideElement from 'vue-click-outside-element';
import FormFitter from '../../common/FormFitter.vue';
import FormSection from "../../components/FormSection.vue";
import FormTabs from "../../components/FormTabs.vue";
import PrimeVue from 'primevue/config'
import 'primevue/resources/themes/saga-blue/theme.css'
import 'primevue/resources/primevue.min.css'
import 'primeicons/primeicons.css'

window.process_app = {
    data: reactive({
        process: false,
    }),
}
const app = createApp(ProcessApp)

app.use(vueClickOutsideElement)
app.use(PrimeVue)
app.component('FormFitter', FormFitter)
app.component('FormSection', FormSection)
app.component('FormTabs', FormTabs)
app.mount('#chub-process-app')
