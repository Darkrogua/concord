import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import api, { csrf } from '../services/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const ready = ref(false)

  const isAuthenticated = computed(() => Boolean(user.value))
  const activeSignature = computed(() => user.value?.active_signature)

  async function fetchUser() {
    try {
      const { data } = await api.get('/api/user')
      user.value = data.user
    } catch {
      user.value = null
    } finally {
      ready.value = true
    }
  }

  async function login(payload) {
    await csrf()
    const { data } = await api.post('/api/login', payload)
    user.value = data.user
  }

  async function register(payload) {
    await csrf()
    const { data } = await api.post('/api/register', payload)
    user.value = data.user
  }

  async function logout() {
    await api.post('/api/logout')
    user.value = null
  }

  async function forgotPassword(email) {
    await csrf()
    await api.post('/api/forgot-password', { email })
  }

  async function resetPassword(payload) {
    await csrf()
    await api.post('/api/reset-password', payload)
  }

  async function updateProfile(payload) {
    const { data } = await api.put('/api/user', payload)
    user.value = data.user
  }

  async function activateSignature(id) {
    await api.post(`/api/signatures/${id}/activate`)
    await fetchUser()
  }

  return {
    user,
    ready,
    isAuthenticated,
    activeSignature,
    fetchUser,
    login,
    register,
    logout,
    forgotPassword,
    resetPassword,
    updateProfile,
    activateSignature,
  }
})
