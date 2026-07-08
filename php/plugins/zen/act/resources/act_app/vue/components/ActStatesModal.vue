<template>
  <EditFieldModal
    :open="open"
    title="Версии акта"
    title-id="act-states-modal-title"
    @close="$emit('close')"
  >
    <AsyncRegion :loading="loading" variant="modal" pattern="list">
      <p v-if="error" class="tg-error">{{ error }}</p>
      <p v-else-if="snapshots.length === 0" class="tg-empty tg-empty--compact">Пока нет сохранённых версий.</p>
      <template v-else>
        <p
          v-if="mergeHintVisible"
          class="tg-states-hint"
          role="status"
          aria-live="polite"
        >
          Укажите состояние для слияния
        </p>
        <ul class="tg-states-list">
          <li
            v-for="item in snapshots"
            :key="item.snapshot_key"
            class="tg-states-list__item"
            :class="{ 'tg-states-list__item--merge-selected': isMergeSelected(item) }"
          >
            <div class="tg-states-list__meta">
              <span class="tg-states-list__index">#{{ item.display_index }}</span>
              <span class="tg-states-list__date">{{ formatTimestamp(item.timestamp) }}</span>
            </div>
            <div class="tg-states-list__actions">
              <button
                type="button"
                class="tg-states-list__merge"
                :class="{ 'tg-states-list__merge--active': isMergeSelected(item) }"
                :aria-label="mergeButtonLabel(item)"
                :aria-pressed="isMergeSelected(item)"
                :disabled="merging || restoringKey !== ''"
                @click="onMergeClick(item)"
              >
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M8 7h12M8 12h8M8 17h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                  <path d="M4 7V5a1 1 0 011-1h2M4 12v-2a1 1 0 011-1h2M4 17v-2a1 1 0 011-1h2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
              </button>
              <button
                type="button"
                class="tg-states-list__restore"
                :aria-label="`Откатить к версии ${formatTimestamp(item.timestamp)}`"
                :disabled="restoringKey === item.snapshot_key || merging"
                @click="$emit('restore', item)"
              >
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M3 12a9 9 0 0115.5-6.7L21 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                  <path d="M21 3v5h-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                  <path d="M21 12a9 9 0 01-15.5 6.7L3 16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                  <path d="M3 21v-5h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
              </button>
            </div>
          </li>
        </ul>
      </template>
    </AsyncRegion>
  </EditFieldModal>

  <EditFieldModal
    :open="mergeConfirmOpen"
    title="Слияние состояний"
    title-id="act-states-merge-confirm-title"
    @close="closeMergeConfirm"
  >
    <div v-if="mergeRange" class="tg-states-merge-confirm">
      <p class="tg-states-merge-confirm__lead">
        {{ mergeRange.count }} {{ mergeCountLabel }} объединятся в одну:
      </p>
      <ul class="tg-states-merge-confirm__list">
        <li v-for="item in mergeRange.items" :key="item.snapshot_key">
          <span class="tg-states-list__index">#{{ item.display_index }}</span>
          <span>{{ formatTimestamp(item.timestamp) }}</span>
        </li>
      </ul>
      <div class="tg-states-merge-confirm__actions">
        <button
          type="button"
          class="tg-states-merge-confirm__cancel"
          :disabled="merging"
          @click="closeMergeConfirm"
        >
          Отмена
        </button>
        <button
          type="button"
          class="tg-states-merge-confirm__submit"
          :disabled="merging"
          @click="confirmMerge"
        >
          Слить состояния
        </button>
      </div>
    </div>
  </EditFieldModal>
</template>

<script>
import EditFieldModal from './EditFieldModal.vue'
import AsyncRegion from './AsyncRegion.vue'
import { formatUtcCompactTimestamp } from '../timezone'

export default {
  name: 'ActStatesModal',
  components: { EditFieldModal, AsyncRegion },
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    snapshots: {
      type: Array,
      default: () => [],
    },
    loading: {
      type: Boolean,
      default: false,
    },
    error: {
      type: String,
      default: '',
    },
    restoringKey: {
      type: String,
      default: '',
    },
    merging: {
      type: Boolean,
      default: false,
    },
    userTimezone: {
      type: String,
      default: '',
    },
  },
  emits: ['close', 'restore', 'merge'],
  data() {
    return {
      mergeAnchorIndex: null,
      mergeHintVisible: false,
      mergeConfirmOpen: false,
      mergeRange: null,
      mergeHintTimer: null,
    }
  },
  computed: {
    mergeCountLabel() {
      const count = this.mergeRange?.count ?? 0
      const mod10 = count % 10
      const mod100 = count % 100
      if (mod10 === 1 && mod100 !== 11) {
        return 'версия'
      }
      if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) {
        return 'версии'
      }
      return 'версий'
    },
  },
  watch: {
    open(value) {
      if (value) {
        this.resetMergeFlow()
      }
    },
    merging(value, oldValue) {
      if (oldValue && !value) {
        this.resetMergeFlow()
      }
    },
  },
  beforeUnmount() {
    this.clearMergeHintTimer()
  },
  methods: {
    formatTimestamp(timestamp) {
      return formatUtcCompactTimestamp(timestamp, this.userTimezone)
    },
    isMergeSelected(item) {
      return this.mergeAnchorIndex === item.display_index
    },
    mergeButtonLabel(item) {
      if (this.isMergeSelected(item)) {
        return `Снять выбор версии ${this.formatTimestamp(item.timestamp)} для слияния`
      }
      if (this.mergeAnchorIndex === null) {
        return `Выбрать версию ${this.formatTimestamp(item.timestamp)} для слияния`
      }
      return `Слить с версией ${this.formatTimestamp(item.timestamp)}`
    },
    onMergeClick(item) {
      if (this.merging || this.restoringKey) {
        return
      }

      const index = item.display_index

      if (this.mergeAnchorIndex === null) {
        this.mergeAnchorIndex = index
        this.showMergeHint()
        return
      }

      if (this.mergeAnchorIndex === index) {
        this.resetMergeFlow()
        return
      }

      const from = Math.min(this.mergeAnchorIndex, index)
      const to = Math.max(this.mergeAnchorIndex, index)
      const items = this.snapshots
        .filter((row) => row.display_index >= from && row.display_index <= to)
        .sort((a, b) => a.display_index - b.display_index)

      this.clearMergeHintTimer()
      this.mergeHintVisible = false
      this.mergeRange = {
        from_index: from,
        to_index: to,
        count: items.length,
        items,
      }
      this.mergeConfirmOpen = true
    },
    showMergeHint() {
      this.clearMergeHintTimer()
      this.mergeHintVisible = true
      this.mergeHintTimer = window.setTimeout(() => {
        this.mergeHintVisible = false
        this.mergeHintTimer = null
      }, 3000)
    },
    clearMergeHintTimer() {
      if (this.mergeHintTimer !== null) {
        window.clearTimeout(this.mergeHintTimer)
        this.mergeHintTimer = null
      }
    },
    closeMergeConfirm() {
      if (this.merging) {
        return
      }
      this.mergeConfirmOpen = false
      this.mergeRange = null
      this.mergeAnchorIndex = null
    },
    confirmMerge() {
      if (!this.mergeRange || this.merging) {
        return
      }
      this.$emit('merge', {
        from_index: this.mergeRange.from_index,
        to_index: this.mergeRange.to_index,
      })
    },
    resetMergeFlow() {
      this.clearMergeHintTimer()
      this.mergeAnchorIndex = null
      this.mergeHintVisible = false
      this.mergeConfirmOpen = false
      this.mergeRange = null
    },
  },
}
</script>
