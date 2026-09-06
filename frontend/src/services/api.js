import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

export async function csrf() {
  await api.get('/sanctum/csrf-cookie')
}

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const config = error.config
    if (error.response?.status === 419 && config && !config._csrfRetried) {
      config._csrfRetried = true
      await csrf()
      return api.request(config)
    }
    return Promise.reject(error)
  },
)

export function extractApiError(error, fallback = 'Ошибка запроса') {
  const data = error?.response?.data
  if (!data) return fallback

  if (data.errors) {
    const messages = Object.values(data.errors).flat().filter(Boolean)
    if (messages.length) return messages.join(' ')
  }

  if (data.message === 'CSRF token mismatch.') {
    return 'Сессия истекла. Обновите страницу и попробуйте снова.'
  }

  if (typeof data.message === 'string' && !data.message.startsWith('validation.')) {
    return data.message
  }

  return fallback
}

export default api
