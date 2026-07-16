<template>
  <GroupMembersView
    v-if="sectionGroupMembersOpen && editingGroup"
    :group-title="editingGroup.title"
    :contacts="contacts"
    :member-ids="editingGroup.memberIds"
    @back="closeSectionGroupMembers"
    @save="onSectionGroupMembersSave"
  />

  <CreateGroupView
    v-if="sectionGroupCreateOpen"
    :contacts="contacts"
    @back="closeSectionGroupCreate"
    @save="onSectionGroupCreate"
  />

  <GroupsManageView
    v-if="sectionGroupPickerOpen"
    :groups="groups"
    picker-mode
    :initial-selected-ids="sectionGroupPickerIds"
    @back="closeSectionGroupPicker"
    @confirm="onSectionGroupsConfirm"
    @create-group="() => openSectionGroupCreate(true)"
    @edit-group="openSectionGroupMembers"
  />

  <AgreementSettingsView
    v-if="agreementSettingsOpen && agreement"
    :agreement="agreement"
    @back="closeAgreementSettings"
    @save="onAgreementSettingsSave"
  />

  <SectionSettingsView
    v-if="sectionSettingsOpen && activeSection"
    v-show="!sectionGroupPickerOpen && !sectionGroupCreateOpen && !sectionGroupMembersOpen"
    ref="sectionSettingsRef"
    :section="activeSection"
    :contacts="contacts"
    :groups="groups"
    @back="closeSectionSettings"
    @save="onSectionSettingsSave"
    @pick-groups="openSectionGroupPicker"
  />

  <div
    v-if="agreement && !agreementSettingsOpen && !sectionSettingsOpen && !sectionGroupPickerOpen && !sectionGroupCreateOpen && !sectionGroupMembersOpen"
    class="concord-page concord-page--editor"
  >
    <header class="concord-header concord-header--editor">
      <button type="button" class="concord-icon-btn" aria-label="Назад" @click="goBack">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <h1 class="concord-header__title concord-header__title--truncate">
        {{ agreement.title || 'Без названия' }}
      </h1>
      <button
        type="button"
        class="concord-icon-btn"
        aria-label="Настройки согласования"
        @click="openAgreementSettings"
      >
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
          <circle cx="5" cy="12" r="1.6" fill="currentColor"/>
          <circle cx="12" cy="12" r="1.6" fill="currentColor"/>
          <circle cx="19" cy="12" r="1.6" fill="currentColor"/>
        </svg>
      </button>
    </header>

    <div v-if="showSectionTabs" class="concord-agreement-editor__tabs-wrap">
      <div class="concord-tabs concord-tabs--scroll" role="tablist" aria-label="Контейнеры согласования">
        <button
          v-for="section in agreement.sections"
          :key="section.id"
          type="button"
          role="tab"
          :class="['concord-tabs__item', { 'concord-tabs__item--active': activeSectionId === section.id }]"
          :aria-selected="activeSectionId === section.id"
          :title="section.title"
          @click="activeSectionId = section.id"
        >
          {{ formatSectionTabTitle(section.title) }}
        </button>
      </div>
    </div>

    <main class="concord-agreement-editor">
      <section v-if="showDraftIntro" class="concord-agreement-editor__panel">
        <p v-if="agreement.description" class="concord-agreement-editor__description">
          {{ agreement.description }}
        </p>
        <p v-else class="concord-agreement-editor__description concord-agreement-editor__description--muted">
          {{ editorIntro }}
        </p>

        <BlockAddZone
          :show-block-types="showBlockTypes"
          :block-types="editorBlockTypes"
          @toggle="onBlockAddClick"
          @add="addBlock"
        />
      </section>

      <AgreementContainerCard
        v-if="showContainer && activeSection"
        :agreement="agreement"
        :section="activeSection"
        :groups="groups"
        :contacts="contacts"
        @section-settings="openSectionSettings"
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

        <BlockAddZone
          :show-block-types="showBlockTypes"
          :block-types="editorBlockTypes"
          @toggle="onBlockAddClick"
          @add="addBlock"
        />
      </AgreementContainerCard>

      <section v-if="showNewContainer" class="concord-agreement-editor__add-zone concord-agreement-editor__add-zone--section">
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

    <TextBlockEditorSheet
      :open="textEditorOpen"
      :block="editingTextBlock"
      @close="closeTextEditor"
    />
  </div>
