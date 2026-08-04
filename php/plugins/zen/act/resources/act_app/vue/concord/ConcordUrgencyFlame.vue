<template>
  <img
    class="concord-urgency-flame"
    :class="{
      'concord-urgency-flame--urgent': urgent,
      'concord-urgency-flame--tall': tall,
    }"
    :src="flameIcon"
    aria-hidden="true"
    alt=""
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
  data() {
    return { flameIcon }
  },
}
</script>

<style scoped>
.concord-urgency-flame {
  display: block;
  flex-shrink: 0;
  width: 20px;
  height: 20px;
  object-fit: contain;
}

.concord-urgency-flame--tall {
  width: 100%;
  height: 100%;
}
</style>
