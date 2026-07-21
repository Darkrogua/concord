<template>
  <article class="concord-container" :class="{ 'concord-container--collapsed': !expanded }">
    <header class="concord-container__header">
      <h2 class="concord-container__title">{{ section.title }}</h2>

      <div class="concord-container__header-actions">
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
          class="concord-container__toggle"
          :aria-expanded="expanded"
          :aria-label="expanded ? 'Свернуть раздел' : 'Развернуть раздел'"
          @click="toggleExpanded"
        >
          <svg
            class="concord-container__toggle-icon"
            viewBox="0 0 24 24"
            width="22"
            height="22"
            fill="none"
            aria-hidden="true"
          >
            <path
              d="M6 9l6 6 6-6"
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
      <slot />
    </div>

    <footer class="concord-container__footer">
      <div class="concord-container__footer-title-row">
        <h3 class="concord-container__footer-title">{{ section.title }}</h3>
        <button
          type="button"
          class="concord-icon-btn concord-container__settings"
          aria-label="Настройки раздела"
          @click.stop="$emit('section-settings')"
        >
          <ConcordGearIcon :size="20" />
        </button>
      </div>

      <div class="concord-container__footer-info">
        <div class="concord-container__footer-details">
          <span
            class="concord-container__flame"
            :class="{
              'concord-container__flame--urgent': showUrgency && !isDeadlineSoon,
              'concord-container__flame--soon': isDeadlineSoon && !isDeadlineOverdue,
              'concord-container__flame--overdue': isDeadlineOverdue,
            }"
            aria-hidden="true"
          >
            <ConcordUrgencyFlame :urgent="showUrgency" tall />
          </span>
          <p
            class="concord-container__deadline"
            :class="{
              'concord-container__deadline--soon': isDeadlineSoon && !isDeadlineOverdue,
              'concord-container__deadline--overdue': isDeadlineOverdue,
            }"
          >
            <span>{{ agreement.deadline }}</span>
            <span
              v-if="deadlineHint && deadlineHint !== '—'"
              class="concord-container__days"
              :class="{
                'concord-container__days--soon': isDeadlineSoon && !isDeadlineOverdue,
                'concord-container__days--overdue': isDeadlineOverdue,
              }"
            >
              ({{ deadlineHint }})
            </span>
          </p>
          <p class="concord-container__voters">Согласующих: {{ votersLabel }}</p>
        </div>
        <ParticipantAvatars :people="participants" :max="5" compact />
      </div>

      <div class="concord-container__voting">
        <div
          v-for="row in votingRows"
          :key="row.key"
          class="concord-container__vote-row"
        >
          <div class="concord-container__vote-head">
            <span>{{ row.label }}</span>
            <span>{{ row.percent }}%</span>
          </div>
          <div class="concord-container__vote-track" aria-hidden="true">
            <div class="concord-container__vote-fill" :style="{ width: `${row.percent}%` }" />
          </div>
        </div>
      </div>
    </footer>
  </article>
</template>

<script>
import { ref } from 'vue'
import ConcordGearIcon from './ConcordGearIcon.vue'
import ConcordUrgencyFlame from './ConcordUrgencyFlame.vue'
import ParticipantAvatars from './ParticipantAvatars.vue'
import {
  resolveSectionParticipants,
  resolveSectionVotersCount,
  sectionHasConfiguredParticipants,
} from './mock-groups.js'
import {
  formatAgreementDaysLabel,
  getAgreementDaysRemaining,
  getAgreementDeadlineHint,
  isAgreementDeadlineSoon,
} from './mock-agreements.js'

export default {
  name: 'AgreementContainerCard',
  components: { ConcordGearIcon, ConcordUrgencyFlame, ParticipantAvatars },
  props: {
    agreement: {
      type: Object,
      required: true,
    },
    section: {
      type: Object,
      required: true,
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
  emits: ['section-settings'],
  setup() {
    const expanded = ref(true)

    function toggleExpanded() {
      expanded.value = !expanded.value
    }

    return { expanded, toggleExpanded }
  },
  computed: {
    sectionParticipants() {
      return resolveSectionParticipants(this.section, this.groups, this.contacts)
    },
    votersCount() {
      if (sectionHasConfiguredParticipants(this.section)) {
        return resolveSectionVotersCount(this.section, this.groups, this.contacts)
      }
      return this.agreement.total || this.agreement.participants?.length || 0
    },
    votersLabel() {
      const count = this.votersCount
      return count ? `${count} чел.` : 'не назначены'
    },
    participants() {
      if (sectionHasConfiguredParticipants(this.section)) {
        return this.sectionParticipants
      }
      return this.agreement.participants || []
    },
    daysLabel() {
      return formatAgreementDaysLabel(
        this.agreement.startDate || this.agreement.createdAt,
        this.agreement.deadline
      )
    },
    daysRemaining() {
      return getAgreementDaysRemaining(this.agreement.deadline)
    },
    isDeadlineSoon() {
      return isAgreementDeadlineSoon(this.agreement.deadline)
    },
    isDeadlineOverdue() {
      return this.daysRemaining !== null && this.daysRemaining < 0
    },
    showUrgency() {
      return Boolean(this.agreement.isUrgent || this.isDeadlineSoon)
    },
    deadlineHint() {
      return getAgreementDeadlineHint(this.agreement.deadline, this.daysLabel)
    },
    votingStats() {
      return this.section.votingStats || { approved: 0, rejected: 0, pending: 100 }
    },
    votingRows() {
      const stats = this.votingStats
      return [
        { key: 'approved', label: 'Согласовано', percent: stats.approved || 0 },
        { key: 'rejected', label: 'Не согласовано', percent: stats.rejected || 0 },
        { key: 'pending', label: 'Не голосовали', percent: stats.pending || 0 },
      ]
    },
  },
}
</script>
