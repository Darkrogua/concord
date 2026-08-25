<template>
  <div class="concord-agreement-editor-host">
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
    ref="agreementSettingsRef"
    :agreement="agreement"
    @back="closeAgreementSettings"
    @save="onAgreementSettingsSave"
  />

  <SectionSettingsView
    v-if="sectionSettingsOpen && activeSection"
    v-show="!sectionGroupPickerOpen && !sectionGroupCreateOpen && !sectionGroupMembersOpen"
    ref="sectionSettingsRef"
    :agreement="agreement"
    :section="activeSection"
    :contacts="contacts"
    :groups="groups"
    @back="closeSectionSettings"
    @delete="onSectionDelete"
    @save="onSectionSettingsSave"
    @pick-groups="openSectionGroupPicker"
  />

  <SectionScheduleSheet
    v-if="sectionScheduleOpen && scheduleSection"
    :open="sectionScheduleOpen"
    :agreement="agreement"
    :section="scheduleSection"
    @close="closeSectionSchedule"
    @save="saveSectionSchedule"
  />

  <SectionParticipantsSheet
    v-if="sectionParticipantsOpen && participantsSection"
    ref="sectionParticipantsRef"
    :open="sectionParticipantsOpen"
    :section="participantsSection"
    :contacts="contacts"
    :groups="groups"
    @close="closeSectionParticipants"
    @save="saveSectionParticipants"
    @pick-groups="openSectionGroupPicker"
    @open-settings="openParticipantsSectionSettings"
  />

  <AgreementApproverView
    v-if="sectionPreviewOpen && previewAgreement"
    :agreement="previewAgreement"
    :initial-section-id="previewSectionId"
    preview
    @back="closeSectionPreview"
    @section-settings="openPreviewSectionSettings"
  />

  <Teleport to="body">
    <div
      v-if="showEditorSurface && showSectionTabs"
      ref="editorChromeRef"
      class="concord-agreement-editor__chrome"
    >
      <ConcordPageHeader
        ref="editorTitleRef"
        class="concord-agreement-editor__chrome-header"
        :title="agreement?.title || 'Без названия'"
        chrome
        :hidden="!editorHeaderVisible"
        show-back
        @back="goBack"
      >
        <template #right>
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
        </template>
      </ConcordPageHeader>

      <div class="concord-agreement-editor__tabs-wrap">
        <div ref="sectionTabsRef" class="concord-tabs concord-tabs--scroll" role="tablist" aria-label="Разделы согласования">
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
    :style="[editorPageStyle, { viewTransitionName }]"
  >
    <ConcordPageHeader
      v-if="!showSectionTabs"
      ref="editorTitleRef"
      :title="agreement?.title || 'Без названия'"
      :title-lines="2"
      :compact-title="editorTitleTwoLines"
      show-back
      @back="goBack"
    >
      <template #right>
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
      </template>
    </ConcordPageHeader>

    <div
      v-if="showSectionTabs"
      class="concord-agreement-editor__chrome-spacer"
      aria-hidden="true"
      :style="{ height: `${editorChromeHeight}px` }"
    />

    <main
      ref="editorMainRef"
      class="concord-agreement-editor"
    >
      <section v-if="showDraftIntro" class="concord-agreement-editor__intro-section">
        <div class="concord-agreement-editor__panel">
            <div v-if="agreement.description" class="concord-agreement-editor__description-wrap">
          <p
            ref="descriptionRef"
            class="concord-agreement-editor__description"
            :class="{ 'concord-agreement-editor__description--clamped': !descriptionExpanded }"
          >
            {{ agreement.description }}
          </p>
          <button
            v-if="descriptionOverflows"
            type="button"
            class="concord-agreement-editor__description-toggle"
            @click="expandDescription"
          >
            Смотреть
          </button>
          <button
            v-else-if="descriptionExpanded && descriptionCollapsible"
            type="button"
            class="concord-agreement-editor__description-toggle"
            @click="collapseDescription"
          >
            Скрыть
          </button>
            </div>
            <p v-else class="concord-agreement-editor__description concord-agreement-editor__description--muted">
              {{ editorIntro }}
            </p>

            <BlockAddZone
              :show-block-types="openBlockTypesSectionId === firstSectionId"
              :block-types="editorBlockTypes"
              @toggle="toggleBlockTypes(firstSectionId)"
              @add="(blockType) => addBlock(firstSectionId, blockType)"
            />
        </div>
      </section>

      <div
        v-for="(section, sectionIndex) in agreement.sections"
        :key="section.id"
        :id="sectionAnchorId(section.id)"
        :ref="(el) => setSectionRef(section.id, el)"
        class="concord-agreement-editor__section-anchor"
      >
        <AgreementContainerCard
          v-if="shouldShowSectionContainer(section)"
          :section-number="sectionIndex + 1"
          :agreement="agreement"
          :section="section"
          :groups="groups"
          :contacts="contacts"
          @preview="openSectionPreview(section.id)"
          @section-settings="openSectionSettings(section.id)"
          @section-schedule="openSectionSchedule(section.id)"
          @section-participants="openSectionParticipants(section.id)"
        >
          <template #default="{ expanded: sectionExpanded }">
            <ConcordEditorBlocksList
              v-if="section.blocks?.length"
              :section="section"
              :default-expand-first="sectionExpanded"
              @delete-block="requestDeleteBlock(section.id, $event)"
            >
              <template #preview="{ block }">
                <template v-if="block.type === 'text'">
                  <p class="concord-editor-block__text-title">{{ getTextBlockPreviewTitle(block) }}</p>
                  <p v-if="getTextBlockPreviewExcerpt(block)" class="concord-editor-block__text-excerpt">
                    {{ getTextBlockPreviewExcerpt(block) }}
                  </p>
                </template>

                <template v-else-if="block.type === 'gallery'">
                  <div v-if="(block.photos || []).length" class="concord-editor-block__gallery-preview">
                    <div class="concord-editor-block__gallery-thumbs">
                      <img
                        v-for="photo in galleryPreviewPhotos(block)"
                        :key="photo.id"
                        :src="photo.previewUrl"
                        :alt="photo.name"
                      >
                    </div>
                    <span v-if="galleryMoreCount(block)" class="concord-editor-block__more-badge">
                      +{{ galleryMoreCount(block) }}
                    </span>
                  </div>
                </template>

                <template v-else-if="block.type === 'files'">
                  <div v-if="(block.files || []).length" class="concord-editor-block__chips">
                    <span
                      v-for="file in filesPreviewItems(block)"
                      :key="file.id"
                      class="concord-editor-block__chip"
                    >
                      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" aria-hidden="true">
                        <path d="M8 3h6l5 5v13a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.4"/>
                        <path d="M14 3v5h5" stroke="currentColor" stroke-width="1.4"/>
                      </svg>
                      <span class="concord-editor-block__chip-text">{{ file.name }}</span>
                    </span>
                    <span v-if="filesMoreCount(block)" class="concord-editor-block__more-badge">
                      +{{ filesMoreCount(block) }}
                    </span>
                  </div>
                </template>

                <template v-else-if="block.type === 'link'">
                  <div v-if="(block.links || []).length" class="concord-editor-block__chips">
                    <span
                      v-for="link in linksPreviewItems(block)"
                      :key="link.id"
                      class="concord-editor-block__chip concord-editor-block__chip--url"
                      :title="link.url"
                    >
                      <span class="concord-editor-block__chip-text">
                        {{ formatLinkPreviewUrl(link.url) }}
                      </span>
                    </span>
                    <span v-if="linksMoreCount(block)" class="concord-editor-block__more-badge">
                      +{{ linksMoreCount(block) }}
                    </span>
                  </div>
                </template>

                <template v-else>
                  <p class="concord-editor-block__preview-meta">{{ getEditorBlockSummary(block) || 'Блок добавлен' }}</p>
                </template>
              </template>

              <template #default="{ block }">
                <TextBlockInlineEditor v-if="block.type === 'text'" :block="block" />
                <ConcordGalleryBlockCard v-else-if="block.type === 'gallery'" :block="block" />
                <ConcordFilesBlockCard v-else-if="block.type === 'files'" :block="block" />
                <ConcordLinksBlockCard v-else-if="block.type === 'link'" :block="block" />
                <article v-else class="concord-agreement-editor__block-card">
                  <p class="concord-agreement-editor__block-placeholder">
                    Блок добавлен. Контент появится на следующем этапе.
                  </p>
                </article>
              </template>
            </ConcordEditorBlocksList>

            <BlockAddZone
              :show-block-types="openBlockTypesSectionId === section.id"
              :block-types="editorBlockTypes"
              @toggle="toggleBlockTypes(section.id)"
              @add="(blockType) => addBlock(section.id, blockType)"
            />
          </template>
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

        <div v-if="canLaunch" class="concord-agreement-editor__launch-group">
          <button
            type="button"
            class="concord-agreement-editor__launch-btn concord-agreement-editor__launch-btn--inline"
            @click="launchAgreement"
          >
            Запустить
          </button>
          <p class="concord-agreement-editor__launch-hint">
            После запуска согласование отправится участникам
          </p>
        </div>
      </section>
    </main>
  </div>

  <TextBlockEditorSheet
    :open="textEditorOpen"
    :block="editingTextBlock"
    @close="closeTextEditor"
  />

  <Teleport to="body">
    <ConcordConfirmSheet
      :open="blockDeleteConfirmOpen"
      title="Удалить блок?"
      :message="blockDeleteConfirmMessage"
      confirm-label="Да"
      cancel-label="Нет"
      @confirm="confirmDeleteBlock"
      @cancel="cancelDeleteBlock"
      @dismiss="cancelDeleteBlock"
    />

    <ConcordConfirmSheet
      :open="sectionSetupConfirmOpen"
      title="Предыдущий раздел не настроен"
      :message="sectionSetupConfirmMessage"
      confirm-label="Всё равно добавить"
      cancel-label="Настроить раздел"
      confirm-tone="primary"
      @confirm="confirmAddSection"
      @cancel="openPreviousSectionSettings"
      @dismiss="dismissSectionSetupConfirm"
    />
  </Teleport>
  </div>
