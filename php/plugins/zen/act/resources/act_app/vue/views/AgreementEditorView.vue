<template>
  <div v-if="agreement" class="concord-page concord-page--editor">
    <header class="concord-header concord-header--editor">
      <button type="button" class="concord-icon-btn" aria-label="Назад" @click="$emit('back')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <h1 class="concord-header__title concord-header__title--truncate">
        #{{ agreement.number }} {{ agreement.title }}
      </h1>
      <button
        type="button"
        class="concord-icon-btn"
        aria-label="Настройки согласования"
        @click="onAgreementSettings"
      >
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
          <circle cx="5" cy="12" r="1.6" fill="currentColor"/>
          <circle cx="12" cy="12" r="1.6" fill="currentColor"/>
          <circle cx="19" cy="12" r="1.6" fill="currentColor"/>
        </svg>
      </button>
    </header>

    <div class="concord-agreement-editor__tabs-wrap">
      <div class="concord-tabs concord-tabs--scroll" role="tablist" aria-label="Контейнеры согласования">
        <button
          v-for="section in agreement.sections"
          :key="section.id"
          type="button"
          role="tab"
          :class="['concord-tabs__item', { 'concord-tabs__item--active': activeSectionId === section.id }]"
          :aria-selected="activeSectionId === section.id"
          @click="activeSectionId = section.id"
        >
          {{ section.title }}
        </button>
      </div>
    </div>

    <main class="concord-agreement-editor">
      <AgreementContainerCard
        v-if="activeSection"
        :agreement="agreement"
        :section="activeSection"
        :groups="groups"
        @section-settings="openSectionSettings(activeSection)"
      >
        <div v-if="activeSection.blocks?.length" class="concord-container__blocks">
          <template v-for="block in activeSection.blocks" :key="block.id">
            <ConcordFilesBlockCard v-if="block.type === 'files'" :block="block" />
            <ConcordGalleryBlockCard v-else-if="block.type === 'gallery'" :block="block" />
            <ConcordTextBlockPreview
              v-else-if="block.type === 'text'"
              :block="block"
              @edit="openTextEditor(block)"
            />
            <ConcordCheckboxBlockCard v-else-if="block.type === 'checkbox'" :block="block" />
            <article v-else class="concord-agreement-editor__block-card">
              <h3 class="concord-agreement-editor__block-title">{{ block.label }}</h3>
              <p class="concord-agreement-editor__block-placeholder">Блок добавлен. Контент появится на следующем этапе.</p>
            </article>
          </template>
        </div>

        <section class="concord-agreement-editor__add-zone concord-agreement-editor__add-zone--block">
          <button
            type="button"
            class="concord-agreement-editor__add-btn concord-agreement-editor__add-btn--block"
            aria-label="Добавить блок"
            @click="onBlockAddClick"
          >
            <span aria-hidden="true">+</span>
          </button>

          <div v-if="showBlockTypes" class="concord-agreement-editor__block-types">
            <button
              v-for="blockType in blockTypes"
              :key="blockType.id"
              type="button"
              class="concord-agreement-editor__block-type"
              @click="addBlock(blockType)"
            >
              {{ blockType.label }}
            </button>
          </div>
        </section>
      </AgreementContainerCard>

      <section class="concord-agreement-editor__add-zone concord-agreement-editor__add-zone--section">
        <button
          type="button"
          class="concord-agreement-editor__add-btn concord-agreement-editor__add-btn--section"
          aria-label="Добавить контейнер"
          @click="openNewSection"
        >
          <span aria-hidden="true">+</span>
        </button>
        <p class="concord-agreement-editor__add-section-label">Новый контейнер</p>
      </section>
    </main>

    <SectionEditorSheet
      :open="sectionSheetOpen"
      :is-edit="sectionSheetMode === 'edit'"
      :initial-title="sectionSheetTitle"
      :initial-participant-ids="sectionSheetParticipantIds"
      :initial-group-ids="sectionSheetGroupIds"
      :contacts="contacts"
      :groups="groups"
      @close="closeSectionSheet"
      @save="onSectionSheetSave"
    />

    <TextBlockEditorSheet
      :open="textEditorOpen"
      :block="editingTextBlock"
      @close="closeTextEditor"
    />
  </div>
</template>

