<template>
  <button
    type="button"
    class="concord-profile-field"
    :class="{ 'concord-profile-field--interactive': interactive }"
    :disabled="!interactive"
    @click="onClick"
  >
    <span class="concord-profile-field__label">{{ label }}</span>
    <span
      class="concord-profile-field__value"
      :class="{ 'concord-profile-field__value--strong': strong }"
    >
      {{ displayValue }}
    </span>
  </button>
</template>

<script>
export default {
  name: 'ConcordProfileFieldRow',
  props: {
    label: {
      type: String,
      required: true,
    },
    value: {
      type: String,
      default: '',
    },
    placeholder: {
      type: String,
      default: '—',
    },
    interactive: {
      type: Boolean,
      default: false,
    },
    strong: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['click'],
  computed: {
    displayValue() {
      return this.value?.trim() ? this.value : this.placeholder
    },
  },
  methods: {
    onClick(event) {
      if (!this.interactive) {
        return
      }
      event.preventDefault()
      event.stopPropagation()
      this.$emit('click')
    },
  },
}
</script>
