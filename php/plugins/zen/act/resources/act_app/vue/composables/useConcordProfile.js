import { computed, reactive, toValue, watch } from 'vue'
import { DEFAULT_PROFILE } from '../concord/mock-notifications.js'

const STORAGE_KEY = 'concord_profile_v1'

function cloneProfile(profile) {
  return { ...profile }
}

function loadProfiles() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) {
      return {}
    }
    const parsed = JSON.parse(raw)
    return parsed && typeof parsed === 'object' ? parsed : {}
  } catch {
    return {}
  }
}

function persistProfiles(profiles) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(profiles))
  } catch {
    // ignore quota / private mode errors in preview
  }
}

const profiles = reactive(loadProfiles())

watch(
  profiles,
  () => {
    persistProfiles({ ...profiles })
  },
  { deep: true }
)

function ensureProfile(accountId) {
  const id = String(accountId || '1')
  if (!profiles[id]) {
    profiles[id] = cloneProfile(DEFAULT_PROFILE)
    return profiles[id]
  }
  const defaults = cloneProfile(DEFAULT_PROFILE)
  for (const key of Object.keys(defaults)) {
    if (profiles[id][key] === undefined) {
      profiles[id][key] = defaults[key]
    }
  }
  return profiles[id]
}

function getAvatarInitial(profile) {
  const first = String(profile?.firstName || '').trim()
  const last = String(profile?.lastName || '').trim()
  const letter = first.charAt(0) || last.charAt(0) || profile?.avatarInitial || '?'
  return letter.toUpperCase()
}

export function useConcordProfile(accountId) {
  const profile = computed(() => ensureProfile(toValue(accountId)))

  watch(
    () => [toValue(accountId), profile.value.firstName, profile.value.lastName],
    () => {
      profile.value.avatarInitial = getAvatarInitial(profile.value)
    },
    { immediate: true }
  )

  function updateProfile(patch) {
    const current = ensureProfile(toValue(accountId))
    Object.assign(current, patch)
    current.avatarInitial = getAvatarInitial(current)
  }

  function setAvatar(dataUrl) {
    updateProfile({ avatarUrl: dataUrl })
  }

  function removeAvatar() {
    updateProfile({ avatarUrl: '' })
  }

  return {
    profile,
    updateProfile,
    setAvatar,
    removeAvatar,
  }
}
