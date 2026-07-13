<template>
  <Teleport to="body">
    <template v-if="open">
      <div class="concord-sheet-backdrop" @click="$emit('close')" />
      <div
        class="concord-filter-modal"
        :class="{ 'concord-filter-modal--tall': step === 'created' || step === 'participant' || step === 'status' }"
        role="dialog"
        aria-label="Добавить фильтр"
      >
        <div class="concord-filter-modal__header">
          <button
            v-if="step !== 'categories'"
            type="button"
            class="concord-icon-btn"
            aria-label="Назад"
            @click="$emit('back')"
          >
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
          <h2 class="concord-filter-modal__title">{{ modalTitle }}</h2>
        </div>

        <div v-if="step === 'categories'" class="concord-filter-modal__body">
          <div class="concord-filter-modal__search">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true">
              <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.6"/>
              <path d="M16 16l5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
            <input type="search" placeholder="Найти..." class="concord-filter-modal__search-input">
          </div>

          <button
            v-for="category in categories"
            :key="category.id"
            type="button"
            class="concord-filter-modal__item"
            @click="$emit('select-category', category)"
          >
            <span class="concord-filter-modal__item-left">
              <FilterChipIcon :category="category.id" />
              <span>{{ category.label }}</span>
            </span>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true">
              <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
        </div>

        <div v-else-if="step === 'urgency'" class="concord-filter-modal__body">
          <label
            v-for="option in urgencyOptions"
            :key="option.id"
            class="concord-filter-modal__radio"
          >
            <input
              type="radio"
              name="urgency"
              :value="option.id"
              :checked="selectedUrgency === option.id"
              @change="selectedUrgency = option.id"
            >
            <span class="concord-filter-modal__radio-mark" />
            <span>{{ option.label }}</span>
          </label>

          <button type="button" class="concord-filter-modal__submit" @click="submitUrgency">
            Добавить
          </button>
        </div>

        <div v-else-if="step === 'created'" class="concord-filter-modal__body concord-filter-modal__body--scroll">
          <div
            v-for="option in createdOptions"
            :key="option.id"
            class="concord-filter-modal__option-group"
          >
            <label class="concord-filter-modal__radio">
              <input
                type="radio"
                name="created"
                :value="option.id"
                :checked="selectedCreated === option.id"
                @change="selectedCreated = option.id"
              >
              <span class="concord-filter-modal__radio-mark" />
              <span>{{ option.label }}</span>
            </label>

            <div
              v-if="option.needsDate && selectedCreated === option.id"
              class="concord-filter-modal__date-field"
            >
              <input
                v-model="createdDate"
                type="text"
                class="concord-filter-modal__date-input"
                placeholder="дд.мм.гггг"
                inputmode="numeric"
              >
              <label class="concord-filter-modal__date-calendar" aria-label="Выбрать дату">
                <input
                  type="date"
                  class="concord-filter-modal__date-native"
                  :value="nativeDateValue"
                  @change="onNativeDateChange"
                >
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
                  <rect x="4" y="5" width="16" height="15" rx="2" stroke="currentColor" stroke-width="1.6"/>
                  <path d="M8 3v4M16 3v4M4 10h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
              </label>
            </div>
          </div>

          <div class="concord-filter-modal__footer-actions">
            <button type="button" class="concord-filter-modal__submit-text" @click="submitCreated">
              ДОБАВИТЬ
            </button>
          </div>
        </div>

        <div v-else-if="step === 'participant'" class="concord-filter-modal__body concord-filter-modal__body--scroll">
          <label class="concord-filter-modal__radio concord-filter-modal__radio--static">
            <input type="radio" name="participant-match" value="is" checked>
            <span class="concord-filter-modal__radio-mark" />
            <span>это</span>
          </label>

          <div class="concord-filter-modal__combobox">
            <input
              v-model="participantQuery"
              type="search"
              class="concord-filter-modal__combobox-input"
              placeholder="Имя или email"
            >
            <svg class="concord-filter-modal__combobox-caret" viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true">
              <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>

          <div class="concord-filter-modal__users">
            <button
              v-for="user in filteredParticipants"
              :key="user.id"
              type="button"
              class="concord-filter-modal__user"
              :class="{ 'concord-filter-modal__user--selected': selectedParticipantId === user.id }"
              @click="selectedParticipantId = user.id"
            >
              <span class="concord-filter-modal__user-avatar">{{ user.initial }}</span>
              <span class="concord-filter-modal__user-info">
                <span class="concord-filter-modal__user-name">{{ user.name }}</span>
                <span class="concord-filter-modal__user-email">{{ user.email }}</span>
              </span>
            </button>

            <p v-if="!filteredParticipants.length" class="concord-filter-modal__empty">
              Ничего не найдено
            </p>
          </div>

          <div class="concord-filter-modal__footer-actions">
            <button
              type="button"
              class="concord-filter-modal__submit-text"
              :disabled="!selectedParticipantId"
              @click="submitParticipant"
            >
              ДОБАВИТЬ
            </button>
          </div>
        </div>

        <div v-else-if="step === 'status'" class="concord-filter-modal__body">
          <div class="concord-filter-modal__option-group">
            <label class="concord-filter-modal__radio">
              <input
                type="radio"
                name="status-match"
                value="is"
                :checked="statusMatch === 'is'"
                @change="statusMatch = 'is'"
              >
              <span class="concord-filter-modal__radio-mark" />
              <span>это</span>
            </label>

            <div v-if="statusMatch === 'is'" class="concord-filter-modal__select-wrap">
              <label class="concord-filter-modal__select">
                <span class="concord-filter-modal__select-label">Выберите статус</span>
                <select v-model="selectedStatusId" class="concord-filter-modal__select-input">
                  <option
                    v-for="status in statusOptions"
                    :key="status.id"
                    :value="status.id"
                  >
                    {{ status.label }}
                  </option>
                </select>
                <svg class="concord-filter-modal__select-caret" viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true">
                  <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </label>
            </div>
          </div>

          <div class="concord-filter-modal__option-group">
            <label class="concord-filter-modal__radio">
              <input
                type="radio"
                name="status-match"
                value="is_not"
                :checked="statusMatch === 'is_not'"
                @change="statusMatch = 'is_not'"
              >
              <span class="concord-filter-modal__radio-mark" />
              <span>не</span>
            </label>

            <div v-if="statusMatch === 'is_not'" class="concord-filter-modal__select-wrap">
              <label class="concord-filter-modal__select">
                <span class="concord-filter-modal__select-label">Выберите статус</span>
                <select v-model="selectedStatusId" class="concord-filter-modal__select-input">
                  <option
                    v-for="status in statusOptions"
                    :key="status.id"
                    :value="status.id"
                  >
                    {{ status.label }}
                  </option>
                </select>
                <svg class="concord-filter-modal__select-caret" viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true">
                  <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </label>
            </div>
          </div>

          <div class="concord-filter-modal__footer-actions">
            <button type="button" class="concord-filter-modal__submit-text" @click="submitStatus">
              ДОБАВИТЬ
            </button>
          </div>
        </div>
      </div>
    </template>
  </Teleport>
