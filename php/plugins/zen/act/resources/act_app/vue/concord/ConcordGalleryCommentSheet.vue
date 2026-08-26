<template>
  <Teleport to="body">
    <template v-if="open && target">
      <div class="concord-sheet-backdrop concord-gallery-comment-sheet__backdrop" @click="$emit('close')" />
      <section
        class="concord-sheet concord-gallery-comment-sheet"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        @click.stop
      >
        <div class="concord-sheet__handle" aria-hidden="true" />
        <div class="concord-gallery-comment-sheet__head">
          <div class="concord-gallery-comment-sheet__thumb" aria-hidden="true">
            <img v-if="target.previewUrl" :src="target.previewUrl" :alt="target.name || 'Вложение'">
            <div v-else class="concord-gallery-comment-sheet__thumb-doc">
              <svg viewBox="0 0 24 24" width="24" height="24" fill="none">
                <path d="M8 3h6l5 5v13a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.4"/>
                <path d="M14 3v5h5" stroke="currentColor" stroke-width="1.4"/>
              </svg>
            </div>
          </div>
          <div class="concord-gallery-comment-sheet__meta">
            <h2 :id="titleId" class="concord-gallery-comment-sheet__title">{{ title }}</h2>
            <p v-if="target.name" class="concord-gallery-comment-sheet__filename">{{ target.name }}</p>
          </div>
        </div>

        <textarea
          ref="textareaRef"
          v-model="target.comment"
          class="concord-gallery-comment-sheet__input"
          rows="4"
          maxlength="500"
          :placeholder="placeholder"
          :aria-label="title"
          @keydown.esc.prevent="$emit('close')"
        />

        <p class="concord-gallery-comment-sheet__hint">
          {{ hint }}
        </p>

        <div class="concord-create-sheet__actions">
          <button type="button" class="concord-create-sheet__btn concord-create-sheet__btn--save" @click="$emit('close')">
            Готово
          </button>
        </div>
      </section>
    </template>
  </Teleport>
</template>

<script>
import { computed, nextTick, ref, watch } from 'vue'

export default {
  name: 'ConcordGalleryCommentSheet',
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    photo: {
      type: Object,
      default: null,
    },
    item: {
      type: Object,
      default: null,
    },
    title: {
      type: String,
      default: 'Подпись к фото',
    },
    hint: {
      type: String,
      default: 'Как в Telegram — подпись видна при просмотре фото согласователями.',
    },
    placeholder: {
      type: String,
      default: 'Опишите, что на фото важно для согласования…',
    },
  },
  emits: ['close'],
  setup(props) {
    const textareaRef = ref(null)
    const titleId = `attachment-comment-title-${Math.random().toString(36).slice(2, 8)}`

    const target = computed(() => props.item || props.photo)

    watch(
      () => props.open,
      (isOpen) => {
        if (!isOpen) {
          return
        }
        nextTick(() => {
          textareaRef.value?.focus()
        })
      }
    )

    return { textareaRef, titleId, target }
  },
}
</script>
