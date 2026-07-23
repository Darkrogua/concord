<template>
  <div class="concord-avatars" :class="{ 'concord-avatars--compact': compact }" aria-hidden="true">
    <span
      v-if="showCount"
      class="concord-avatars__item concord-avatars__item--count"
      :class="{ 'concord-avatars__item--count-wide': countLabel.length > 2 }"
      :style="{ zIndex: slotCount }"
    >
      {{ countLabel }}
    </span>
    <span
      v-for="(person, index) in visiblePeople"
      :key="personKey(person, index)"
      class="concord-avatars__item"
      :class="{ 'concord-avatars__item--compact': personLabel(person).length > 1 }"
      :style="{ zIndex: slotCount - index - 1 }"
    >
      {{ personLabel(person) }}
    </span>
  </div>
</template>

<script>
const MAX_VISIBLE_SLOTS = 6

export default {
  name: 'ParticipantAvatars',
  props: {
    people: {
      type: Array,
      default: () => [],
    },
    total: {
      type: Number,
      default: null,
    },
    max: {
      type: Number,
      default: MAX_VISIBLE_SLOTS,
    },
    compact: {
      type: Boolean,
      default: false,
    },
  },
  computed: {
    participantTotal() {
      if (typeof this.total === 'number' && this.total >= 0) {
        return this.total
      }
      return this.people.length
    },
    slotCount() {
      const limit = Math.min(Math.max(this.max, 2), MAX_VISIBLE_SLOTS)
      return Math.min(limit, this.showCount ? 1 + this.visiblePeople.length : this.visiblePeople.length)
    },
    showCount() {
      return this.participantTotal > 0
    },
    visiblePeople() {
      const limit = Math.min(Math.max(this.max, 2), MAX_VISIBLE_SLOTS)
      const peopleSlots = this.showCount ? limit - 1 : limit
      return this.people.slice(0, peopleSlots)
    },
    countLabel() {
      if (this.participantTotal > 99) {
        return '99+'
      }
      return String(this.participantTotal)
    },
  },
  methods: {
    personLabel(person) {
      return person.initial || person.label || '?'
    },
    personKey(person, index) {
      return person.id || `${this.personLabel(person)}-${index}`
    },
  },
}
</script>
