<template>
  <section class="concord-agreement-results" aria-label="Итоги согласования">
    <div class="concord-agreement-results__hero" :class="`concord-agreement-results__hero--${verdict.tone}`">
      <div class="concord-agreement-results__hero-main">
        <p class="concord-agreement-results__eyebrow">Итог по согласованию</p>
        <h2 class="concord-agreement-results__title">{{ verdict.title }}</h2>
        <p class="concord-agreement-results__subtitle">{{ verdict.subtitle }}</p>
        <p class="concord-agreement-results__sections-summary">{{ overview.sectionsSummary }}</p>
      </div>
      <div class="concord-agreement-results__totals" aria-label="Сводка голосов по всему согласованию">
        <div class="concord-agreement-results__total">
          <span class="concord-agreement-results__total-value">{{ overview.totals.approved }}</span>
          <span class="concord-agreement-results__total-label">За</span>
        </div>
        <div class="concord-agreement-results__total concord-agreement-results__total--rejected">
          <span class="concord-agreement-results__total-value">{{ overview.totals.rejected }}</span>
          <span class="concord-agreement-results__total-label">Против</span>
        </div>
        <div class="concord-agreement-results__total concord-agreement-results__total--pending">
          <span class="concord-agreement-results__total-value">{{ overview.totals.pending }}</span>
          <span class="concord-agreement-results__total-label">Ожидают</span>
        </div>
      </div>
    </div>

    <section class="concord-agreement-results__overview" aria-label="Общая сводка">
      <h3 class="concord-agreement-results__block-title">Общая сводка</h3>
      <div class="concord-agreement-results__overview-sections">
        <div
          v-for="section in overview.sections"
          :key="`overview-${section.id}`"
          class="concord-agreement-results__overview-section"
          :class="`concord-agreement-results__overview-section--${section.status}`"
        >
          <span class="concord-agreement-results__overview-section-title">{{ section.title }}</span>
          <span class="concord-agreement-results__overview-section-badge">{{ section.statusLabel }}</span>
          <span class="concord-agreement-results__overview-section-stats">
            {{ section.approved }} за · {{ section.rejected }} против
          </span>
        </div>
      </div>

      <div v-if="overview.allRejections.length" class="concord-agreement-results__overview-rejections">
        <h4 class="concord-agreement-results__overview-rejections-title">
          Все замечания ({{ overview.allRejections.length }})
        </h4>
        <ul class="concord-agreement-results__overview-rejections-list">
          <li
            v-for="item in overview.allRejections"
            :key="`${item.sectionId}-${item.participantId}`"
            class="concord-agreement-results__overview-rejection"
          >
            <p class="concord-agreement-results__overview-rejection-meta">
              <span class="concord-agreement-results__overview-rejection-section">{{ item.sectionTitle }}</span>
              <span class="concord-agreement-results__overview-rejection-name">{{ item.name }}</span>
            </p>
            <p class="concord-agreement-results__overview-rejection-text">{{ item.reason }}</p>
            <button
              type="button"
              class="concord-agreement-results__rejection-more"
              @click.stop="$emit('view-reason', item.reason)"
            >
              Читать полностью
            </button>
          </li>
        </ul>
      </div>
      <p v-else-if="overview.totals.rejected === 0 && overview.totals.pending === 0" class="concord-agreement-results__overview-empty">
        Замечаний по согласованию нет — все разделы прошли без отказов.
      </p>
    </section>

    <section class="concord-agreement-results__sections-wrap" aria-label="Итоги по разделам">
      <h3 class="concord-agreement-results__block-title">Итоги по разделам</h3>
      <div class="concord-agreement-results__sections">
      <article
        v-for="section in sectionResults"
        :key="section.id"
        class="concord-agreement-results__section"
      >
        <button
          type="button"
          class="concord-agreement-results__section-head"
          :aria-expanded="expandedSections[section.id] !== false"
          @click="toggleSection(section.id)"
        >
          <div class="concord-agreement-results__section-title-wrap">
            <span class="concord-agreement-results__section-index">{{ section.index }}</span>
            <div class="concord-agreement-results__section-meta">
              <h3 class="concord-agreement-results__section-title">{{ section.title }}</h3>
              <p class="concord-agreement-results__section-stats">
                {{ section.approved }} за · {{ section.rejected }} против · {{ section.pending }} ожидают
              </p>
            </div>
          </div>
          <span
            class="concord-agreement-results__section-badge"
            :class="`concord-agreement-results__section-badge--${section.status}`"
          >
            {{ section.statusLabel }}
          </span>
        </button>

        <div v-show="expandedSections[section.id] !== false" class="concord-agreement-results__section-body">
          <div class="concord-agreement-results__rejections">
            <h4 class="concord-agreement-results__rejections-title">Замечания и отказы</h4>
            <ul v-if="section.rejections.length" class="concord-agreement-results__rejections-list">
              <li
                v-for="item in section.rejections"
                :key="item.participantId"
                class="concord-agreement-results__rejection"
              >
                <div class="concord-agreement-results__rejection-head">
                  <span class="concord-agreement-results__avatar">{{ item.initial }}</span>
                  <span class="concord-agreement-results__rejection-name">{{ item.name }}</span>
                </div>
                <p class="concord-agreement-results__rejection-text">{{ item.reason }}</p>
                <button
                  type="button"
                  class="concord-agreement-results__rejection-more"
                  @click.stop="$emit('view-reason', item.reason)"
                >
                  Читать полностью
                </button>
              </li>
            </ul>
            <p v-else class="concord-agreement-results__rejections-empty">
              {{ section.rejected > 0
                ? 'Отказов без комментария нет.'
                : 'Отказов нет — все участники проголосовали «За».' }}
            </p>
          </div>

          <div class="concord-agreement-results__roster">
            <h4 class="concord-agreement-results__roster-title">
              {{ section.rejected > 0 ? 'Остальные участники' : 'Кто согласовал' }}
            </h4>
            <ul class="concord-agreement-results__roster-list">
              <li
                v-for="person in section.roster"
                :key="person.id"
                class="concord-agreement-results__roster-item"
              >
                <span class="concord-agreement-results__avatar">{{ person.initial }}</span>
                <span class="concord-agreement-results__roster-name">{{ person.name }}</span>
                <span
                  class="concord-agreement-results__roster-status"
                  :class="`concord-agreement-results__roster-status--${person.decision}`"
                >
                  {{ rosterStatusLabel(person.decision) }}
                </span>
              </li>
            </ul>
          </div>
        </div>
      </article>
      </div>
    </section>
  </section>
</template>

<script>
import { computed, reactive } from 'vue'
import {
  getAgreementResultsOverview,
  getAgreementResultsVerdict,
  getAgreementSectionResults,
} from './agreement-results-utils.js'

export default {
  name: 'AgreementResultsSummary',
  props: {
    agreement: {
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
  emits: ['view-reason'],
  setup(props) {
    const expandedSections = reactive({})

    const overview = computed(() =>
      getAgreementResultsOverview(props.agreement, props.groups, props.contacts)
    )
    const verdict = computed(() =>
      getAgreementResultsVerdict(props.agreement, props.groups, props.contacts)
    )
    const sectionResults = computed(() =>
      getAgreementSectionResults(props.agreement, props.groups, props.contacts)
    )

    function toggleSection(sectionId) {
      expandedSections[sectionId] = expandedSections[sectionId] === false
    }

    function rosterStatusLabel(decision) {
      if (decision === 'approved') {
        return 'За'
      }
      if (decision === 'rejected') {
        return 'Против'
      }
      return 'Ожидает'
    }

    return {
      expandedSections,
      overview,
      verdict,
      sectionResults,
      toggleSection,
      rosterStatusLabel,
    }
  },
}
</script>
