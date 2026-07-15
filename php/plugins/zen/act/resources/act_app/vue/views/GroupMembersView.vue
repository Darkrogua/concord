<template>
  <div class="concord-page concord-page--groups">
    <header class="concord-header concord-header--editor concord-header--groups">
      <button type="button" class="concord-icon-btn" aria-label="Назад" @click="$emit('back')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <h1 class="concord-header__title concord-group-title">
        <span class="concord-group-title__prefix">Группа:</span>
        <input
          ref="titleInputRef"
          v-model="editableTitle"
          type="text"
          class="concord-group-title__input"
          :aria-label="`Название группы: ${groupTitle}`"
          autocomplete="off"
          spellcheck="false"
          @keydown.enter.prevent="titleInputRef?.blur()"
        >
      </h1>
      <ConcordGroupHeaderActions
        :member-count="selectedMemberIds.length"
        :disabled="!canSave"
        @save="saveAndBack"
      />
    </header>

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
import ConcordGroupHeaderActions from '../concord/ConcordGroupHeaderActions.vue'
import { filterContacts } from '../concord/mock-groups.js'

export default {
  name: 'GroupMembersView',
  components: { ContactCheckboxList, ConcordGroupHeaderActions },
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
    const editableTitle = ref(props.groupTitle)
    const titleInputRef = ref(null)

    watch(
      () => props.memberIds,
      (value) => {
        selectedMemberIds.value = [...value]
      }
    )

    watch(
      () => props.groupTitle,
      (value) => {
        editableTitle.value = value
      }
    )

    const filteredContacts = computed(() => filterContacts(props.contacts, searchQuery.value))
    const canSave = computed(() => editableTitle.value.trim().length > 0)

    function saveAndBack() {
      if (!canSave.value) {
        return
      }
      emit('save', {
        title: editableTitle.value.trim(),
        memberIds: [...selectedMemberIds.value],
      })
      emit('back')
    }

    return {
      searchQuery,
      selectedMemberIds,
      editableTitle,
      titleInputRef,
      filteredContacts,
      canSave,
      saveAndBack,
    }
  },
}
</script>
