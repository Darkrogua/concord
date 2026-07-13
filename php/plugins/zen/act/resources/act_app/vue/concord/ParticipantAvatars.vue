<template>
  <div class="concord-avatars" aria-hidden="true">
    <span
      v-for="(person, index) in visiblePeople"
      :key="`${personLabel(person)}-${index}`"
      class="concord-avatars__item"
      :class="{ 'concord-avatars__item--compact': personLabel(person).length > 1 }"
      :style="{ zIndex: visiblePeople.length - index }"
    >
      {{ personLabel(person) }}
    </span>
    <span v-if="overflowCount > 0" class="concord-avatars__item concord-avatars__item--more">
      +{{ overflowCount }}
    </span>
  </div>
</template>

<script>
export default {
  name: 'ParticipantAvatars',
  props: {
    people: {
      type: Array,
      default: () => [],
    },
    max: {
      type: Number,
      default: 4,
    },
  },
  computed: {
    visiblePeople() {
      return this.people.slice(0, this.max)
    },
    overflowCount() {
      return Math.max(0, this.people.length - this.max)
    },
  },
  methods: {
    personLabel(person) {
      return person.label || person.initial || '?'
    },
  },
}
</script>