<script>
import { computed, ref, watch } from 'vue'
import { AGREEMENT_BLOCK_TYPES, ensureAgreementSections } from '../concord/mock-agreements.js'
import AgreementContainerCard from '../concord/AgreementContainerCard.vue'
import ConcordGalleryBlockCard from '../concord/ConcordGalleryBlockCard.vue'
import ConcordCheckboxBlockCard from '../concord/ConcordCheckboxBlockCard.vue'
import ConcordFilesBlockCard from '../concord/ConcordFilesBlockCard.vue'
import ConcordTextBlockPreview from '../concord/ConcordTextBlockPreview.vue'
import SectionEditorSheet from '../concord/SectionEditorSheet.vue'
import TextBlockEditorSheet from '../concord/TextBlockEditorSheet.vue'

export default {
  name: 'AgreementEditorView',
  components: {
    AgreementContainerCard,
    ConcordCheckboxBlockCard,
    ConcordFilesBlockCard,
    ConcordGalleryBlockCard,
    ConcordTextBlockPreview,
    SectionEditorSheet,
    TextBlockEditorSheet,
  },
  props: {
    agreement: {
      type: Object,
      default: null,
    },
    contacts: {
      type: Array,
      default: () => [],
    },
    groups: {
      type: Array,
      default: () => [],
    },
  },
  emits: ['back', 'add-block', 'add-section', 'update-section'],
  setup(props, { emit }) {
    const activeSectionId = ref(null)
    const showBlockTypes = ref(false)
    const blockTypes = AGREEMENT_BLOCK_TYPES

    const sectionSheetOpen = ref(false)
    const sectionSheetMode = ref('create')
    const sectionSheetTitle = ref('')
    const sectionSheetParticipantIds = ref([])
    const sectionSheetGroupIds = ref([])
    const editingSectionId = ref(null)

    const textEditorOpen = ref(false)
    const editingTextBlock = ref(null)

    watch(
      () => props.agreement,
      (agreement) => {
        if (!agreement) {
          return
        }
        ensureAgreementSections(agreement)
        if (!activeSectionId.value || !agreement.sections.some((item) => item.id === activeSectionId.value)) {
          activeSectionId.value = agreement.sections[0]?.id || null
        }
      },
      { immediate: true }
    )

    const activeSection = computed(() =>
      props.agreement?.sections?.find((item) => item.id === activeSectionId.value) || null
    )

    function onAgreementSettings() {
      console.info('[concord] agreement settings', props.agreement?.id)
    }

    function onBlockAddClick() {
      showBlockTypes.value = !showBlockTypes.value
    }

    function addBlock(blockType) {
      if (!activeSectionId.value) {
        return
      }
      emit('add-block', { sectionId: activeSectionId.value, blockType })
      showBlockTypes.value = false
    }

    function openTextEditor(block) {
      editingTextBlock.value = block
      textEditorOpen.value = true
    }

    function closeTextEditor() {
      textEditorOpen.value = false
      editingTextBlock.value = null
    }

    function openNewSection() {
      sectionSheetMode.value = 'create'
      editingSectionId.value = null
      sectionSheetTitle.value = `Контейнер ${(props.agreement?.sections?.length || 0) + 1}`
      sectionSheetParticipantIds.value = []
      sectionSheetGroupIds.value = []
      sectionSheetOpen.value = true
    }

    function openSectionSettings(section) {
      sectionSheetMode.value = 'edit'
      editingSectionId.value = section.id
      sectionSheetTitle.value = section.title
      sectionSheetParticipantIds.value = [...(section.participantIds || [])]
      sectionSheetGroupIds.value = [...(section.groupIds || [])]
      sectionSheetOpen.value = true
    }

    function closeSectionSheet() {
      sectionSheetOpen.value = false
    }

    function onSectionSheetSave(payload) {
      if (sectionSheetMode.value === 'edit' && editingSectionId.value) {
        emit('update-section', {
          sectionId: editingSectionId.value,
          ...payload,
        })
        return
      }
      emit('add-section', payload)
    }

    watch(
      () => props.agreement?.sections?.length,
      (length, prev) => {
        if (length > prev && props.agreement?.sections?.length) {
          activeSectionId.value = props.agreement.sections[props.agreement.sections.length - 1].id
        }
      }
    )

    return {
      activeSectionId,
      activeSection,
      showBlockTypes,
      blockTypes,
      sectionSheetOpen,
      sectionSheetMode,
      sectionSheetTitle,
      sectionSheetParticipantIds,
      sectionSheetGroupIds,
      textEditorOpen,
      editingTextBlock,
      onAgreementSettings,
      onBlockAddClick,
      addBlock,
      openTextEditor,
      closeTextEditor,
      openNewSection,
      openSectionSettings,
      closeSectionSheet,
      onSectionSheetSave,
    }
  },
}
</script>
