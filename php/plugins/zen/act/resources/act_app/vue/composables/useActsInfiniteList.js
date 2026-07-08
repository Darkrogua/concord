import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useAppState } from '../app-state'

function buildQueryString(params) {
  const search = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && String(value) !== '') {
      search.set(key, String(value))
    }
  })
  const query = search.toString()
  return query ? `?${query}` : ''
}

export function useActsInfiniteList(getFilterParams) {
  const { api } = useAppState()
  const acts = ref([])
  const cursor = ref('')
  const hasMore = ref(true)
  const loading = ref(false)
  const loadingMore = ref(false)
  const error = ref('')
  const initialLoaded = ref(false)
  const sentinel = ref(null)
  let observer = null
  let requestToken = 0

  const fetchPage = (mode) => new Promise((resolve) => {
    const token = ++requestToken
    const params = {
      ...getFilterParams(),
      limit: 20,
    }
    if (mode === 'more' && cursor.value) {
      params.cursor = cursor.value
    }

    api({
      api: `Acts:list${buildQueryString(params)}`,
      then: (res) => {
        if (token !== requestToken) {
          resolve(false)
          return
        }
        if (!res?.ok) {
          error.value = res?.errors?.[0]?.message || 'Не удалось загрузить акты'
          resolve(false)
          return
        }

        const items = res.data?.acts || []
        const page = res.data?.page || {}
        if (mode === 'more') {
          acts.value = [...acts.value, ...items]
        } else {
          acts.value = items
        }
        cursor.value = page.next_cursor || ''
        hasMore.value = Boolean(page.has_more)
        error.value = ''
        resolve(true)
      },
      catch: () => {
        if (token !== requestToken) {
          resolve(false)
          return
        }
        error.value = 'Ошибка сети'
        resolve(false)
      },
    })
  })

  const loadFirst = async () => {
    loading.value = true
    loadingMore.value = false
    cursor.value = ''
    hasMore.value = true
    const ok = await fetchPage('first')
    loading.value = false
    initialLoaded.value = true
    return ok
  }

  const loadMore = async () => {
    if (loading.value || loadingMore.value || !hasMore.value) {
      return false
    }
    loadingMore.value = true
    const ok = await fetchPage('more')
    loadingMore.value = false
    return ok
  }

  const prependAct = (act) => {
    if (!act?.id) {
      return
    }
    const exists = acts.value.some((item) => item.id === act.id)
    if (!exists) {
      acts.value = [act, ...acts.value]
    }
  }

  const setupObserver = () => {
    if (observer) {
      observer.disconnect()
    }
    if (!sentinel.value) {
      return
    }
    observer = new IntersectionObserver((entries) => {
      if (entries.some((entry) => entry.isIntersecting)) {
        loadMore()
      }
    }, {
      root: null,
      rootMargin: '200px',
      threshold: 0,
    })
    observer.observe(sentinel.value)
  }

  onMounted(() => {
    setupObserver()
  })

  onBeforeUnmount(() => {
    if (observer) {
      observer.disconnect()
      observer = null
    }
  })

  const reconnectObserver = () => {
    setupObserver()
  }

  return {
    acts,
    hasMore,
    loading,
    loadingMore,
    error,
    initialLoaded,
    sentinel,
    loadFirst,
    loadMore,
    prependAct,
    reconnectObserver,
  }
}
