<template>
  <div class="concord-page concord-page--groups">
    <header class="concord-header concord-header--editor concord-header--groups">
      <button type="button" class="concord-icon-btn" aria-label="Назад" @click="$emit('back')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <h1 class="concord-header__title">Создание группы</h1>
      <ConcordGroupHeaderActions
        :member-count="selectedMemberIds.length"
        :disabled="!canSave"
        @save="saveGroup"
      />
    </header>

    <main class="concord-groups">
      <label class="concord-groups__field">
        <span class="concord-groups__field-label">Название группы</span>
        <input
          v-model="groupTitle"
          type="text"
          class="concord-groups__field-input"
          placeholder="Введите текст"
        >
      </label>

      <div class="concord-groups__picker">
        <ContactCheckboxList
          :contacts="filteredContacts"
          :selected-ids="selectedMemberIds"
          :search-query="searchQuery"
          @update:selected-ids="selectedMemberIds = $event"
          @update:search-query="searchQuery = $event"
        />
      </div>
    </main>
  </div>
</template>

<script>
import { computed, ref } from 'vue'
import ContactCheckboxList from '../concord/ContactCheckboxList.vue'
import ConcordGroupHeaderActions from '../concord/ConcordGroupHeaderActions.vue'
import { filterContacts } from '../concord/mock-groups.js'

export default {
  name: 'CreateGroupView',
  components: { ContactCheckboxList, ConcordGroupHeaderActions },
  props: {
    contacts: {
      type: Array,
      required: true,
    },
  },
  emits: ['back', 'save'],
  setup(props, { emit }) {
    const groupTitle = ref('')
    const searchQuery = ref('')
    const selectedMemberIds = ref([])

    const filteredContacts = computed(() => filterContacts(props.contacts, searchQuery.value))
    const canSave = computed(() => groupTitle.value.trim().length > 0)

    function saveGroup() {
      if (!canSave.value) {
        return
      }
      emit('save', {
        title: groupTitle.value.trim(),
        memberIds: [...selectedMemberIds.value],
      })
      emit('back')
    }

    return {
      groupTitle,
      searchQuery,
      selectedMemberIds,
      filteredContacts,
      canSave,
      saveGroup,
    }
  },
}
</script>