</template>

<script>
import { computed, ref, watch, onMounted, onBeforeUnmount, nextTick, defineExpose } from 'vue'
import {
  AGREEMENT_EDITOR_BLOCK_TYPES,
  AGREEMENT_EDITOR_INTRO,
  ensureAgreementEditBaseline,
  ensureAgreementSections,
  formatSectionTabTitle,
  isLaunchedAgreement,
  setAgreementEditBaseline,
} from '../concord/mock-agreements.js'
import AgreementContainerCard from '../concord/AgreementContainerCard.vue'
import BlockAddZone from '../concord/BlockAddZone.vue'
import ConcordGalleryBlockCard from '../concord/ConcordGalleryBlockCard.vue'
import ConcordLinksBlockCard from '../concord/ConcordLinksBlockCard.vue'
import ConcordFilesBlockCard from '../concord/ConcordFilesBlockCard.vue'
import ConcordEditorBlocksList from '../concord/ConcordEditorBlocksList.vue'
import {
  getEditorBlockLabel,
  getEditorBlockSummary,
  getTextBlockPreviewExcerpt,
  getTextBlockPreviewTitle,
} from '../concord/editor-block-utils.js'
import TextBlockInlineEditor from '../concord/TextBlockInlineEditor.vue'
import AgreementApproverView from './AgreementApproverView.vue'
import AgreementSettingsView from './AgreementSettingsView.vue'
import SectionSettingsView from './SectionSettingsView.vue'
import GroupsManageView from './GroupsManageView.vue'
import GroupMembersView from './GroupMembersView.vue'
import CreateGroupView from './CreateGroupView.vue'
import TextBlockEditorSheet from '../concord/TextBlockEditorSheet.vue'
import ConcordConfirmSheet from '../concord/ConcordConfirmSheet.vue'
import ConcordPageHeader from '../concord/ConcordPageHeader.vue'
import SectionScheduleSheet from '../concord/SectionScheduleSheet.vue'
import SectionParticipantsSheet from '../concord/SectionParticipantsSheet.vue'
import { resetConcordScrollPosition } from '../concord/scroll-top.js'

