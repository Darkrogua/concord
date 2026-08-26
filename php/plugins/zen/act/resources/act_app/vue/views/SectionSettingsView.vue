<template>
  <div class="concord-page concord-page--section-settings">
    <ConcordPageHeader title="Настройки раздела" show-back @back="save">
      <template #right>
        <ConcordGroupHeaderActions :show-count="false" @save="save" />
      </template>
    </ConcordPageHeader>

    <main class="concord-section-settings">
      <section class="concord-section-settings__accordion">
        <button
          type="button"
          class="concord-section-settings__accordion-head"
          :aria-expanded="expanded.name"
          @click="toggle('name')"
        >
          <span>Наименование</span>
          <svg
            class="concord-section-settings__chevron"
            :class="{ 'concord-section-settings__chevron--open': expanded.name }"
            viewBox="0 0 24 24"
            width="20"
            height="20"
            fill="none"
            aria-hidden="true"
          >
            <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
        <div v-if="expanded.name" class="concord-section-settings__accordion-body">
          <input
            v-model="form.title"
            type="text"
            class="concord-section-settings__input"
            placeholder="Введите название"
          >
        </div>
      </section>

      <section class="concord-section-settings__accordion">
        <button
          type="button"
          class="concord-section-settings__accordion-head"
          :aria-expanded="expanded.dates"
          @click="toggle('dates')"
        >
          <span>Сроки раздела</span>
          <svg
            class="concord-section-settings__chevron"
            :class="{ 'concord-section-settings__chevron--open': expanded.dates }"
            viewBox="0 0 24 24"
            width="20"
            height="20"
            fill="none"
            aria-hidden="true"
          >
            <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
        <div v-if="expanded.dates" class="concord-section-settings__accordion-body">
          <p v-if="agreementPeriodHint" class="concord-section-settings__dates-hint">
            {{ agreementPeriodHint }}
          </p>
          <div class="concord-section-settings__dates-grid">
            <label class="concord-section-settings__date-field">
              <span>Начало</span>
              <div class="concord-section-settings__date-controls">
                <input
                  v-model="form.startDate"
                  type="date"
                  :min="sectionStartDateMin"
                  :max="sectionStartDateMax"
                  aria-label="Дата начала раздела"
                >
                <input
                  v-model="form.startTime"
                  type="time"
                  :min="sectionStartTimeMin"
                  :max="sectionStartTimeMax"
                  aria-label="Время начала раздела"
                >
              </div>
            </label>
            <label class="concord-section-settings__date-field">
              <span>Окончание</span>
              <div class="concord-section-settings__date-controls">
                <input
                  v-model="form.endDate"
                  type="date"
                  :min="sectionEndDateMin"
                  :max="sectionEndDateMax"
                  aria-label="Дата окончания раздела"
                >
                <input
                  v-model="form.endTime"
                  type="time"
                  :min="sectionEndTimeMin"
                  :max="sectionEndTimeMax"
                  aria-label="Время окончания раздела"
                >
              </div>
            </label>
          </div>
          <p v-if="hasInvalidDateRange" class="concord-section-settings__dates-error">
            Период раздела должен находиться в рамках периода согласования.
          </p>
        </div>
      </section>

      <section class="concord-section-settings__accordion">
        <button
          type="button"
          class="concord-section-settings__accordion-head"
          :aria-expanded="expanded.participants"
          @click="toggle('participants')"
        >
          <span>Участники</span>
          <span class="concord-section-settings__accordion-trail">
            <span v-if="participantsBadgeCount" class="concord-section-settings__badge">
              {{ participantsBadgeCount }}
            </span>
            <svg
              class="concord-section-settings__chevron"
              :class="{ 'concord-section-settings__chevron--open': expanded.participants }"
              viewBox="0 0 24 24"
              width="20"
              height="20"
              fill="none"
              aria-hidden="true"
            >
              <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </button>
        <div v-if="expanded.participants" class="concord-section-settings__accordion-body">
          <div class="concord-section-settings__participants">
            <ul class="concord-section-settings__participant-list">
              <li
                v-for="group in selectedGroups"
                :key="`group-${group.id}`"
                class="concord-section-settings__participant concord-section-settings__participant--group"
              >
                <span class="concord-notifications__group-icon-wrap" aria-hidden="true">
                  <span class="concord-notifications__group-icon">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none">
                      <circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="1.6"/>
                      <path d="M6 19.5c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                  </span>
                  <span class="concord-notifications__group-badge">{{ groupMemberCount(group) }}</span>
                </span>
                <span class="concord-section-settings__participant-name">{{ group.title }}</span>
                <button
                  type="button"
                  class="concord-section-settings__participant-remove"
                  aria-label="Удалить группу"
                  @click="removeGroup(group.id)"
                >
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true">
                    <path d="M5 7h14M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7m2 0v11.5A1.5 1.5 0 0 1 15.5 20h-7A1.5 1.5 0 0 1 7 18.5V7h10Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </button>
              </li>
              <li
                v-for="contact in selectedContacts"
                :key="contact.id"
                class="concord-section-settings__participant"
              >
                <span class="concord-section-settings__participant-avatar" aria-hidden="true">
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none">
                    <circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M5.5 19.5c.7-3 2.8-4.5 6.5-4.5s5.8 1.5 6.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                  </svg>
                </span>
                <span class="concord-section-settings__participant-name">{{ contact.shortName }}</span>
                <button
                  type="button"
                  class="concord-section-settings__participant-star"
                  :class="{ 'concord-section-settings__participant-star--active': form.leaderId === contact.id }"
                  :aria-label="form.leaderId === contact.id ? 'Убрать ответственного' : 'Назначить ответственным'"
                  @click="toggleLeader(contact.id)"
                >
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true">
                    <path
                      d="M12 3.8 14.1 9l5.4.4-4.1 3.2 1.5 5.2L12 15.9 7.1 17.8l1.5-5.2-4.1-3.2 5.4-.4L12 3.8Z"
                      :fill="form.leaderId === contact.id ? 'currentColor' : 'none'"
                      stroke="currentColor"
                      stroke-width="1.5"
                      stroke-linejoin="round"
                    />
                  </svg>
                </button>
                <button
                  type="button"
                  class="concord-section-settings__participant-remove"
                  aria-label="Удалить участника"
                  @click="removeParticipant(contact.id)"
                >
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true">
                    <path d="M5 7h14M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7m2 0v11.5A1.5 1.5 0 0 1 15.5 20h-7A1.5 1.5 0 0 1 7 18.5V7h10Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </button>
              </li>
            </ul>

            <div v-if="participantMenuOpen" class="concord-section-settings__participant-menu">
              <button type="button" class="concord-section-settings__menu-btn concord-section-settings__menu-btn--primary" @click="openContactPicker">
                + Добавить
              </button>
              <button type="button" class="concord-section-settings__menu-btn" @click="openGroupPicker">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" aria-hidden="true">
                  <circle cx="8" cy="9" r="2.5" stroke="currentColor" stroke-width="1.4"/>
                  <circle cx="16" cy="9" r="2.5" stroke="currentColor" stroke-width="1.4"/>
                  <path d="M4.5 18c.4-2.2 1.8-3.5 3.5-3.5S11.1 15.8 11.5 18M12.5 18c.4-2.2 1.8-3.5 3.5-3.5S19.1 15.8 19.5 18" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                </svg>
                Выбрать группу
              </button>
            </div>

            <button
              v-else
              type="button"
              class="concord-section-settings__participant-add"
              aria-label="Добавить участника"
              @click="participantMenuOpen = true"
            >
              <span aria-hidden="true">+</span>
            </button>
          </div>
        </div>
      </section>

      <section class="concord-section-settings__accordion">
        <button
          type="button"
          class="concord-section-settings__accordion-head"
          :aria-expanded="expanded.reminder"
          @click="toggle('reminder')"
        >
          <span>Напоминание</span>
          <svg
            class="concord-section-settings__chevron"
            :class="{ 'concord-section-settings__chevron--open': expanded.reminder }"
            viewBox="0 0 24 24"
            width="20"
            height="20"
            fill="none"
            aria-hidden="true"
          >
            <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
        <div v-if="expanded.reminder" class="concord-section-settings__accordion-body">
          <label class="concord-section-settings__toggle-row">
            <span>За 1 день</span>
            <input v-model="form.settings.reminders.day1" type="checkbox" class="concord-toggle">
          </label>
          <label class="concord-section-settings__toggle-row">
            <span>За 2 часа</span>
            <input v-model="form.settings.reminders.hours2" type="checkbox" class="concord-toggle">
          </label>
          <label class="concord-section-settings__toggle-row">
            <span>За 1 час</span>
            <input v-model="form.settings.reminders.hour1" type="checkbox" class="concord-toggle">
          </label>
        </div>
      </section>

      <section class="concord-section-settings__accordion">
        <button
          type="button"
          class="concord-section-settings__accordion-head"
          :aria-expanded="expanded.visibilityDetails"
          @click="toggle('visibilityDetails')"
        >
          <span>Видимость</span>
          <svg
            class="concord-section-settings__chevron"
            :class="{ 'concord-section-settings__chevron--open': expanded.visibilityDetails }"
            viewBox="0 0 24 24"
            width="20"
            height="20"
            fill="none"
            aria-hidden="true"
          >
            <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
        <div v-if="expanded.visibilityDetails" class="concord-section-settings__accordion-body">
          <label class="concord-section-settings__toggle-row">
            <span>Участники видят друг друга</span>
            <input
              :checked="form.settings.participantsSeeEachOther"
              type="checkbox"
              class="concord-toggle"
              @change="form.settings.participantsSeeEachOther = true"
            >
          </label>
          <label class="concord-section-settings__toggle-row">
            <span>Участники не видят друг друга</span>
            <input
              :checked="!form.settings.participantsSeeEachOther"
              type="checkbox"
              class="concord-toggle"
              @change="form.settings.participantsSeeEachOther = false"
            >
          </label>
        </div>
      </section>

      <section class="concord-section-settings__accordion">
        <button
          type="button"
          class="concord-section-settings__accordion-head"
          :aria-expanded="expanded.completion"
          @click="toggle('completion')"
        >
          <span>Условия завершения</span>
          <svg
            class="concord-section-settings__chevron"
            :class="{ 'concord-section-settings__chevron--open': expanded.completion }"
            viewBox="0 0 24 24"
            width="20"
            height="20"
            fill="none"
            aria-hidden="true"
          >
            <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
        <div v-if="expanded.completion" class="concord-section-settings__accordion-body">
          <label class="concord-section-settings__toggle-row">
            <span>Все участники согласовали проект</span>
            <input
              :checked="form.settings.completionCondition === 'all'"
              type="checkbox"
              class="concord-toggle"
              @change="form.settings.completionCondition = 'all'"
            >
          </label>
          <label class="concord-section-settings__toggle-row">
            <span>Проект согласовали большинство участников</span>
            <input
              :checked="form.settings.completionCondition === 'majority'"
              type="checkbox"
              class="concord-toggle"
              @change="form.settings.completionCondition = 'majority'"
            >
          </label>
        </div>
      </section>

      <section class="concord-section-settings__row">
        <span class="concord-section-settings__row-label">Важность</span>
        <label class="concord-section-settings__row-toggle">
          <span>{{ form.settings.isImportant ? 'Да' : 'Нет' }}</span>
          <input v-model="form.settings.isImportant" type="checkbox" class="concord-toggle">
        </label>
      </section>

      <section class="concord-section-settings__results">
        <span class="concord-section-settings__results-label">Показывать результаты:</span>
        <div class="concord-section-settings__results-toggles">
          <span
            class="concord-section-settings__result-option"
            :class="{ 'concord-section-settings__result-option--active': !showResultsAfter }"
          >
            До
          </span>
          <input v-model="showResultsAfter" type="checkbox" class="concord-toggle" aria-label="Показывать результаты после завершения">
          <span
            class="concord-section-settings__result-option"
            :class="{ 'concord-section-settings__result-option--active': showResultsAfter }"
          >
            После
          </span>
        </div>
      </section>

      <button
        type="button"
        class="concord-section-settings__delete"
        @click="deleteConfirmOpen = true"
      >
        Удалить раздел
      </button>
    </main>

    <footer class="concord-section-settings__footer">
      <button
        type="button"
        class="concord-section-settings__done"
        :disabled="hasInvalidDateRange"
        @click="save"
      >
        Готово
      </button>
    </footer>

    <ConcordConfirmSheet
      :open="deleteConfirmOpen"
      title="Удалить раздел?"
      message="Все блоки и настройки этого раздела будут удалены без возможности восстановления."
      confirm-label="Удалить"
      cancel-label="Отмена"
      @confirm="confirmDelete"
      @cancel="deleteConfirmOpen = false"
    />

    <template v-if="contactPickerOpen">
      <div class="concord-sheet-backdrop" @click="closeContactPicker" />
      <div class="concord-sheet concord-section-settings__picker" role="dialog" aria-label="Добавить участников">
        <div class="concord-sheet__handle" aria-hidden="true" />
        <div class="concord-section-settings__picker-head">
          <h2 class="concord-sheet__title concord-section-settings__picker-title">Участники</h2>
          <button
            type="button"
            class="concord-section-settings__select-all"
            :disabled="!filteredContacts.length"
            @click="toggleSelectAllContacts"
          >
            {{ allFilteredContactsSelected ? 'Снять выделение' : 'Выделить всех' }}
          </button>
        </div>
        <ContactCheckboxList
          :contacts="filteredContacts"
          :selected-ids="pickerParticipantIds"
          :search-query="contactSearchQuery"
          @update:selected-ids="pickerParticipantIds = $event"
          @update:search-query="contactSearchQuery = $event"
        />
        <div class="concord-create-sheet__actions">
          <button type="button" class="concord-create-sheet__btn concord-create-sheet__btn--cancel" @click="closeContactPicker">
            Отмена
          </button>
          <button type="button" class="concord-create-sheet__btn concord-create-sheet__btn--save" @click="applyContactPicker">
            Добавить
          </button>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import { computed, defineExpose, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import ContactCheckboxList from '../concord/ContactCheckboxList.vue'
import ConcordConfirmSheet from '../concord/ConcordConfirmSheet.vue'
import ConcordGroupHeaderActions from '../concord/ConcordGroupHeaderActions.vue'
import ConcordPageHeader from '../concord/ConcordPageHeader.vue'
import {
  combineRuDateTime,
  DEFAULT_SECTION_SETTINGS,
  ensureSectionSettings,
  formatIsoDateToRu,
  formatRuDateTimeToFormParts,
} from '../concord/mock-agreements.js'
import {
  collectSelectedGroupMemberIds,
  filterContacts,
  getGroupMembersCount,
  resolveSectionVotersCount,
} from '../concord/mock-groups.js'
import { resetConcordScrollPosition } from '../concord/scroll-top.js'
import { sectionScheduleHasInvalidRange } from '../concord/agreement-form-utils.js'

const AUTOSAVE_MS = 350

function cloneSettings(settings = DEFAULT_SECTION_SETTINGS) {
  return {
    ...DEFAULT_SECTION_SETTINGS,
    ...settings,
    reminders: {
      ...DEFAULT_SECTION_SETTINGS.reminders,
      ...(settings.reminders || {}),
    },
  }
}

function toRuDateTime(date, time) {
  if (!date) {
    return ''
  }
  return combineRuDateTime(formatIsoDateToRu(date), time)
}

export default {
  name: 'SectionSettingsView',
  components: { ContactCheckboxList, ConcordConfirmSheet, ConcordGroupHeaderActions, ConcordPageHeader },
  props: {
    agreement: {
      type: Object,
      default: null,
    },
    section: {
      type: Object,
      required: true,
    },
    contacts: {
      type: Array,
      default: () => [],
    },
    groups: {
      type: Array,
      default: () => [],
    },
  },
  emits: ['back', 'delete', 'save', 'pick-groups'],
  setup(props, { emit }) {
    const expanded = reactive({
      name: false,
      dates: false,
      participants: true,
      reminder: false,
      completion: false,
      visibilityDetails: false,
    })

    const form = ref(createFormFromSection(props.section))
    const participantMenuOpen = ref(false)
    const contactPickerOpen = ref(false)
    const contactSearchQuery = ref('')
    const pickerParticipantIds = ref([])
    const deleteConfirmOpen = ref(false)
    let syncingFromSection = false
    let autosaveTimer = null

    onMounted(() => {
      resetConcordScrollPosition()
      requestAnimationFrame(resetConcordScrollPosition)
    })

    watch(
      () => props.section,
      (section) => {
        syncingFromSection = true
        form.value = createFormFromSection(section)
        syncingFromSection = false
      }
    )

    const selectedGroups = computed(() =>
      props.groups.filter((group) => form.value.groupIds.includes(group.id))
    )

    const coveredByGroupsIds = computed(() =>
      collectSelectedGroupMemberIds(form.value.groupIds, props.groups)
    )

    /** People added individually and not already covered by a selected group. */
    const selectedContacts = computed(() =>
      props.contacts.filter(
        (contact) =>
          form.value.participantIds.includes(contact.id)
          && !coveredByGroupsIds.value.has(contact.id)
      )
    )

    const participantsBadgeCount = computed(() =>
      resolveSectionVotersCount(
        {
          participantIds: form.value.participantIds,
          groupIds: form.value.groupIds,
        },
        props.groups,
        props.contacts
      )
    )

    const filteredContacts = computed(() => {
      const available = props.contacts.filter((contact) => !coveredByGroupsIds.value.has(contact.id))
      return filterContacts(available, contactSearchQuery.value)
    })

    const allFilteredContactsSelected = computed(() => {
      if (!filteredContacts.value.length) {
        return false
      }
      return filteredContacts.value.every((contact) => pickerParticipantIds.value.includes(contact.id))
    })

    const showResultsAfter = computed({
      get() {
        return form.value.settings.showResultsAfter
      },
      set(value) {
        form.value.settings.showResultsAfter = value
        form.value.settings.showResultsBefore = !value
      },
    })

    const agreementPeriod = computed(() => ({
      start: formatRuDateTimeToFormParts(props.agreement?.startDate || ''),
      end: formatRuDateTimeToFormParts(props.agreement?.deadline || ''),
    }))

    const agreementPeriodHint = computed(() => {
      const { start, end } = agreementPeriod.value
      if (!start.date && !end.date) {
        return ''
      }
      const startLabel = props.agreement?.startDate || 'не указано'
      const endLabel = props.agreement?.deadline || 'не указано'
      return `В рамках согласования: ${startLabel} — ${endLabel}`
    })

    const sectionStartDateMin = computed(() => agreementPeriod.value.start.date || '')
    const sectionStartDateMax = computed(() => agreementPeriod.value.end.date || '')
    const sectionEndDateMin = computed(() => agreementPeriod.value.start.date || '')
    const sectionEndDateMax = computed(() => agreementPeriod.value.end.date || '')

    const sectionStartTimeMin = computed(() => (
      form.value.startDate === agreementPeriod.value.start.date
        ? agreementPeriod.value.start.time
        : ''
    ))
    const sectionStartTimeMax = computed(() => (
      form.value.startDate === agreementPeriod.value.end.date
        ? agreementPeriod.value.end.time
        : ''
    ))
    const sectionEndTimeMin = computed(() => (
      form.value.endDate === agreementPeriod.value.start.date
        ? agreementPeriod.value.start.time
        : ''
    ))
    const sectionEndTimeMax = computed(() => (
      form.value.endDate === agreementPeriod.value.end.date
        ? agreementPeriod.value.end.time
        : ''
    ))

    const hasInvalidDateRange = computed(() =>
      sectionScheduleHasInvalidRange(form.value, agreementPeriod.value)
    )

    function createFormFromSection(section) {
      ensureSectionSettings(section)
      const groupIds = [...(section.groupIds || [])]
      const covered = collectSelectedGroupMemberIds(groupIds, props.groups)
      return {
        title: section.title || '',
        participantIds: [...(section.participantIds || [])].filter((id) => !covered.has(id)),
        groupIds,
        leaderId: section.leaderId || null,
        startDate: formatRuDateTimeToFormParts(section.startDate || '').date,
        startTime: formatRuDateTimeToFormParts(section.startDate || '').time,
        endDate: formatRuDateTimeToFormParts(section.deadline || '').date,
        endTime: formatRuDateTimeToFormParts(section.deadline || '').time,
        settings: cloneSettings(section.settings),
      }
    }

    function toggle(key) {
      expanded[key] = !expanded[key]
    }

    function toggleLeader(contactId) {
      form.value.leaderId = form.value.leaderId === contactId ? null : contactId
    }

    function removeParticipant(contactId) {
      form.value.participantIds = form.value.participantIds.filter((id) => id !== contactId)
      if (form.value.leaderId === contactId) {
        form.value.leaderId = null
      }
    }

    function removeGroup(groupId) {
      form.value.groupIds = form.value.groupIds.filter((id) => id !== groupId)
    }

    function groupMemberCount(group) {
      return getGroupMembersCount(group)
    }

    function openContactPicker() {
      participantMenuOpen.value = false
      pickerParticipantIds.value = [...form.value.participantIds]
      contactSearchQuery.value = ''
      contactPickerOpen.value = true
    }

    function closeContactPicker() {
      contactPickerOpen.value = false
    }

    function pruneParticipantsCoveredByGroups(participantIds, groupIds = form.value.groupIds) {
      const covered = collectSelectedGroupMemberIds(groupIds, props.groups)
      return participantIds.filter((id) => !covered.has(id))
    }

    function applyContactPicker() {
      form.value.participantIds = pruneParticipantsCoveredByGroups(pickerParticipantIds.value)
      if (
        form.value.leaderId
        && !form.value.participantIds.includes(form.value.leaderId)
        && !collectSelectedGroupMemberIds(form.value.groupIds, props.groups).has(form.value.leaderId)
      ) {
        form.value.leaderId = null
      }
      closeContactPicker()
    }

    function toggleSelectAllContacts() {
      const filteredIds = filteredContacts.value.map((contact) => contact.id)
      if (!filteredIds.length) {
        return
      }
      if (allFilteredContactsSelected.value) {
        pickerParticipantIds.value = pickerParticipantIds.value.filter((id) => !filteredIds.includes(id))
        return
      }
      pickerParticipantIds.value = [...new Set([...pickerParticipantIds.value, ...filteredIds])]
    }

    function applyGroupSelection(groupIds) {
      form.value.groupIds = [...groupIds]
      form.value.participantIds = pruneParticipantsCoveredByGroups(
        form.value.participantIds,
        form.value.groupIds
      )
      participantMenuOpen.value = false
    }

    function openGroupPicker() {
      participantMenuOpen.value = false
      emit('pick-groups', [...form.value.groupIds])
    }

    function getSavePayload() {
      if (hasInvalidDateRange.value) {
        return null
      }
      const title = form.value.title.trim() || props.section?.title?.trim() || 'Раздел'
      const participantIds = pruneParticipantsCoveredByGroups(form.value.participantIds)
      return {
        title,
        participantIds: [...participantIds].sort(),
        groupIds: [...form.value.groupIds].sort(),
        leaderId: form.value.leaderId || null,
        startDate: toRuDateTime(form.value.startDate, form.value.startTime),
        deadline: toRuDateTime(form.value.endDate, form.value.endTime),
        settings: cloneSettings(form.value.settings),
      }
    }

    function getSectionPayload(section) {
      ensureSectionSettings(section)
      const groupIds = [...(section.groupIds || [])]
      const covered = collectSelectedGroupMemberIds(groupIds, props.groups)
      const participantIds = [...(section.participantIds || [])].filter((id) => !covered.has(id))
      return {
        title: section.title?.trim() || 'Раздел',
        participantIds: participantIds.sort(),
        groupIds: groupIds.sort(),
        leaderId: section.leaderId || null,
        startDate: section.startDate || '',
        deadline: section.deadline || '',
        settings: cloneSettings(section.settings),
      }
    }

    function hasUnsavedChanges() {
      const next = getSavePayload()
      if (!next) {
        return true
      }
      return JSON.stringify(next) !== JSON.stringify(getSectionPayload(props.section))
    }

    function applyChanges() {
      const payload = getSavePayload()
      if (!payload) {
        return false
      }
      form.value.participantIds = payload.participantIds
      emit('save', {
        title: payload.title,
        participantIds: payload.participantIds,
        groupIds: payload.groupIds,
        leaderId: payload.leaderId,
        startDate: payload.startDate,
        deadline: payload.deadline,
        settings: payload.settings,
      })
      return true
    }

    function scheduleAutosave() {
      if (syncingFromSection) {
        return
      }
      clearTimeout(autosaveTimer)
      autosaveTimer = setTimeout(() => {
        autosaveTimer = null
        applyChanges()
      }, AUTOSAVE_MS)
    }

    watch(form, scheduleAutosave, { deep: true })

    onBeforeUnmount(() => {
      clearTimeout(autosaveTimer)
      applyChanges()
    })

    function save() {
      clearTimeout(autosaveTimer)
      autosaveTimer = null
      if (!applyChanges()) {
        return
      }
      emit('back')
    }

    function confirmDelete() {
      deleteConfirmOpen.value = false
      emit('delete', props.section.id)
      emit('back')
    }

    defineExpose({ applyGroupSelection, hasUnsavedChanges, save })

    return {
      expanded,
      form,
      participantMenuOpen,
      contactPickerOpen,
      contactSearchQuery,
      pickerParticipantIds,
      deleteConfirmOpen,
      selectedGroups,
      selectedContacts,
      participantsBadgeCount,
      filteredContacts,
      allFilteredContactsSelected,
      showResultsAfter,
      agreementPeriodHint,
      sectionStartDateMin,
      sectionStartDateMax,
      sectionEndDateMin,
      sectionEndDateMax,
      sectionStartTimeMin,
      sectionStartTimeMax,
      sectionEndTimeMin,
      sectionEndTimeMax,
      hasInvalidDateRange,
      groupMemberCount,
      toggle,
      toggleLeader,
      removeParticipant,
      removeGroup,
      openContactPicker,
      closeContactPicker,
      applyContactPicker,
      toggleSelectAllContacts,
      openGroupPicker,
      applyGroupSelection,
      confirmDelete,
      save,
    }
  },
}
</script>
