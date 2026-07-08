<template>
  <section class="tg-act-panel">
    <div class="tg-act-panel__stats">
      <div class="tg-act-panel__stat" aria-label="Блоки">
        <svg class="tg-act-panel__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <rect x="3" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/>
          <rect x="14" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/>
          <rect x="3" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/>
          <rect x="14" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.8"/>
        </svg>
        <span class="tg-act-panel__value">{{ blocksCount }}</span>
      </div>

      <button
        v-if="isMine && blocksCount > 1"
        type="button"
        :class="[
          'tg-act-panel__stat',
          'tg-act-panel__stat--button',
          'tg-act-panel__stat--toggle',
          { 'tg-act-panel__stat--toggle-on': reorderEnabled },
        ]"
        :aria-label="reorderEnabled ? 'Отключить перемещение блоков' : 'Включить перемещение блоков'"
        :title="reorderEnabled ? 'Отключить перемещение блоков' : 'Включить перемещение блоков'"
        :aria-pressed="reorderEnabled"
        @click="$emit('toggle-reorder')"
      >
        <svg class="tg-act-panel__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M8 9l4-4 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M8 15l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>

      <button
        v-if="isMine"
        type="button"
        class="tg-act-panel__stat tg-act-panel__stat--button"
        aria-label="Доступ"
        title="Настройки доступа"
        @click="$emit('open-access')"
      >
        <svg class="tg-act-panel__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M12 3l7 4v5c0 4.2-2.9 8-7 9-4.1-1-7-4.8-7-9V7l7-4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
        </svg>
      </button>

      <button
        v-if="isMine"
        type="button"
        class="tg-act-panel__stat tg-act-panel__stat--button"
        aria-label="Версии"
        @click="$emit('open-states')"
      >
        <svg class="tg-act-panel__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M12 8v4l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
        </svg>
        <span class="tg-act-panel__value">{{ statesCount }}</span>
      </button>

      <a
        v-if="isMine && previewUrl"
        :href="previewUrl"
        class="tg-act-panel__stat tg-act-panel__stat--button tg-act-panel__stat--link"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="Предпросмотр"
        title="Предпросмотр"
      >
        <svg class="tg-act-panel__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path
            d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
          <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
        </svg>
      </a>

      <button
        v-if="showTags"
        type="button"
        class="tg-act-panel__stat tg-act-panel__stat--button"
        aria-label="Теги"
        title="Теги"
        @click="$emit('open-tags')"
      >
        <svg class="tg-act-panel__icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path
            d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linejoin="round"
          />
          <circle cx="7" cy="7" r="1.5" fill="currentColor" />
        </svg>
        <span class="tg-act-panel__value">{{ tagsCount }}</span>
      </button>
    </div>

    <router-link
      v-if="!isMine && ownerLogin"
      class="tg-act-panel__owner"
      :to="{ name: 'profile', params: { login: ownerLogin } }"
    >
      @{{ ownerLogin }}
    </router-link>
  </section>
</template>

<script>
export default {
  name: 'ActControlPanel',
  props: {
    blocksCount: {
      type: Number,
      default: 0,
    },
    statesCount: {
      type: Number,
      default: 0,
    },
    isMine: {
      type: Boolean,
      default: false,
    },
    ownerLogin: {
      type: String,
      default: '',
    },
    previewUrl: {
      type: String,
      default: '',
    },
    showTags: {
      type: Boolean,
      default: false,
    },
    tagsCount: {
      type: Number,
      default: 0,
    },
    reorderEnabled: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['open-states', 'open-access', 'open-tags', 'toggle-reorder'],
}
</script>
