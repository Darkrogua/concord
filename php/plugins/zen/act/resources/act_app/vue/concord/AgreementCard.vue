<template>
  <article
    class="concord-card"
    :class="cardModifiers"
    @click="$emit('open', agreement.id)"
  >
    <header
      :class="[
        'concord-card__header',
        {
          'concord-card__header--draft': isDraft,
          'concord-card__header--approved': isApprovedHeader,
        },
      ]"
    >
      <div class="concord-card__id">
        <span
          v-if="showFlame"
          class="concord-card__flame"
          :title="urgencyTitle"
          aria-hidden="true"
        >
          <ConcordUrgencyFlame urgent />
        </span>
        <span class="concord-card__number">#{{ agreement.number }}</span>
      </div>

      <div class="concord-card__status-area">
        <span v-if="isDraft" class="concord-card__draft-label">Черновик</span>

        <span
          v-else-if="isApprovedHeader"
          class="concord-card__status-plate concord-card__status-plate--approved"
        >
          {{ approvedHeaderText }}
        </span>

        <span
          v-else-if="isDeadlineOverdue"
          class="concord-card__status-plate concord-card__status-plate--overdue"
        >
          <template v-if="agreement.isOwner">
            <span class="concord-card__badge concord-card__badge--overdue">Создано мной</span>
            <span v-if="agreement.deadline">до {{ agreement.deadline }}</span>
          </template>
          <template v-else>
            <span class="concord-card__status-label">Ждёт согласования до:</span>
            <span class="concord-card__status-date">{{ agreement.deadline }}</span>
          </template>
        </span>

        <template v-else-if="agreement.isOwner">
          <span class="concord-card__badge concord-card__badge--owner">Создано мной</span>
          <span v-if="agreement.deadline" class="concord-card__deadline-prefix">
            до <span class="concord-card__status-date">{{ agreement.deadline }}</span>
          </span>
        </template>

        <span v-else class="concord-card__status-text">
          <span class="concord-card__status-label">Ждёт согласования до:</span>
          <span class="concord-card__status-date">{{ agreement.deadline }}</span>
        </span>
      </div>

      <span
        v-if="showDaysLabel"
        :class="['concord-card__days', `concord-card__days--${daysLabelTone}`]"
      >
        ({{ daysLabelText }})
      </span>
    </header>

    <div
      v-if="!isDraft"
      :class="['concord-card__rule', `concord-card__rule--${ruleTone}`]"
      aria-hidden="true"
    />

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
        :class="{ 'concord-card__action--active': agreement.isFavorite }"
        aria-label="Избранное"
        @click="$emit('toggle-favorite', agreement.id)"
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
          <div class="concord-card__progress-fill" :style="{ width: `${progressPercent}%` }" />
        </div>
      </div>

      <span class="concord-card__count" aria-label="Прогресс голосования">
        <span class="concord-card__count-voted">{{ agreement.voted }}</span><span class="concord-card__count-sep"> из </span><span class="concord-card__count-total">{{ agreement.total }}</span>
      </span>
    </div>
  </article>
</template>

<script>
import ConcordUrgencyFlame from './ConcordUrgencyFlame.vue'
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

export default {
  name: 'AgreementCard',
  components: { ConcordUrgencyFlame },
  props: {
    agreement: {
      type: Object,
      required: true,
    },
  },
  emits: ['open', 'toggle-favorite', 'duplicate', 'edit'],
  computed: {
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
      return Boolean(this.agreement.isUrgent)
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
      return this.agreement.total || this.agreement.participants?.length || 0
    },
    participantsLabel() {
      return pluralizeParticipants(this.participantsCount)
    },
    showDaysLabel() {
      return !this.isDraft && !this.isApprovedHeader && Boolean(this.agreement.deadline)
    },
    daysLabelText() {
      if (this.isDeadlineOverdue) {
        return 'просрочено'
      }
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
    ruleTone() {
      if (this.isApprovedHeader) {
        return 'approved'
      }
      if (this.isDeadlineOverdue) {
        return 'overdue'
      }
      return this.agreement.isOwner ? 'owner' : 'participant'
    },
    authorInitials() {
      return getPersonInitials(this.agreement.author?.name)
    },
    urgencyTitle() {
      return this.agreement.isUrgent ? 'Срочность установлена' : 'Срочность не установлена'
    },
  },
}
</script>
