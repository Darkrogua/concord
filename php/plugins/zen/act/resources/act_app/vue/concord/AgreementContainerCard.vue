<template>
  <article class="concord-container">
    <header class="concord-container__header">
      <h2 class="concord-container__title">{{ section.title }}</h2>
      <button
        type="button"
        class="concord-icon-btn concord-container__settings"
        aria-label="Настройки контейнера"
        @click="$emit('section-settings')"
      >
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
          <circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.5"/>
          <path d="M12 3.5v2M12 18.5v2M5.2 5.2l1.4 1.4M17.4 17.4l1.4 1.4M3.5 12h2M18.5 12h2M5.2 18.8l1.4-1.4M17.4 6.6l1.4-1.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
      </button>
    </header>

    <div class="concord-container__body">
      <slot />
    </div>

    <footer class="concord-container__footer">
      <div class="concord-container__footer-meta">
        <span class="concord-container__footer-number">#{{ agreement.number }}</span>
        <span class="concord-container__footer-created">Создана: {{ agreement.createdAt }}</span>
      </div>

      <div class="concord-container__footer-title-row">
        <h3 class="concord-container__footer-title">{{ section.title }}</h3>
        <button
          type="button"
          class="concord-icon-btn concord-container__settings"
          aria-label="Настройки контейнера"
          @click="$emit('section-settings')"
        >
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.5"/>
            <path d="M12 3.5v2M12 18.5v2M5.2 5.2l1.4 1.4M17.4 17.4l1.4 1.4M3.5 12h2M18.5 12h2M5.2 18.8l1.4-1.4M17.4 6.6l1.4-1.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
        </button>
      </div>

      <div class="concord-container__footer-info">
        <div class="concord-container__footer-details">
          <span
            class="concord-container__flame"
            :class="{ 'concord-container__flame--urgent': agreement.isUrgent }"
            aria-hidden="true"
          >
            <ConcordUrgencyFlame :urgent="agreement.isUrgent" tall />
          </span>
          <p class="concord-container__deadline">
            <span>{{ agreement.deadline }}</span>
            <span v-if="agreement.daysLabel && agreement.daysLabel !== '—'" class="concord-container__days">
              ({{ agreement.daysLabel }})
            </span>
          </p>
          <p class="concord-container__voters">Согласующих: {{ votersLabel }}</p>
        </div>
        <ParticipantAvatars :people="participants" :max="5" />
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
import ConcordUrgencyFlame from './ConcordUrgencyFlame.vue'
import ParticipantAvatars from './ParticipantAvatars.vue'

export default {
  name: 'AgreementContainerCard',
  components: { ConcordUrgencyFlame, ParticipantAvatars },
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
  },
  emits: ['section-settings'],
  computed: {
    votersCount() {
      const section = this.section
      let count = section.participantIds?.length || 0
      for (const groupId of section.groupIds || []) {
        const group = this.groups.find((item) => item.id === groupId)
        count += group?.memberCount || group?.members?.length || 0
      }
      if (count > 0) {
        return count
      }
      return this.agreement.total || this.agreement.participants?.length || 0
    },
    votersLabel() {
      const count = this.votersCount
      return count ? `${count} чел.` : 'не назначены'
    },
    participants() {
      if (this.agreement.participants?.length) {
        return this.agreement.participants
      }
      return []
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
