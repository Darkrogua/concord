<template>
  <article :class="['concord-card', { 'concord-card--expanded': expanded }]">
    <button type="button" class="concord-card__head" @click="onHeadClick">
      <div class="concord-card__row">
        <h2 class="concord-card__title">{{ agreement.title }}</h2>
        <time class="concord-card__date">{{ agreement.date }}</time>
      </div>
      <div class="concord-card__footer">
        <ConcordStatusBadge :status="agreement.status" />
        <div class="concord-card__actions" @click.stop>
          <button
            type="button"
            class="concord-card__action"
            :class="{ 'concord-card__action--active': agreement.isFavorite }"
            aria-label="Избранное"
            @click="$emit('toggle-favorite', agreement.id)"
          >
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
              <path
                :fill="agreement.isFavorite ? 'currentColor' : 'none'"
                d="M12 17.3 6.2 21l1.6-6.7L2 9.3l6.9-.6L12 2l3.1 6.7 6.9.6-5.8 4.9 1.6 6.7z"
                stroke="currentColor"
                stroke-width="1.35"
                stroke-linejoin="round"
              />
            </svg>
          </button>
          <button
            type="button"
            class="concord-card__action"
            aria-label="Дублировать"
            @click="$emit('duplicate', agreement.id)"
          >
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
              <rect x="8" y="8" width="11" height="13" rx="2" stroke="currentColor" stroke-width="1.35"/>
              <path d="M6 16H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" stroke="currentColor" stroke-width="1.35"/>
            </svg>
          </button>
          <button
            v-if="agreement.isOwner"
            type="button"
            class="concord-card__action"
            aria-label="Редактировать"
            @click="$emit('edit', agreement.id)"
          >
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
              <path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3z" stroke="currentColor" stroke-width="1.35" stroke-linejoin="round"/>
            </svg>
          </button>
        </div>
      </div>
      <div v-if="!expanded" class="concord-card__divider" aria-hidden="true" />
    </button>
    <div v-if="expanded" class="concord-card__sections" @click="onSectionsClick">
      <SectionProgressBar
        v-for="section in agreement.sections"
        :key="section.id"
        :section="section"
      />
    </div>
    <div v-if="expanded" class="concord-card__divider concord-card__divider--bottom" aria-hidden="true" />
  </article>
</template>

<script>
import ConcordStatusBadge from './ConcordStatusBadge.vue'
import SectionProgressBar from './SectionProgressBar.vue'

export default {
  name: 'AgreementCard',
  components: { ConcordStatusBadge, SectionProgressBar },
  props: {
    agreement: {
      type: Object,
      required: true,
    },
    expanded: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['toggle-expand', 'open', 'toggle-favorite', 'duplicate', 'edit'],
  methods: {
    onHeadClick() {
      this.$emit('toggle-expand', this.agreement.id)
    },
    onSectionsClick() {
      this.$emit('open', this.agreement.id)
    },
  },
}
</script>
