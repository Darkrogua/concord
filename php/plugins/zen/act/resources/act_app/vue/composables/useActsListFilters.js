import { computed, reactive, watch } from 'vue'
import {
  applyFilters,
  cloneFilters,
  defaultFilters,
  parseStoredFilters,
} from './filter-state.js'

const STORAGE_KEY = 'act_list_filters'

function persistFilters(filters) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(cloneFilters(filters)))
}

export function useActsListFilters() {
  const filters = reactive(parseStoredFilters(localStorage.getItem(STORAGE_KEY)))

  watch(filters, () => {
    persistFilters(filters)
  }, { deep: true })

  const activeCount = computed(() => {
    let count = 0
    if (filters.owners.enabled) count += 1
    if (filters.created.enabled) count += 1
    if (filters.sortCreated.enabled) count += 1
    if (filters.tags.enabled) count += 1
    return count
  })

  const hasActiveFilters = computed(() => activeCount.value > 0)

  const ownersSummary = computed(() => {
    if (!filters.owners.enabled || filters.owners.users.length === 0) {
      return 'Не задан'
    }
    const count = filters.owners.users.length
    if (count === 1) {
      return `@${filters.owners.users[0].login}`
    }
    return `${count} пользователя`
  })

  const createdSummary = computed(() => {
    if (!filters.created.enabled) {
      return 'Не задан'
    }
    const { from, to } = filters.created
    if (from && to) {
      return `${formatDateLabel(from)} – ${formatDateLabel(to)}`
    }
    if (from) {
      return `от ${formatDateLabel(from)}`
    }
    if (to) {
      return `до ${formatDateLabel(to)}`
    }
    return 'Не задан'
  })

  const sortSummary = computed(() => {
    if (!filters.sortCreated.enabled) {
      return 'Не задана'
    }
    return filters.sortCreated.order === 'asc' ? 'Сначала старые' : 'Сначала новые'
  })

  const tagsSummary = computed(() => {
    if (!filters.tags.enabled || filters.tags.items.length === 0) {
      return 'Не задан'
    }
    const parts = []
    filters.tags.items.forEach((tag, index) => {
      if (index > 0) {
        parts.push(filters.tags.ops[index - 1] === 'and' ? 'AND' : 'OR')
      }
      parts.push(tag)
    })
    return parts.join(' ')
  })

  function formatDateLabel(value) {
    const parts = String(value).split('-')
    if (parts.length !== 3) {
      return value
    }
    return `${parts[2]}.${parts[1]}.${parts[0]}`
  }

  function toQueryParams() {
    const params = {}
    if (filters.owners.enabled && filters.owners.users.length > 0) {
      params.owner_logins = filters.owners.users.map((u) => u.login).join(',')
    }
    if (filters.created.enabled) {
      if (filters.created.from) {
        params.created_from = filters.created.from
      }
      if (filters.created.to) {
        params.created_to = filters.created.to
      }
    }
    if (filters.sortCreated.enabled) {
      params.sort_created = filters.sortCreated.order
    }
    if (filters.tags.enabled && filters.tags.items.length > 0) {
      params.tags = filters.tags.items.join(',')
      if (filters.tags.ops.length > 0) {
        params.tag_ops = filters.tags.ops.join(',')
      }
    }
    return params
  }

  function resetAll() {
    applyFilters(filters, defaultFilters())
  }

  function setFilterEnabled(type, enabled) {
    if (type === 'owners') {
      filters.owners.enabled = enabled
      if (!enabled) {
        filters.owners.users = []
      }
    } else if (type === 'created') {
      filters.created.enabled = enabled
      if (!enabled) {
        filters.created.from = ''
        filters.created.to = ''
      }
    } else if (type === 'sortCreated') {
      filters.sortCreated.enabled = enabled
    } else if (type === 'tags') {
      filters.tags.enabled = enabled
      if (!enabled) {
        filters.tags.items = []
        filters.tags.ops = []
      }
    }
  }

  function replaceFilters(snapshot) {
    applyFilters(filters, snapshot)
  }

  return {
    filters,
    activeCount,
    hasActiveFilters,
    ownersSummary,
    createdSummary,
    sortSummary,
    tagsSummary,
    toQueryParams,
    resetAll,
    setFilterEnabled,
    replaceFilters,
  }
}

export { cloneFilters, defaultFilters, filtersEqual } from './filter-state.js'
