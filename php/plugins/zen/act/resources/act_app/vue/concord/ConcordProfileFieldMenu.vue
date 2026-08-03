<template>
  <div
    v-if="open"
    class="concord-profile-field-menu"
    role="dialog"
    :aria-label="title"
    @click.self="onClose"
  >
    <div class="concord-profile-field-menu__sheet" @click.stop>
      <div class="concord-sheet__handle" aria-hidden="true" />
      <div class="concord-profile-field-menu__preview">
        <span class="concord-profile-field-menu__label">{{ label }}</span>
        <span class="concord-profile-field-menu__value">{{ value }}</span>
      </div>
      <button
        v-for="action in actions"
        :key="action.id"
        type="button"
        class="concord-menu-sheet__item"
        :class="{ 'concord-menu-sheet__item--destructive': action.destructive }"
        @click.stop="onSelect(action.id)"
      >
        <span class="concord-menu-sheet__item-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none">
            <path :d="action.iconPath" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"></path>
          </svg>
        </span>
        {{ action.label }}
      </button>
      <button type="button" class="concord-menu-sheet__item" @click.stop="onClose">
        Отмена
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ConcordProfileFieldMenu',
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    title: {
      type: String,
      default: 'Действие',
    },
    label: {
      type: String,
      default: '',
    },
    value: {
      type: String,
      default: '',
    },
    actions: {
      type: Array,
      default: () => [],
    },
  },
  emits: ['close', 'select'],
  methods: {
    onSelect(actionId) {
      this.$emit('select', actionId)
    },
    onClose() {
      this.$emit('close')
    },
  },
}
</script>
