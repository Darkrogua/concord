import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const AppLayout = () => import('../layouts/AppLayout.vue')

function appPage(path, name, loader) {
  return {
    path,
    component: AppLayout,
    meta: { auth: true },
    children: [{ path: '', name, component: loader }],
  }
}

const routes = [
  { path: '/', name: 'landing', component: () => import('../views/LandingView.vue'), meta: { public: true } },
  { path: '/login', component: () => import('../views/LoginView.vue'), meta: { guest: true } },
  { path: '/register', component: () => import('../views/RegisterView.vue'), meta: { guest: true } },
  { path: '/forgot-password', component: () => import('../views/ForgotPasswordView.vue'), meta: { guest: true } },
  { path: '/reset-password', component: () => import('../views/ResetPasswordView.vue'), meta: { guest: true } },
  { path: '/share/:token', component: () => import('../views/PublicAgreementView.vue'), meta: { public: true } },
  {
    path: '/agreements',
    component: AppLayout,
    meta: { auth: true },
    children: [
      { path: '', name: 'home', component: () => import('../views/AgreementsListView.vue') },
      { path: 'create', name: 'create', component: () => import('../views/AgreementFormView.vue') },
      { path: ':id/edit', name: 'edit', component: () => import('../views/AgreementFormView.vue') },
      { path: ':id', name: 'show', component: () => import('../views/AgreementDetailView.vue') },
    ],
  },
  appPage('/notifications', 'notifications', () => import('../views/NotificationsView.vue')),
  appPage('/profile', 'profile', () => import('../views/ProfileView.vue')),
  appPage('/groups', 'groups', () => import('../views/GroupsView.vue')),
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

  const isPublic = to.matched.some((record) => record.meta.public)
  const needsAuth = to.matched.some((record) => record.meta.auth)

  if (needsAuth && !isPublic && !auth.isAuthenticated) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }
  if (to.meta.guest && auth.isAuthenticated) {
    return { path: '/agreements' }
  }
  return true
})

export default router
