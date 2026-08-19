<template>
  <article class="concord-search-result" :class="cardModifiers">
    <button type="button" class="concord-search-result__button" @click="$emit('open', result)">
      <div class="concord-search-result__header">
        <span class="concord-search-result__number">#{{ agreement.number }}</span>
        <span class="concord-search-result__status">{{ statusLabel }}</span>
        <span v-if="agreement.deadline" class="concord-search-result__deadline">до {{ agreement.deadline }}</span>
      </div>

      <h2 class="concord-search-result__title">
        <template v-for="(part, index) in highlightedTitle" :key="index">
          <mark v-if="part.match" class="concord-search-result__highlight">{{ part.text }}</mark>
          <template v-else>{{ part.text }}</template>
        </template>
      </h2>

      <p v-if="match" class="concord-search-result__context">
        <span class="concord-search-result__context-label">{{ match.label }}</span>
        <span class="concord-search-result__context-text">
          <template v-for="(part, index) in highlightedPreview" :key="index">
            <mark v-if="part.match" class="concord-search-result__highlight">{{ part.text }}</mark>
            <template v-else>{{ part.text }}</template>
          </template>
        </span>
      </p>

      <div class="concord-search-result__footer">
        <div class="concord-card__progress" aria-hidden="true">
          <div class="concord-card__progress-track">
            <div class="concord-card__progress-fill" :style="{ width: `${progressPercent}%` }" />
          </div>
        </div>

        <span class="concord-card__count" aria-label="Прогресс голосования">
          <span class="concord-card__count-voted">{{ votedCount }}</span><span class="concord-card__count-sep"> из </span><span class="concord-card__count-total">{{ participantsCount }}</span>
        </span>
      </div>

      <svg class="concord-search-result__arrow" viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
        <path d="m9 6 6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
  </article>
</template>

<script>
import {
  resolveSectionVotersCount,
  sectionHasConfiguredParticipants,
} from './mock-groups.js'

function highlightParts(value, query) {
  const text = String(value || '')
  const needle = String(query || '').trim()
  if (!needle) {
    return [{ text, match: false }]
  }

  const parts = []
  const lowerText = text.toLocaleLowerCase()
  const lowerNeedle = needle.toLocaleLowerCase()
  let position = 0
  let index = lowerText.indexOf(lowerNeedle, position)

  while (index !== -1) {
    if (index > position) {
      parts.push({ text: text.slice(position, index), match: false })
    }
    parts.push({ text: text.slice(index, index + needle.length), match: true })
    position = index + needle.length
    index = lowerText.indexOf(lowerNeedle, position)
  }

  if (position < text.length) {
    parts.push({ text: text.slice(position), match: false })
  }
  return parts.length ? parts : [{ text, match: false }]
}

function getSectionVotersTotal(section, groups, contacts) {
  const live = resolveSectionVotersCount(section, groups, contacts)
  const snapshot = Array.isArray(section?.voterIds) ? section.voterIds.length : 0
  const stored = Number(section?.total) || 0
  const groupIds = section?.groupIds || []
  const hasMissingGroups = groupIds.some(
    (groupId) => !groups.some((group) => group.id === groupId)
  )

  if (hasMissingGroups) {
    return Math.max(live, snapshot, stored)
  }
  if (sectionHasConfiguredParticipants(section) || live || snapshot || stored) {
    return live || snapshot || stored
  }
  return 0
}

function getAgreementMetrics(agreement, groups, contacts) {
  let total = 0
  let voted = 0

  for (const section of agreement?.sections || []) {
    const sectionTotal = getSectionVotersTotal(section, groups, contacts)
    total += sectionTotal

    const sectionVoted = (section.votes || []).filter((vote) => vote?.participantId).length
    voted += sectionVoted || Number(section.voted) || 0
  }

  if (!total) {
    total = agreement?.total || agreement?.participants?.length || 0
    voted = agreement?.voted || 0
  }

  return {
    total,
    voted: Math.min(total, voted),
  }
}

function progressFor(agreement, groups, contacts) {
  const metrics = getAgreementMetrics(agreement, groups, contacts)
  if (!metrics.total) {
    return 0
  }
  if (agreement.isOwner && agreement.status === 'approved') {
    return 100
  }
  if (agreement.status === 'approved' || agreement.status === 'completed') {
    return 100
  }
  return Math.min(100, Math.round((metrics.voted / metrics.total) * 100))
}

export default {
  name: 'ConcordSearchResultCard',
  props: {
    result: {
      type: Object,
      required: true,
    },
    query: {
      type: String,
      default: '',
    },
    groups: {
      type: Array,
      default: () => [],
    },
    contacts: {
      type: Array,
      default: () => [],
    },
  },
  emits: ['open'],
  computed: {
    agreement() {
      return this.result.agreement
    },
    match() {
      return this.result.match
    },
    statusLabel() {
      if (this.agreement.status === 'draft') {
        return 'Черновик'
      }
      if (this.agreement.status === 'approved' || this.agreement.status === 'completed') {
        return 'Согласовано'
      }
      return this.agreement.isOwner ? 'Создано мной' : 'Ждёт решения'
    },
    highlightedTitle() {
      return highlightParts(this.agreement.title, this.query)
    },
    highlightedPreview() {
      return highlightParts(this.match?.preview, this.query)
    },
    participantsCount() {
      return getAgreementMetrics(this.agreement, this.groups, this.contacts).total
    },
    votedCount() {
      return getAgreementMetrics(this.agreement, this.groups, this.contacts).voted
    },
    progressPercent() {
      return progressFor(this.agreement, this.groups, this.contacts)
    },
    progressTone() {
      if (this.agreement.status === 'draft' || !this.agreement.createdAt) {
        return 'draft'
      }
      if (
        (this.agreement.isOwner && this.agreement.status === 'approved')
        || this.agreement.status === 'approved'
        || this.agreement.status === 'completed'
      ) {
        return 'done'
      }
      if (this.agreement.isOwner) {
        return 'owner'
      }
      return 'participant'
    },
    cardModifiers() {
      return [`concord-card--progress-${this.progressTone}`]
    },
  },
}
</script>
