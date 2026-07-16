<template>
  <section class="concord-agreement-editor__add-zone concord-agreement-editor__add-zone--block">
    <div class="concord-agreement-editor__add-divider">
      <button
        type="button"
        class="concord-agreement-editor__add-btn concord-agreement-editor__add-btn--block"
        :aria-expanded="showBlockTypes"
        aria-label="Добавить блок"
        @click="$emit('toggle')"
      >
        <span aria-hidden="true">+</span>
      </button>
    </div>

    <div v-if="showBlockTypes" class="concord-agreement-editor__block-types">
      <button
        v-for="blockType in blockTypes"
        :key="blockType.id"
        type="button"
        class="concord-agreement-editor__block-type"
        @click="$emit('add', blockType)"
      >
        {{ blockType.label }}
      </button>
    </div>
  </section>
</template>

<script>
export default {
  name: 'BlockAddZone',
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