</template>

<script>
import { computed, ref, watch } from 'vue'
import FilterChipIcon from './FilterChipIcon.vue'
import {
  CREATED_OPTIONS,
  FILTER_CATEGORIES,
  FILTER_PARTICIPANTS,
  FILTER_STATUS_OPTIONS,
  URGENCY_OPTIONS,
  formatDateRu,
} from './mock-agreements.js'

function parseDateRu(value) {
  const match = /^(\d{2})\.(\d{2})\.(\d{4})$/.exec(value.trim())
  if (!match) {
    return null
  }
  const [, day, month, year] = match
  return `${year}-${month}-${day}`
}

function formatNativeToRu(value) {
  if (!value) {
    return ''
  }
  const [year, month, day] = value.split('-')
  return `${day}.${month}.${year}`
}

export default {
  name: 'FilterPickerModal',
  components: { FilterChipIcon },
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    step: {
      type: String,
      default: 'categories',
    },
    selectedCategory: {
      type: Object,
      default: null,
    },
  },
  emits: ['close', 'select-category', 'add-urgency', 'add-created', 'add-participant', 'add-status', 'back'],
  setup(props, { emit }) {
    const selectedUrgency = ref('set')
    const selectedCreated = ref('last_7d')
    const createdDate = ref(formatDateRu())
    const participantQuery = ref('')
    const selectedParticipantId = ref(FILTER_PARTICIPANTS[0]?.id || '')
    const statusMatch = ref('is')
    const selectedStatusId = ref('planned')
    const categories = FILTER_CATEGORIES
    const urgencyOptions = URGENCY_OPTIONS
    const createdOptions = CREATED_OPTIONS
    const participants = FILTER_PARTICIPANTS
    const statusOptions = FILTER_STATUS_OPTIONS

    const modalTitle = computed(() => {
      if (props.step === 'urgency') {
        return 'Срочность'
      }
      if (props.step === 'created') {
        return 'Создана'
      }
      if (props.step === 'participant') {
        return 'Участник'
      }
      if (props.step === 'status') {
        return 'Статус'
      }
      return 'Добавить фильтр'
    })

    const nativeDateValue = computed(() => parseDateRu(createdDate.value) || '')

    const filteredParticipants = computed(() => {
      const query = participantQuery.value.trim().toLowerCase()
      if (!query) {
        return participants
      }
      return participants.filter(
        (user) =>
          user.name.toLowerCase().includes(query) ||
          user.email.toLowerCase().includes(query)
      )
    })

    function resetState() {
      selectedUrgency.value = 'set'
      selectedCreated.value = 'last_7d'
      createdDate.value = formatDateRu()
      participantQuery.value = ''
      selectedParticipantId.value = participants[0]?.id || ''
      statusMatch.value = 'is'
      selectedStatusId.value = 'planned'
    }

    watch(
      () => props.open,
      (open) => {
        if (open) {
          resetState()
        }
      }
    )

    function submitUrgency() {
      const option = urgencyOptions.find((item) => item.id === selectedUrgency.value)
      if (option) {
        emit('add-urgency', option)
      }
    }

    function submitCreated() {
      const option = createdOptions.find((item) => item.id === selectedCreated.value)
      if (!option) {
        return
      }
      if (option.needsDate && !createdDate.value.trim()) {
        return
      }
      emit('add-created', {
        option,
        date: option.needsDate ? createdDate.value.trim() : null,
      })
    }

    function onNativeDateChange(event) {
      createdDate.value = formatNativeToRu(event.target.value)
    }

    function submitParticipant() {
      const participant = participants.find((user) => user.id === selectedParticipantId.value)
      if (!participant) {
        return
      }
      emit('add-participant', {
        participant,
        match: 'is',
      })
    }

    function submitStatus() {
      const status = statusOptions.find((item) => item.id === selectedStatusId.value)
      if (!status) {
        return
      }
      emit('add-status', {
        status,
        match: statusMatch.value,
      })
    }

    return {
      categories,
      urgencyOptions,
      createdOptions,
      participants,
      statusOptions,
      filteredParticipants,
      selectedUrgency,
      selectedCreated,
      createdDate,
      participantQuery,
      selectedParticipantId,
      statusMatch,
      selectedStatusId,
      nativeDateValue,
      modalTitle,
      submitUrgency,
      submitCreated,
      submitParticipant,
      submitStatus,
      onNativeDateChange,
    }
  },
}
</script>
