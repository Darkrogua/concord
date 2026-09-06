import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  { path: '/', name: 'landing', component: () => import('../views/LandingView.vue'), meta: { public: true } },
  { path: '/login', component: () => import('../views/LoginView.vue'), meta: { guest: true } },
  { path: '/register', component: () => import('../views/RegisterView.vue'), meta: { guest: true } },
  { path: '/forgot-password', component: () => import('../views/ForgotPasswordView.vue'), meta: { guest: true } },
  { path: '/reset-password', component: () => import('../views/ResetPasswordView.vue'), meta: { guest: true } },
  { path: '/share/:token', component: () => import('../views/PublicAgreementView.vue'), meta: { public: true } },
  {
    component: () => import('../layouts/AppLayout.vue'),
    meta: { auth: true },
    children: [
      { path: '/agreements', name: 'home', component: () => import('../views/AgreementsListView.vue') },
      { path: '/agreements/create', name: 'create', component: () => import('../views/AgreementFormView.vue') },
      { path: '/agreements/:id/edit', name: 'edit', component: () => import('../views/AgreementFormView.vue') },
      { path: '/agreements/:id', name: 'show', component: () => import('../views/AgreementDetailView.vue') },
      { path: '/notifications', name: 'notifications', component: () => import('../views/NotificationsView.vue') },
      { path: '/profile', name: 'profile', component: () => import('../views/ProfileView.vue') },
      { path: '/groups', name: 'groups', component: () => import('../views/GroupsView.vue') },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.ready) {
    await auth.fetchUser()
  }
  if (to.meta.auth && !auth.isAuthenticated) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }
  if (to.meta.guest && auth.isAuthenticated) {
    return { path: '/agreements' }
  }
  return true
})

export default router
