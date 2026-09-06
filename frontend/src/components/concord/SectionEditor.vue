<template>
  <article class="concord-container concord-container--editor">
    <header class="concord-container__header">
      <div class="concord-container__header-main">
        <input
          v-model="section.name"
          class="concord-container__title"
          type="text"
          aria-label="Название раздела"
          @change="$emit('save-section')"
        >
      </div>
      <div class="concord-container__header-actions">
        <button
          type="button"
          class="concord-editor-block__delete"
          aria-label="Удалить раздел"
          @click="$emit('delete-section')"
        >
          <ConcordGroupDeleteIcon />
        </button>
      </div>
    </header>

    <div class="concord-container__body">
      <div v-if="section.blocks?.length" class="concord-container__blocks">
        <ConcordEditorBlockShell
          v-for="block in section.blocks"
          :key="block.id"
          :label="getEditorBlockLabel(block)"
          :placeholder="getEditorBlockTypeLabel(block)"
          :summary="getEditorBlockSummary(block)"
          :default-expanded="block.id === expandedBlockId"
          @update:label="(value) => updateBlockLabel(block, value)"
          @delete="$emit('delete-block', block)"
        >
          <template #preview>
            <p class="concord-editor-block__preview-meta">{{ getEditorBlockTypeLabel(block) }}</p>
            <p class="concord-editor-block__text-excerpt">{{ getEditorBlockSummary(block) }}</p>
          </template>
          <EditorBlockContent
            :block="block"
            @save="$emit('save-block', block)"
            @delete-file="(file) => $emit('delete-file', block, file)"
            @upload-file="(file) => $emit('upload-file', block, file)"
          />
        </ConcordEditorBlockShell>
      </div>

      <BlockAddZone
        :show-block-types="pickerOpen"
        :block-types="EDITOR_BLOCK_TYPES"
        @toggle="pickerOpen = !pickerOpen"
        @add="onAddBlock"
      />
    </div>
  </article>
</template>

<script setup>
import { ref } from 'vue'
import BlockAddZone from './BlockAddZone.vue'
import ConcordEditorBlockShell from './ConcordEditorBlockShell.vue'
import ConcordGroupDeleteIcon from './ConcordGroupDeleteIcon.vue'
import EditorBlockContent from './EditorBlockContent.vue'
import {
  EDITOR_BLOCK_TYPES,
  getEditorBlockLabel,
  getEditorBlockSummary,
  getEditorBlockTypeLabel,
} from './editor-block-utils.js'

defineProps({
  section: { type: Object, required: true },
  expandedBlockId: { type: [Number, String], default: null },
})

const emit = defineEmits([
  'save-section',
  'delete-section',
  'save-block',
  'add-block',
  'delete-block',
  'delete-file',
  'upload-file',
])

const pickerOpen = ref(false)

function onAddBlock(blockType) {
  pickerOpen.value = false
  emit('add-block', blockType.id)
}

function updateBlockLabel(block, value) {
  block.label = value
  block.title = value
  emit('save-block', block)
}
</script>
