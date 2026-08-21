<template>
  <article
    class="concord-editor-block"
    :class="{
      'concord-editor-block--expanded': expanded,
      'concord-editor-block--collapsed': !expanded,
    }"
    :data-block-id="blockId"
    @click="onShellClick"
  >
    <div class="concord-editor-block__main">
      <div class="concord-editor-block__head">
        <div class="concord-editor-block__head-title">
          <button
            type="button"
            class="concord-editor-block__handle"
            data-concord-no-toggle
            aria-label="Перетащить блок"
            @click.stop
          >
            <svg viewBox="0 0 16 16" width="16" height="16" fill="currentColor" aria-hidden="true">
              <circle cx="5" cy="4" r="1.2" />
              <circle cx="11" cy="4" r="1.2" />
              <circle cx="5" cy="8" r="1.2" />
              <circle cx="11" cy="8" r="1.2" />
              <circle cx="5" cy="12" r="1.2" />
              <circle cx="11" cy="12" r="1.2" />
            </svg>
          </button>
          <label
            class="concord-editor-block__head-text"
            data-concord-no-toggle
            @click.stop
          >
            <span class="concord-editor-block__label-hint">Название</span>
            <input
              ref="labelInputRef"
              v-model="draftLabel"
              class="concord-editor-block__label-input"
              type="text"
              :placeholder="placeholder"
              autocomplete="off"
              autocorrect="off"
              spellcheck="false"
              @click.stop
              @mousedown.stop
              @pointerdown.stop
              @keydown.enter.prevent="blurLabelInput"
              @keydown.esc.prevent="revertLabel"
              @focus="onLabelFocus"
              @blur="commitLabel"
            >
          </label>
        </div>
        <div class="concord-editor-block__head-actions">
          <span v-if="summary" class="concord-editor-block__summary">{{ summary }}</span>
          <button
            type="button"
            class="concord-editor-block__delete"
            data-concord-no-toggle
            aria-label="Удалить блок"
            :disabled="isLocked()"
            @click.stop="$emit('delete')"
          >
            <ConcordGroupDeleteIcon />
          </button>
          <button
            type="button"
            class="concord-editor-block__toggle"
            :aria-expanded="expanded"
            :aria-label="expanded ? 'Свернуть блок' : 'Развернуть блок'"
            @click.stop="toggle"
          >
            <svg
              class="concord-editor-block__chevron"
              viewBox="0 0 24 24"
              width="20"
              height="20"
              fill="none"
              aria-hidden="true"
            >
              <path
                d="M6 9l6 6 6-6"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </button>
        </div>
      </div>

      <div
        v-if="!expanded"
        class="concord-editor-block__preview"
        :aria-expanded="expanded"
      >
        <slot name="preview" />
      </div>

      <div v-else class="concord-editor-block__body">
        <slot />
      </div>
    </div>
  </article>
</template>

<script>
import { inject, ref, watch } from 'vue'
import ConcordGroupDeleteIcon from './ConcordGroupDeleteIcon.vue'

const NO_TOGGLE_SELECTOR = [
  '[data-concord-no-toggle]',
  '.concord-editor-block__handle',
  '.concord-editor-block__head-text',
  '.concord-editor-block__head-actions',
  '.concord-editor-block__delete',
  '.concord-editor-block__label-input',
  '.concord-block__add-btn',
  '.concord-agreement-editor__add-btn',
  '.concord-block-types',
  'input',
  'textarea',
  'select',
  'option',
  'a[href]',
  'button',
  'label',
  '[contenteditable="true"]',
  '[role="textbox"]',
  '[role="button"]',
].join(', ')

export default {
  name: 'ConcordEditorBlockShell',
  components: { ConcordGroupDeleteIcon },
  props: {
    blockId: {
      type: String,
      default: '',
    },
    label: {
      type: String,
      required: true,
    },
    placeholder: {
      type: String,
      default: 'Название блока',
    },
    summary: {
      type: String,
      default: '',
    },
    defaultExpanded: {
      type: Boolean,
      default: false,
    },
    expandOnAdd: {
      type: Boolean,
      default: false,
    },
    forceCollapsed: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['expand', 'collapse', 'update:label', 'delete'],
  setup(props, { emit }) {
    const sectionBlocksLocked = inject('sectionBlocksLocked', false)
    const expanded = ref(props.defaultExpanded)
    const labelInputRef = ref(null)
    const draftLabel = ref(props.label)
    const labelSnapshot = ref(props.label)
    let syncingFromProp = false

    watch(
      () => props.defaultExpanded,
      (value) => {
        if (props.forceCollapsed) {
          return
        }
        expanded.value = value
      }
    )

    watch(
      () => props.expandOnAdd,
      (shouldExpand) => {
        if (shouldExpand && !props.forceCollapsed && !isLocked()) {
          expanded.value = true
        }
      },
      { immediate: true }
    )

    watch(
      () => props.forceCollapsed,
      (locked) => {
        if (locked) {
          expanded.value = false
        }
      },
      { immediate: true }
    )

    watch(
      () => props.label,
      (value) => {
        if (document.activeElement === labelInputRef.value) {
          return
        }
        syncingFromProp = true
        draftLabel.value = value
        labelSnapshot.value = value
        syncingFromProp = false
      }
    )

    watch(draftLabel, (value) => {
      if (syncingFromProp) {
        return
      }
      emit('update:label', value)
    })

    watch(
      () => sectionBlocksLocked?.value ?? sectionBlocksLocked,
      (locked) => {
        if (locked) {
          expanded.value = false
        }
      },
      { immediate: true }
    )

    function isLocked() {
      return sectionBlocksLocked?.value ?? sectionBlocksLocked
    }

    function shouldIgnoreToggle(event) {
      return Boolean(event.target.closest(NO_TOGGLE_SELECTOR))
    }

    function toggle() {
      if (isLocked() || props.forceCollapsed) {
        return
      }
      const next = !expanded.value
      expanded.value = next
      if (next) {
        emit('expand')
      } else {
        emit('collapse')
      }
    }

    function onShellClick(event) {
      if (shouldIgnoreToggle(event)) {
        return
      }
      toggle()
    }

    function onLabelFocus() {
      labelSnapshot.value = draftLabel.value
    }

    function commitLabel() {
      const next = String(draftLabel.value || '').trim() || props.placeholder
      draftLabel.value = next
      labelSnapshot.value = next
      emit('update:label', next)
    }

    function revertLabel() {
      syncingFromProp = true
      draftLabel.value = labelSnapshot.value
      syncingFromProp = false
      emit('update:label', labelSnapshot.value)
      blurLabelInput()
    }

    function blurLabelInput() {
      labelInputRef.value?.blur()
    }

    return {
      expanded,
      labelInputRef,
      draftLabel,
      isLocked,
      toggle,
      onShellClick,
      onLabelFocus,
      commitLabel,
      revertLabel,
      blurLabelInput,
    }
  },
}
</script>
