<template>
  <article class="concord-container concord-container--editor">
    <header class="concord-container__header">
      <div class="concord-container__header-main">
        <h2 class="concord-container__title">{{ sectionTitle }}</h2>
        <p class="concord-container__meta">{{ sectionMeta }}</p>
      </div>

      <div class="concord-container__header-actions">
        <button
          type="button"
          class="concord-icon-btn concord-container__preview"
          aria-label="Предпросмотр согласования как согласователь"
          @click.stop="$emit('preview')"
        >
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
            <path d="M2.8 12s3.3-5.5 9.2-5.5 9.2 5.5 9.2 5.5-3.3 5.5-9.2 5.5S2.8 12 2.8 12Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
            <circle cx="12" cy="12" r="2.7" stroke="currentColor" stroke-width="1.7"/>
          </svg>
        </button>

        <button
          type="button"
          class="concord-icon-btn concord-container__settings"
          aria-label="Настройки раздела"
          @click.stop="$emit('section-settings')"
        >
          <ConcordGearIcon :size="20" />
        </button>

        <button
          type="button"
          class="concord-icon-btn concord-container__confirm"
          :class="{ 'concord-container__confirm--expanded': expanded }"
          :aria-expanded="expanded"
          :aria-label="expanded ? 'Свернуть раздел' : 'Развернуть раздел'"
          @click="toggleExpanded"
        >
          <svg viewBox="0 0 16 16" width="18" height="18" fill="none" aria-hidden="true">
            <path
              d="m3.5 5.5 4.5 4.5 4.5-4.5"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </button>
      </div>
    </header>

    <div v-show="expanded" class="concord-container__body">
      <slot :expanded="expanded" />
    </div>

    <footer
      class="concord-container__footer"
      :class="{
        'concord-container__footer--has-results': hasVoteActivity,
        'concord-container__footer--section-approved': isSectionApproved,
      }"
    >
      <div v-if="expanded" class="concord-container__footer-section-head">
        <div class="concord-container__footer-section-main">
          <h2 class="concord-container__title">{{ sectionTitle }}</h2>
          <p class="concord-container__meta">{{ sectionMeta }}</p>
        </div>
        <button
          type="button"
          class="concord-icon-btn concord-container__settings"
          aria-label="Настройки раздела"
          @click.stop="$emit('section-settings')"
        >
          <ConcordGearIcon :size="20" />
        </button>
      </div>

      <div class="concord-container__footer-facts">
        <button
          type="button"
          class="concord-container__footer-fact concord-container__footer-fact--action"
          aria-label="Настроить сроки раздела"
          @click="$emit('section-schedule')"
        >
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
            <rect x="4.5" y="5.5" width="15" height="14" rx="1.5" stroke="currentColor" stroke-width="1.6"/>
            <path d="M8 3.8v3.6M16 3.8v3.6M4.5 10h15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
          <div>
            <span>Срок согласования</span>
            <strong>{{ sectionDeadline || 'Не указан' }}</strong>
          </div>
        </button>
        <button
          type="button"
          class="concord-container__footer-fact concord-container__footer-fact--action"
          aria-label="Настроить участников раздела"
          @click="$emit('section-participants')"
        >
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
            <circle cx="12" cy="8" r="3.4" stroke="currentColor" stroke-width="1.6"/>
            <path d="M5.5 20c.7-3.2 3-5 6.5-5s5.8 1.8 6.5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
          <div>
            <span>Согласующих</span>
            <strong>{{ votersLabel }}</strong>
          </div>
        </button>
      </div>

      <div
        v-if="hasVoteActivity"
        class="concord-container__footer-result"
      >
        <span class="concord-container__footer-result-stats">{{ sectionVoteSummary }}</span>
        <span
          class="concord-container__footer-result-badge"
          :class="`concord-container__footer-result-badge--${sectionStatusTone}`"
        >
          {{ sectionStatusLabel }}
        </span>
      </div>

      <div class="concord-container__voting">
        <div
          v-for="row in votingRows"
          :key="row.key"
          class="concord-container__vote-row"
          :class="`concord-container__vote-row--${row.key}`"
        >
          <span class="concord-container__vote-label">
            {{ row.label }}
            <span class="concord-container__vote-count">({{ row.count }})</span>
          </span>
          <div class="concord-container__vote-track" aria-hidden="true">
            <div class="concord-container__vote-fill" :style="{ width: `${row.percent}%` }" />
          </div>
          <span class="concord-container__vote-percent">{{ row.percent }}%</span>
        </div>
      </div>

      <div v-if="rejectedVotes.length" class="concord-container__comments">
        <h4 class="concord-container__comments-title">Комментарии к отказам</h4>
        <ul class="concord-container__comments-list">
          <li
            v-for="vote in rejectedVotes"
            :key="vote.participantId"
            class="concord-container__comment"
          >
            <div class="concord-container__comment-head">
              <span class="concord-container__comment-avatar">{{ vote.initial }}</span>
              <span class="concord-container__comment-name">{{ vote.name }}</span>
            </div>
            <p class="concord-container__comment-text">{{ vote.reason }}</p>
            <button
              type="button"
              class="concord-container__comment-more"
              @click="openReasonModal(vote.reason)"
            >
              Читать полностью
            </button>
          </li>
        </ul>
      </div>
    </footer>

    <ConcordReasonViewModal
      :open="reasonModalOpen"
      :reason="reasonModalText"
      @close="closeReasonModal"
    />
  </article>
