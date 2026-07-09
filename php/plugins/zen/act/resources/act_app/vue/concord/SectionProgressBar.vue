<template>
  <div class="concord-section">
    <div class="concord-section__head">
      <span class="concord-section__name">{{ section.name }}</span>
      <span class="concord-section__count">
        <svg class="concord-section__count-icon" viewBox="0 0 16 16" fill="none" aria-hidden="true">
          <path
            d="M3 8.5 6.5 12 13 4"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
        {{ section.voted }} из {{ section.total }}
      </span>
    </div>
    <div class="concord-progress" role="progressbar" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100">
      <div class="concord-progress__track">
        <div class="concord-progress__fill" :style="{ width: `${percent}%` }" />
        <div class="concord-progress__marker" :style="markerStyle" />
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SectionProgressBar',
  props: {
    section: {
      type: Object,
      required: true,
    },
  },
  computed: {
    percent() {
      const total = Number(this.section.total) || 0
      if (total <= 0) {
        return 0
      }
      return Math.min(100, Math.round((Number(this.section.voted) / total) * 100))
    },
    markerStyle() {
      const percent = this.percent

      if (percent <= 0) {
        return { left: '0%', transform: 'translateY(-50%)' }
      }

      if (percent >= 100) {
        return { left: '100%', transform: 'translate(-100%, -50%)' }
      }

      return { left: `${percent}%`, transform: 'translate(-50%, -50%)' }
    },
  },
}
</script>
