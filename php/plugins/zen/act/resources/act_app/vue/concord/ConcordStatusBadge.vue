<template>
  <span :class="['concord-status', `concord-status--${variant}`]">
    {{ label }}
  </span>
</template>

<script>
const LABELS = {
  draft: 'Черновик',
  awaiting: 'Ждет согласования',
  approved: 'Согласовано',
}

export default {
  name: 'ConcordStatusBadge',
  props: {
    status: {
      type: String,
      required: true,
      validator: (v) => ['draft', 'awaiting', 'approved'].includes(v),
    },
  },
  computed: {
    variant() {
      return this.status
    },
    label() {
      return LABELS[this.status] || LABELS.awaiting
    },
  },
}
</script>