</template>

<script>
import { computed, provide, ref } from 'vue'
import ConcordGearIcon from './ConcordGearIcon.vue'
import ConcordReasonViewModal from './ConcordReasonViewModal.vue'
import {
  resolveSectionParticipants,
  resolveSectionVotersCount,
} from './mock-groups.js'
import {
  getSectionVoteCounts,
  isSectionFullyApproved,
} from './agreement-results-utils.js'

function pluralizeRu(value, forms) {
  const mod10 = value % 10
  const mod100 = value % 100
  if (mod10 === 1 && mod100 !== 11) {
    return forms[0]
  }
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) {
    return forms[1]
  }
  return forms[2]
}

function buildSectionMeta(section) {
  const blocks = section?.blocks || []
  let fileCount = 0
  let photoCount = 0

  for (const block of blocks) {
    if (block.type === 'files') {
      fileCount += (block.files || []).length
    }
    if (block.type === 'gallery') {
      photoCount += (block.photos || []).length
    }
  }

  const blockCount = blocks.length
  return [
    `${blockCount} ${pluralizeRu(blockCount, ['блок', 'блока', 'блоков'])}`,
    `${fileCount} ${pluralizeRu(fileCount, ['файл', 'файла', 'файлов'])}`,
    `${photoCount} ${pluralizeRu(photoCount, ['фото', 'фото', 'фото'])}`,
  ].join(' • ')
}

