<template>
  <div class="concord-page concord-page--groups">
    <ConcordPageHeader title="Создание группы" show-back @back="$emit('back')">
      <template #right>
        <ConcordGroupHeaderActions
          :member-count="selectedMemberIds.length"
          :disabled="!canSave"
          @save="saveGroup"
        />
      </template>
    </ConcordPageHeader>

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
import ConcordPageHeader from '../concord/ConcordPageHeader.vue'
import { filterContacts } from '../concord/mock-groups.js'

export default {
  name: 'CreateGroupView',
  components: { ContactCheckboxList, ConcordGroupHeaderActions, ConcordPageHeader },
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
