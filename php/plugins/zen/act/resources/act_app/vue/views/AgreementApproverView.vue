<template>
  <Teleport to="body">
    <div
      v-if="showSectionTabs"
      ref="approverChromeRef"
      class="concord-agreement-editor__chrome"
    >
      <header
        class="concord-header concord-header--editor concord-agreement-editor__chrome-header"
        :class="{ 'concord-agreement-editor__chrome-header--hidden': !approverHeaderVisible }"
      >
        <button type="button" class="concord-icon-btn" aria-label="Назад" @click="goBack">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
        <h1 class="concord-header__title concord-header__title--editor-clamp">
          {{ agreement?.title || 'Без названия' }}
        </h1>
        <button
          v-if="!preview"
          type="button"
          class="concord-icon-btn"
          aria-label="Меню согласования"
          @click="openMenu"
        >
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
            <circle cx="5" cy="12" r="1.6" fill="currentColor"/>
            <circle cx="12" cy="12" r="1.6" fill="currentColor"/>
            <circle cx="19" cy="12" r="1.6" fill="currentColor"/>
          </svg>
        </button>
      </header>

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

  <div class="concord-page concord-page--approver" :style="[approverPageStyle, { viewTransitionName }]">
    <header v-if="!showSectionTabs" class="concord-header concord-header--editor">
      <button type="button" class="concord-icon-btn" aria-label="Назад" @click="goBack">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <h1 class="concord-header__title concord-header__title--editor-clamp">
        {{ agreement?.title || 'Без названия' }}
      </h1>
      <button
        v-if="!preview"
        type="button"
        class="concord-icon-btn"
        aria-label="Меню согласования"
        @click="openMenu"
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
      :style="{ height: `${approverChromeHeight}px` }"
    />

    <main ref="approverMainRef" class="concord-approver">
      <div
        v-for="section in agreement.sections"
        :key="section.id"
        :id="sectionAnchorId(section.id)"
        :ref="(el) => setSectionRef(section.id, el)"
        class="concord-approver__section"
        :class="{
          'concord-approver__section--active': isActiveSection(section),
          'concord-approver__section--inactive': !isActiveSection(section),
          'concord-approver__section--expanded': expandedSections[section.id] !== false,
        }"
      >
        <div class="concord-approver__section-card">
          <header class="concord-approver__section-header">
            <div class="concord-approver__section-header-main">
              <h2 class="concord-approver__section-title">
                {{ section.title }}
              </h2>
            </div>
            <div class="concord-approver__section-header-actions">
              <button
                type="button"
                class="concord-approver__toggle"
                :aria-expanded="expandedSections[section.id] !== false"
                :aria-label="expandedSections[section.id] !== false ? 'Свернуть раздел' : 'Развернуть раздел'"
                @click="toggleSection(section.id)"
              >
                <svg
                  class="concord-approver__toggle-icon"
                  viewBox="0 0 24 24"
                  width="20"
                  height="20"
                  fill="none"
                  aria-hidden="true"
                >
                  <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
            </div>
          </header>

          <div v-show="expandedSections[section.id] !== false" class="concord-approver__section-body">
            <div v-if="section.blocks?.length" class="concord-approver__article">
              <article
                v-for="block in section.blocks"
                :key="block.id"
                class="concord-approver__block"
                :class="`concord-approver__block--${block.type || 'text'}`"
              >
                <template v-if="block.type === 'text'">
                  <h3 v-if="getEditorBlockLabel(block)" class="concord-approver__block-title">
                    {{ getEditorBlockLabel(block) }}
                  </h3>
                  <p v-if="block.title?.trim()" class="concord-approver__block-subtitle">
                    {{ block.title.trim() }}
                  </p>
                  <p v-if="block.description" class="concord-approver__block-lead">{{ block.description }}</p>
                  <ConcordApproverTextContent :content="block.content" />
                </template>
                <template v-else-if="block.type === 'link'">
                  <h3 v-if="getEditorBlockLabel(block)" class="concord-approver__block-title">
                    {{ getEditorBlockLabel(block) }}
                  </h3>
                  <div v-if="(block.links || []).length" class="concord-approver__links concord-approver__links--compact">
                    <div
                      v-for="link in block.links"
                      :key="link.id"
                      class="concord-approver__link-item"
                      :class="{ 'concord-approver__link-item--commented': linkComment(link) }"
                    >
                      <a
                        :href="link.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="concord-approver__link concord-approver__link--compact"
                        :title="link.url"
                      >
                        <span class="concord-approver__link-icon" aria-hidden="true">
                          <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
                            <path d="M10 6H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                            <path d="M14 4h6v6M10 14 20 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                          </svg>
                        </span>
                        <span class="concord-approver__link-main">
                          <span
                            class="concord-approver__link-line"
                            :class="{ 'concord-approver__link-line--stacked': !linkComment(link) }"
                          >
                            <span class="concord-approver__link-title">{{ getLinkDisplayLabel(link) }}</span>
                            <span class="concord-approver__link-url">{{ formatLinkDisplayUrl(link.url) }}</span>
                          </span>
                        </span>
                      </a>
                      <button
                        v-if="linkComment(link)"
                        type="button"
                        class="concord-approver__comment-trigger concord-approver__link-comment-trigger"
                        :aria-label="`Показать комментарий к ссылке ${getLinkDisplayLabel(link)}`"
                        @click.stop="openReasonModal(linkComment(link))"
                      >
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" aria-hidden="true">
                          <path d="M5 5.5h14v10H9l-4 3v-13Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                          <path d="M8.5 9h7M8.5 12h4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                      </button>
                    </div>
                  </div>
                </template>
                <template v-else>
                  <h3 v-if="block.title || block.label" class="concord-approver__block-title">
                    {{ block.title || block.label }}
                  </h3>
                  <p v-if="block.description" class="concord-approver__block-lead">{{ block.description }}</p>
                  <p v-if="block.content" class="concord-approver__block-content">{{ stripContent(block.content) }}</p>
                </template>

                <div v-if="(block.files || []).length" class="concord-approver__files">
                  <div
                    v-for="file in block.files"
                    :key="file.id"
                    class="concord-approver__file-item"
                    :class="{ 'concord-approver__file-item--commented': fileComment(file) }"
                  >
                    <button
                      type="button"
                      class="concord-approver__file"
                      :aria-label="fileDownloadLabel(file)"
                      @click="onFileDownload(file)"
                    >
                      <span class="concord-approver__file-preview">
                        <img v-if="file.previewUrl" :src="file.previewUrl" alt="">
                        <svg v-else viewBox="0 0 24 24" width="28" height="28" fill="none" aria-hidden="true">
                          <path d="M8 3h6l5 5v13a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.4"/>
                          <path d="M14 3v5h5" stroke="currentColor" stroke-width="1.4"/>
                        </svg>
                        <span class="concord-approver__file-download" aria-hidden="true">
                          <svg viewBox="0 0 24 24" width="16" height="16" fill="none">
                            <path d="M12 4v10M7 9l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M5 19h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                          </svg>
                        </span>
                      </span>
                      <span class="concord-approver__file-name">{{ file.name }}</span>
                    </button>
                    <button
                      v-if="fileComment(file)"
                      type="button"
                      class="concord-approver__comment-trigger concord-approver__file-comment-trigger"
                      :aria-label="`Показать комментарий к файлу ${file.name || ''}`"
                      @click.stop="openReasonModal(fileComment(file))"
                    >
                      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" aria-hidden="true">
                        <path d="M5 5.5h14v10H9l-4 3v-13Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                        <path d="M8.5 9h7M8.5 12h4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                      </svg>
                    </button>
                  </div>
                </div>

                <ConcordGalleryCarousel
                  v-if="(block.photos || []).length"
                  :photos="block.photos"
                  @open="openGalleryViewer(block.photos, $event)"
                  @comment="openReasonModal"
                />

                <div v-if="(block.items || []).length" class="concord-approver__checkboxes">
                  <p v-if="block.prompt" class="concord-approver__checkbox-prompt">{{ block.prompt }}</p>
                  <ul class="concord-approver__checkbox-list">
                    <li
                      v-for="item in block.items"
                      :key="item.id"
                      class="concord-approver__checkbox-item"
                    >
                      <span
                        class="concord-approver__checkbox-box"
                        :class="{ 'concord-approver__checkbox-box--checked': item.checked }"
                      />
                      <span>{{ item.label }}</span>
                    </li>
                  </ul>
                </div>

                <div v-if="block.type !== 'link' && (block.links || []).length" class="concord-approver__links">
                  <a
                    v-for="link in block.links"
                    :key="link.id"
                    :href="link.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="concord-approver__link"
                  >
                    <span class="concord-approver__link-title">{{ link.title }}</span>
                    <span v-if="link.description" class="concord-approver__link-desc">{{ link.description }}</span>
                    <span class="concord-approver__link-url">{{ link.url }}</span>
                  </a>
                </div>
              </article>
            </div>

          </div>

          <div class="concord-approver__section-footer">
            <div class="concord-approver__section-info">
              <div class="concord-approver__section-info-main">
                <span
                  class="concord-approver__section-flame"
                  :class="{
                    'concord-approver__section-flame--urgent': isUrgent,
                    'concord-approver__section-flame--soon': isDeadlineSoon,
                    'concord-approver__section-flame--overdue': isDeadlineOverdue,
                  }"
                  aria-hidden="true"
                >
                  <ConcordUrgencyFlame :urgent="isUrgent" />
                </span>
                <div class="concord-approver__section-info-text">
                  <span class="concord-approver__section-deadline">{{ agreement.deadline }} ({{ daysLabel }})</span>
                  <span class="concord-approver__section-voters">Согласующих: {{ sectionParticipants(section).length }} чел.</span>
                </div>
              </div>
              <ParticipantAvatars
                :people="sectionParticipants(section)"
                :total="sectionParticipants(section).length"
                compact
              />
            </div>

            <ApproverVoteSection
              v-if="!preview && isActiveSection(section)"
              :section="section"
              :user-vote="userVoteForSection(section)"
              @vote-yes="openYesConfirm(section.id)"
              @vote-no="openNoReason(section.id)"
              @view-reason="openReasonModal"
            />

            <ApproverVoteStats
              v-else
              :section="section"
            />
          </div>
        </div>
      </div>
    </main>

    <ApproverVoteConfirmModal
      :open="yesConfirmOpen"
      @confirm="onVoteYes"
      @close="yesConfirmOpen = false"
    />

    <ApproverVoteRejectModal
      :open="noReasonOpen"
      @confirm="onVoteNo"
      @close="noReasonOpen = false"
    />

    <ConcordImageViewer
      :open="imageViewerOpen"
      :photos="imageViewerPhotos"
      :index="imageViewerIndex"
      @close="closeImageViewer"
    />

    <ConcordReasonViewModal
      :open="reasonModalOpen"
      :reason="reasonModalText"
      @close="closeReasonModal"
    />

    <div v-if="menuOpen" class="concord-sheet-backdrop" @click="menuOpen = false" />
    <div v-if="menuOpen" class="concord-menu-sheet concord-agreement-info-sheet" role="dialog" aria-modal="true" aria-label="О согласовании">
      <div class="concord-sheet__handle" aria-hidden="true" />
      <div class="concord-agreement-info-sheet__heading">
        <span>О согласовании</span>
        <button type="button" class="concord-agreement-info-sheet__close" aria-label="Закрыть" @click="menuOpen = false">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
            <path d="m7 7 10 10M17 7 7 17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
      <h2 class="concord-agreement-info-sheet__title">{{ agreement?.title || 'Без названия' }}</h2>
      <p class="concord-agreement-info-sheet__description">
        {{ agreement?.description || 'Описание не добавлено.' }}
      </p>
      <button type="button" class="concord-agreement-info-sheet__done" @click="menuOpen = false">
        Готово
      </button>
    </div>
  </div>
