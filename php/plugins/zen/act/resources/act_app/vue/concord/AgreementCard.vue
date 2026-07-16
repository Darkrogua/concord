<template>
  <article class="concord-card" @click="$emit('open', agreement.id)">
    <div class="concord-card__meta">
      <span class="concord-card__number">#{{ agreement.number }}</span>
      <span class="concord-card__created">Создана: {{ agreement.createdAt }}</span>
    </div>

    <h2 class="concord-card__title">{{ agreement.title }}</h2>

    <div class="concord-card__info">
      <div class="concord-card__details">
        <span
          class="concord-card__flame"
          :class="{
            'concord-card__flame--urgent': showUrgency && !isDeadlineSoon,
            'concord-card__flame--soon': isDeadlineSoon && !isDeadlineOverdue,
            'concord-card__flame--overdue': isDeadlineOverdue,
          }"
          :title="urgencyTitle"
          aria-hidden="true"
        >
          <ConcordUrgencyFlame :urgent="showUrgency" tall />
        </span>
        <p
          class="concord-card__deadline"
          :class="{
            'concord-card__deadline--soon': isDeadlineSoon && !isDeadlineOverdue,
            'concord-card__deadline--overdue': isDeadlineOverdue,
          }"
        >
          <span class="concord-card__deadline-date">{{ agreement.deadline }}</span>
          <span
            class="concord-card__days"
            :class="{
              'concord-card__days--soon': isDeadlineSoon && !isDeadlineOverdue,
              'concord-card__days--overdue': isDeadlineOverdue,
            }"
          >
            ({{ deadlineHint }})
          </span>
        </p>
        <p class="concord-card__author-name">{{ agreement.author.name }}</p>
      </div>

      <ParticipantAvatars :people="agreement.participants" :max="5" compact />
    </div>

    <div class="concord-card__footer">
      <span :class="['concord-card__status', `concord-card__status--${agreement.status}`]">
        {{ statusLabel }}
      </span>

      <div class="concord-card__actions" @click.stop>
        <button
          type="button"
          class="concord-card__action concord-card__action--star"
          :class="{ 'concord-card__action--active': agreement.isFavorite }"
          aria-label="Избранное"
          @click="$emit('toggle-favorite', agreement.id)"
        >
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
            <path
              :fill="agreement.isFavorite ? 'currentColor' : 'none'"
              d="M12 17.3 6.2 21l1.6-6.7L2 9.3l6.9-.6L12 2l3.1 6.7 6.9.6-5.8 4.9 1.6 6.7z"
              stroke="currentColor"
              stroke-width="1.5"
              stroke-linejoin="round"
            />
          </svg>
        </button>
        <button
          type="button"
          class="concord-card__action concord-card__action--copy"
          aria-label="Дублировать"
          @click="$emit('duplicate', agreement.id)"
        >
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
            <rect x="8" y="8" width="11" height="13" rx="2" stroke="currentColor" stroke-width="1.5"/>
            <path d="M6 16H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" stroke="currentColor" stroke-width="1.5"/>
          </svg>
        </button>
        <button
          v-if="agreement.isOwner"
          type="button"
          class="concord-card__action concord-card__action--edit"
          aria-label="Редактировать"
          @click="$emit('edit', agreement.id)"
        >
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
            <path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
          </svg>
        </button>
        <span class="concord-card__count" aria-label="Прогресс голосования">
          <span class="concord-card__count-voted">{{ agreement.voted }}</span><span class="concord-card__count-total">/{{ agreement.total }}</span>
        </span>
      </div>
    </div>

    <div class="concord-card__progress" aria-hidden="true">
      <div class="concord-card__progress-track">
        <div class="concord-card__progress-fill" :style="{ width: `${progressPercent}%` }" />
      </div>
    </div>
  </article>
</template>

<script>
import ParticipantAvatars from './ParticipantAvatars.vue'
import ConcordUrgencyFlame from './ConcordUrgencyFlame.vue'
import { formatAgreementDaysLabel, getAgreementDaysRemaining, getAgreementDeadlineHint, isAgreementDeadlineSoon } from './mock-agreements.js'

const STATUS_LABELS = {
  draft: 'Черновик',
  awaiting: 'Ждет согласования',
  approved: 'Согласовано',
}

export default {
  name: 'AgreementCard',
  components: { ParticipantAvatars, ConcordUrgencyFlame },
  props: {
    agreement: {
      type: Object,
      required: true,
    },
  },
  emits: ['open', 'toggle-favorite', 'duplicate', 'edit'],
  computed: {
    statusLabel() {
      return STATUS_LABELS[this.agreement.status] || STATUS_LABELS.awaiting
    },
    progressPercent() {
      if (!this.agreement.total) {
        return 0
      }
      return Math.min(100, Math.round((this.agreement.voted / this.agreement.total) * 100))
    },
    daysLabel() {
      return formatAgreementDaysLabel(
        this.agreement.startDate || this.agreement.createdAt,
        this.agreement.deadline
      )
    },
    daysRemaining() {
      return getAgreementDaysRemaining(this.agreement.deadline)
    },
    isDeadlineSoon() {
      return isAgreementDeadlineSoon(this.agreement.deadline)
    },
    isDeadlineOverdue() {
      return this.daysRemaining !== null && this.daysRemaining < 0
    },
    showUrgency() {
      return Boolean(this.agreement.isUrgent || this.isDeadlineSoon)
    },
    deadlineHint() {
      return getAgreementDeadlineHint(this.agreement.deadline, this.daysLabel)
    },
    urgencyTitle() {
      if (this.isDeadlineOverdue) {
        return 'Срок согласования истёк'
      }
      if (this.isDeadlineSoon) {
        return this.deadlineHint
      }
      return this.agreement.isUrgent ? 'Срочность установлена' : 'Срочность не установлена'
    },
  },
}
</script>
