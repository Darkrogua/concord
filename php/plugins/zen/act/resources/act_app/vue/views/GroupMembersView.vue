<template>
  <div class="concord-page concord-page--groups">
    <header class="concord-header concord-header--editor concord-header--groups">
      <button type="button" class="concord-icon-btn" aria-label="Назад" @click="$emit('back')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <h1 class="concord-header__title">Группа: {{ groupTitle }}</h1>
      <button type="button" class="concord-icon-btn" aria-label="Меню" @click="onMenu">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <circle cx="6" cy="12" r="1.5" fill="currentColor"/>
          <circle cx="12" cy="12" r="1.5" fill="currentColor"/>
          <circle cx="18" cy="12" r="1.5" fill="currentColor"/>
        </svg>
      </button>
    </header>

    <div class="concord-groups-toolbar">
      <button type="button" class="concord-groups-toolbar__done" @click="saveAndBack">Готово</button>
      <span class="concord-groups-toolbar__avatar" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none">
          <circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="1.6"/>
          <path d="M6 19.5c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
        <span class="concord-groups-toolbar__avatar-dot" />
      </span>
    </div>

    <main class="concord-groups">
      <ContactCheckboxList
        :contacts="filteredContacts"
        :selected-ids="selectedMemberIds"
        :search-query="searchQuery"
        @update:selected-ids="selectedMemberIds = $event"
        @update:search-query="searchQuery = $event"
      />
    </main>
  </div>
</template>

<script>
import { computed, ref, watch } from 'vue'
import ContactCheckboxList from '../concord/ContactCheckboxList.vue'
import { filterContacts } from '../concord/mock-groups.js'

export default {
  name: 'GroupMembersView',
  components: { ContactCheckboxList },
  props: {
    groupTitle: {
      type: String,
      required: true,
    },
    contacts: {
      type: Array,
      required: true,
    },
    memberIds: {
      type: Array,
      required: true,
    },
  },
  emits: ['back', 'save'],
  setup(props, { emit }) {
    const searchQuery = ref('')
    const selectedMemberIds = ref([...props.memberIds])

    watch(
      () => props.memberIds,
      (value) => {
        selectedMemberIds.value = [...value]
      }
    )

    const filteredContacts = computed(() => filterContacts(props.contacts, searchQuery.value))

    function saveAndBack() {
      emit('save', [...selectedMemberIds.value])
      emit('back')
    }

    function onMenu() {
      console.info('[concord] group members menu')
    }

    return {
      searchQuery,
      selectedMemberIds,
      filteredContacts,
      saveAndBack,
      onMenu,
    }
  },
}
</script>
