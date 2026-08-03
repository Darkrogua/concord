<template>
  <template v-if="open">
    <div class="concord-sheet-backdrop" @click="$emit('close')" />
    <div class="concord-sheet concord-profile-field-sheet" role="dialog" :aria-labelledby="titleId" @click.stop>
      <div class="concord-sheet__handle" aria-hidden="true" />
      <h2 :id="titleId" class="concord-sheet__title">{{ title }}</h2>
      <div class="concord-profile-field-sheet__body">
        <slot />
      </div>
      <div class="concord-create-sheet__actions">
        <button
          type="button"
          class="concord-create-sheet__btn concord-create-sheet__btn--cancel"
          @click.stop="$emit('close')"
        >
          Отмена
        </button>
        <button
          type="button"
          class="concord-create-sheet__btn concord-create-sheet__btn--save"
          :disabled="saveDisabled"
          @click.stop="$emit('save')"
        >
          {{ saving ? '…' : 'Сохранить' }}
        </button>
      </div>
    </div>
  </template>
</template>

<script>
export default {
  name: 'ConcordProfileFieldSheet',
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    title: {
      type: String,
      required: true,
    },
    titleId: {
      type: String,
      required: true,
    },
    saveDisabled: {
      type: Boolean,
      default: false,
    },
    saving: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['close', 'save'],
}
</script>
