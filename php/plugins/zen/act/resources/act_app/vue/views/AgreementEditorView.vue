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

  <Teleport to="body">
    <div
      v-if="showEditorSurface && showSectionTabs"
      ref="editorChromeRef"
      class="concord-agreement-editor__chrome"
    >
      <header class="concord-header concord-header--editor">
        <button type="button" class="concord-icon-btn" aria-label="Назад" @click="goBack">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
        <h1 class="concord-header__title concord-header__title--truncate">
          {{ agreement?.title || 'Без названия' }}
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

      <div class="concord-agreement-editor__tabs-wrap">
        <div class="concord-tabs concord-tabs--scroll" role="tablist" aria-label="Разделы согласования">
          <button
            v-for="section in agreement.sections"
            :key="section.id"
            type="button"
            role="tab"
            :class="['concord-tabs__item', { 'concord-tabs__item--active': activeSectionId === section.id }]"
            :aria-selected="activeSectionId === section.id"
            :title="section.title"
            @click="scrollToSection(section.id)"
          >
            {{ formatSectionTabTitle(section.title) }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <div
    v-if="showEditorSurface"
    class="concord-page concord-page--editor"
    :style="editorPageStyle"
  >
    <header v-if="!showSectionTabs" class="concord-header concord-header--editor">
      <button type="button" class="concord-icon-btn" aria-label="Назад" @click="goBack">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <h1 class="concord-header__title concord-header__title--truncate">
        {{ agreement?.title || 'Без названия' }}
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

    <div
      v-if="showSectionTabs"
      class="concord-agreement-editor__chrome-spacer"
      aria-hidden="true"
      :style="{ height: `${editorChromeHeight}px` }"
    />

    <main ref="editorMainRef" class="concord-agreement-editor">
      <section v-if="showDraftIntro" class="concord-agreement-editor__panel">
        <p v-if="agreement.description" class="concord-agreement-editor__description">
          {{ agreement.description }}
        </p>
        <p v-else class="concord-agreement-editor__description concord-agreement-editor__description--muted">
          {{ editorIntro }}
        </p>

        <BlockAddZone
          :show-block-types="openBlockTypesSectionId === firstSectionId"
          :block-types="editorBlockTypes"
          @toggle="toggleBlockTypes(firstSectionId)"
          @add="(blockType) => addBlock(firstSectionId, blockType)"
        />
      </section>

      <div
        v-for="section in agreement.sections"
        :key="section.id"
        :id="sectionAnchorId(section.id)"
        :ref="(el) => setSectionRef(section.id, el)"
        class="concord-agreement-editor__section-anchor"
      >
        <AgreementContainerCard
          v-if="shouldShowSectionContainer(section)"
          :agreement="agreement"
          :section="section"
          :groups="groups"
          :contacts="contacts"
          @section-settings="openSectionSettings(section.id)"
        >
          <div v-if="section.blocks?.length" class="concord-container__blocks">
            <template v-for="block in section.blocks" :key="block.id">
              <ConcordFilesBlockCard v-if="block.type === 'files'" :block="block" />
              <ConcordGalleryBlockCard v-else-if="block.type === 'gallery'" :block="block" />
              <ConcordTextBlockPreview
                v-else-if="block.type === 'text'"
                :block="block"
                @edit="openTextEditor(block)"
              />
              <ConcordLinksBlockCard v-else-if="block.type === 'link'" :block="block" />
              <article v-else class="concord-agreement-editor__block-card">
                <h3 class="concord-agreement-editor__block-title">{{ block.label }}</h3>
                <p class="concord-agreement-editor__block-placeholder">Блок добавлен. Контент появится на следующем этапе.</p>
              </article>
            </template>
          </div>

          <BlockAddZone
            :show-block-types="openBlockTypesSectionId === section.id"
            :block-types="editorBlockTypes"
            @toggle="toggleBlockTypes(section.id)"
            @add="(blockType) => addBlock(section.id, blockType)"
          />
        </AgreementContainerCard>
      </div>

      <section v-if="showNewContainer" class="concord-agreement-editor__add-zone concord-agreement-editor__add-zone--section">
        <button
          type="button"
          class="concord-agreement-editor__add-btn concord-agreement-editor__add-btn--section"
          aria-label="Добавить раздел"
          @click="openNewSection"
        >
          <span aria-hidden="true">+</span>
        </button>
        <p class="concord-agreement-editor__add-section-label">Новый раздел</p>
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
import { computed, ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import {
  AGREEMENT_EDITOR_BLOCK_TYPES,
  AGREEMENT_EDITOR_INTRO,
  ensureAgreementSections,
  formatSectionTabTitle,
} from '../concord/mock-agreements.js'
import AgreementContainerCard from '../concord/AgreementContainerCard.vue'
import BlockAddZone from '../concord/BlockAddZone.vue'
import ConcordGalleryBlockCard from '../concord/ConcordGalleryBlockCard.vue'
import ConcordLinksBlockCard from '../concord/ConcordLinksBlockCard.vue'
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
    ConcordLinksBlockCard,
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
    const openBlockTypesSectionId = ref(null)
    const editorMainRef = ref(null)
    const editorChromeRef = ref(null)
    const editorChromeHeight = ref(118)
    const sectionRefs = new Map()
    const isProgrammaticScroll = ref(false)
    let sectionObserver = null
    let chromeResizeObserver = null
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

    const showEditorSurface = computed(() =>
      Boolean(
        props.agreement
        && !agreementSettingsOpen.value
        && !sectionSettingsOpen.value
        && !sectionGroupPickerOpen.value
        && !sectionGroupCreateOpen.value
        && !sectionGroupMembersOpen.value
      )
    )

    const isDraft = computed(() => props.agreement?.status === 'draft')

    const firstSectionId = computed(() => props.agreement?.sections?.[0]?.id || null)

    const activeSection = computed(() =>
      props.agreement?.sections?.find((item) => item.id === activeSectionId.value) || null
    )

    const firstSectionHasBlocks = computed(() => (props.agreement?.sections?.[0]?.blocks?.length || 0) > 0)

    const sectionCount = computed(() => props.agreement?.sections?.length || 0)

    const hasAnySectionWithBlocks = computed(() =>
      props.agreement?.sections?.some((section) => section.blocks?.length > 0) ?? false
    )

    const showDraftIntro = computed(() => isDraft.value && !firstSectionHasBlocks.value && sectionCount.value === 1)

    const showSectionTabs = computed(() => sectionCount.value > 1)

    const showNewContainer = computed(() => hasAnySectionWithBlocks.value || sectionCount.value > 1)

    const editorPageStyle = computed(() => ({
      '--concord-editor-scroll-anchor-offset': `${editorChromeHeight.value + 8}px`,
    }))

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
        if (showDraftIntro.value && firstSectionId.value) {
          openBlockTypesSectionId.value = firstSectionId.value
        }
        nextTick(() => {
          setupChromeResizeObserver()
          setupSectionObserver()
        })
      },
      { immediate: true }
    )

    watch(firstSectionHasBlocks, (value, prev) => {
      if (!isDraft.value || sectionCount.value !== 1) {
        return
      }
      if (value) {
        openBlockTypesSectionId.value = null
        return
      }
      if (prev && !value && firstSectionId.value) {
        openBlockTypesSectionId.value = firstSectionId.value
      }
    })

    function updateEditorChromeHeight() {
      const measured = editorChromeRef.value?.offsetHeight || 0
      editorChromeHeight.value = measured || 118
    }

    function setupChromeResizeObserver() {
      chromeResizeObserver?.disconnect()
      updateEditorChromeHeight()
      if (!editorChromeRef.value || typeof ResizeObserver === 'undefined') {
        return
      }
      chromeResizeObserver = new ResizeObserver(() => {
        updateEditorChromeHeight()
      })
      chromeResizeObserver.observe(editorChromeRef.value)
    }

    function getSectionObserverMargin() {
      const offset = Math.max(editorChromeHeight.value + 8, 72)
      return `-${offset}px 0px -55% 0px`
    }

    function sectionAnchorId(sectionId) {
      return `concord-section-${sectionId}`
    }

    function setSectionRef(sectionId, el) {
      const previous = sectionRefs.get(sectionId)
      if (previous && sectionObserver) {
        sectionObserver.unobserve(previous)
      }
      if (el) {
        el.dataset.sectionId = sectionId
        sectionRefs.set(sectionId, el)
        if (sectionObserver) {
          sectionObserver.observe(el)
        }
        return
      }
      sectionRefs.delete(sectionId)
    }

    function shouldShowSectionContainer(section) {
      if (!isDraft.value) {
        return true
      }
      if (section.blocks?.length) {
        return true
      }
      return sectionCount.value > 1
    }

    function scrollToSection(sectionId) {
      if (!sectionId) {
        return
      }
      isProgrammaticScroll.value = true
      activeSectionId.value = sectionId
      const target = sectionRefs.get(sectionId) || document.getElementById(sectionAnchorId(sectionId))
      target?.scrollIntoView({ behavior: 'smooth', block: 'start' })
      window.setTimeout(() => {
        isProgrammaticScroll.value = false
      }, 700)
    }

    function setupSectionObserver() {
      sectionObserver?.disconnect()
      sectionObserver = null
      if (!showSectionTabs.value) {
        return
      }

      sectionObserver = new IntersectionObserver(
        (entries) => {
          if (isProgrammaticScroll.value) {
            return
          }
          const visible = entries
            .filter((entry) => entry.isIntersecting)
            .sort((a, b) => b.intersectionRatio - a.intersectionRatio)
          const topEntry = visible[0]
          if (!topEntry) {
            return
          }
          const sectionId = topEntry.target.dataset.sectionId
          if (sectionId) {
            activeSectionId.value = sectionId
          }
        },
        {
          root: null,
          rootMargin: getSectionObserverMargin(),
          threshold: [0, 0.15, 0.35, 0.55, 0.75, 1],
        }
      )

      sectionRefs.forEach((element) => {
        sectionObserver.observe(element)
      })
    }

    onMounted(() => {
      nextTick(() => {
        setupChromeResizeObserver()
        setupSectionObserver()
      })
    })

    onBeforeUnmount(() => {
      sectionObserver?.disconnect()
      chromeResizeObserver?.disconnect()
    })

    watch(showEditorSurface, (visible) => {
      if (!visible) {
        return
      }
      nextTick(() => {
        setupChromeResizeObserver()
        setupSectionObserver()
      })
    })

    watch(showSectionTabs, () => {
      nextTick(() => {
        setupChromeResizeObserver()
        setupSectionObserver()
      })
    })

    watch(editorChromeHeight, () => {
      setupSectionObserver()
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

    function toggleBlockTypes(sectionId) {
      if (!sectionId) {
        return
      }
      openBlockTypesSectionId.value = openBlockTypesSectionId.value === sectionId ? null : sectionId
    }

    function addBlock(sectionId, blockType) {
      if (!sectionId) {
        return
      }
      emit('add-block', { sectionId, blockType })
      openBlockTypesSectionId.value = null
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
        title: `Раздел ${nextIndex}`,
        participantIds: [],
        groupIds: [],
      })
    }

    function openSectionSettings(sectionId) {
      activeSectionId.value = sectionId
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
          const newSection = props.agreement.sections[props.agreement.sections.length - 1]
          nextTick(() => {
            setupChromeResizeObserver()
            setupSectionObserver()
            scrollToSection(newSection.id)
            if (isDraft.value) {
              openBlockTypesSectionId.value = newSection.id
            }
          })
        }
      }
    )

    watch(sectionCount, () => {
      nextTick(() => {
        setupChromeResizeObserver()
        setupSectionObserver()
      })
    })

    return {
      activeSectionId,
      activeSection,
      showEditorSurface,
      firstSectionId,
      editorMainRef,
      editorChromeHeight,
      editorPageStyle,
      isDraft,
      showDraftIntro,
      showSectionTabs,
      showNewContainer,
      openBlockTypesSectionId,
      formatSectionTabTitle,
      sectionAnchorId,
      setSectionRef,
      shouldShowSectionContainer,
      scrollToSection,
      toggleBlockTypes,
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
