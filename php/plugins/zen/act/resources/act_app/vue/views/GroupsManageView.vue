<template>
  <div class="concord-page concord-page--groups">
    <header class="concord-header concord-header--editor concord-header--groups">
      <button type="button" class="concord-icon-btn" aria-label="Назад" @click="$emit('back')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <h1 class="concord-header__title">Группы</h1>
      <button type="button" class="concord-icon-btn" aria-label="Меню" @click="onMenu">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <circle cx="6" cy="12" r="1.5" fill="currentColor"/>
          <circle cx="12" cy="12" r="1.5" fill="currentColor"/>
          <circle cx="18" cy="12" r="1.5" fill="currentColor"/>
        </svg>
      </button>
    </header>

    <div class="concord-groups-toolbar">
      <button type="button" class="concord-groups-toolbar__done" @click="$emit('back')">Готово</button>
    </div>

    <main class="concord-groups">
      <label class="concord-groups__search-wrap">
        <span class="concord-groups__search-label">Поиск</span>
        <input v-model="searchQuery" type="search" class="concord-groups__search" placeholder="Поиск">
      </label>

      <ul class="concord-groups__list">
        <li
          v-for="group in filteredGroups"
          :key="group.id"
          class="concord-groups__row"
        >
          <span class="concord-groups__title">{{ group.title }}</span>
          <div class="concord-groups__actions">
            <button
              type="button"
              class="concord-groups__checkbox"
              :class="{ 'concord-groups__checkbox--checked': isSelected(group.id) }"
              :aria-label="isSelected(group.id) ? 'Снять выбор' : 'Выбрать группу'"
              @click="toggleGroup(group.id)"
            >
              <svg v-if="isSelected(group.id)" viewBox="0 0 16 16" width="12" height="12" fill="none" aria-hidden="true">
                <path d="M3.5 8.2 6.4 11 12.5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
            <button
              type="button"
              class="concord-notifications__group-action-btn"
              aria-label="Редактировать группу"
              @click="$emit('edit-group', group.id)"
            >
              <ConcordGroupEditIcon />
            </button>
            <button
              type="button"
              class="concord-notifications__group-action-btn"
              aria-label="Удалить группу"
              @click="$emit('delete-group', group.id)"
            >
              <ConcordGroupDeleteIcon />
            </button>
          </div>
        </li>
      </ul>
    </main>

    <button type="button" class="concord-groups-fab" aria-label="Создать группу" @click="$emit('create-group')">
      <span aria-hidden="true">+</span>
    </button>
  </div>
</template>

<script>
import { computed, ref } from 'vue'
import ConcordGroupDeleteIcon from '../concord/ConcordGroupDeleteIcon.vue'
import ConcordGroupEditIcon from '../concord/ConcordGroupEditIcon.vue'

export default {
  name: 'GroupsManageView',
  components: { ConcordGroupEditIcon, ConcordGroupDeleteIcon },
  props: {
    groups: {
      type: Array,
      required: true,
    },
  },
  emits: ['back', 'edit-group', 'delete-group', 'create-group'],
  setup(props) {
    const searchQuery = ref('')
    const selectedGroupIds = ref([])

    const filteredGroups = computed(() => {
      const query = searchQuery.value.trim().toLowerCase()
      if (!query) {
        return props.groups
      }
      return props.groups.filter((group) => group.title.toLowerCase().includes(query))
    })

    function isSelected(groupId) {
      return selectedGroupIds.value.includes(groupId)
    }

    function toggleGroup(groupId) {
      if (isSelected(groupId)) {
        selectedGroupIds.value = selectedGroupIds.value.filter((id) => id !== groupId)
        return
      }
      selectedGroupIds.value = [...selectedGroupIds.value, groupId]
    }

    function onMenu() {
      console.info('[concord] groups menu')
    }

    return {
      searchQuery,
      filteredGroups,
      isSelected,
      toggleGroup,
      onMenu,
    }
  },
}
</script>