</template>

<script>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import {
  formatLinkDisplayUrl,
  getEditorBlockLabel,
  getLinkDisplayLabel,
} from '../concord/editor-block-utils.js'
import { downloadAgreementFile, openAgreementFile } from '../concord/file-download-utils.js'
import { ensureAgreementSections, formatSectionTabTitle, getAgreementDaysRemaining, formatAgreementDaysLabel, CURRENT_APPROVER_ID } from '../concord/mock-agreements.js'
import { resolveSectionParticipants, MOCK_CONTACTS } from '../concord/mock-groups.js'
import ParticipantAvatars from '../concord/ParticipantAvatars.vue'
import ConcordUrgencyFlame from '../concord/ConcordUrgencyFlame.vue'
import ConcordImageViewer from '../concord/ConcordImageViewer.vue'
import ConcordGalleryCarousel from '../concord/ConcordGalleryCarousel.vue'
import ConcordApproverTextContent from '../concord/ConcordApproverTextContent.vue'
import ApproverVoteSection from '../concord/ApproverVoteSection.vue'
import ApproverVoteStats from '../concord/ApproverVoteStats.vue'
import ApproverVoteConfirmModal from '../concord/ApproverVoteConfirmModal.vue'
import ApproverVoteRejectModal from '../concord/ApproverVoteRejectModal.vue'
import ConcordReasonViewModal from '../concord/ConcordReasonViewModal.vue'
import { resetConcordScrollPosition } from '../concord/scroll-top.js'