</template>

<script>
import { computed, ref, watch } from 'vue'
import {
  AGREEMENT_EDITOR_BLOCK_TYPES,
  AGREEMENT_EDITOR_INTRO,
  ensureAgreementSections,
  formatSectionTabTitle,
} from '../concord/mock-agreements.js'
import AgreementContainerCard from '../concord/AgreementContainerCard.vue'
import BlockAddZone from '../concord/BlockAddZone.vue'
import ConcordGalleryBlockCard from '../concord/ConcordGalleryBlockCard.vue'
import ConcordCheckboxBlockCard from '../concord/ConcordCheckboxBlockCard.vue'
import ConcordFilesBlockCard from '../concord/ConcordFilesBlockCard.vue'
import ConcordTextBlockPreview from '../concord/ConcordTextBlockPreview.vue'
import AgreementSettingsView from './AgreementSettingsView.vue'
import SectionSettingsView from './SectionSettingsView.vue'
import GroupsManageView from './GroupsManageView.vue'
import GroupMembersView from './GroupMembersView.vue'
import CreateGroupView from './CreateGroupView.vue'
import TextBlockEditorSheet from '../concord/TextBlockEditorSheet.vue'

export default {
  name: 'AgreementEditorView',
  components: {
    AgreementContainerCard,
    AgreementSettingsView,
    BlockAddZone,
    ConcordCheckboxBlockCard,
    ConcordFilesBlockCard,
    ConcordGalleryBlockCard,
    ConcordTextBlockPreview,
    CreateGroupView,
    GroupMembersView,
    GroupsManageView,
    SectionSettingsView,
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
  emits: ['back', 'add-block', 'add-section', 'update-section', 'create-group', 'update-group'],
  setup(props, { emit }) {
    const activeSectionId = ref(null)
    const showBlockTypes = ref(false)
    const editorBlockTypes = AGREEMENT_EDITOR_BLOCK_TYPES
    const editorIntro = AGREEMENT_EDITOR_INTRO

    const sectionSettingsOpen = ref(false)
    const agreementSettingsOpen = ref(false)
    const sectionSettingsRef = ref(null)
    const sectionGroupPickerOpen = ref(false)
    const sectionGroupPickerIds = ref([])
    const sectionGroupCreateOpen = ref(false)
    const sectionGroupCreateReturnToPicker = ref(false)
    const sectionGroupMembersOpen = ref(false)
    const editingGroupId = ref(null)

    const textEditorOpen = ref(false)
    const editingTextBlock = ref(null)

    const isDraft = computed(() => props.agreement?.status === 'draft')

    const activeSection = computed(() =>
      props.agreement?.sections?.find((item) => item.id === activeSectionId.value) || null
    )

    const hasBlocks = computed(() => (activeSection.value?.blocks?.length || 0) > 0)

    const sectionCount = computed(() => props.agreement?.sections?.length || 0)

    const isFirstSection = computed(() => activeSectionId.value === props.agreement?.sections?.[0]?.id)

    const hasAnySectionWithBlocks = computed(() =>
      props.agreement?.sections?.some((section) => section.blocks?.length > 0) ?? false
    )

    const showDraftIntro = computed(() => isDraft.value && !hasBlocks.value && sectionCount.value === 1)

    const showContainer = computed(() => {
      if (!isDraft.value) {
        return true
      }
      if (hasBlocks.value) {
        return true
      }
      return sectionCount.value > 1
    })

    const showSectionTabs = computed(() => sectionCount.value > 1)

    const showNewContainer = computed(() => hasAnySectionWithBlocks.value || sectionCount.value > 1)

    const editingGroup = computed(() =>
      props.groups.find((item) => item.id === editingGroupId.value) || null
    )

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
        if (showDraftIntro.value) {
          showBlockTypes.value = true
        }
      },
      { immediate: true }
    )

    watch(hasBlocks, (value, prev) => {
      if (value) {
        showBlockTypes.value = false
        return
      }
      if (isDraft.value && prev && !value) {
        showBlockTypes.value = true
      }
    })

    watch(activeSectionId, () => {
      if (!isDraft.value) {
        return
      }
      if (showDraftIntro.value || (showContainer.value && !hasBlocks.value)) {
        showBlockTypes.value = true
      }
    })

    function openAgreementSettings() {
      agreementSettingsOpen.value = true
    }

    function closeAgreementSettings() {
      agreementSettingsOpen.value = false
    }

    function onAgreementSettingsSave(payload) {
      if (!props.agreement) {
        return
      }
      props.agreement.title = payload.title
      props.agreement.description = payload.description
    }

    function goBack() {
      emit('back')
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
      const nextIndex = (props.agreement?.sections?.length || 0) + 1
      emit('add-section', {
        title: `Контейнер ${nextIndex}`,
        participantIds: [],
        groupIds: [],
      })
    }

    function openSectionSettings() {
      sectionSettingsOpen.value = true
    }

    function closeSectionSettings() {
      sectionSettingsOpen.value = false
    }

    function onSectionSettingsSave(payload) {
      if (!activeSectionId.value) {
        return
      }
      emit('update-section', {
        sectionId: activeSectionId.value,
        ...payload,
      })
      if (payload.settings?.isImportant !== undefined) {
        props.agreement.isUrgent = payload.settings.isImportant
      }
    }

    function openSectionGroupPicker(groupIds = []) {
      sectionGroupPickerIds.value = [...groupIds]
      sectionGroupPickerOpen.value = true
    }

    function closeSectionGroupPicker() {
      sectionGroupPickerOpen.value = false
    }

    function onSectionGroupsConfirm(groupIds) {
      sectionSettingsRef.value?.applyGroupSelection(groupIds)
      closeSectionGroupPicker()
    }

    function openSectionGroupCreate(returnToPicker = false) {
      sectionGroupCreateReturnToPicker.value = returnToPicker
      sectionGroupCreateOpen.value = true
      if (returnToPicker) {
        sectionGroupPickerOpen.value = false
      }
    }

    function closeSectionGroupCreate() {
      sectionGroupCreateOpen.value = false
      if (sectionGroupCreateReturnToPicker.value) {
        sectionGroupPickerOpen.value = true
      }
      sectionGroupCreateReturnToPicker.value = false
    }

    function onSectionGroupCreate(payload) {
      emit('create-group', payload)
      sectionGroupCreateOpen.value = false
      if (sectionGroupCreateReturnToPicker.value) {
        sectionGroupPickerOpen.value = true
      }
      sectionGroupCreateReturnToPicker.value = false
    }

    function openSectionGroupMembers(groupId) {
      editingGroupId.value = groupId
      sectionGroupMembersOpen.value = true
    }

    function closeSectionGroupMembers() {
      sectionGroupMembersOpen.value = false
      editingGroupId.value = null
      sectionGroupPickerOpen.value = true
    }

    function onSectionGroupMembersSave(payload) {
      if (!editingGroupId.value) {
        return
      }
      emit('update-group', {
        groupId: editingGroupId.value,
        title: payload.title,
        memberIds: payload.memberIds,
      })
      closeSectionGroupMembers()
    }

    watch(
      () => props.agreement?.sections?.length,
      (length, prev) => {
        if (length > prev && props.agreement?.sections?.length) {
          activeSectionId.value = props.agreement.sections[props.agreement.sections.length - 1].id
          if (isDraft.value) {
            showBlockTypes.value = true
          }
        }
      }
    )

    return {
      activeSectionId,
      activeSection,
      isDraft,
      hasBlocks,
      showDraftIntro,
      showContainer,
      showSectionTabs,
      showNewContainer,
      showBlockTypes,
      formatSectionTabTitle,
      editorBlockTypes,
      editorIntro,
      agreementSettingsOpen,
      sectionSettingsOpen,
      sectionSettingsRef,
      sectionGroupPickerOpen,
      sectionGroupPickerIds,
      sectionGroupCreateOpen,
      sectionGroupMembersOpen,
      editingGroup,
      textEditorOpen,
      editingTextBlock,
      goBack,
      openAgreementSettings,
      closeAgreementSettings,
      onAgreementSettingsSave,
      onBlockAddClick,
      addBlock,
      openTextEditor,
      closeTextEditor,
      openNewSection,
      openSectionSettings,
      closeSectionSettings,
      onSectionSettingsSave,
      openSectionGroupPicker,
      closeSectionGroupPicker,
      onSectionGroupsConfirm,
      openSectionGroupCreate,
      closeSectionGroupCreate,
      onSectionGroupCreate,
      openSectionGroupMembers,
      closeSectionGroupMembers,
      onSectionGroupMembersSave,
    }
  },
}
</script>
