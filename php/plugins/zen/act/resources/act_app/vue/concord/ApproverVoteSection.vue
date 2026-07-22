<template>
  <div class="concord-vote-section" :class="{ 'concord-vote-section--voted': hasVoted }">
    <div v-if="!hasVoted" class="concord-vote-section__question">
      <span class="concord-vote-section__question-text">Согласовать?</span>
      <div class="concord-vote-section__actions">
        <button type="button" class="concord-vote-section__btn concord-vote-section__btn--yes" @click="$emit('vote-yes')">
          Да
        </button>
        <button type="button" class="concord-vote-section__btn concord-vote-section__btn--no" @click="$emit('vote-no')">
          Нет
        </button>
      </div>
    </div>
    <div v-else class="concord-vote-section__result">
      <span class="concord-vote-section__status" :class="`concord-vote-section__status--${userVote.decision}`">
        {{ statusLabel }}
      </span>
      <span v-if="userVote.reason" class="concord-vote-section__reason">{{ userVote.reason }}</span>
    </div>

    <div class="concord-vote-section__stats">
      <div class="concord-vote-section__stat">
        <span>Согласовано</span>
        <span>{{ stats.approved }}%</span>
      </div>
      <div class="concord-vote-section__stat">
        <span>Не согласовано</span>
        <span>{{ stats.rejected }}%</span>
      </div>
      <div class="concord-vote-section__stat">
        <span>Не голосовали</span>
        <span>{{ stats.pending }}%</span>
      </div>
    </div>
  </div>
</template>

<script>
import { computed } from 'vue'

export default {
  name: 'ApproverVoteSection',
  props: {
    section: {
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

    const stats = computed(() => props.section?.votingStats || { approved: 0, rejected: 0, pending: 100 })

    const statusLabel = computed(() => {
      if (props.userVote?.decision === 'approved') {
        return 'Согласовано'
      }
      if (props.userVote?.decision === 'rejected') {
        return 'Не согласовано'
      }
      return 'Ждет согласования'
    })

    return { hasVoted, stats, statusLabel }
  },
}
</script>
