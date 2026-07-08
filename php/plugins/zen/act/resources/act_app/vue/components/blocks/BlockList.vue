<template>
  <section :class="mode === 'site' ? 'tg-site-blocks' : 'tg-blocks'">
    <div
      v-if="blocks.length > 0"
      ref="listRef"
      :class="['tg-blocks__list', { 'tg-blocks__list--reordering': reordering }]"
    >
      <template v-if="mode === 'site'">
        <template v-for="block in blocks" :key="block.id">
          <TextBlock
            v-if="blockType(block) === 'text'"
            :block="block"
            :viewer="viewer"
            :mode="mode"
            :start-in-edit-mode="block.id === editBlockId"
            @save="$emit('save', $event)"
            @delete="$emit('delete', $event)"
            @settings="$emit('settings', $event)"
          />
          <MarkdownBlock
            v-else-if="blockType(block) === 'markdown'"
            :block="block"
            :viewer="viewer"
            :mode="mode"
            :start-in-edit-mode="block.id === editBlockId"
            @save="$emit('save', $event)"
            @delete="$emit('delete', $event)"
            @settings="$emit('settings', $event)"
          />
          <WebappBlock
            v-else-if="blockType(block) === 'webapp'"
            :block="block"
            :viewer="viewer"
            :mode="mode"
            :start-in-edit-mode="block.id === editBlockId"
            @save="$emit('save', $event)"
            @delete="$emit('delete', $event)"
            @settings="$emit('settings', $event)"
          />
          <ChecklistBlock
            v-else-if="blockType(block) === 'checklist'"
            :act-id="actId"
            :block="block"
            :viewer="viewer"
            :mode="mode"
            :view-as-login="viewAsLogin"
            :start-in-edit-mode="block.id === editBlockId"
            @save="$emit('save', $event)"
            @delete="$emit('delete', $event)"
            @settings="$emit('settings', $event)"
            @sign-checklist-item="$emit('sign-checklist-item', $event)"
          />
          <GalleryBlock
            v-else-if="blockType(block) === 'gallery'"
            :act-id="actId"
            :block="block"
            :viewer="viewer"
            :mode="mode"
            :start-in-edit-mode="block.id === editBlockId"
            @save="$emit('save', $event)"
            @delete="$emit('delete', $event)"
            @settings="$emit('settings', $event)"
          />
        </template>
      </template>

      <template v-else>
        <div
          v-for="block in blocks"
          :key="block.id"
          :class="['tg-block-item', { 'tg-block-item--no-handle': !showDragHandles }]"
          :data-block-id="block.id"
        >
          <button
            v-if="showDragHandles"
            type="button"
            class="tg-block-drag-handle"
            aria-label="Переместить блок"
            :disabled="reordering"
          >
            <span aria-hidden="true">≡</span>
          </button>
          <div class="tg-block-item__content">
            <TextBlock
              v-if="blockType(block) === 'text'"
              :block="block"
              :viewer="viewer"
              :mode="mode"
              :start-in-edit-mode="block.id === editBlockId"
              @save="$emit('save', $event)"
              @delete="$emit('delete', $event)"
              @settings="$emit('settings', $event)"
            />
            <MarkdownBlock
              v-else-if="blockType(block) === 'markdown'"
              :block="block"
              :viewer="viewer"
              :mode="mode"
              :start-in-edit-mode="block.id === editBlockId"
              @save="$emit('save', $event)"
              @delete="$emit('delete', $event)"
              @settings="$emit('settings', $event)"
            />
            <WebappBlock
              v-else-if="blockType(block) === 'webapp'"
              :block="block"
              :viewer="viewer"
              :mode="mode"
              :start-in-edit-mode="block.id === editBlockId"
              @save="$emit('save', $event)"
              @delete="$emit('delete', $event)"
              @settings="$emit('settings', $event)"
            />
            <ChecklistBlock
              v-else-if="blockType(block) === 'checklist'"
              :act-id="actId"
              :block="block"
              :viewer="viewer"
              :mode="mode"
              :view-as-login="viewAsLogin"
              :start-in-edit-mode="block.id === editBlockId"
              @save="$emit('save', $event)"
              @delete="$emit('delete', $event)"
              @settings="$emit('settings', $event)"
              @sign-checklist-item="$emit('sign-checklist-item', $event)"
            />
            <GalleryBlock
              v-else-if="blockType(block) === 'gallery'"
              :act-id="actId"
              :block="block"
              :viewer="viewer"
              :mode="mode"
              :start-in-edit-mode="block.id === editBlockId"
              @save="$emit('save', $event)"
              @delete="$emit('delete', $event)"
              @settings="$emit('settings', $event)"
            />
            <article v-else class="tg-block-card tg-block-card--unsupported">
              <p class="tg-block-card__type">{{ blockType(block) }}</p>
              <h2 class="tg-block-card__title">{{ block.name || 'Блок' }}</h2>
              <p class="tg-stub__text">Тип блока пока не поддерживается в SPA.</p>
            </article>
          </div>
        </div>
      </template>
    </div>

    <button
      v-if="canAdd"
      type="button"
      class="tg-block-add"
      aria-label="Добавить блок"
      :disabled="adding || reordering"
      @click="$emit('add')"
    >
      <span class="tg-block-add__plus" aria-hidden="true">{{ adding ? '…' : '+' }}</span>
    </button>
  </section>
</template>

<script>
import { computed, onMounted, ref, toRef } from 'vue'
import ChecklistBlock from './ChecklistBlock.vue'
import GalleryBlock from './GalleryBlock.vue'
import MarkdownBlock from './MarkdownBlock.vue'
import TextBlock from './TextBlock.vue'
import WebappBlock from './WebappBlock.vue'
import { useBlockSortable } from './useBlockSortable.js'

export default {
  name: 'BlockList',
  components: { ChecklistBlock, GalleryBlock, MarkdownBlock, TextBlock, WebappBlock },
  props: {
    blocks: {
      type: Array,
      default: () => [],
    },
    viewer: {
      type: String,
      default: 'guest',
      validator: (value) => ['owner', 'guest'].includes(value),
    },
    viewAsLogin: {
      type: String,
      default: '',
    },
    canAdd: {
      type: Boolean,
      default: false,
    },
    adding: {
      type: Boolean,
      default: false,
    },
    reordering: {
      type: Boolean,
      default: false,
    },
    editBlockId: {
      type: String,
      default: '',
    },
    actId: {
      type: String,
      default: '',
    },
    reorderEnabled: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['save', 'delete', 'add', 'reorder', 'settings', 'sign-checklist-item'],
  setup(props, { emit }) {
    const listRef = ref(null)
    const mode = computed(() => (props.viewer === 'guest' ? 'site' : 'edit'))
    const reorderEnabled = toRef(props, 'reorderEnabled')
    const showDragHandles = computed(() => (
      props.viewer === 'owner'
      && props.blocks.length > 1
      && reorderEnabled.value
    ))
    const canReorder = showDragHandles
    const reordering = toRef(props, 'reordering')

    const blockType = (block) => block?.data?.type || 'unknown'

    const onReorder = (blockIds) => {
      emit('reorder', blockIds)
    }

    const { initSortable } = useBlockSortable(listRef, {
      canReorder,
      reordering,
      onReorder,
    })

    onMounted(() => {
      initSortable()
    })

    return {
      listRef,
      mode,
      blockType,
      showDragHandles,
    }
  },
}
</script>
