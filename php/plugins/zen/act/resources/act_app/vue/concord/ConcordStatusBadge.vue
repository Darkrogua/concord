<template>
  <span :class="['concord-status', `concord-status--${variant}`]">
    {{ label }}
  </span>
</template>

<script>
const LABELS = {
  awaiting: 'Ждет согласования',
  overdue: 'Ждет согласования',
  approved: 'Согласовано',
}

export default {
  name: 'ConcordStatusBadge',
  props: {
    status: {
      type: String,
      required: true,
      validator: (v) => ['awaiting', 'overdue', 'approved'].includes(v),
    },
  },
  computed: {
    variant() {
      if (this.status === 'approved') {
        return 'approved'
      }
      if (this.status === 'overdue') {
        return 'overdue'
      }
      return 'awaiting'
    },
    label() {
      return LABELS[this.status] || LABELS.awaiting
    },
  },
}
</script>
