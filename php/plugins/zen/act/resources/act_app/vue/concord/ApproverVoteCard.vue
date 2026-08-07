<template>
  <div class="concord-vote-card">
    <div class="concord-vote-card__info">
      <div class="concord-vote-card__details">
        <span
          class="concord-vote-card__flame"
          :class="{
            'concord-vote-card__flame--urgent': showUrgency && !isDeadlineSoon,
            'concord-vote-card__flame--soon': isDeadlineSoon && !isDeadlineOverdue,
            'concord-vote-card__flame--overdue': isDeadlineOverdue,
          }"
          aria-hidden="true"
        >
          <ConcordUrgencyFlame :urgent="showUrgency" tall />
        </span>
        <div class="concord-vote-card__meta">
          <span class="concord-vote-card__deadline">{{ agreement.deadline }}</span>
          <span class="concord-vote-card__days">({{ daysLabel }})</span>
        </div>
        <div class="concord-vote-card__voters">
          Согласующих: {{ agreement.total }} чел.
        </div>
      </div>
      <ParticipantAvatars
        :people="agreement.participants"
        :total="agreement.total || agreement.participants?.length || 0"
        compact
      />
    </div>

    <div v-if="!hasVoted" class="concord-vote-card__question">
      <span class="concord-vote-card__question-text">Согласовать?</span>
      <div class="concord-vote-card__actions">
        <button type="button" class="concord-vote-card__btn concord-vote-card__btn--yes" @click="$emit('vote-yes')">
          Да
        </button>
        <button type="button" class="concord-vote-card__btn concord-vote-card__btn--no" @click="$emit('vote-no')">
          Нет
        </button>
      </div>
    </div>

    <div v-else class="concord-vote-card__result">
      <div class="concord-vote-card__status-row">
        <span class="concord-vote-card__status" :class="`concord-vote-card__status--${userVote.decision}`">
          {{ statusLabel }}
        </span>
        <span v-if="userVote.reason" class="concord-vote-card__reason">{{ userVote.reason }}</span>
      </div>
      <div class="concord-vote-card__stats">
        <div class="concord-vote-card__stat">
          <span>Согласовано</span>
          <span>{{ stats.approved }}%</span>
        </div>
        <div class="concord-vote-card__stat">
          <span>Не согласовано</span>
          <span>{{ stats.rejected }}%</span>
        </div>
        <div class="concord-vote-card__stat">
          <span>Не голосовали</span>
          <span>{{ stats.pending }}%</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { computed } from 'vue'
import ConcordUrgencyFlame from './ConcordUrgencyFlame.vue'
import ParticipantAvatars from './ParticipantAvatars.vue'
import {
  formatAgreementDaysLabel,
  getAgreementDaysRemaining,
  isAgreementDeadlineSoon,
} from './mock-agreements.js'

export default {
  name: 'ApproverVoteCard',
  components: { ConcordUrgencyFlame, ParticipantAvatars },
  props: {
    agreement: {
      type: Object,
      required: true,
    },
    userVote: {
      type: Object,
      default: null,
    },
  },
  emits: ['vote-yes', 'vote-no'],
  setup(props) {
    const hasVoted = computed(() => Boolean(props.userVote?.decision))

    const stats = computed(() => {
      const section = props.agreement?.sections?.[0]
      return section?.votingStats || { approved: 0, rejected: 0, pending: 100 }
    })

    const daysLabel = computed(() =>
      formatAgreementDaysLabel(
        props.agreement.startDate || props.agreement.createdAt,
        props.agreement.deadline
      )
    )

    const daysRemaining = computed(() => getAgreementDaysRemaining(props.agreement.deadline))
    const isDeadlineSoon = computed(() => isAgreementDeadlineSoon(props.agreement.deadline))
    const isDeadlineOverdue = computed(() => daysRemaining.value !== null && daysRemaining.value < 0)
    const showUrgency = computed(() => Boolean(
      (props.agreement.isUrgent && !props.agreement.urgentAcknowledged)
      || isDeadlineSoon.value
    ))

    const statusLabel = computed(() => {
      if (props.userVote?.decision === 'approved') {
        return 'Согласовано'
      }
      if (props.userVote?.decision === 'rejected') {
        return 'Не согласовано'
      }
      return 'Ждет согласования'
    })

    return {
      hasVoted,
      stats,
      daysLabel,
      isDeadlineSoon,
      isDeadlineOverdue,
      showUrgency,
      statusLabel,
    }
  },
}
</script>
