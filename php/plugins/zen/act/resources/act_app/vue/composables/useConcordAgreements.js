import { ref, watch } from 'vue'
import { MOCK_AGREEMENTS, ensureAgreementSections } from '../concord/mock-agreements.js'

const STORAGE_KEY = 'concord_agreements_v14'

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

function serializeAgreementsForStorage(agreements) {
  return agreements.map((agreement) => ({
    ...agreement,
    sections: (agreement.sections || []).map((section) => ({
      ...section,
      blocks: (section.blocks || []).map(stripHeavyBlockContent),
    })),
  }))
}

function stripHeavyContentInPlace(agreement) {
  for (const section of agreement.sections || []) {
    for (const block of section.blocks || []) {
      for (const photo of block.photos || []) {
        if (photo.previewUrl) {
          delete photo.previewUrl
        }
      }
      for (const file of block.files || []) {
        delete file.downloadUrl
        delete file.previewUrl
        delete file.textContent
      }
    }
  }
}

function normalizeLoadedAgreements(items) {
  return items.map((item) => {
    const agreement = cloneAgreement(item)
    ensureAgreementSections(agreement)
    stripHeavyContentInPlace(agreement)
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
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(serializeAgreementsForStorage(agreements)))
  } catch {
    // ignore quota / private mode errors in preview
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

  function persist() {
    persistAgreements(agreements.value)
  }

  let persistTimer = null
  watch(agreements, () => {
    clearTimeout(persistTimer)
    persistTimer = setTimeout(() => {
      persistAgreements(agreements.value)
    }, 1200)
  }, { deep: true })

  if (typeof window !== 'undefined') {
    window.addEventListener('beforeunload', persist)
    window.addEventListener('pagehide', persist)
  }

  return { agreements, loading, persist }
}
