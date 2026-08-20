<template>
  <article
    class="concord-card"
    :class="[cardModifiers, { 'concord-card--new': isNew }]"
    :style="cardStyle"
    @click="$emit('open', agreement.id)"
  >
    <header
      :class="[
        'concord-card__header',
        {
          'concord-card__header--draft': isDraft,
          'concord-card__header--approved': isApprovedHeader,
          'concord-card__header--has-days': showDaysLabel,
        },
      ]"
    >
      <div class="concord-card__id">
        <span class="concord-card__number">#{{ agreement.number }}</span>
        <span class="concord-card__flame-slot" aria-hidden="true">
          <span
            v-if="showFlame"
            class="concord-card__flame"
            :title="urgencyTitle"
            aria-label="Срочное согласование"
          >
            <ConcordUrgencyFlame urgent />
          </span>
        </span>
      </div>

      <div class="concord-card__status-area">
        <span
          v-if="isDraft"
          class="concord-card__status-plate concord-card__status-plate--draft"
        >
          Черновик
        </span>

        <span
          v-else-if="isApprovedHeader"
          class="concord-card__status-plate concord-card__status-plate--approved"
        >
          {{ approvedHeaderText }}
        </span>

        <span v-else-if="agreement.isOwner" class="concord-card__status-pill concord-card__status-pill--owner">
          <span class="concord-card__status-label">Создано мной</span>
          <template v-if="agreement.deadline">
            <span class="concord-card__status-label">до</span>
            <span class="concord-card__status-date">{{ deadlineDate }}</span>
          </template>
        </span>

        <span v-else class="concord-card__status-pill concord-card__status-pill--waiting">
          <span class="concord-card__status-label">Ждёт решения до:</span>
          <span class="concord-card__status-date">{{ deadlineDate }}</span>
        </span>
      </div>

      <span
        v-if="showDaysLabel"
        :class="['concord-card__days', `concord-card__days--${daysLabelTone}`]"
      >
        ({{ daysLabelText }})
      </span>
    </header>

    <h2 class="concord-card__title">{{ agreement.title }}</h2>

    <div class="concord-card__info">
      <div class="concord-card__author">
        <span class="concord-card__author-avatar" aria-hidden="true">{{ authorInitials }}</span>
        <span class="concord-card__author-name">{{ agreement.author.name }}</span>
      </div>

      <div class="concord-card__participants" aria-label="Участники согласования">
        <span class="concord-card__participants-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none">
            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v1.5h16V18c0-2.66-5.33-4-8-4z" fill="currentColor"/>
          </svg>
        </span>
        <span class="concord-card__participants-label">{{ participantsLabel }}</span>
      </div>
    </div>

    <div class="concord-card__footer" @click.stop>
      <button
        type="button"
        class="concord-card__action concord-card__action--star"
        :class="{
          'concord-card__action--active': agreement.isFavorite,
          'concord-card__action--favorite-motion': favoriteAnimating,
        }"
        aria-label="Избранное"
        @click="onToggleFavorite"
      >
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" aria-hidden="true">
          <path
            :fill="agreement.isFavorite ? 'currentColor' : 'none'"
            d="M12 17.3 6.2 21l1.6-6.7L2 9.3l6.9-.6L12 2l3.1 6.7 6.9.6-5.8 4.9 1.6 6.7z"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linejoin="round"
          />
        </svg>
      </button>

      <div class="concord-card__progress" aria-hidden="true">
        <div class="concord-card__progress-track">
          <div class="concord-card__progress-fill" :style="{ width: `${displayedProgress}%` }" />
        </div>
      </div>

      <span class="concord-card__count" aria-label="Прогресс голосования">
        <span class="concord-card__count-voted">{{ displayedVoted }}</span><span class="concord-card__count-sep"> из </span><span class="concord-card__count-total">{{ participantsCount }}</span>
      </span>
    </div>
  </article>
</template>

<script>
import { onBeforeUnmount, ref, watch } from 'vue'
import ConcordUrgencyFlame from './ConcordUrgencyFlame.vue'
import {
  resolveSectionVotersCount,
  sectionHasConfiguredParticipants,
} from './mock-groups.js'
import {
  getAgreementDaysRemaining,
  pluralizeDays,
  pluralizeParticipants,
} from './mock-agreements.js'

function getPersonInitials(name = '') {
  const parts = String(name).trim().split(/\s+/).filter(Boolean)
  if (!parts.length) {
    return '?'
  }
  if (parts.length === 1) {
    return parts[0].slice(0, 2).toUpperCase()
  }
  return `${parts[0][0] || ''}${parts[1][0] || ''}`.toUpperCase()
}

/**
 * Voting seats across sections (not unique people).
 * Same person in two sections counts twice — each section needs its own vote.
 */
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