export default {
  name: 'AgreementContainerCard',
  components: { ConcordGearIcon, ConcordReasonViewModal },
  props: {
    agreement: {
      type: Object,
      required: true,
    },
    section: {
      type: Object,
      required: true,
    },
    sectionNumber: {
      type: Number,
      default: null,
    },
    groups: {
      type: Array,
      default: () => [],
    },
    contacts: {
      type: Array,
      default: () => [],
    },
  },
  emits: ['preview', 'section-settings', 'section-schedule', 'section-participants'],
  setup() {
    const expanded = ref(true)
    const reasonModalOpen = ref(false)
    const reasonModalText = ref('')

    provide('sectionBlocksLocked', computed(() => !expanded.value))

    function toggleExpanded() {
      expanded.value = !expanded.value
    }

    function openReasonModal(reason) {
      reasonModalText.value = reason
      reasonModalOpen.value = true
    }

    function closeReasonModal() {
      reasonModalOpen.value = false
      reasonModalText.value = ''
    }

    return { expanded, toggleExpanded, reasonModalOpen, reasonModalText, openReasonModal, closeReasonModal }
  },
  computed: {
    sectionTitle() {
      const title = this.section.title || 'Раздел'
      if (this.sectionNumber) {
        return `${this.sectionNumber}. ${title}`
      }
      return title
    },
    sectionMeta() {
      return buildSectionMeta(this.section)
    },
    sectionDeadline() {
      return this.section?.deadline || this.agreement?.deadline || ''
    },
    sectionParticipants() {
      return resolveSectionParticipants(this.section, this.groups, this.contacts)
    },
    votersCount() {
      const live = resolveSectionVotersCount(this.section, this.groups, this.contacts)
      const snapshot = Array.isArray(this.section?.voterIds) ? this.section.voterIds.length : 0
      const stored = Number(this.section?.total) || 0
      const groupIds = this.section?.groupIds || []
      const hasMissingGroups = groupIds.some(
        (groupId) => !this.groups.some((group) => group.id === groupId)
      )
      // Custom group disappeared from profile (reload / stale storage) — don't undercount.
      if (hasMissingGroups) {
        return Math.max(live, snapshot, stored)
      }
      return live || snapshot || stored
    },
    votersLabel() {
      const count = this.votersCount
      return count ? `${count} ${pluralizeRu(count, ['человек', 'человека', 'человек'])}` : 'не назначены'
    },
    voteCounts() {
      return getSectionVoteCounts(this.section, this.groups, this.contacts)
    },
    hasVoteActivity() {
      return (this.section?.votes || []).some((vote) => vote?.participantId)
    },
    isSectionApproved() {
      return isSectionFullyApproved(this.section, this.groups, this.contacts)
    },
    sectionVoteSummary() {
      const { approved, rejected, pending } = this.voteCounts
      return `${approved} за · ${rejected} против · ${pending} ожидают`
    },
    sectionStatusTone() {
      if (this.isSectionApproved) {
        return 'approved'
      }
      const { rejected, pending, approved, total } = this.voteCounts
      if (pending === 0 && rejected > 0) {
        return 'rejected'
      }
      if (approved + rejected > 0 && total) {
        return 'progress'
      }
      return 'pending'
    },
    sectionStatusLabel() {
      if (this.isSectionApproved) {
        return 'Согласован'
      }
      const { rejected, pending, approved, total } = this.voteCounts
      if (pending === 0 && rejected > 0) {
        return 'Не согласован'
      }
      if (approved + rejected > 0 && total) {
        return `${approved + rejected} из ${total}`
      }
      return 'Ждёт голосов'
    },
    participants() {
      return this.sectionParticipants
    },
    votingRows() {
      const counts = this.voteCounts
      const labels = this.hasVoteActivity
        ? { approved: 'За', rejected: 'Против', pending: 'Ожидают' }
        : { approved: 'Согласовано', rejected: 'Не согласовано', pending: 'Не голосовали' }

      return [
        {
          key: 'approved',
          label: labels.approved,
          percent: counts.approvedPercent,
          count: counts.approved,
        },
        {
          key: 'rejected',
          label: labels.rejected,
          percent: counts.rejectedPercent,
          count: counts.rejected,
        },
        {
          key: 'pending',
          label: labels.pending,
          percent: counts.pendingPercent,
          count: counts.pending,
        },
      ]
    },
    rejectedVotes() {
      const participants = this.sectionParticipants
      const votes = this.section.votes || []
      return votes
        .filter((vote) => vote.decision === 'rejected' && vote.reason)
        .map((vote) => {
          const person = participants.find((p) => p.id === vote.participantId)
          return {
            participantId: vote.participantId,
            name: person?.shortName || person?.name || vote.participantId,
            initial: person?.initial || '?',
            reason: vote.reason,
            votedAt: vote.votedAt,
          }
        })
        .sort((a, b) => (a.votedAt || '').localeCompare(b.votedAt || ''))
    },
  },
}
</script>
