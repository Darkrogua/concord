<template>
  <article
    class="concord-card"
    :class="[cardModifiers, { 'concord-card--new': isNew }]"
    @click="$emit('open', agreement.id)"
  >
    <header
      :class="[
        'concord-card__header',
        {
          'concord-card__header--draft': isDraft,
          'concord-card__header--approved': isApprovedHeader,
          'concord-card__header--has-days': showDaysLabel,
        },
      ]"
    >
      <div class="concord-card__id">
        <span class="concord-card__number">#{{ agreement.number }}</span>
      </div>

      <div class="concord-card__status-area">
        <span v-if="isDraft" class="concord-card__status-plate concord-card__status-plate--draft">
          Черновик
        </span>
        <span v-else-if="isApprovedHeader" class="concord-card__status-plate concord-card__status-plate--approved">
          {{ approvedHeaderText }}
        </span>
        <span v-else-if="agreement.isOwner" class="concord-card__status-pill concord-card__status-pill--owner">
          <span class="concord-card__status-label">Создано мной</span>
          <template v-if="agreement.deadline">
            <span class="concord-card__status-label">до</span>
            <span class="concord-card__status-date">{{ agreement.deadline }}</span>
          </template>
        </span>
        <span v-else class="concord-card__status-pill concord-card__status-pill--waiting">
          <span class="concord-card__status-label">Ждёт решения до:</span>
          <span class="concord-card__status-date">{{ agreement.deadline || '—' }}</span>
        </span>
      </div>

      <span
        v-if="showDaysLabel"
        :class="['concord-card__days', `concord-card__days--${daysLabelTone}`]"
      >
        ({{ daysLabelText }})
      </span>
    </header>

    <h2 class="concord-card__title">{{ agreement.title }}</h2>

    <div class="concord-card__info">
      <div class="concord-card__author">
        <span class="concord-card__author-avatar" aria-hidden="true">{{ authorInitials }}</span>
        <span class="concord-card__author-name">{{ agreement.author.name }}</span>
      </div>
      <div class="concord-card__participants" aria-label="Участники согласования">
        <span class="concord-card__participants-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none">
            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v1.5h16V18c0-2.66-5.33-4-8-4z" fill="currentColor"/>
          </svg>
        </span>
        <span class="concord-card__participants-label">{{ participantsLabel }}</span>
      </div>
    </div>

    <div class="concord-card__footer" @click.stop>
      <button
        type="button"
        class="concord-card__action concord-card__action--star"
        :class="{
          'concord-card__action--active': agreement.isFavorite,
          'concord-card__action--favorite-motion': favoriteAnimating,
        }"
        aria-label="Избранное"
        @click="onToggleFavorite"
      >
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" aria-hidden="true">
          <path
            :fill="agreement.isFavorite ? 'currentColor' : 'none'"
            d="M12 17.3 6.2 21l1.6-6.7L2 9.3l6.9-.6L12 2l3.1 6.7 6.9.6-5.8 4.9 1.6 6.7z"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linejoin="round"
          />
        </svg>
      </button>

      <div class="concord-card__progress" aria-hidden="true">
        <div class="concord-card__progress-track">
          <div class="concord-card__progress-fill" :style="{ width: `${displayedProgress}%` }" />
        </div>
      </div>

      <span class="concord-card__count" aria-label="Прогресс голосования">
        <span class="concord-card__count-voted">{{ displayedVoted }}</span><span class="concord-card__count-sep"> из </span><span class="concord-card__count-total">{{ participantsCount }}</span>
      </span>
    </div>
  </article>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import {
  getAgreementDaysRemaining,
  getAgreementMetrics,
  getPersonInitials,
  pluralizeDays,
  pluralizeParticipants,
} from './agreement-card-utils.js'

const props = defineProps({
  agreement: { type: Object, required: true },
  isNew: { type: Boolean, default: false },
})

const emit = defineEmits(['open', 'toggle-favorite'])

const displayedProgress = ref(0)
const displayedVoted = ref(0)
const favoriteAnimating = ref(false)
let progressTimer = null
let favoriteTimer = null

const metrics = computed(() => getAgreementMetrics(props.agreement))
const participantsCount = computed(() => metrics.value.total)
const participantsLabel = computed(() => pluralizeParticipants(participantsCount.value))
const authorInitials = computed(() => getPersonInitials(props.agreement.author?.name))

const isDraft = computed(() => props.agreement.status === 'draft')
const isFullyApproved = computed(() => ['completed', 'approved'].includes(props.agreement.status))
const isApprovedHeader = computed(() => isFullyApproved.value)
const approvedHeaderText = computed(() => (props.agreement.isOwner ? 'Согласовано' : 'Согласовано мной'))

const daysRemaining = computed(() => getAgreementDaysRemaining(props.agreement.deadlineRaw))
const isDeadlineOverdue = computed(() => daysRemaining.value !== null && daysRemaining.value < 0)

const showDaysLabel = computed(() => !isDraft.value && !isApprovedHeader.value && Boolean(props.agreement.deadlineRaw))
const daysLabelText = computed(() => (daysRemaining.value !== null ? pluralizeDays(daysRemaining.value) : '—'))
const daysLabelTone = computed(() => {
  if (isDeadlineOverdue.value) return 'overdue'
  if (daysRemaining.value !== null && daysRemaining.value >= 0 && daysRemaining.value <= 5) return 'soon'
  return 'ok'
})

const progressTone = computed(() => {
  if (isDraft.value) return 'draft'
  if (isFullyApproved.value) return 'done'
  if (props.agreement.isOwner) return 'owner'
  return 'participant'
})

const cardModifiers = computed(() => [
  `concord-card--role-${props.agreement.isOwner ? 'owner' : 'participant'}`,
  `concord-card--progress-${progressTone.value}`,
])

function progressFor() {
  const { total, voted } = metrics.value
  if (!total) return 0
  if (isFullyApproved.value) return 100
  return Math.min(100, Math.round((voted / total) * 100))
}

function clearProgressTimer() {
  window.clearTimeout(progressTimer)
  progressTimer = null
}

watch(
  () => [props.agreement.status, JSON.stringify(props.agreement.sections || [])],
  (_next, _previous, onCleanup) => {
    const nextProgress = progressFor()
    const { voted } = metrics.value
    if (_previous === undefined) {
      displayedProgress.value = nextProgress
      displayedVoted.value = voted
      return
    }
    clearProgressTimer()
    displayedProgress.value = nextProgress
    progressTimer = window.setTimeout(() => {
      displayedVoted.value = voted
      progressTimer = null
    }, 360)
    onCleanup(clearProgressTimer)
  },
  { immediate: true },
)

function onToggleFavorite() {
  window.clearTimeout(favoriteTimer)
  favoriteAnimating.value = false
  requestAnimationFrame(() => {
    favoriteAnimating.value = true
    favoriteTimer = window.setTimeout(() => {
      favoriteAnimating.value = false
      favoriteTimer = null
    }, 300)
  })
  emit('toggle-favorite', props.agreement.id)
}

onBeforeUnmount(() => {
  clearProgressTimer()
  window.clearTimeout(favoriteTimer)
})
</script>
