<template>
  <div class="tg-list__row tg-profile-field">
    <span class="tg-list__label">{{ label }}</span>
    <span :class="['tg-list__value', { 'tg-list__value--strong': strong }]">{{ displayValue }}</span>
    <button
      v-if="editable"
      type="button"
      class="tg-profile-field__edit"
      :aria-label="editLabel"
      @click="$emit('edit')"
    >
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M4 20h4l10-10-4-4L4 16v4z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
        <path d="M13 7l4 4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
      </svg>
    </button>
  </div>
</template>

<script>
export default {
  name: 'ProfileEditableRow',
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
    editable: {
      type: Boolean,
      default: false,
    },
    strong: {
      type: Boolean,
      default: false,
    },
    editLabel: {
      type: String,
      required: true,
    },
  },
  emits: ['edit'],
  computed: {
    displayValue() {
      return this.value?.trim() ? this.value : this.placeholder
    },
  },
}
</script>
