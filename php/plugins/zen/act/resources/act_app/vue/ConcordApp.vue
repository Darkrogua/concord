<template>
  <div class="concord-app">
    <AgreementsListView
      v-if="currentView === 'list'"
      :agreements="agreements"
      :filter-sections="filterSections"
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
      @reorder-filters="reorderFilters"
      @reorder-sections="reorderSections"
    />

    <GeneralSettingsView
      v-else-if="currentView === 'settings'"
      @delete-account="onDeleteAccount"
      @open-notification-settings="goToNotificationSettings"
      @open-groups="goToGroupsManage"
    />

    <NotificationSettingsView
      v-else-if="currentView === 'notification-settings'"
      @back="goToSettings"
    />

    <GroupsManageView
      v-else-if="currentView === 'groups-manage'"
      :groups="profileGroups"
      @back="goToSettings"
      @edit-group="openGroupMembers"
      @delete-group="deleteGroup"
      @create-group="goToCreateGroup"
    />

    <GroupMembersView
      v-else-if="currentView === 'group-members'"
      :group-title="activeGroupTitle"
      :contacts="groupContacts"
      :member-ids="activeGroupMemberIds"
      @back="closeGroupMembers"
      @save="saveGroupMembers"
    />

    <CreateGroupView
      v-else-if="currentView === 'group-create'"
      :contacts="groupContacts"
      @back="closeCreateGroup"
      @save="onCreateGroup"
    />

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

    <CreateAgreementView
      v-else-if="currentView === 'create-agreement'"
      @back="goToList"
      @save-draft="onSaveAgreementDraft"
    />

    <AgreementEditorView
      v-else-if="currentView === 'agreement-editor' && editingAgreement?.isOwner"
      :agreement="editingAgreement"
      :contacts="groupContacts"
      :groups="profileGroups"
      @back="onAgreementEditorBack"
      @add-block="onAddAgreementBlock"
      @delete-block="onDeleteAgreementBlock"
      @add-section="onAddAgreementSection"
      @update-section="onUpdateAgreementSection"
      @create-group="onCreateGroup"
      @update-group="onUpdateGroup"
    />

    <AgreementApproverView
      v-else-if="currentView === 'agreement-editor' && editingAgreement && !editingAgreement.isOwner"
      :agreement="editingAgreement"
      @back="onAgreementEditorBack"
      @vote="onVote"
    />

    <NotificationsView
      v-else-if="currentView === 'notifications'"
      :sections="notificationSections"
      @back="goToList"
      @open-agreement="onOpenAgreementFromNotification"
      @mark-read="markNotificationRead"
    />

    <ConcordBottomNav
      v-if="showBottomNav"
      :active="navActive"
      :avatar-initial="activeAccountInitial"
      :profile-label="activeAccountLabel"
      :notifications-badge="unreadNotificationsCount"
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
import { computed, nextTick, ref, watch } from 'vue'
import AgreementsListView from './views/AgreementsListView.vue'
import FilterSettingsView from './views/FilterSettingsView.vue'
import FilterEditorView from './views/FilterEditorView.vue'
import GeneralSettingsView from './views/GeneralSettingsView.vue'
import NotificationSettingsView from './views/NotificationSettingsView.vue'
import CreateAgreementView from './views/CreateAgreementView.vue'
import AgreementEditorView from './views/AgreementEditorView.vue'
import AgreementApproverView from './views/AgreementApproverView.vue'
import NotificationsView from './views/NotificationsView.vue'
import GroupsManageView from './views/GroupsManageView.vue'
import GroupMembersView from './views/GroupMembersView.vue'
import CreateGroupView from './views/CreateGroupView.vue'
import AccountSwitcherSheet from './concord/AccountSwitcherSheet.vue'
import ConcordBottomNav from './concord/ConcordBottomNav.vue'
import CreateProjectSheet from './concord/CreateProjectSheet.vue'
import { formatAccountNavLabel, getAccountById } from './concord/mock-accounts.js'
import { DEFAULT_FILTER_SECTIONS, createDraftAgreement, createAgreementBlock, createAgreementSection, ensureAgreementSections, getNextAgreementNumber } from './concord/mock-agreements.js'
import { useConcordAgreements } from './composables/useConcordAgreements.js'
import { MOCK_NOTIFICATION_SECTIONS } from './concord/mock-notifications.js'
import {
  MOCK_CONTACTS,
  cloneProfileGroups,
  createProfileGroup,
  syncGroupMemberCount,
} from './concord/mock-groups.js'
import { resetConcordScrollPosition } from './concord/scroll-top.js'

