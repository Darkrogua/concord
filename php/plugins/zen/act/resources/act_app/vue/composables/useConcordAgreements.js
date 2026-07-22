import { ref, watch } from 'vue'
import { MOCK_AGREEMENTS, ensureAgreementSections } from '../concord/mock-agreements.js'

const STORAGE_KEY = 'concord_agreements_v7'

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
      settings: section.settings ? { ...section.settings } : undefined,
      votingStats: section.votingStats ? { ...section.votingStats } : undefined,
      votes: (section.votes || []).map((vote) => ({ ...vote })),
      userVote: section.userVote ? { ...section.userVote } : undefined,
    })),
  }
}

function normalizeLoadedAgreements(items) {
  return items.map((item) => {
    const agreement = cloneAgreement(item)
    ensureAgreementSections(agreement)
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

function persistAgreements(agreements) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(agreements))
  } catch {
    // ignore quota / private mode errors in preview
  }
}

export function useConcordAgreements() {
  const agreements = ref(loadAgreements())

  function persist() {
    persistAgreements(agreements.value)
  }

  watch(agreements, persist, { deep: true, flush: 'sync' })

  if (typeof window !== 'undefined') {
    window.addEventListener('beforeunload', persist)
    window.addEventListener('pagehide', persist)
  }

  return { agreements, persist }
}
