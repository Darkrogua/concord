<template>
  <template v-if="open">
    <div class="concord-sheet-backdrop" @click="$emit('close')" />
    <section
      class="concord-sheet concord-section-participants-sheet"
      role="dialog"
      aria-modal="true"
      aria-labelledby="section-participants-title"
    >
      <div class="concord-sheet__handle" aria-hidden="true" />
      <h2 id="section-participants-title" class="concord-sheet__title">Участники раздела</h2>
      <p v-if="participantsCount" class="concord-section-participants-sheet__hint">
        {{ participantsCountLabel }}
      </p>

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

      <button
        type="button"
        class="concord-section-participants-sheet__settings-link"
        @click="$emit('open-settings')"
      >
        Подробные настройки раздела
      </button>

      <div class="concord-create-sheet__actions">
        <button type="button" class="concord-create-sheet__btn concord-create-sheet__btn--cancel" @click="$emit('close')">
          Отмена
        </button>
        <button type="button" class="concord-create-sheet__btn concord-create-sheet__btn--save" @click="save">
          Сохранить
        </button>
      </div>
    </section>

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
  </template>
</template>

<script>
import { computed, defineExpose, ref, watch } from 'vue'
import ContactCheckboxList from './ContactCheckboxList.vue'
import {
  collectSelectedGroupMemberIds,
  filterContacts,
  getGroupMembersCount,
  resolveSectionVotersCount,
} from './mock-groups.js'

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

function createFormFromSection(section, groups) {
  const groupIds = [...(section.groupIds || [])]
  const covered = collectSelectedGroupMemberIds(groupIds, groups)
  return {
    participantIds: [...(section.participantIds || [])].filter((id) => !covered.has(id)),
    groupIds,
  }
}

export default {
  name: 'SectionParticipantsSheet',
  components: { ContactCheckboxList },
  props: {
    open: { type: Boolean, default: false },
    section: { type: Object, required: true },
    contacts: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
  },
  emits: ['close', 'save', 'pick-groups', 'open-settings'],
  setup(props, { emit }) {
    const form = ref({ participantIds: [], groupIds: [] })
    const participantMenuOpen = ref(false)
    const contactPickerOpen = ref(false)
    const contactSearchQuery = ref('')
    const pickerParticipantIds = ref([])

    function resetForm() {
      form.value = createFormFromSection(props.section, props.groups)
      participantMenuOpen.value = false
      contactPickerOpen.value = false
      contactSearchQuery.value = ''
    }

    watch(() => [props.open, props.section], resetForm, { immediate: true })

    const selectedGroups = computed(() =>
      props.groups.filter((group) => form.value.groupIds.includes(group.id))
    )

    const coveredByGroupsIds = computed(() =>
      collectSelectedGroupMemberIds(form.value.groupIds, props.groups)
    )

    const selectedContacts = computed(() =>
      props.contacts.filter(
        (contact) =>
          form.value.participantIds.includes(contact.id)
          && !coveredByGroupsIds.value.has(contact.id)
      )
    )

    const participantsCount = computed(() =>
      resolveSectionVotersCount(form.value, props.groups, props.contacts)
    )

    const participantsCountLabel = computed(() => {
      const count = participantsCount.value
      return `${count} ${pluralizeRu(count, ['согласующий', 'согласующих', 'согласующих'])}`
    })

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

    function groupMemberCount(group) {
      return getGroupMembersCount(group)
    }

    function pruneParticipantsCoveredByGroups(participantIds, groupIds = form.value.groupIds) {
      const covered = collectSelectedGroupMemberIds(groupIds, props.groups)
      return participantIds.filter((id) => !covered.has(id))
    }

    function removeParticipant(contactId) {
      form.value.participantIds = form.value.participantIds.filter((id) => id !== contactId)
    }

    function removeGroup(groupId) {
      form.value.groupIds = form.value.groupIds.filter((id) => id !== groupId)
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

    function applyContactPicker() {
      form.value.participantIds = pruneParticipantsCoveredByGroups(pickerParticipantIds.value)
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

    function save() {
      emit('save', {
        participantIds: pruneParticipantsCoveredByGroups(form.value.participantIds),
        groupIds: [...form.value.groupIds],
      })
    }

    defineExpose({ applyGroupSelection })

    return {
      form,
      participantMenuOpen,
      contactPickerOpen,
      contactSearchQuery,
      pickerParticipantIds,
      selectedGroups,
      selectedContacts,
      participantsCount,
      participantsCountLabel,
      filteredContacts,
      allFilteredContactsSelected,
      groupMemberCount,
      removeParticipant,
      removeGroup,
      openContactPicker,
      closeContactPicker,
      applyContactPicker,
      toggleSelectAllContacts,
      openGroupPicker,
      save,
    }
  },
}
</script>
