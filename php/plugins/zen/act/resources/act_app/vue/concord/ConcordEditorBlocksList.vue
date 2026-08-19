<template>
  <div
    v-if="blocks.length"
    ref="listRef"
    class="concord-container__blocks"
  >
    <ConcordEditorBlockShell
      v-for="(block, blockIndex) in blocks"
      :key="block.id"
      :block-id="block.id"
      :label="typeof block.label === 'string' ? block.label : getEditorBlockTypeLabel(block)"
      :placeholder="getEditorBlockTypeLabel(block)"
      :summary="block.type === 'text' ? '' : getEditorBlockSummary(block)"
      :default-expanded="defaultExpandFirst && blockIndex === 0"
      :force-collapsed="isDragging"
      @update:label="(value) => renameEditorBlock(block, value)"
      @delete="$emit('delete-block', block)"
    >
      <template #preview>
        <slot name="preview" :block="block" />
      </template>
      <slot :block="block" />
    </ConcordEditorBlockShell>
  </div>
</template>

<script>
import { computed, ref, watch } from 'vue'
import ConcordEditorBlockShell from './ConcordEditorBlockShell.vue'
import {
  getEditorBlockSummary,
  getEditorBlockTypeLabel,
  renameEditorBlock,
} from './editor-block-utils.js'
import { useEditorBlockSortable } from './useEditorBlockSortable.js'

export default {
  name: 'ConcordEditorBlocksList',
  components: { ConcordEditorBlockShell },
  props: {
    section: {
      type: Object,
      required: true,
    },
    defaultExpandFirst: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['delete-block'],
  setup(props, { emit }) {
    const listRef = ref(null)
    const isDragging = ref(false)
    const blocks = computed(() => props.section.blocks || [])
    const canReorder = computed(() => blocks.value.length > 1)

    function reorderBlocks(blockIds) {
      const current = props.section.blocks || []
      const byId = new Map(current.map((block) => [block.id, block]))
      const next = blockIds.map((id) => byId.get(id)).filter(Boolean)
      for (const block of current) {
        if (!next.includes(block)) {
          next.push(block)
        }
      }
      props.section.blocks = next
    }

    const { initSortable } = useEditorBlockSortable(listRef, {
      canReorder,
      onReorder: reorderBlocks,
      onDragStart() {
        isDragging.value = true
      },
      onDragEnd() {
        isDragging.value = false
      },
    })

    watch(
      () => blocks.value.map((block) => block.id).join('|'),
      () => {
        initSortable()
      },
      { flush: 'post' }
    )

    return {
      listRef,
      blocks,
      isDragging,
      getEditorBlockSummary,
      getEditorBlockTypeLabel,
      renameEditorBlock,
    }
  },
}
</script>
