<template>
  <div class="concord-vote-stats">
    <div class="concord-vote-stats__stat">
      <span>Согласовано</span>
      <span>{{ stats.approved }}%</span>
    </div>
    <div class="concord-vote-stats__stat">
      <span>Не согласовано</span>
      <span>{{ stats.rejected }}%</span>
    </div>
    <div class="concord-vote-stats__stat">
      <span>Не голосовали</span>
      <span>{{ stats.pending }}%</span>
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
