import { ref, watch } from 'vue'
import { MOCK_AGREEMENTS, ensureAgreementSections } from '../concord/mock-agreements.js'
import { syncSectionVotingStatsFromVotes } from '../concord/agreement-results-utils.js'

const STORAGE_KEY = 'concord_agreements_v16'
const PERSIST_DEBOUNCE_MS = 350

const bundledPhotoPreviews = new Map()
for (const agreement of MOCK_AGREEMENTS) {
  for (const section of agreement.sections || []) {
    for (const block of section.blocks || []) {
      for (const photo of block.photos || []) {
        if (photo.id && photo.previewUrl) {
          bundledPhotoPreviews.set(photo.id, photo.previewUrl)
        }
      }
    }
  }
}

function cloneAgreement(item) {
  return {
    ...item,
    author: item.author ? { ...item.author } : undefined,
    participants: (item.participants || []).map((participant) => ({ ...participant })),
    sections: (item.sections || []).map((section) => ({
      ...section,
      blocks: (section.blocks || []).map((block) => ({
        ...block,
        files: block.files ? block.files.map((file) => ({ ...file })) : undefined,
        photos: block.photos ? block.photos.map((photo) => ({ ...photo })) : undefined,
        items: block.items ? block.items.map((entry) => ({ ...entry })) : undefined,
      })),
      participantIds: section.participantIds ? [...section.participantIds] : undefined,
      groupIds: section.groupIds ? [...section.groupIds] : undefined,
      voterIds: section.voterIds ? [...section.voterIds] : undefined,
      settings: section.settings ? { ...section.settings } : undefined,
      votingStats: section.votingStats ? { ...section.votingStats } : undefined,
      votes: (section.votes || []).map((vote) => ({ ...vote })),
      userVote: section.userVote ? { ...section.userVote } : undefined,
    })),
  }
}

function stripHeavyBlockContent(block) {
  return {
    ...block,
    photos: (block.photos || []).map((photo) => ({
      id: photo.id,
      name: photo.name || '',
      comment: photo.comment || '',
    })),
    files: (block.files || []).map((file) => ({
      id: file.id,
      name: file.name || '',
      mime: file.mime || '',
      comment: file.comment || '',
      size: file.size,
    })),
  }
}

function serializeBlockForStorage(block, { omitBodies = false } = {}) {
  if (omitBodies) {
    return {
      id: block.id,
      type: block.type,
      label: block.label,
      title: block.title,
    }
  }
  return stripHeavyBlockContent(block)
}

function serializeAgreementsForStorage(agreements, options = {}) {
  const omitBodies = options.omitBodies === true
  return agreements.map((agreement) => ({
    ...agreement,
    sections: (agreement.sections || []).map((section) => ({
      ...section,
      blocks: (section.blocks || []).map((block) => serializeBlockForStorage(block, { omitBodies })),
    })),
  }))
}

function restoreBundledPhotoPreviews(agreement) {
  for (const section of agreement.sections || []) {
    for (const block of section.blocks || []) {
      for (const photo of block.photos || []) {
        if (!photo.previewUrl && bundledPhotoPreviews.has(photo.id)) {
          photo.previewUrl = bundledPhotoPreviews.get(photo.id)
        }
      }
    }
  }
}

function normalizeLoadedAgreements(items) {
  return items.map((item) => {
    const agreement = cloneAgreement(item)
    ensureAgreementSections(agreement)
    for (const section of agreement.sections || []) {
      if ((section.votes || []).some((vote) => vote?.participantId)) {
        syncSectionVotingStatsFromVotes(section)
      }
    }
    restoreBundledPhotoPreviews(agreement)
    return agreement
  })
}

function loadAgreements() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) {
      return normalizeLoadedAgreements(MOCK_AGREEMENTS)
    }
    const parsed = JSON.parse(raw)
    if (!Array.isArray(parsed) || parsed.length === 0) {
      return normalizeLoadedAgreements(MOCK_AGREEMENTS)
    }
    return normalizeLoadedAgreements(parsed)
  } catch {
    return normalizeLoadedAgreements(MOCK_AGREEMENTS)
  }
}

function scheduleLeanStorageRewrite(agreements) {
  if (typeof window === 'undefined') {
    return
  }
  window.setTimeout(() => {
    persistAgreements(agreements)
  }, 0)
}

function persistAgreements(agreements) {
  if (typeof localStorage === 'undefined') {
    return true
  }

  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(serializeAgreementsForStorage(agreements)))
    return true
  } catch (error) {
    try {
      localStorage.setItem(
        STORAGE_KEY,
        JSON.stringify(serializeAgreementsForStorage(agreements, { omitBodies: true }))
      )
      console.warn(
        '[concord] Черновик сохранён без содержимого блоков: localStorage переполнен.',
        error
      )
      return true
    } catch (fallbackError) {
      console.warn('[concord] Не удалось сохранить черновик в localStorage.', fallbackError)
      return false
    }
  }
}

export function useConcordAgreements() {
  const agreements = ref(loadAgreements())
  const loading = ref(true)

  if (typeof window !== 'undefined') {
    scheduleLeanStorageRewrite(agreements.value)
    window.requestAnimationFrame(() => {
      window.setTimeout(() => {
        loading.value = false
      }, 120)
    })
  } else {
    loading.value = false
  }

  let persistTimer = null

  function flushPersist() {
    clearTimeout(persistTimer)
    persistTimer = null
    return persistAgreements(agreements.value)
  }

  function persist() {
    return flushPersist()
  }

  function schedulePersist(delay = PERSIST_DEBOUNCE_MS) {
    clearTimeout(persistTimer)
    persistTimer = setTimeout(() => {
      persistTimer = null
      persistAgreements(agreements.value)
    }, delay)
  }

  watch(
    agreements,
    () => {
      schedulePersist(PERSIST_DEBOUNCE_MS)
    },
    { deep: true }
  )

  if (typeof window !== 'undefined') {
    window.addEventListener('beforeunload', flushPersist)
    window.addEventListener('pagehide', flushPersist)
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'hidden') {
        flushPersist()
      }
    })
  }

  return { agreements, loading, persist, flushPersist, schedulePersist }
}
