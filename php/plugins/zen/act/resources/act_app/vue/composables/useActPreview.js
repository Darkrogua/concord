import { computed, ref } from 'vue'
import { useAppState } from '../app-state'

const storageKey = (actId) => `act_preview_as_${actId}`

const isPrincipal = (login) => login === '@public' || login === '@authenticated'

const pickDefaultAudience = (audiences) => {
  const realUser = audiences.find((item) => item.login && !isPrincipal(item.login))
  if (realUser) {
    return realUser.login
  }
  const pub = audiences.find((item) => item.login === '@public')
  return pub?.login || audiences[0]?.login || '@public'
}

export function useActPreview(actIdRef, { isPreview }) {
  const { state, api } = useAppState()
  const previewAs = ref('')
  const audiences = ref([])
  const audiencesLoading = ref(false)
  const readyActId = ref('')

  const loadAudiences = (actId) => new Promise((resolve) => {
    audiencesLoading.value = true
    api({
      api: `Access:previewAudiences?act_id=${encodeURIComponent(actId)}`,
      then: (res) => {
        audiencesLoading.value = false
        if (res?.ok && Array.isArray(res.data?.audiences)) {
          audiences.value = res.data.audiences
          resolve(audiences.value)
          return
        }
        audiences.value = []
        resolve([])
      },
      catch: () => {
        audiencesLoading.value = false
        audiences.value = []
        resolve([])
      },
    })
  })

  const ensureReady = async (actId) => {
    if (!actId) {
      return
    }

    const stored = sessionStorage.getItem(storageKey(actId))
    if (stored) {
      previewAs.value = stored
    }

    if (readyActId.value !== actId || audiences.value.length === 0) {
      const list = await loadAudiences(actId)
      readyActId.value = actId

      if (stored && list.some((item) => item.login === stored)) {
        previewAs.value = stored
      } else {
        const next = pickDefaultAudience(list)
        previewAs.value = next
        sessionStorage.setItem(storageKey(actId), next)
      }
    } else if (stored && audiences.value.some((item) => item.login === stored)) {
      previewAs.value = stored
    }
  }

  const setPreviewAs = (login) => {
    const actId = typeof actIdRef === 'function' ? actIdRef() : actIdRef.value
    previewAs.value = login
    if (actId) {
      sessionStorage.setItem(storageKey(actId), login)
    }
  }

  const querySuffix = () => {
    const asViewer = encodeURIComponent(previewAs.value || '@public')
    return `&front_view=1&as_viewer=${asViewer}`
  }

  const viewAsLogin = computed(() => {
    if (!isPreview.value) {
      return ''
    }
    const value = previewAs.value
    if (value === '@public') {
      return ''
    }
    if (value === '@authenticated') {
      return String(state.user?.login || state.user?.username || '').trim()
    }
    return value
  })

  return {
    previewAs,
    audiences,
    audiencesLoading,
    ensureReady,
    setPreviewAs,
    querySuffix,
    viewAsLogin,
  }
}
