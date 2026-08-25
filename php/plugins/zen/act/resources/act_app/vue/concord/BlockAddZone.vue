<template>
  <section class="concord-agreement-editor__add-zone concord-agreement-editor__add-zone--block">
    <div class="concord-agreement-editor__block-picker">
      <div v-if="showBlockTypes" class="concord-agreement-editor__block-picker-head">
        <h3 class="concord-agreement-editor__block-picker-title">Добавить блок</h3>
        <button
          type="button"
          class="concord-agreement-editor__block-picker-close"
          @click="$emit('toggle')"
        >
          Скрыть
        </button>
      </div>

      <div class="concord-profile-card">
        <button
          v-if="!showBlockTypes"
          type="button"
          class="concord-agreement-editor__block-trigger"
          aria-label="Добавить блок"
          @click="$emit('toggle')"
        >
          <span class="concord-agreement-editor__block-type-icon" aria-hidden="true">
            <ConcordPlusIcon />
          </span>
          <span class="concord-agreement-editor__block-type-label">Добавить блок</span>
        </button>

        <template v-else>
          <button
            v-for="(blockType, index) in blockTypes"
            :key="blockType.id"
            type="button"
            class="concord-agreement-editor__block-type-row"
            :class="{ 'concord-agreement-editor__block-type-row--last': index === blockTypes.length - 1 }"
            @click="$emit('add', blockType)"
          >
            <span class="concord-agreement-editor__block-type-icon" aria-hidden="true">
              <ConcordPlusIcon />
            </span>
            <span class="concord-agreement-editor__block-type-label">{{ blockType.label }}</span>
          </button>
        </template>
      </div>
    </div>
  </section>
</template>

<script>
import ConcordPlusIcon from './ConcordPlusIcon.vue'

export default {
  name: 'BlockAddZone',
  components: { ConcordPlusIcon },
  props: {
    showBlockTypes: {
      type: Boolean,
      default: false,
    },
    blockTypes: {
      type: Array,
      required: true,
    },
  },
  emits: ['toggle', 'add'],
}
</script>
