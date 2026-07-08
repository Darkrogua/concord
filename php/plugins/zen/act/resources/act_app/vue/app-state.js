import { reactive, readonly } from 'vue'
import { actApi } from '../../common/js/act-api'

const api = actApi()

const state = reactive({
  booting: true,
  user: null,
})

function applyUser(user) {
  state.user = user || null
  if (user) {
    localStorage.setItem('act_user_login', user.login)
  } else {
    localStorage.removeItem('act_user_login')
  }
}

export function useAppState() {
  return {
    state: readonly(state),
    loadUser() {
      return new Promise((resolve) => {
        state.booting = true
        api({
          api: 'Auth:me',
          then: (res) => {
            state.booting = false
            if (res?.ok && res.data?.user) {
              applyUser(res.data.user)
              resolve(state.user)
              return
            }
            applyUser(null)
            resolve(null)
          },
          catch: () => {
            state.booting = false
            applyUser(null)
            resolve(null)
          },
        })
      })
    },
    setUser(user) {
      applyUser(user)
    },
    clearUser() {
      applyUser(null)
      localStorage.removeItem('act_token')
    },
    logout() {
      const finish = () => {
        applyUser(null)
        localStorage.removeItem('act_token')
      }

      return new Promise((resolve) => {
        api({
          api: 'Auth:logout',
          data: {},
          then: () => {
            finish()
            resolve()
          },
          catch: () => {
            finish()
            resolve()
          },
        })
      })
    },
    api,
  }
}
