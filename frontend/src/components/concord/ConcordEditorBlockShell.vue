<template>
  <article
    class="concord-editor-block"
    :class="{
      'concord-editor-block--expanded': expanded,
      'concord-editor-block--collapsed': !expanded,
    }"
  >
    <div class="concord-editor-block__main">
      <div class="concord-editor-block__head">
        <div class="concord-editor-block__head-title">
          <label class="concord-editor-block__head-text" @click.stop>
            <span class="concord-editor-block__label-hint">Название</span>
            <input
              v-model="draftLabel"
              class="concord-editor-block__label-input"
              type="text"
              :placeholder="placeholder"
              @change="commitLabel"
              @keydown.enter.prevent="$event.target.blur()"
            >
          </label>
        </div>
        <div class="concord-editor-block__head-actions">
          <span v-if="summary" class="concord-editor-block__summary">{{ summary }}</span>
          <button
            type="button"
            class="concord-editor-block__delete"
            aria-label="Удалить блок"
            @click.stop="$emit('delete')"
          >
            <ConcordGroupDeleteIcon />
          </button>
          <button
            type="button"
            class="concord-editor-block__toggle"
            :aria-expanded="expanded"
            :aria-label="expanded ? 'Свернуть блок' : 'Развернуть блок'"
            @click.stop="expanded = !expanded"
          >
            <svg class="concord-editor-block__chevron" viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
              <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>
        </div>
      </div>

      <div v-if="!expanded" class="concord-editor-block__preview">
        <slot name="preview" />
      </div>

      <div v-else class="concord-editor-block__body">
        <slot />
      </div>
    </div>
  </article>
</template>

<script setup>
import { ref, watch } from 'vue'
import ConcordGroupDeleteIcon from './ConcordGroupDeleteIcon.vue'

const props = defineProps({
  label: { type: String, default: '' },
  placeholder: { type: String, default: 'Блок' },
  summary: { type: String, default: '' },
  defaultExpanded: { type: Boolean, default: false },
})

const emit = defineEmits(['update:label', 'delete'])

const expanded = ref(props.defaultExpanded)
const draftLabel = ref(props.label)

watch(() => props.label, (value) => {
  draftLabel.value = value
})

function commitLabel() {
  emit('update:label', draftLabel.value)
}
</script>
