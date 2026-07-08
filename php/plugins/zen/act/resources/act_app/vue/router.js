import { createRouter, createWebHistory } from 'vue-router'
import AuthView from './views/AuthView.vue'
import ActsHomeView from './views/ActsHomeView.vue'
import ProfileView from './views/ProfileView.vue'
import ActView from './views/ActView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/app',
      name: 'acts',
      component: ActsHomeView,
      meta: { requiresAuth: true, tab: 'acts' },
    },
    {
      path: '/app/profile/:login',
      name: 'profile',
      component: ProfileView,
      props: true,
      meta: { tab: 'profile' },
    },
    {
      path: '/act_:uuid',
      name: 'act',
      component: ActView,
      props: true,
      meta: { standalone: true },
    },
    {
      path: '/app/auth',
      name: 'auth',
      component: AuthView,
      meta: { guest: true },
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/app',
    },
  ],
})

export default router
