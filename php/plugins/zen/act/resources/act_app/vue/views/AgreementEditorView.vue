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
    @delete="onSectionDelete"
    @save="onSectionSettingsSave"
    @pick-groups="openSectionGroupPicker"
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
      <header
        class="concord-header concord-header--editor concord-agreement-editor__chrome-header"
        :class="{ 'concord-agreement-editor__chrome-header--hidden': !editorHeaderVisible }"
      >
        <button type="button" class="concord-icon-btn" aria-label="Назад" @click="goBack">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
        <h1
          ref="editorTitleRef"
          class="concord-header__title concord-header__title--editor-clamp"
          :class="{ 'concord-header__title--editor-two-lines': editorTitleTwoLines }"
        >
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
    :style="[editorPageStyle, { viewTransitionName }]"
  >
    <header v-if="!showSectionTabs" class="concord-header concord-header--editor">
      <button type="button" class="concord-icon-btn" aria-label="Назад" @click="goBack">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <h1
        ref="editorTitleRef"
        class="concord-header__title concord-header__title--editor-clamp"
        :class="{ 'concord-header__title--editor-two-lines': editorTitleTwoLines }"
      >
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
        >
          <template #default="{ expanded: sectionExpanded }">
            <ConcordEditorBlocksList
              v-if="section.blocks?.length"
              :section="section"
              :default-expand-first="sectionExpanded"
            >
              <template #preview="{ block }">
                <template v-if="block.type === 'text'">
                  <p class="concord-editor-block__text-title">{{ getTextBlockPreviewTitle(block) }}</p>
                  <p v-if="getTextBlockPreviewExcerpt(block)" class="concord-editor-block__text-excerpt">
                    {{ getTextBlockPreviewExcerpt(block) }}
                  </p>
                </template>

                <template v-else-if="block.type === 'gallery'">
                  <p class="concord-editor-block__preview-meta">{{ getEditorBlockSummary(block) }}</p>
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
                  <p class="concord-editor-block__preview-meta">{{ getEditorBlockSummary(block) }}</p>
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
                  <p class="concord-editor-block__preview-meta">{{ getEditorBlockSummary(block) }}</p>
                  <div v-if="(block.links || []).length" class="concord-editor-block__chips">
                    <span
                      v-for="link in linksPreviewItems(block)"
                      :key="link.id"
                      class="concord-editor-block__chip concord-editor-block__chip--url"
                    >
                      {{ link.url }}
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
        <button
          v-if="canLaunch"
          type="button"
          class="concord-agreement-editor__launch-btn concord-agreement-editor__launch-btn--inline"
          @click="launchAgreement"
        >
          Запустить
        </button>
        <p v-if="canLaunch" class="concord-agreement-editor__launch-hint">
          После запуска согласование отправится участникам
        </p>
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
import ConcordEditorBlocksList from '../concord/ConcordEditorBlocksList.vue'
import {
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
    initialSectionId: {
      type: String,
      default: null,
    },
    viewTransitionName: {
      type: String,
      default: '',
    },
  },
  emits: ['back', 'add-block', 'add-section', 'delete-section', 'update-section', 'create-group', 'update-group', 'launch', 'update-agreement'],
  setup(props, { emit }) {
    const activeSectionId = ref(null)
    const openBlockTypesSectionId = ref(null)
    const editorMainRef = ref(null)
    const editorChromeRef = ref(null)
    const editorChromeHeight = ref(118)
    const editorHeaderVisible = ref(true)
    const editorTitleRef = ref(null)
    const editorTitleTwoLines = ref(false)
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
        const title = editorTitleRef.value
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
      resetConcordScrollPosition()
      requestAnimationFrame(() => {
        resetEditorHeaderVisibility()
        nextTick(() => {
          setupEditorChromeResizeObserver()
          setupSectionObserver()
          updateActiveSectionFromScroll()
          updateDescriptionOverflow()
          updateEditorTitleLines()
        })
      })
      window.addEventListener('scroll', onScroll, { passive: true })
      document.addEventListener('scroll', onScroll, { passive: true, capture: true })
      window.addEventListener('resize', updateEditorTitleLines, { passive: true })
    })

    onBeforeUnmount(() => {
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
      if (payload.daysLabel) {
        props.agreement.daysLabel = payload.daysLabel
      }
      emit('update-agreement')
    }

    function goBack() {
      emit('back')
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
      nextTick(() => {
        resetConcordScrollPosition()
        requestAnimationFrame(resetConcordScrollPosition)
      })
    }

    function openSectionPreview(sectionId) {
      previewSectionId.value = sectionId
      sectionPreviewOpen.value = true
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
      }
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
      showEditorSurface,
      firstSectionId,
      editorMainRef,
      editorChromeRef,
      editorTitleRef,
      editorTitleTwoLines,
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
      linksMoreCount,
      openTextEditor,
      closeTextEditor,
      openNewSection,
      openSectionPreview,
      closeSectionPreview,
      openPreviewSectionSettings,
      openSectionSettings,
      closeSectionSettings,
      onSectionSettingsSave,
      onSectionDelete,
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
