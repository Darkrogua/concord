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
          <span v-if="sectionBadge(section)" class="concord-tabs__badge">{{ sectionBadge(section) }}</span>
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
      >
        <h2 class="concord-approver__section-title">{{ section.title }}</h2>

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
          </article>
        </div>
      </div>
    </main>

    <ApproverVoteCard
      :agreement="agreement"
      :user-vote="userVote"
      @vote-yes="openYesConfirm"
      @vote-no="openNoReason"
    />

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
      <button type="button" class="concord-menu-sheet__item" @click="goToInfo">
        Информация о согласовании
      </button>
      <button type="button" class="concord-menu-sheet__item" @click="goToParticipants">
        Участники
      </button>
      <button type="button" class="concord-menu-sheet__item concord-menu-sheet__item--destructive" @click="menuOpen = false">
        Закрыть
      </button>
    </div>
  </div>
</template>

<script>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ensureAgreementSections, formatSectionTabTitle } from '../concord/mock-agreements.js'
import ApproverVoteCard from '../concord/ApproverVoteCard.vue'
import ApproverVoteConfirmModal from '../concord/ApproverVoteConfirmModal.vue'
import ApproverVoteRejectModal from '../concord/ApproverVoteRejectModal.vue'

export default {
  name: 'AgreementApproverView',
  components: { ApproverVoteCard, ApproverVoteConfirmModal, ApproverVoteRejectModal },
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

    const userVote = computed(() => props.agreement?.userVote || null)

    const showSectionTabs = computed(() => (props.agreement?.sections?.length || 0) > 1)

    function stripContent(value) {
      return String(value || '')
        .replace(/<[^>]+>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
    }

    function sectionBadge(section) {
      const pending = section.blocks?.filter((block) => !block.reviewed).length
      return pending || section.blocks?.length || 0
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

    function goToInfo() {
      menuOpen.value = false
    }

    function goToParticipants() {
      menuOpen.value = false
    }

    function openYesConfirm() {
      yesConfirmOpen.value = true
    }

    function openNoReason() {
      noReasonOpen.value = true
    }

    function onVoteYes() {
      yesConfirmOpen.value = false
      emit('vote', { agreementId: props.agreement.id, decision: 'approved', reason: '' })
    }

    function onVoteNo(reason) {
      noReasonOpen.value = false
      emit('vote', { agreementId: props.agreement.id, decision: 'rejected', reason })
    }

    return {
      activeSectionId,
      approverMainRef,
      showSectionTabs,
      userVote,
      yesConfirmOpen,
      noReasonOpen,
      menuOpen,
      formatSectionTabTitle,
      stripContent,
      sectionBadge,
      sectionAnchorId,
      setSectionRef,
      scrollToSection,
      goBack,
      openMenu,
      goToInfo,
      goToParticipants,
      openYesConfirm,
      openNoReason,
      onVoteYes,
      onVoteNo,
    }
  },
}
</script>