export default {
  name: 'AgreementApproverView',
  components: {
    ParticipantAvatars,
    ConcordUrgencyFlame,
    ConcordImageViewer,
    ConcordGalleryCarousel,
    ConcordApproverTextContent,
    ConcordReasonViewModal,
    ApproverVoteSection,
    ApproverVoteStats,
    ApproverVoteConfirmModal,
    ApproverVoteRejectModal,
  },
  props: {
    agreement: {
      type: Object,
      default: null,
    },
    viewTransitionName: {
      type: String,
      default: '',
    },
    preview: {
      type: Boolean,
      default: false,
    },
    initialSectionId: {
      type: String,
      default: null,
    },
  },
  emits: ['back', 'section-settings', 'vote'],
  setup(props, { emit }) {
    const activeSectionId = ref(null)
    const approverMainRef = ref(null)
    const approverChromeRef = ref(null)
    const approverChromeHeight = ref(118)
    const approverHeaderVisible = ref(true)
    const sectionTabsRef = ref(null)
    const sectionRefs = new Map()
    const isProgrammaticScroll = ref(false)
    let sectionObserver = null
    let chromeResizeObserver = null
    let scrollFrame = null
    let lastScrollY = 0

    const yesConfirmOpen = ref(false)
    const noReasonOpen = ref(false)
    const menuOpen = ref(false)
    const activeVoteSectionId = ref(null)
    const imageViewerOpen = ref(false)
    const imageViewerPhotos = ref([])
    const imageViewerIndex = ref(0)
    const reasonModalOpen = ref(false)
    const reasonModalText = ref('')

    const expandedSections = reactive({})

    const showSectionTabs = computed(() => (props.agreement?.sections?.length || 0) > 1)

    const approverPageStyle = computed(() => ({
      '--concord-editor-scroll-anchor-offset': `${approverChromeHeight.value + 8}px`,
    }))

    const daysLabel = computed(() =>
      props.agreement?.daysLabel ||
      formatAgreementDaysLabel(
        props.agreement?.startDate || props.agreement?.createdAt,
        props.agreement?.deadline
      )
    )

    const daysRemaining = computed(() => getAgreementDaysRemaining(props.agreement?.deadline))
    const isDeadlineSoon = computed(() => {
      const remaining = daysRemaining.value
      return remaining !== null && remaining >= 0 && remaining <= 3
    })
    const isDeadlineOverdue = computed(() => {
      const remaining = daysRemaining.value
      return remaining !== null && remaining < 0
    })
    const isUrgent = computed(() => Boolean(
      (props.agreement?.isUrgent && !props.agreement?.urgentAcknowledged)
      || isDeadlineSoon.value
    ))

    function isActiveSection(section) {
      const participants = resolveSectionParticipants(section, [], MOCK_CONTACTS)
      return participants.some((person) => person.id === CURRENT_APPROVER_ID)
    }

    function sectionParticipants(section) {
      return resolveSectionParticipants(section, [], MOCK_CONTACTS)
    }

    function userVoteForSection(section) {
      return section.userVote || null
    }

    function stripContent(value) {
      return String(value || '')
        .replace(/<[^>]+>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
    }

    function sectionAnchorId(sectionId) {
      return `concord-approver-section-${sectionId}`
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

    function updateApproverChromeHeight() {
      const measured = approverChromeRef.value?.offsetHeight || 0
      approverChromeHeight.value = measured || 118
    }

    function setupApproverChromeResizeObserver() {
      chromeResizeObserver?.disconnect()
      updateApproverChromeHeight()
      if (!approverChromeRef.value || typeof ResizeObserver === 'undefined') {
        return
      }
      chromeResizeObserver = new ResizeObserver(() => {
        updateApproverChromeHeight()
      })
      chromeResizeObserver.observe(approverChromeRef.value)
    }

    function resetApproverHeaderVisibility() {
      lastScrollY = window.scrollY || document.documentElement.scrollTop || document.body.scrollTop || 0
      approverHeaderVisible.value = true
    }

    function getSectionObserverMargin() {
      const offset = Math.max(approverChromeHeight.value + 8, 72)
      return `-${offset}px 0px -55% 0px`
    }

    function toggleSection(sectionId) {
      expandedSections[sectionId] = expandedSections[sectionId] === false
      nextTick(updateActiveSectionFromScroll)
    }

    function scrollToSection(sectionId) {
      if (!sectionId) {
        return
      }
      isProgrammaticScroll.value = true
      activeSectionId.value = sectionId
      expandedSections[sectionId] = true
      const target = sectionRefs.get(sectionId) || document.getElementById(sectionAnchorId(sectionId))
      target?.scrollIntoView({ behavior: 'smooth', block: 'start' })
      window.setTimeout(() => {
        isProgrammaticScroll.value = false
        updateActiveSectionFromScroll()
      }, 700)
    }

    function updateActiveSectionFromScroll() {
      const chromeBottom = approverChromeRef.value?.getBoundingClientRect().bottom
      const scrollAnchor = chromeBottom || Math.max(approverChromeHeight.value, 72)
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
          approverHeaderVisible.value = true
        } else if (currentY > lastScrollY + 6) {
          approverHeaderVisible.value = false
        } else if (currentY < lastScrollY - 6) {
          approverHeaderVisible.value = true
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

    watch(
      () => props.agreement,
      (agreement) => {
        if (!agreement) {
          return
        }
        ensureAgreementSections(agreement)
        for (const section of agreement.sections || []) {
          if (expandedSections[section.id] === undefined) {
            expandedSections[section.id] = true
          }
        }
        const initialSectionId = props.initialSectionId
        const hasInitialSection = initialSectionId
          && agreement.sections.some((item) => item.id === initialSectionId)
        if (hasInitialSection) {
          activeSectionId.value = initialSectionId
        } else if (!activeSectionId.value || !agreement.sections.some((item) => item.id === activeSectionId.value)) {
          activeSectionId.value = agreement.sections[0]?.id || null
        }
        nextTick(() => {
          setupApproverChromeResizeObserver()
          setupSectionObserver()
          if (hasInitialSection) {
            scrollToSection(initialSectionId)
          } else {
            updateActiveSectionFromScroll()
          }
        })
      },
      { immediate: true }
    )

    onMounted(() => {
      resetConcordScrollPosition()
      requestAnimationFrame(() => {
        resetApproverHeaderVisibility()
        nextTick(() => {
          setupApproverChromeResizeObserver()
          setupSectionObserver()
          updateActiveSectionFromScroll()
        })
      })
      window.addEventListener('scroll', onScroll, { passive: true })
      document.addEventListener('scroll', onScroll, { passive: true, capture: true })
    })

    onBeforeUnmount(() => {
      sectionObserver?.disconnect()
      chromeResizeObserver?.disconnect()
      window.removeEventListener('scroll', onScroll)
      document.removeEventListener('scroll', onScroll, true)
      if (scrollFrame) {
        cancelAnimationFrame(scrollFrame)
      }
    })

    watch(showSectionTabs, () => {
      resetApproverHeaderVisibility()
      nextTick(() => {
        setupApproverChromeResizeObserver()
        setupSectionObserver()
      })
    })

    watch(approverHeaderVisible, () => {
      nextTick(updateApproverChromeHeight)
    })

    watch(approverChromeHeight, () => {
      setupSectionObserver()
    })

    function goBack() {
      emit('back')
    }

    function openMenu() {
      menuOpen.value = true
    }

    function openGalleryViewer(photos, index = 0) {
      imageViewerPhotos.value = photos || []
      imageViewerIndex.value = index
      imageViewerOpen.value = true
    }

    function closeImageViewer() {
      imageViewerOpen.value = false
      imageViewerPhotos.value = []
      imageViewerIndex.value = 0
    }

    function fileComment(file) {
      return String(file?.comment || '').trim()
    }

    function linkComment(link) {
      return String(link?.description || link?.comment || '').trim()
    }

    function fileDownloadLabel(file) {
      const name = file?.name || 'файл'
      return `Скачать ${name}`
    }

    function onFileDownload(file) {
      if (downloadAgreementFile(file)) {
        return
      }
      if (openAgreementFile(file)) {
        return
      }
      window.alert('Файл недоступен. Вернитесь в редактор и загрузите документ заново.')
    }

    function openReasonModal(reason) {
      reasonModalText.value = reason
      reasonModalOpen.value = true
    }

    function closeReasonModal() {
      reasonModalOpen.value = false
      reasonModalText.value = ''
    }

    function openYesConfirm(sectionId) {
      activeVoteSectionId.value = sectionId
      yesConfirmOpen.value = true
    }

    function openNoReason(sectionId) {
      activeVoteSectionId.value = sectionId
      noReasonOpen.value = true
    }

    function onVoteYes() {
      yesConfirmOpen.value = false
      emit('vote', { agreementId: props.agreement.id, sectionId: activeVoteSectionId.value, decision: 'approved', reason: '', participantId: CURRENT_APPROVER_ID })
      activeVoteSectionId.value = null
    }

    function onVoteNo(reason) {
      noReasonOpen.value = false
      emit('vote', { agreementId: props.agreement.id, sectionId: activeVoteSectionId.value, decision: 'rejected', reason, participantId: CURRENT_APPROVER_ID })
      activeVoteSectionId.value = null
    }

    return {
      activeSectionId,
      approverMainRef,
      approverChromeRef,
      approverChromeHeight,
      approverHeaderVisible,
      approverPageStyle,
      sectionTabsRef,
      showSectionTabs,
      expandedSections,
      yesConfirmOpen,
      noReasonOpen,
      menuOpen,
      imageViewerOpen,
      imageViewerPhotos,
      imageViewerIndex,
      reasonModalOpen,
      reasonModalText,
      formatSectionTabTitle,
      isActiveSection,
      sectionParticipants,
      userVoteForSection,
      daysLabel,
      isUrgent,
      isDeadlineSoon,
      isDeadlineOverdue,
      stripContent,
      getEditorBlockLabel,
      formatLinkDisplayUrl,
      getLinkDisplayLabel,
      sectionAnchorId,
      setSectionRef,
      toggleSection,
      scrollToSection,
      goBack,
      openMenu,
      openGalleryViewer,
      closeImageViewer,
      fileComment,
      linkComment,
      fileDownloadLabel,
      onFileDownload,
      openReasonModal,
      closeReasonModal,
      openYesConfirm,
      openNoReason,
      onVoteYes,
      onVoteNo,
    }
  },
}
</script>