export default {
  name: 'AgreementCard',
  components: { ConcordUrgencyFlame },
  props: {
    agreement: {
      type: Object,
      required: true,
    },
    viewTransitionName: {
      type: String,
      default: '',
    },
    isNew: {
      type: Boolean,
      default: false,
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
  emits: ['open', 'toggle-favorite', 'duplicate', 'edit'],
  setup(props, { emit }) {
    const displayedProgress = ref(0)
    const displayedVoted = ref(0)
    const favoriteAnimating = ref(false)
    let progressTimer = null
    let favoriteTimer = null

    function progressFor(agreement) {
      const metrics = getAgreementMetrics(agreement, props.groups, props.contacts)
      if (!metrics.total) {
        return 0
      }
      if (agreement.isOwner && agreement.status === 'approved') {
        return 100
      }
      return Math.min(100, Math.round((metrics.voted / metrics.total) * 100))
    }

    function clearProgressTimer() {
      window.clearTimeout(progressTimer)
      progressTimer = null
    }

    watch(
      () => [
        props.agreement.voted,
        props.agreement.total,
        props.agreement.status,
        JSON.stringify(props.agreement.sections || []),
      ],
      (_next, _previous, onCleanup) => {
        const metrics = getAgreementMetrics(props.agreement, props.groups, props.contacts)
        const nextProgress = progressFor(props.agreement)
        if (_previous === undefined) {
          displayedProgress.value = nextProgress
          displayedVoted.value = metrics.voted
          return
        }
        clearProgressTimer()
        displayedProgress.value = nextProgress
        progressTimer = window.setTimeout(() => {
          displayedVoted.value = metrics.voted
          progressTimer = null
        }, 360)
        onCleanup(clearProgressTimer)
      },
      { immediate: true }
    )

    function onToggleFavorite() {
      window.clearTimeout(favoriteTimer)
      favoriteAnimating.value = false
      requestAnimationFrame(() => {
        favoriteAnimating.value = true
        favoriteTimer = window.setTimeout(() => {
          favoriteAnimating.value = false
          favoriteTimer = null
        }, 300)
      })
      emit('toggle-favorite', props.agreement.id)
    }

    onBeforeUnmount(() => {
      clearProgressTimer()
      window.clearTimeout(favoriteTimer)
    })

    return {
      displayedProgress,
      displayedVoted,
      favoriteAnimating,
      onToggleFavorite,
    }
  },
  computed: {
    cardStyle() {
      return this.viewTransitionName
        ? { viewTransitionName: this.viewTransitionName }
        : {}
    },
    isDraft() {
      return this.agreement.status === 'draft' || !this.agreement.createdAt
    },
    daysRemaining() {
      return getAgreementDaysRemaining(this.agreement.deadline)
    },
    isDeadlineOverdue() {
      return this.daysRemaining !== null && this.daysRemaining < 0
    },
    showFlame() {
      return Boolean(this.agreement.isUrgent && !this.agreement.urgentAcknowledged)
    },
    isOwnerApproved() {
      return this.agreement.isOwner && this.agreement.status === 'approved'
    },
    isParticipantSectionApproved() {
      return !this.agreement.isOwner && Boolean(this.agreement.mySectionApproved)
    },
    isApprovedHeader() {
      return this.isOwnerApproved || this.isParticipantSectionApproved
    },
    approvedHeaderText() {
      if (this.isOwnerApproved) {
        return `Согласовано: ${this.agreement.approvedAt || this.agreement.deadline}`
      }
      return `Согласовано мной: ${this.agreement.mySectionApprovedAt || this.agreement.deadline}`
    },
    participantsCount() {
      return getAgreementMetrics(this.agreement, this.groups, this.contacts).total
    },
    participantsLabel() {
      return pluralizeParticipants(this.participantsCount)
    },
    showDaysLabel() {
      return !this.isDraft && !this.isApprovedHeader && Boolean(this.agreement.deadline)
    },
    daysLabelText() {
      if (this.daysRemaining !== null) {
        return pluralizeDays(this.daysRemaining)
      }
      return '—'
    },
    daysLabelTone() {
      if (this.isDeadlineOverdue) {
        return 'overdue'
      }
      if (this.daysRemaining !== null && this.daysRemaining >= 0 && this.daysRemaining <= 5) {
        return 'soon'
      }
      return 'ok'
    },
    progressPercent() {
      if (!this.agreement.total) {
        return 0
      }
      if (this.isOwnerApproved || this.agreement.status === 'approved') {
        return 100
      }
      return Math.min(100, Math.round((this.agreement.voted / this.agreement.total) * 100))
    },
    progressTone() {
      if (this.isDraft) {
        return 'draft'
      }
      if (this.isOwnerApproved || this.agreement.status === 'approved') {
        return 'done'
      }
      if (this.agreement.isOwner) {
        return 'owner'
      }
      return 'participant'
    },
    cardModifiers() {
      return [
        `concord-card--role-${this.agreement.isOwner ? 'owner' : 'participant'}`,
        `concord-card--progress-${this.progressTone}`,
      ]
    },
    authorInitials() {
      return getPersonInitials(this.agreement.author?.name)
    },
    deadlineDate() {
      return String(this.agreement.deadline || '').trim().split(/\s+/)[0]
    },
    urgencyTitle() {
      return this.agreement.isUrgent ? 'Срочность установлена' : 'Срочность не установлена'
    },
  },
}
</script>
