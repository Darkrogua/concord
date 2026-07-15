<template>
  <Transition name="concord-search">
    <div v-if="open" class="concord-overlay" role="dialog" aria-label="Поиск">
      <div class="concord-overlay__header">
        <button type="button" class="concord-icon-btn" aria-label="Назад" @click="$emit('close')">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
        <input
          ref="inputRef"
          :value="query"
          class="concord-overlay__input"
          type="search"
          placeholder="Поиск"
          autocomplete="off"
          @input="$emit('update:query', $event.target.value)"
        >
        <button
          v-if="query"
          type="button"
          class="concord-icon-btn"
          aria-label="Очистить"
          @click="$emit('update:query', '')"
        >
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M7 7l10 10M17 7 7 17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </button>
      </div>

      <div class="concord-overlay__scopes" role="group" aria-label="Область поиска">
        <button
          v-for="option in scopeOptions"
          :key="option.id"
          type="button"
          class="concord-sheet__chip"
          :class="{ 'concord-sheet__chip--active': scope === option.id }"
          :aria-pressed="scope === option.id"
          @click="$emit('update:scope', option.id)"
        >
          {{ option.label }}
        </button>
      </div>

      <main class="concord-list concord-overlay__body">
        <slot />
      </main>
    </div>
  </Transition>
</template>

<script>
import { nextTick, ref, watch } from 'vue'
import { SEARCH_SCOPE_OPTIONS } from './mock-agreements.js'

export default {
  name: 'ConcordSearchOverlay',
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    query: {
      type: String,
      default: '',
    },
    scope: {
      type: String,
      default: 'content',
    },
  },
  emits: ['close', 'update:query', 'update:scope'],
  setup(props) {
    const inputRef = ref(null)
    const scopeOptions = SEARCH_SCOPE_OPTIONS

    watch(
      () => props.open,
      async (open) => {
        if (!open) {
          return
        }
        await nextTick()
        requestAnimationFrame(() => inputRef.value?.focus())
      }
    )

    return {
      inputRef,
      scopeOptions,
    }
  },
}
</script>