export default {
  name: 'ConcordApp',
  components: {
    AgreementsListView,
    FilterSettingsView,
    FilterEditorView,
    GeneralSettingsView,
    NotificationSettingsView,
    CreateAgreementView,
    AgreementEditorView,
    AgreementApproverView,
    NotificationsView,
    GroupsManageView,
    GroupMembersView,
    CreateGroupView,
    AccountSwitcherSheet,
    ConcordBottomNav,
    CreateProjectSheet,
  },
  setup() {
    const { agreements, persist } = useConcordAgreements()
    const filterSections = ref(
      DEFAULT_FILTER_SECTIONS.map((section) => ({
        ...section,
        filters: section.filters.map((filter) => ({ ...filter })),
      }))
    )

    const notificationSections = ref(
      MOCK_NOTIFICATION_SECTIONS.map((section) => ({
        ...section,
        items: section.items.map((item) => ({ ...item })),
      }))
    )
    const profileGroups = ref(cloneProfileGroups())
    const groupContacts = ref(MOCK_CONTACTS.map((contact) => ({ ...contact })))
    const activeGroupId = ref(null)
    const groupMembersReturnView = ref('groups-manage')
    const groupCreateReturnView = ref('groups-manage')

    const currentView = ref('list')
    const editingAgreementId = ref(null)
    const editingSectionId = ref(null)
    const editorTitle = ref('Новый фильтр')
    const editorName = ref('')
    const editorFilters = ref([])
    const pickerOpen = ref(false)

    const createOpen = ref(false)
    const accountOpen = ref(false)
    const activeAccountId = ref('1')

    const activeAccountInitial = computed(() => getAccountById(activeAccountId.value).initial)

    const activeAccountLabel = computed(() =>
      formatAccountNavLabel(getAccountById(activeAccountId.value).name)
    )

    const navActive = computed(() => {
      if (
        currentView.value === 'settings'
        || currentView.value === 'notification-settings'
        || currentView.value === 'groups-manage'
        || currentView.value.startsWith('group')
      ) {
        return 'settings'
      }
      if (currentView.value === 'notifications') {
        return 'notifications'
      }
      return 'list'
    })

    const showBottomNav = computed(() => ![
      'create-agreement',
      'filter-editor',
    ].includes(currentView.value))

    watch(currentView, () => {
      nextTick(() => {
        requestAnimationFrame(() => {
          resetConcordScrollPosition()
        })
      })
    })

    const activeGroupTitle = computed(() => {
      const group = profileGroups.value.find((item) => item.id === activeGroupId.value)
      return group?.title || ''
    })

    const activeGroupMemberIds = computed(() => {
      const group = profileGroups.value.find((item) => item.id === activeGroupId.value)
      return group?.memberIds ? [...group.memberIds] : []
    })

    const editingAgreement = computed(() =>
      agreements.value.find((item) => item.id === editingAgreementId.value) || null
    )

    const unreadNotificationsCount = computed(() => {
      let count = 0
      for (const section of notificationSections.value) {
        for (const item of section.items) {
          if (!item.empty && !item.isRead) {
            count += 1
          }
        }
      }
      return count || undefined
    })

    function goToFilterSettings() {
      currentView.value = 'filter-settings'
    }

    function goToList() {
      currentView.value = 'list'
    }

    function onAgreementEditorBack() {
      persist()
      goToList()
    }

    function goToSettings() {
      currentView.value = 'settings'
    }

    function goToNotificationSettings() {
      currentView.value = 'notification-settings'
    }

    function goToGroupsManage() {
      currentView.value = 'groups-manage'
    }

    function goToCreateGroup(returnView = 'groups-manage') {
      groupCreateReturnView.value = returnView
      currentView.value = 'group-create'
    }

    function closeCreateGroup() {
      currentView.value = groupCreateReturnView.value
    }

    function openGroupMembers(groupId, returnView = 'groups-manage') {
      activeGroupId.value = groupId
      groupMembersReturnView.value = returnView
      currentView.value = 'group-members'
    }

    function closeGroupMembers() {
      currentView.value = groupMembersReturnView.value
    }

    function saveGroupMembers(payload) {
      const index = profileGroups.value.findIndex((item) => item.id === activeGroupId.value)
      if (index === -1) {
        return
      }
      const memberIds = Array.isArray(payload) ? payload : payload.memberIds
      const title = Array.isArray(payload) ? profileGroups.value[index].title : payload.title
      profileGroups.value[index] = syncGroupMemberCount({
        ...profileGroups.value[index],
        title: String(title || profileGroups.value[index].title).trim() || profileGroups.value[index].title,
        memberIds: [...memberIds],
      })
    }

    function deleteGroup(groupId) {
      profileGroups.value = profileGroups.value.filter((group) => group.id !== groupId)
    }

    function onDeleteAccount() {
      console.info('[concord] delete account — not implemented in preview')
    }

    function onCreateGroup(payload) {
      profileGroups.value.push(createProfileGroup(payload.title, payload.memberIds))
    }

    function onUpdateGroup({ groupId, title, memberIds }) {
      const index = profileGroups.value.findIndex((item) => item.id === groupId)
      if (index === -1) {
        return
      }
      profileGroups.value[index] = syncGroupMemberCount({
        ...profileGroups.value[index],
        title: String(title || profileGroups.value[index].title).trim() || profileGroups.value[index].title,
        memberIds: [...memberIds],
      })
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
          section.tabLabel = payload.name
          section.filters = payload.filters.map((filter) => ({ ...filter }))
        }
      } else {
        const sectionId = `custom-${Date.now()}`
        filterSections.value.push({
          id: sectionId,
          tabId: sectionId,
          tabLabel: payload.name,
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

    function reorderFilters({ sectionId, filterIds }) {
      const section = filterSections.value.find((item) => item.id === sectionId)
      if (!section || !Array.isArray(filterIds) || filterIds.length < 2) {
        return
      }
      const byId = new Map(section.filters.map((filter) => [filter.id, filter]))
      const next = filterIds.map((id) => byId.get(id)).filter(Boolean)
      if (next.length !== section.filters.length) {
        return
      }
      const currentIds = section.filters.map((filter) => filter.id)
      if (currentIds.join(',') === filterIds.join(',')) {
        return
      }
      section.filters = next
    }

    function reorderSections(sectionIds) {
      if (!Array.isArray(sectionIds) || sectionIds.length < 2) {
        return
      }
      const byId = new Map(filterSections.value.map((section) => [section.id, section]))
      const next = sectionIds.map((id) => byId.get(id)).filter(Boolean)
      if (next.length !== filterSections.value.length) {
        return
      }
      const currentIds = filterSections.value.map((section) => section.id)
      if (currentIds.join(',') === sectionIds.join(',')) {
        return
      }
      filterSections.value = next
    }

    function toggleFavorite(id) {
      const item = agreements.value.find((agreement) => agreement.id === id)
      if (item) {
        item.isFavorite = !item.isFavorite
      }
    }

    function onOpenAgreement(id) {
      const item = agreements.value.find((agreement) => agreement.id === id)
      if (!item) {
        return
      }
      ensureAgreementSections(item)
      editingAgreementId.value = id
      currentView.value = 'agreement-editor'
    }

    function onEditAgreement(id) {
      const item = agreements.value.find((agreement) => agreement.id === id)
      if (!item || item.status !== 'draft') {
        console.info('[concord] edit agreement', id)
        return
      }
      ensureAgreementSections(item)
      editingAgreementId.value = id
      currentView.value = 'agreement-editor'
    }

    function onDuplicateAgreement(id) {
      console.info('[concord] duplicate agreement', id)
    }

    function onCreateOption(type) {
      if (type === 'approval') {
        currentView.value = 'create-agreement'
        return
      }
      console.info('[concord] create voting — not implemented in preview')
    }

    function onSaveAgreementDraft(form) {
      const nextNumber = getNextAgreementNumber(agreements.value)
      const draft = createDraftAgreement(form, nextNumber)
      agreements.value.unshift(draft)
      editingAgreementId.value = draft.id
      currentView.value = 'agreement-editor'
      persist()
    }

    function onAddAgreementBlock({ sectionId, blockType }) {
      const agreement = agreements.value.find((item) => item.id === editingAgreementId.value)
      if (!agreement) {
        return
      }
      ensureAgreementSections(agreement)
      const section = agreement.sections.find((item) => item.id === sectionId)
      if (!section) {
        return
      }
      if (!section.blocks) {
        section.blocks = []
      }
      const isFirstBlock = section.blocks.length === 0
      section.blocks.push(createAgreementBlock(blockType))
      if (isFirstBlock) {
        section.votingStats = { approved: 0, rejected: 0, pending: 100 }
      }
      persist()
    }

    function onDeleteAgreementBlock({ sectionId, blockId }) {
      const agreement = agreements.value.find((item) => item.id === editingAgreementId.value)
      if (!agreement) {
        return
      }
      const section = agreement.sections?.find((item) => item.id === sectionId)
      if (!section?.blocks) {
        return
      }
      section.blocks = section.blocks.filter((block) => block.id !== blockId)
    }

    function onAddAgreementSection(payload) {
      const agreement = agreements.value.find((item) => item.id === editingAgreementId.value)
      if (!agreement) {
        return
      }
      ensureAgreementSections(agreement)
      const section = createAgreementSection(payload.title)
      section.participantIds = [...payload.participantIds]
      section.groupIds = [...payload.groupIds]
      agreement.sections.push(section)
      persist()
    }

    function onVote({ agreementId, sectionId, decision, reason, participantId }) {
      const agreement = agreements.value.find((item) => item.id === agreementId)
      if (!agreement) {
        return
      }
      const section = agreement.sections?.find((item) => item.id === sectionId)
      if (!section) {
        return
      }
      section.userVote = { decision, reason }
      if (!Array.isArray(section.votes)) {
        section.votes = []
      }
      const voterId = participantId || activeAccountId.value
      const existingIndex = section.votes.findIndex((vote) => vote.participantId === voterId)
      const voteEntry = { participantId: voterId, decision, reason, votedAt: new Date().toISOString() }
      if (existingIndex >= 0) {
        section.votes[existingIndex] = voteEntry
      } else {
        section.votes.push(voteEntry)
      }
      const stats = section.votingStats || { approved: 0, rejected: 0, pending: 100 }
      const voters = section.participantIds?.length || agreement.total || 1
      const share = Math.round(100 / voters)
      if (decision === 'approved') {
        stats.approved = Math.min(100, (stats.approved || 0) + share)
      } else {
        stats.rejected = Math.min(100, (stats.rejected || 0) + share)
      }
      stats.pending = Math.max(0, 100 - stats.approved - stats.rejected)
      section.votingStats = stats
      persist()
    }

    function onUpdateAgreementSection({
      sectionId,
      title,
      participantIds,
      groupIds,
      leaderId,
      settings,
    }) {
      const agreement = agreements.value.find((item) => item.id === editingAgreementId.value)
      if (!agreement) {
        return
      }
      const section = agreement.sections?.find((item) => item.id === sectionId)
      if (!section) {
        return
      }
      section.title = title
      section.participantIds = [...participantIds]
      section.groupIds = [...groupIds]
      if (leaderId !== undefined) {
        section.leaderId = leaderId
      }
      if (settings) {
        section.settings = {
          ...section.settings,
          ...settings,
          reminders: {
            ...(section.settings?.reminders || {}),
            ...(settings.reminders || {}),
          },
        }
        if (settings.isImportant !== undefined) {
          agreement.isUrgent = settings.isImportant
        }
      }
      persist()
    }

    function onCreateAccount() {
      console.info('[concord] create new account')
    }

    function onNotifications() {
      currentView.value = 'notifications'
    }

    function markNotificationRead(notificationId) {
      for (const section of notificationSections.value) {
        const item = section.items.find((entry) => entry.id === notificationId)
        if (item) {
          item.isRead = true
          return
        }
      }
    }

    function onOpenAgreementFromNotification(agreementId) {
      onOpenAgreement(agreementId)
      currentView.value = 'list'
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
      activeAccountLabel,
      navActive,
      showBottomNav,
      goToFilterSettings,
      goToList,
      onAgreementEditorBack,
      goToSettings,
      goToNotificationSettings,
      goToGroupsManage,
      goToCreateGroup,
      closeCreateGroup,
      openGroupMembers,
      closeGroupMembers,
      saveGroupMembers,
      deleteGroup,
      onCreateGroup,
      onUpdateGroup,
      onNavigate,
      openFilterEditor,
      openNewFilter,
      closeFilterEditor,
      saveFilterEditor,
      removeFilter,
      reorderFilters,
      reorderSections,
      toggleFavorite,
      onOpenAgreement,
      onEditAgreement,
      onDuplicateAgreement,
      onCreateOption,
      onSaveAgreementDraft,
      onAddAgreementBlock,
      onDeleteAgreementBlock,
      onAddAgreementSection,
      onUpdateAgreementSection,
      onVote,
      editingAgreement,
      editingAgreementId,
      onCreateAccount,
      onNotifications,
      notificationSections,
      unreadNotificationsCount,
      markNotificationRead,
      onOpenAgreementFromNotification,
      profileGroups,
      groupContacts,
      activeGroupTitle,
      activeGroupMemberIds,
    }
  },
}
</script>
