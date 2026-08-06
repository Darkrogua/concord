import { ref, watch } from 'vue'
import {
  DEFAULT_PROFILE_GROUPS,
  cloneProfileGroups,
  syncGroupMemberCount,
} from '../concord/mock-groups.js'

const STORAGE_KEY = 'concord_profile_groups_v1'

function normalizeGroups(groups) {
  if (!Array.isArray(groups) || groups.length === 0) {
    return cloneProfileGroups(DEFAULT_PROFILE_GROUPS)
  }
  return groups
    .filter((group) => group && typeof group === 'object' && group.id)
    .map((group) => syncGroupMemberCount({
      id: String(group.id),
      title: String(group.title || 'Группа').trim() || 'Группа',
      memberIds: Array.isArray(group.memberIds) ? group.memberIds.filter(Boolean).map(String) : [],
      memberCount: 0,
    }))
}

function loadGroups() {
  try {
    if (typeof localStorage === 'undefined') {
      return normalizeGroups(DEFAULT_PROFILE_GROUPS)
    }
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) {
      return normalizeGroups(DEFAULT_PROFILE_GROUPS)
    }
    const parsed = JSON.parse(raw)
    const normalized = normalizeGroups(parsed)
    return normalized.length ? normalized : normalizeGroups(DEFAULT_PROFILE_GROUPS)
  } catch {
    return normalizeGroups(DEFAULT_PROFILE_GROUPS)
  }
}

function persistGroups(groups) {
  try {
    if (typeof localStorage === 'undefined') {
      return
    }
    localStorage.setItem(STORAGE_KEY, JSON.stringify(groups))
  } catch {
    // ignore quota / private mode errors in preview
  }
}

export function useConcordGroups() {
  const profileGroups = ref(loadGroups())

  function persist() {
    persistGroups(profileGroups.value)
  }

  watch(profileGroups, persist, { deep: true, flush: 'sync' })

  if (typeof window !== 'undefined') {
    window.addEventListener('beforeunload', persist)
    window.addEventListener('pagehide', persist)
  }

  return { profileGroups, persist }
}
