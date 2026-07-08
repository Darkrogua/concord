<template>
  <Teleport to="body">
    <Transition name="tg-modal">
      <div
        v-if="open"
        class="tg-modal-backdrop"
        @click.self="$emit('close')"
      >
        <div
          class="tg-modal"
          role="dialog"
          :aria-labelledby="titleId"
          aria-modal="true"
        >
          <button
            type="button"
            class="tg-modal__close"
            aria-label="Закрыть"
            @click="$emit('close')"
          >
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </button>
          <div class="tg-modal__header">
            <h3 :id="titleId" class="tg-modal__title">{{ title }}</h3>
          </div>
          <div class="tg-modal__body tg-scroll">
            <slot />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script>
export default {
  name: 'EditFieldModal',
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
  },
  emits: ['close'],
}
</script>