export default {
  name: 'AgreementEditorView',
  components: {
    AgreementContainerCard,
    AgreementApproverView,
    AgreementSettingsView,
    BlockAddZone,
    ConcordLinksBlockCard,
    ConcordFilesBlockCard,
    ConcordEditorBlocksList,
    ConcordGalleryBlockCard,
    TextBlockInlineEditor,
    CreateGroupView,
    GroupMembersView,
    GroupsManageView,
    SectionSettingsView,
    SectionScheduleSheet,
    SectionParticipantsSheet,
    TextBlockEditorSheet,
    ConcordConfirmSheet,
    ConcordPageHeader,
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
    initialSectionId: {
      type: String,
      default: null,
    },
    viewTransitionName: {
      type: String,
      default: '',
    },
  },
  emits: ['back', 'add-block', 'add-section', 'delete-section', 'delete-block', 'update-section', 'create-group', 'update-group', 'launch', 'update-agreement', 'reset-votes', 'request-leave', 'edited'],
  setup(props, { emit }) {
    const activeSectionId = ref(null)
    const openBlockTypesSectionId = ref(null)
    const editorMainRef = ref(null)
    const editorChromeRef = ref(null)
    const editorChromeHeight = ref(118)
    const editorHeaderVisible = ref(true)
    const editorTitleRef = ref(null)
    const editorTitleTwoLines = ref(false)
    const sectionTabsRef = ref(null)
    const sectionRefs = new Map()
    const isProgrammaticScroll = ref(false)
    let sectionObserver = null
    let chromeResizeObserver = null
    let scrollFrame = null
    let lastScrollY = 0
    const editorBlockTypes = AGREEMENT_EDITOR_BLOCK_TYPES
    const editorIntro = AGREEMENT_EDITOR_INTRO

    const sectionSettingsOpen = ref(false)
    const agreementSettingsOpen = ref(false)
    const sectionSettingsRef = ref(null)
    const agreementSettingsRef = ref(null)
    const sectionParticipantsRef = ref(null)
    const sectionGroupPickerOpen = ref(false)
    const sectionGroupPickerIds = ref([])
    const sectionGroupCreateOpen = ref(false)
    const sectionGroupCreateReturnToPicker = ref(false)
    const sectionGroupMembersOpen = ref(false)
    const editingGroupId = ref(null)

    const textEditorOpen = ref(false)
    const editingTextBlock = ref(null)
    const sectionPreviewOpen = ref(false)
    const previewSectionId = ref(null)
    const sectionScheduleOpen = ref(false)
    const scheduleSectionId = ref(null)
    const sectionParticipantsOpen = ref(false)
    const participantsSectionId = ref(null)

    const blockDeleteConfirmOpen = ref(false)
    const pendingBlockDelete = ref(null)
    const sectionSetupConfirmOpen = ref(false)
    const previousSectionForSetup = ref(null)
    const editorReady = ref(false)
    const editsTrackingEnabled = ref(false)
    let pendingDirtyTimer = null

    function notifyEdited() {
      if (!editorReady.value || !editsTrackingEnabled.value || !isLaunchedAgreement(props.agreement)) {
        return
      }
      emit('edited')
    }

    function scheduleNotifyEdited() {
      if (!editorReady.value || !editsTrackingEnabled.value || !isLaunchedAgreement(props.agreement)) {
        return
      }
      clearTimeout(pendingDirtyTimer)
      pendingDirtyTimer = setTimeout(notifyEdited, 400)
    }

    const blockDeleteConfirmMessage = computed(() => {
      const label = pendingBlockDelete.value?.label
      if (!label) {
        return 'Вы уверены, что хотите удалить этот блок?'
      }
      return `Вы уверены, что хотите удалить блок «${label}»?`
    })

    const sectionSetupConfirmMessage = computed(() => {
      const section = previousSectionForSetup.value
      const title = section?.title || 'предыдущий раздел'
      return `В разделе «${title}» нет согласующих. Проверьте участников и настройки перед созданием следующего раздела.`
    })

    const descriptionRef = ref(null)
    const descriptionExpanded = ref(false)
    const descriptionOverflows = ref(false)
    const descriptionCollapsible = ref(false)

    const showEditorSurface = computed(() =>
      Boolean(
        props.agreement
        && !agreementSettingsOpen.value
        && !sectionSettingsOpen.value
        && !sectionGroupPickerOpen.value
        && !sectionGroupCreateOpen.value
        && !sectionGroupMembersOpen.value
        && !sectionPreviewOpen.value
      )
    )

    const isDraft = computed(() => props.agreement?.status === 'draft')

    const firstSectionId = computed(() => props.agreement?.sections?.[0]?.id || null)

    const activeSection = computed(() =>
      props.agreement?.sections?.find((item) => item.id === activeSectionId.value) || null
    )

    const previewAgreement = computed(() => props.agreement || null)
    const scheduleSection = computed(() =>
      props.agreement?.sections?.find((section) => section.id === scheduleSectionId.value) || null
    )
    const participantsSection = computed(() =>
      props.agreement?.sections?.find((section) => section.id === participantsSectionId.value) || null
    )

    const firstSectionHasBlocks = computed(() => (props.agreement?.sections?.[0]?.blocks?.length || 0) > 0)

    const sectionCount = computed(() => props.agreement?.sections?.length || 0)

    const hasAnySectionWithBlocks = computed(() =>
      props.agreement?.sections?.some((section) => section.blocks?.length > 0) ?? false
    )

    const showDraftIntro = computed(() => isDraft.value && !firstSectionHasBlocks.value && sectionCount.value === 1)

    const showSectionTabs = computed(() => sectionCount.value > 1)

    const showNewContainer = computed(() => hasAnySectionWithBlocks.value || sectionCount.value !== 1)

    const canLaunch = computed(() => {
      const status = props.agreement?.status
      return Boolean(
        props.agreement?.isOwner
        && !['awaiting', 'approved', 'completed', 'expired', 'archived'].includes(status)
      )
    })

    function initEditBaseline() {
      if (!props.agreement || !isLaunchedAgreement(props.agreement)) {
        return
      }
      setAgreementEditBaseline(props.agreement)
    }

    const editorPageStyle = computed(() => ({
      '--concord-editor-scroll-anchor-offset': `${editorChromeHeight.value + 8}px`,
    }))

    const editingGroup = computed(() =>
      props.groups.find((item) => item.id === editingGroupId.value) || null
    )

    function updateDescriptionOverflow() {
      nextTick(() => {
        const el = descriptionRef.value
        if (!el) {
          descriptionOverflows.value = false
          descriptionCollapsible.value = false
          return
        }
        if (descriptionExpanded.value) {
          descriptionOverflows.value = false
          return
        }
        const overflows = el.scrollHeight > el.clientHeight + 1
        descriptionOverflows.value = overflows
        if (overflows) {
          descriptionCollapsible.value = true
        }
      })
    }

    function expandDescription() {
      descriptionExpanded.value = true
      descriptionOverflows.value = false
    }

    function collapseDescription() {
      descriptionExpanded.value = false
      updateDescriptionOverflow()
    }

    watch(
      () => props.agreement,
      (agreement) => {
        if (!agreement) {
          return
        }
        ensureAgreementSections(agreement)
        const hasInitialSection = props.initialSectionId
          && agreement.sections.some((item) => item.id === props.initialSectionId)
        if (hasInitialSection) {
          activeSectionId.value = props.initialSectionId
        } else if (!activeSectionId.value || !agreement.sections.some((item) => item.id === activeSectionId.value)) {
          activeSectionId.value = agreement.sections[0]?.id || null
        }
        if (showDraftIntro.value && firstSectionId.value) {
          openBlockTypesSectionId.value = firstSectionId.value
        }
        nextTick(() => {
          setupEditorChromeResizeObserver()
          setupSectionObserver()
          if (hasInitialSection) {
            scrollToSection(props.initialSectionId)
          }
          updateDescriptionOverflow()
          updateEditorTitleLines()
        })
      },
      { immediate: true }
    )

    watch(
      () => props.agreement?.description,
      () => {
        descriptionExpanded.value = false
        descriptionCollapsible.value = false
        updateDescriptionOverflow()
      }
    )

    watch(
      () => props.agreement?.title,
      () => updateEditorTitleLines()
    )

    watch(showDraftIntro, (visible) => {
      if (visible) {
        updateDescriptionOverflow()
      }
    })

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

    function setupEditorChromeResizeObserver() {
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

    function resetEditorHeaderVisibility() {
      lastScrollY = window.scrollY || document.documentElement.scrollTop || document.body.scrollTop || 0
      editorHeaderVisible.value = true
    }

    function getSectionObserverMargin() {
      const offset = Math.max(editorChromeHeight.value + 8, 72)
      return `-${offset}px 0px -55% 0px`
    }

    function updateEditorTitleLines() {
      nextTick(() => {
        const title = editorTitleRef.value?.titleRef
        if (!title || typeof document === 'undefined') {
          return
        }

        const probe = title.cloneNode(true)
        Object.assign(probe.style, {
          position: 'fixed',
          visibility: 'hidden',
          pointerEvents: 'none',
          display: 'block',
          width: `${title.clientWidth}px`,
          overflow: 'visible',
          whiteSpace: 'nowrap',
          WebkitLineClamp: 'unset',
          lineClamp: 'unset',
        })
        document.body.appendChild(probe)
        editorTitleTwoLines.value = probe.scrollWidth > probe.clientWidth + 1
        probe.remove()
      })
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
        updateActiveSectionFromScroll()
      }, 700)
    }

    function updateActiveSectionFromScroll() {
      const chromeBottom = editorChromeRef.value?.getBoundingClientRect().bottom
      const scrollAnchor = chromeBottom || Math.max(editorChromeHeight.value, 72)
      let closestSectionId = null
      let closestDistance = Number.POSITIVE_INFINITY

      for (const [sectionId, element] of sectionRefs) {
        const rect = element.getBoundingClientRect()
        if (rect.top <= scrollAnchor && rect.bottom > scrollAnchor) {
          activeSectionId.value = sectionId
          return
        }
        const distance = Math.abs(rect.top - scrollAnchor)
        if (distance < closestDistance) {
          closestDistance = distance
          closestSectionId = sectionId
        }
      }

      if (closestSectionId) {
        activeSectionId.value = closestSectionId
      }
    }

    function onScroll() {
      if (scrollFrame) {
        return
      }
      scrollFrame = requestAnimationFrame(() => {
        scrollFrame = null
        const currentY = window.scrollY || document.documentElement.scrollTop || 0
        if (currentY <= 4) {
          editorHeaderVisible.value = true
        } else if (currentY > lastScrollY + 6) {
          editorHeaderVisible.value = false
        } else if (currentY < lastScrollY - 6) {
          editorHeaderVisible.value = true
        }
        lastScrollY = currentY
        updateActiveSectionFromScroll()
      })
    }

    function setupSectionObserver() {
      sectionObserver?.disconnect()
      sectionObserver = null
      if (!showSectionTabs.value) {
        return
      }

      sectionObserver = new IntersectionObserver(
        () => {
          if (isProgrammaticScroll.value) {
            return
          }
          updateActiveSectionFromScroll()
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
      resetConcordScrollPosition()
      requestAnimationFrame(() => {
        resetEditorHeaderVisibility()
        nextTick(() => {
          setupEditorChromeResizeObserver()
          setupSectionObserver()
          updateActiveSectionFromScroll()
          updateDescriptionOverflow()
          updateEditorTitleLines()
          ensureAgreementEditBaseline(props.agreement)
          initEditBaseline()
          editorReady.value = true
          window.setTimeout(() => {
            editsTrackingEnabled.value = true
          }, 600)
        })
      })
      window.addEventListener('scroll', onScroll, { passive: true })
      document.addEventListener('scroll', onScroll, { passive: true, capture: true })
      window.addEventListener('resize', updateEditorTitleLines, { passive: true })
    })

    onBeforeUnmount(() => {
      clearTimeout(pendingDirtyTimer)
      sectionObserver?.disconnect()
      chromeResizeObserver?.disconnect()
      window.removeEventListener('scroll', onScroll)
      document.removeEventListener('scroll', onScroll, true)
      window.removeEventListener('resize', updateEditorTitleLines)
      if (scrollFrame) {
        cancelAnimationFrame(scrollFrame)
      }
    })

    watch(showEditorSurface, (visible) => {
      if (!visible) {
        return
      }
      resetConcordScrollPosition()
      requestAnimationFrame(() => {
        resetEditorHeaderVisibility()
        nextTick(() => {
          setupEditorChromeResizeObserver()
          setupSectionObserver()
        })
      })
    })

    watch(showSectionTabs, () => {
      resetEditorHeaderVisibility()
      nextTick(() => {
        setupEditorChromeResizeObserver()
        setupSectionObserver()
      })
    })

    watch(editorHeaderVisible, () => {
      nextTick(updateEditorChromeHeight)
    })

    watch(editorChromeHeight, () => {
      setupSectionObserver()
    })

    watch(activeSectionId, (sectionId) => {
      if (!sectionId) {
        return
      }
      nextTick(() => {
        sectionTabsRef.value
          ?.querySelector(`[role="tab"][aria-selected="true"]`)
          ?.scrollIntoView({ block: 'nearest', inline: 'nearest' })
      })
    })

    function openAgreementSettings() {
      agreementSettingsOpen.value = true
      nextTick(() => {
        resetConcordScrollPosition()
        requestAnimationFrame(resetConcordScrollPosition)
      })
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
      props.agreement.startDate = payload.startDate || ''
      props.agreement.deadline = payload.deadline || ''
      props.agreement.isUrgent = Boolean(payload.isImportant)
      props.agreement.urgentAcknowledged = !payload.isImportant
      if (payload.daysLabel) {
        props.agreement.daysLabel = payload.daysLabel
      }
      emit('update-agreement')
      notifyEdited()
    }

    function goBack() {
      emit('request-leave')
    }

    function launchAgreement() {
      emit('launch')
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
      notifyEdited()
    }

    function galleryPreviewPhotos(block) {
      return (block.photos || []).slice(0, 4)
    }

    function galleryMoreCount(block) {
      const total = block.photos?.length || 0
      return total > 4 ? total - 4 : 0
    }

    function filesPreviewItems(block) {
      return (block.files || []).slice(0, 2)
    }

    function filesMoreCount(block) {
      const total = block.files?.length || 0
      return total > 2 ? total - 2 : 0
    }

    function linksPreviewItems(block) {
      return (block.links || []).slice(0, 2)
    }

    function formatLinkPreviewUrl(value) {
      const raw = String(value || '').trim()
      if (!raw) {
        return ''
      }

      try {
        const parsed = new URL(/^https?:\/\//i.test(raw) ? raw : `https://${raw}`)
        const path = parsed.pathname === '/' ? '' : parsed.pathname.replace(/\/$/, '')
        const display = `${parsed.hostname}${path}`
        return display.length > 46 ? `${display.slice(0, 45)}…` : display
      } catch {
        const display = raw.replace(/^https?:\/\//i, '').split(/[?#]/)[0].replace(/\/$/, '')
        return display.length > 46 ? `${display.slice(0, 45)}…` : display
      }
    }

    function linksMoreCount(block) {
      const total = block.links?.length || 0
      return total > 2 ? total - 2 : 0
    }

    function openTextEditor(block) {
      editingTextBlock.value = block
      textEditorOpen.value = true
    }

    function closeTextEditor() {
      textEditorOpen.value = false
      editingTextBlock.value = null
    }

    function addNewSection() {
      const nextIndex = (props.agreement?.sections?.length || 0) + 1
      emit('add-section', {
        title: `Раздел ${nextIndex}`,
        participantIds: [],
        groupIds: [],
      })
      notifyEdited()
    }

    function openNewSection() {
      const sections = props.agreement?.sections || []
      const previousSection = sections[sections.length - 1]
      const hasParticipants = Boolean(
        previousSection
        && ((previousSection.participantIds?.length || 0) > 0 || (previousSection.groupIds?.length || 0) > 0)
      )

      if (!previousSection || hasParticipants) {
        addNewSection()
        return
      }

      previousSectionForSetup.value = previousSection
      sectionSetupConfirmOpen.value = true
    }

    function confirmAddSection() {
      sectionSetupConfirmOpen.value = false
      previousSectionForSetup.value = null
      addNewSection()
    }

    function openPreviousSectionSettings() {
      const sectionId = previousSectionForSetup.value?.id
      sectionSetupConfirmOpen.value = false
      previousSectionForSetup.value = null
      if (sectionId) {
        openSectionSettings(sectionId)
      }
    }

    function dismissSectionSetupConfirm() {
      sectionSetupConfirmOpen.value = false
      previousSectionForSetup.value = null
    }

    function openSectionSettings(sectionId) {
      activeSectionId.value = sectionId
      sectionSettingsOpen.value = true
      nextTick(() => {
        resetConcordScrollPosition()
        requestAnimationFrame(resetConcordScrollPosition)
      })
    }

    function openSectionPreview(sectionId) {
      previewSectionId.value = sectionId
      sectionPreviewOpen.value = true
    }

    function openSectionSchedule(sectionId) {
      scheduleSectionId.value = sectionId
      sectionScheduleOpen.value = true
    }

    function closeSectionSchedule() {
      sectionScheduleOpen.value = false
      scheduleSectionId.value = null
    }

    function saveSectionSchedule(payload) {
      if (!scheduleSection.value) {
        return
      }
      scheduleSection.value.startDate = payload.startDate
      scheduleSection.value.deadline = payload.deadline
      emit('update-agreement')
      closeSectionSchedule()
      notifyEdited()
    }

    function openSectionParticipants(sectionId) {
      participantsSectionId.value = sectionId
      sectionParticipantsOpen.value = true
    }

    function closeSectionParticipants() {
      sectionParticipantsOpen.value = false
      participantsSectionId.value = null
    }

    function saveSectionParticipants(payload) {
      if (!participantsSection.value) {
        return
      }
      emit('update-section', {
        sectionId: participantsSection.value.id,
        title: participantsSection.value.title,
        participantIds: payload.participantIds,
        groupIds: payload.groupIds,
        leaderId: participantsSection.value.leaderId,
        startDate: participantsSection.value.startDate,
        deadline: participantsSection.value.deadline,
        settings: participantsSection.value.settings,
      })
      closeSectionParticipants()
      notifyEdited()
    }

    function openParticipantsSectionSettings() {
      if (!participantsSectionId.value) {
        return
      }
      const sectionId = participantsSectionId.value
      closeSectionParticipants()
      nextTick(() => openSectionSettings(sectionId))
    }

    function closeSectionPreview() {
      sectionPreviewOpen.value = false
      previewSectionId.value = null
    }

    function openPreviewSectionSettings(sectionId) {
      closeSectionPreview()
      nextTick(() => openSectionSettings(sectionId))
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
        props.agreement.urgentAcknowledged = !payload.settings.isImportant
      }
      notifyEdited()
    }

    function onSectionDelete(sectionId) {
      if (!sectionId) {
        return
      }
      emit('delete-section', sectionId)
      if (activeSectionId.value === sectionId) {
        activeSectionId.value = props.agreement?.sections?.[0]?.id || null
      }
      closeSectionSettings()
    }

    function requestDeleteBlock(sectionId, block) {
      if (!sectionId || !block?.id) {
        return
      }
      pendingBlockDelete.value = {
        sectionId,
        blockId: block.id,
        label: getEditorBlockLabel(block),
      }
      blockDeleteConfirmOpen.value = true
    }

    function cancelDeleteBlock() {
      blockDeleteConfirmOpen.value = false
      pendingBlockDelete.value = null
    }

    function confirmDeleteBlock() {
      if (!pendingBlockDelete.value) {
        return
      }
      emit('delete-block', {
        sectionId: pendingBlockDelete.value.sectionId,
        blockId: pendingBlockDelete.value.blockId,
      })
      cancelDeleteBlock()
      notifyEdited()
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
      sectionParticipantsRef.value?.applyGroupSelection(groupIds)
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

    function hasOpenUnsavedOverlay() {
      if (sectionSettingsOpen.value && sectionSettingsRef.value?.hasUnsavedChanges?.()) {
        return true
      }
      if (agreementSettingsOpen.value && agreementSettingsRef.value?.hasUnsavedChanges?.()) {
        return true
      }
      return false
    }

    function flushOpenEditorsBeforeLeave() {
      if (sectionSettingsOpen.value && sectionSettingsRef.value?.hasUnsavedChanges?.()) {
        sectionSettingsRef.value.save()
      }
      if (agreementSettingsOpen.value && agreementSettingsRef.value?.hasUnsavedChanges?.()) {
        agreementSettingsRef.value.save()
      }
    }

    function resetEditTracking() {
      editsTrackingEnabled.value = false
      editorReady.value = false
      nextTick(() => {
        initEditBaseline()
        editorReady.value = true
        window.setTimeout(() => {
          editsTrackingEnabled.value = true
        }, 600)
      })
    }

    defineExpose({ flushOpenEditorsBeforeLeave, hasOpenUnsavedOverlay })

    watch(
      () => props.agreement?.sections,
      () => {
        scheduleNotifyEdited()
      },
      { deep: true }
    )

    watch(
      () => props.agreement?.id,
      () => {
        resetEditTracking()
      }
    )

    watch(
      () => props.agreement?.sections?.length,
      (length, prev) => {
        if (length > prev && props.agreement?.sections?.length) {
          const newSection = props.agreement.sections[props.agreement.sections.length - 1]
          nextTick(() => {
            setupEditorChromeResizeObserver()
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
        setupEditorChromeResizeObserver()
        setupSectionObserver()
      })
    })

    return {
      activeSectionId,
      activeSection,
      sectionPreviewOpen,
      previewAgreement,
      sectionScheduleOpen,
      scheduleSection,
      sectionParticipantsOpen,
      participantsSection,
      showEditorSurface,
      firstSectionId,
      editorMainRef,
      editorChromeRef,
      editorTitleRef,
      editorTitleTwoLines,
      sectionTabsRef,
      editorChromeHeight,
      editorHeaderVisible,
      editorPageStyle,
      isDraft,
      showDraftIntro,
      showSectionTabs,
      showNewContainer,
      canLaunch,
      launchAgreement,
      openBlockTypesSectionId,
      formatSectionTabTitle,
      sectionAnchorId,
      setSectionRef,
      shouldShowSectionContainer,
      scrollToSection,
      toggleBlockTypes,
      editorBlockTypes,
      editorIntro,
      descriptionRef,
      descriptionExpanded,
      descriptionOverflows,
      descriptionCollapsible,
      expandDescription,
      collapseDescription,
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
      getEditorBlockSummary,
      getTextBlockPreviewTitle,
      getTextBlockPreviewExcerpt,
      galleryPreviewPhotos,
      galleryMoreCount,
      filesPreviewItems,
      filesMoreCount,
      linksPreviewItems,
      formatLinkPreviewUrl,
      linksMoreCount,
      openTextEditor,
      closeTextEditor,
      openNewSection,
      openSectionPreview,
      closeSectionPreview,
      openSectionSchedule,
      closeSectionSchedule,
      saveSectionSchedule,
      openSectionParticipants,
      closeSectionParticipants,
      saveSectionParticipants,
      openParticipantsSectionSettings,
      openPreviewSectionSettings,
      openSectionSettings,
      closeSectionSettings,
      onSectionSettingsSave,
      onSectionDelete,
      blockDeleteConfirmOpen,
      blockDeleteConfirmMessage,
      sectionSetupConfirmOpen,
      sectionSetupConfirmMessage,
      requestDeleteBlock,
      cancelDeleteBlock,
      confirmDeleteBlock,
      confirmAddSection,
      openPreviousSectionSettings,
      dismissSectionSetupConfirm,
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
