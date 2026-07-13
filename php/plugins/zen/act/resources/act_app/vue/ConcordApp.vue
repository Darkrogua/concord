<template>
  <div class="concord-app">
    <AgreementsListView
      v-if="currentView === 'list'"
      :agreements="agreements"
      @open-agreement="onOpenAgreement"
      @edit-agreement="onEditAgreement"
      @duplicate-agreement="onDuplicateAgreement"
      @toggle-favorite="toggleFavorite"
      @open-filter-settings="goToFilterSettings"
    />

    <FilterSettingsView
      v-else-if="currentView === 'filter-settings'"
      :sections="filterSections"
      @back="goToList"
      @edit-section="openFilterEditor"
      @add-filter="openNewFilter"
      @remove-filter="removeFilter"
    />

    <GeneralSettingsView v-else-if="currentView === 'settings'" />

    <FilterEditorView
      v-else-if="currentView === 'filter-editor'"
      :section-title="editorTitle"
      :initial-name="editorName"
      :initial-filters="editorFilters"
      :picker-open="pickerOpen"
      @back="closeFilterEditor"
      @save="saveFilterEditor"
      @open-picker="pickerOpen = true"
      @close-picker="pickerOpen = false"
    />

    <ConcordBottomNav
      :active="navActive"
      :avatar-initial="activeAccountInitial"
      @navigate="onNavigate"
      @create="createOpen = true"
      @notifications="onNotifications"
      @switch-account="accountOpen = true"
    />

    <CreateProjectSheet
      :open="createOpen"
      :avatar-initial="activeAccountInitial"
      @close="createOpen = false"
      @select="onCreateOption"
    />

    <AccountSwitcherSheet
      v-model="activeAccountId"
      :open="accountOpen"
      @close="accountOpen = false"
      @create-account="onCreateAccount"
    />
  </div>
</template>

<script>
import { computed, ref } from 'vue'
import AgreementsListView from './views/AgreementsListView.vue'
import FilterSettingsView from './views/FilterSettingsView.vue'
import FilterEditorView from './views/FilterEditorView.vue'
import GeneralSettingsView from './views/GeneralSettingsView.vue'
import AccountSwitcherSheet from './concord/AccountSwitcherSheet.vue'
import ConcordBottomNav from './concord/ConcordBottomNav.vue'
import CreateProjectSheet from './concord/CreateProjectSheet.vue'
import { DEFAULT_FILTER_SECTIONS, MOCK_AGREEMENTS } from './concord/mock-agreements.js'

export default {
  name: 'ConcordApp',
  components: {
    AgreementsListView,
    FilterSettingsView,
    FilterEditorView,
    GeneralSettingsView,
    AccountSwitcherSheet,
    ConcordBottomNav,
    CreateProjectSheet,
  },
  setup() {
    const agreements = ref(MOCK_AGREEMENTS.map((item) => ({ ...item })))
    const filterSections = ref(
      DEFAULT_FILTER_SECTIONS.map((section) => ({
        ...section,
        filters: section.filters.map((filter) => ({ ...filter })),
      }))
    )

    const currentView = ref('list')
    const editingSectionId = ref(null)
    const editorTitle = ref('Новый фильтр')
    const editorName = ref('')
    const editorFilters = ref([])
    const pickerOpen = ref(false)

    const createOpen = ref(false)
    const accountOpen = ref(false)
    const activeAccountId = ref('1')

    const activeAccountInitial = computed(() => {
      const map = { 1: 'А', 2: 'И', 3: 'В' }
      return map[activeAccountId.value] || 'А'
    })

    const navActive = computed(() => {
      if (currentView.value === 'settings') {
        return 'settings'
      }
      return 'list'
    })

    function goToFilterSettings() {
      currentView.value = 'filter-settings'
    }

    function goToList() {
      currentView.value = 'list'
    }

    function onNavigate(view) {
      if (view === 'list') {
        currentView.value = 'list'
        return
      }
      if (view === 'settings') {
        currentView.value = 'settings'
      }
    }

    function openFilterEditor(sectionId) {
      const section = filterSections.value.find((item) => item.id === sectionId)
      if (!section) {
        return
      }
      editingSectionId.value = sectionId
      editorTitle.value = section.title
      editorName.value = section.title
      editorFilters.value = section.filters.map((filter) => ({ ...filter }))
      currentView.value = 'filter-editor'
    }

    function openNewFilter() {
      editingSectionId.value = null
      editorTitle.value = 'Новый фильтр'
      editorName.value = ''
      editorFilters.value = []
      currentView.value = 'filter-editor'
    }

    function closeFilterEditor() {
      pickerOpen.value = false
      currentView.value = 'filter-settings'
    }

    function saveFilterEditor(payload) {
      if (editingSectionId.value) {
        const section = filterSections.value.find((item) => item.id === editingSectionId.value)
        if (section) {
          section.title = payload.name
          section.filters = payload.filters.map((filter) => ({ ...filter }))
        }
      } else {
        filterSections.value.push({
          id: `custom-${Date.now()}`,
          title: payload.name,
          filters: payload.filters.map((filter) => ({ ...filter })),
        })
      }
      closeFilterEditor()
    }

    function removeFilter({ sectionId, filterId }) {
      const section = filterSections.value.find((item) => item.id === sectionId)
      if (!section) {
        return
      }
      section.filters = section.filters.filter((filter) => filter.id !== filterId)
    }

    function toggleFavorite(id) {
      const item = agreements.value.find((agreement) => agreement.id === id)
      if (item) {
        item.isFavorite = !item.isFavorite
      }
    }

    function onOpenAgreement(id) {
      console.info('[concord] open agreement', id)
    }

    function onEditAgreement(id) {
      console.info('[concord] edit agreement', id)
    }

    function onDuplicateAgreement(id) {
      console.info('[concord] duplicate agreement', id)
    }

    function onCreateOption(type) {
      console.info('[concord] create agreement', type)
    }

    function onCreateAccount() {
      console.info('[concord] create new account')
    }

    function onNotifications() {
      console.info('[concord] notifications')
    }

    return {
      agreements,
      filterSections,
      currentView,
      editorTitle,
      editorName,
      editorFilters,
      pickerOpen,
      createOpen,
      accountOpen,
      activeAccountId,
      activeAccountInitial,
      navActive,
      goToFilterSettings,
      goToList,
      onNavigate,
      openFilterEditor,
      openNewFilter,
      closeFilterEditor,
      saveFilterEditor,
      removeFilter,
      toggleFavorite,
      onOpenAgreement,
      onEditAgreement,
      onDuplicateAgreement,
      onCreateOption,
      onCreateAccount,
      onNotifications,
    }
  },
}
</script>
