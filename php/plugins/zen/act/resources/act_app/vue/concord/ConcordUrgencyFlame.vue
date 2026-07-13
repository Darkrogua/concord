<template>
  <span
    class="concord-urgency-flame"
    :class="{
      'concord-urgency-flame--urgent': urgent,
      'concord-urgency-flame--tall': tall,
    }"
    :style="flameStyle"
    aria-hidden="true"
  />
</template>

<script>
import flameIcon from '../../assets/concord/flame-urgent.png'

export default {
  name: 'ConcordUrgencyFlame',
  props: {
    urgent: {
      type: Boolean,
      default: false,
    },
    tall: {
      type: Boolean,
      default: false,
    },
  },
  computed: {
    flameStyle() {
      const mask = `url(${flameIcon})`
      return {
        WebkitMaskImage: mask,
        maskImage: mask,
      }
    },
  },
}
</script>

<style scoped>
.concord-urgency-flame {
  display: block;
  flex-shrink: 0;
  width: 20px;
  height: 20px;
  background-color: var(--concord-card-meta);
  -webkit-mask-repeat: no-repeat;
  mask-repeat: no-repeat;
  -webkit-mask-position: center top;
  mask-position: center top;
  -webkit-mask-size: contain;
  mask-size: contain;
}

.concord-urgency-flame--urgent {
  background-color: var(--concord-card-author);
}

.concord-urgency-flame--tall {
  width: 100%;
  height: 100%;
  -webkit-mask-position: center center;
  mask-position: center center;
}
</style>
