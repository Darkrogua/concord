<template>
  <div class="concord-vote-stats">
    <div class="concord-vote-stats__stat">
      <span>Согласовано</span>
      <span class="concord-vote-stats__stat-value">{{ stats.approved }}%</span>
    </div>
    <div class="concord-vote-stats__stat-bar" aria-hidden="true">
      <div class="concord-vote-stats__stat-fill" :style="{ width: `${stats.approved}%` }" />
    </div>
    <div class="concord-vote-stats__stat">
      <span>Не согласовано</span>
      <span class="concord-vote-stats__stat-value">{{ stats.rejected }}%</span>
    </div>
    <div class="concord-vote-stats__stat-bar" aria-hidden="true">
      <div class="concord-vote-stats__stat-fill concord-vote-stats__stat-fill--rejected" :style="{ width: `${stats.rejected}%` }" />
    </div>
    <div class="concord-vote-stats__stat">
      <span>Не голосовали</span>
      <span class="concord-vote-stats__stat-value">{{ stats.pending }}%</span>
    </div>
    <div class="concord-vote-stats__stat-bar" aria-hidden="true">
      <div class="concord-vote-stats__stat-fill concord-vote-stats__stat-fill--pending" :style="{ width: `${stats.pending}%` }" />
    </div>
  </div>
</template>

<script>
import { computed } from 'vue'

export default {
  name: 'ApproverVoteStats',
  props: {
    section: {
      type: Object,
      required: true,
    },
  },
  setup(props) {
    const stats = computed(() => props.section?.votingStats || { approved: 0, rejected: 0, pending: 100 })
    return { stats }
  },
}
</script>
