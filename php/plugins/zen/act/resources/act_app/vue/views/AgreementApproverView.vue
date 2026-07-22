<template>
  <div class="concord-page concord-page--approver">
    <header class="concord-header concord-header--approver">
      <button type="button" class="concord-icon-btn" aria-label="Назад" @click="goBack">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <h1 class="concord-header__title concord-header__title--truncate">
        Согласование #{{ agreement?.number }}
      </h1>
      <button
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

    <div v-if="showSectionTabs" class="concord-agreement-editor__tabs-wrap">
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
            <h2 class="concord-approver__section-title">{{ section.title }}</h2>
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
          </header>

          <div v-show="expandedSections[section.id] !== false" class="concord-approver__section-body">
            <div v-if="section.blocks?.length" class="concord-approver__blocks">
              <article
                v-for="block in section.blocks"
                :key="block.id"
                class="concord-block concord-approver__block"
              >
                <h3 class="concord-approver__block-title">{{ block.title || block.label }}</h3>
                <p v-if="block.description" class="concord-approver__block-desc">{{ block.description }}</p>
                <p v-if="block.content" class="concord-approver__block-content">{{ stripContent(block.content) }}</p>

                <div v-if="(block.files || []).length" class="concord-approver__files">
                  <div
                    v-for="file in block.files"
                    :key="file.id"
                    class="concord-approver__file"
                  >
                    <div class="concord-approver__file-preview">
                      <img v-if="file.previewUrl" :src="file.previewUrl" :alt="file.name">
                      <svg v-else viewBox="0 0 24 24" width="28" height="28" fill="none">
                        <path d="M8 3h6l5 5v13a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.4"/>
                        <path d="M14 3v5h5" stroke="currentColor" stroke-width="1.4"/>
                      </svg>
                    </div>
                    <p class="concord-approver__file-name">{{ file.name }}</p>
                  </div>
                </div>

                <div v-if="(block.photos || []).length" class="concord-approver__gallery">
                  <div
                    v-for="photo in block.photos"
                    :key="photo.id"
                    class="concord-approver__photo"
                  >
                    <img v-if="photo.previewUrl" :src="photo.previewUrl" :alt="photo.name">
                  </div>
                </div>

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

                <div v-if="(block.links || []).length" class="concord-approver__links">
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
            <div class="concord-approver__section-meta">
              <span class="concord-approver__section-id">#{{ agreement.number }}</span>
              <span class="concord-approver__section-date">Создана: {{ agreement.createdAt }}</span>
            </div>
            <div class="concord-approver__section-participants">
              <span class="concord-approver__section-voters">Согласующих: {{ sectionParticipants(section).length }} чел.</span>
              <ParticipantAvatars :people="sectionParticipants(section)" :max="5" compact />
            </div>

            <ApproverVoteSection
              v-if="isActiveSection(section)"
              :section="section"
              :user-vote="userVoteForSection(section)"
              @vote-yes="openYesConfirm(section.id)"
              @vote-no="openNoReason(section.id)"
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

    <div v-if="menuOpen" class="concord-sheet-backdrop" @click="menuOpen = false" />
    <div v-if="menuOpen" class="concord-menu-sheet" role="dialog" aria-label="Меню согласования">
      <div class="concord-sheet__handle" aria-hidden="true" />
      <button type="button" class="concord-menu-sheet__item" @click="menuOpen = false">
        Закрыть
      </button>
    </div>
  </div>
</template>

<script>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { ensureAgreementSections, formatSectionTabTitle, CURRENT_APPROVER_ID } from '../concord/mock-agreements.js'
import { resolveSectionParticipants, MOCK_CONTACTS } from '../concord/mock-groups.js'
import ParticipantAvatars from '../concord/ParticipantAvatars.vue'
import ApproverVoteSection from '../concord/ApproverVoteSection.vue'
import ApproverVoteStats from '../concord/ApproverVoteStats.vue'
import ApproverVoteConfirmModal from '../concord/ApproverVoteConfirmModal.vue'
import ApproverVoteRejectModal from '../concord/ApproverVoteRejectModal.vue'

export default {
  name: 'AgreementApproverView',
  components: { ParticipantAvatars, ApproverVoteSection, ApproverVoteStats, ApproverVoteConfirmModal, ApproverVoteRejectModal },
  props: {
    agreement: {
      type: Object,
      default: null,
    },
  },
  emits: ['back', 'vote'],
  setup(props, { emit }) {
    const activeSectionId = ref(null)
    const approverMainRef = ref(null)
    const sectionRefs = new Map()
    const isProgrammaticScroll = ref(false)
    let sectionObserver = null

    const yesConfirmOpen = ref(false)
    const noReasonOpen = ref(false)
    const menuOpen = ref(false)
    const activeVoteSectionId = ref(null)

    const expandedSections = reactive({})

    const showSectionTabs = computed(() => (props.agreement?.sections?.length || 0) > 1)

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
      } else {
        sectionRefs.delete(sectionId)
      }
    }

    function toggleSection(sectionId) {
      expandedSections[sectionId] = expandedSections[sectionId] === false
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
          rootMargin: '-72px 0px -55% 0px',
          threshold: [0, 0.15, 0.35, 0.55, 0.75, 1],
        }
      )
      sectionRefs.forEach((element) => sectionObserver.observe(element))
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
        if (!activeSectionId.value || !agreement.sections.some((item) => item.id === activeSectionId.value)) {
          activeSectionId.value = agreement.sections[0]?.id || null
        }
        nextTick(() => setupSectionObserver())
      },
      { immediate: true }
    )

    onMounted(() => {
      nextTick(() => setupSectionObserver())
    })

    onBeforeUnmount(() => {
      sectionObserver?.disconnect()
    })

    function goBack() {
      emit('back')
    }

    function openMenu() {
      menuOpen.value = true
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
      emit('vote', { agreementId: props.agreement.id, sectionId: activeVoteSectionId.value, decision: 'approved', reason: '' })
      activeVoteSectionId.value = null
    }

    function onVoteNo(reason) {
      noReasonOpen.value = false
      emit('vote', { agreementId: props.agreement.id, sectionId: activeVoteSectionId.value, decision: 'rejected', reason })
      activeVoteSectionId.value = null
    }

    return {
      activeSectionId,
      approverMainRef,
      showSectionTabs,
      expandedSections,
      yesConfirmOpen,
      noReasonOpen,
      menuOpen,
      formatSectionTabTitle,
      isActiveSection,
      sectionParticipants,
      userVoteForSection,
      stripContent,
      sectionAnchorId,
      setSectionRef,
      toggleSection,
      scrollToSection,
      goBack,
      openMenu,
      openYesConfirm,
      openNoReason,
      onVoteYes,
      onVoteNo,
    }
  },
}
</script>
