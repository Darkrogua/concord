<template>
  <div class="concord-app">
    <ConcordSplashScreen
      v-if="splashVisible"
      :ready="splashReady"
      @done="onSplashDone"
    />

    <AgreementsListView
      v-if="currentView === 'list'"
      :agreements="agreements"
      :filter-sections="filterSections"
      :groups="profileGroups"
      :contacts="groupContacts"
      :loading="agreementsLoading"
      :opening-agreement-id="viewTransitionAgreementId"
      :new-agreement-id="newlyCreatedAgreementId"
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
      :account-id="activeAccountId"
      @delete-account="onDeleteAccount"
      @open-notification-settings="goToNotificationSettings"
      @open-groups="goToGroupsManage"
    />

    <NotificationSettingsView
      v-else-if="currentView === 'notification-settings'"
      :account-id="activeAccountId"
      @back="goToSettings"
    />

    <GroupsManageView
      v-else-if="currentView === 'groups-manage'"
      :account-id="activeAccountId"
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
      ref="agreementEditorRef"
      :agreement="editingAgreement"
      :contacts="groupContacts"
      :groups="profileGroups"
      :initial-section-id="searchTargetSectionId"
      :view-transition-name="viewTransitionAgreementId ? 'concord-agreement-card' : ''"
      @back="onAgreementEditorBack"
      @add-block="onAddAgreementBlock"
      @delete-block="onDeleteAgreementBlock"
      @add-section="onAddAgreementSection"
      @delete-section="onDeleteAgreementSection"
      @update-section="onUpdateAgreementSection"
      @launch="onLaunchAgreement"
      @create-group="onCreateGroup"
      @update-group="onUpdateGroup"
      @update-agreement="onAgreementEditorUpdated"
      @reset-votes="onResetAgreementVotes"
      @request-leave="onEditorRequestLeave"
      @edited="onAgreementEditorEdited"
    />

    <AgreementApproverView
      v-else-if="currentView === 'agreement-editor' && editingAgreement && !editingAgreement.isOwner"
      :agreement="editingAgreement"
      :initial-section-id="searchTargetSectionId"
      :view-transition-name="viewTransitionAgreementId ? 'concord-agreement-card' : ''"
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
      :avatar-url="activeAccountAvatarUrl"
      :avatar-initial="activeAccountInitial"
      :profile-label="activeAccountLabel"
      :notifications-badge="unreadNotificationsCount"
      :profile-badge="otherAccountsNotificationsBadge"
      @navigate="onNavigate"
      @create="startCreateAgreement"
      @notifications="onNotifications"
      @switch-account="onSwitchAccount"
    />

    <AccountSwitcherSheet
      v-model="activeAccountId"
      :open="accountOpen"
      :notification-badges="accountNotificationBadges"
      @close="accountOpen = false"
      @create-account="onCreateAccount"
    />

    <Teleport to="body">
      <ConcordConfirmSheet
        :open="leaveConfirmOpen"
        :title="leaveConfirmTitle"
        :message="leaveConfirmMessage"
        :confirm-label="leaveConfirmConfirmLabel"
        :cancel-label="leaveConfirmCancelLabel"
        :extra-action-label="leaveConfirmResetLabel"
        confirm-tone="primary"
        :close-on-backdrop="true"
        @confirm="onLeaveConfirmPrimary"
        @cancel="onLeaveConfirmDismiss"
        @extra="onLeaveConfirmSecondary"
        @dismiss="onLeaveConfirmDismiss"
      />
    </Teleport>
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
import ConcordConfirmSheet from './concord/ConcordConfirmSheet.vue'
import ConcordSplashScreen from './concord/ConcordSplashScreen.vue'
import { formatAccountNavLabel, getAccountById } from './concord/mock-accounts.js'
import { DEFAULT_FILTER_SECTIONS, createDraftAgreement, createAgreementBlock, createAgreementSection, ensureAgreementSections, formatDateRu, getNextAgreementNumber, setAgreementEditBaseline, agreementHasVotes, isLaunchedAgreement, resetAgreementVotes } from './concord/mock-agreements.js'
import { useConcordAgreements } from './composables/useConcordAgreements.js'
import { useConcordGroups } from './composables/useConcordGroups.js'
import { useConcordProfile } from './composables/useConcordProfile.js'
import { MOCK_NOTIFICATION_SECTIONS_BY_ACCOUNT, cloneNotificationSectionsByAccount, countUnreadNotifications, pushVoteResetNotifications } from './concord/mock-notifications.js'
import {
  MOCK_CONTACTS,
  collectSectionVoterIds,
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
    ConcordConfirmSheet,
    ConcordSplashScreen,
  },
  setup() {
    const { agreements, loading: agreementsLoading, persist } = useConcordAgreements()
    // Show splash once per app session; sessionStorage survives refresh, clears on new open.
    const SPLASH_SESSION_KEY = 'concord_splash_seen_v1'

    function hasSeenSplashInSession() {
      try {
        return window.sessionStorage.getItem(SPLASH_SESSION_KEY) === '1'
      } catch {
        return false
      }
    }

    function markSplashSeenInSession() {
      try {
        window.sessionStorage.setItem(SPLASH_SESSION_KEY, '1')
        window.localStorage.removeItem(SPLASH_SESSION_KEY)
      } catch {
        // ignore private mode / quota errors in preview
      }
    }

    const splashVisible = ref(typeof window !== 'undefined' ? !hasSeenSplashInSession() : false)
    if (splashVisible.value) {
      markSplashSeenInSession()
    }
    const splashReady = computed(() => !agreementsLoading.value)

    function onSplashDone() {
      splashVisible.value = false
    }
    const { profileGroups } = useConcordGroups()
    const filterSections = ref(
      DEFAULT_FILTER_SECTIONS.map((section) => ({
        ...section,
        filters: section.filters.map((filter) => ({ ...filter })),
      }))
    )

    const notificationSectionsByAccount = ref(cloneNotificationSectionsByAccount(MOCK_NOTIFICATION_SECTIONS_BY_ACCOUNT))
    const groupContacts = ref(MOCK_CONTACTS.map((contact) => ({ ...contact })))
    const activeGroupId = ref(null)
    const groupMembersReturnView = ref('groups-manage')
    const groupCreateReturnView = ref('groups-manage')

    const currentView = ref('list')
    const editingAgreementId = ref(null)
    const searchTargetSectionId = ref(null)
    const viewTransitionAgreementId = ref(null)
    const newlyCreatedAgreementId = ref(null)
    const editingSectionId = ref(null)
    const editorTitle = ref('Новый фильтр')
    const editorName = ref('')
    const editorFilters = ref([])
    const pickerOpen = ref(false)
    const agreementEditorRef = ref(null)
    const leaveConfirmOpen = ref(false)
    const agreementEditorDirty = ref(false)
    const leaveTarget = ref('list')

    const accountOpen = ref(false)
    const activeAccountId = ref('1')
    const { profile: activeProfile } = useConcordProfile(activeAccountId)

    const activeAccountInitial = computed(() => getAccountById(activeAccountId.value).initial)

    const activeAccountAvatarUrl = computed(() => activeProfile.value.avatarUrl || '')

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

    const notificationSections = computed(() =>
      notificationSectionsByAccount.value[activeAccountId.value] || []
    )

    const unreadNotificationsCount = computed(() => {
      const count = countUnreadNotifications(notificationSections.value)
      return count || undefined
    })

    const otherAccountsNotificationsBadge = computed(() => {
      let total = 0
      for (const [accountId, sections] of Object.entries(notificationSectionsByAccount.value)) {
        if (accountId === activeAccountId.value) {
          continue
        }
        total += countUnreadNotifications(sections)
      }
      return total || undefined
    })

    const accountNotificationBadges = computed(() => {
      const badges = {}
      for (const [accountId, sections] of Object.entries(notificationSectionsByAccount.value)) {
        const count = countUnreadNotifications(sections)
        if (count) {
          badges[accountId] = count
        }
      }
      return badges
    })

    function goToFilterSettings() {
      currentView.value = 'filter-settings'
    }

    function goToList() {
      currentView.value = 'list'
    }

    const leaveConfirmTitle = computed(() => 'Сохранить изменения?')

    const leaveConfirmMessage = computed(() => {
      if (agreementHasVotes(editingAgreement.value)) {
        return 'Согласование уже запущено, и участники могли проголосовать. Сохранить без сброса — текущие голоса останутся в силе. Обнулить и сохранить — все поставленные голоса будут сняты, и согласующим потребуется проголосовать заново.'
      }
      return 'Сохранить изменения и выйти из редактора?'
    })

    const leaveConfirmConfirmLabel = computed(() => (
      agreementHasVotes(editingAgreement.value) ? 'Сохранить без сброса' : 'Сохранить'
    ))

    const leaveConfirmCancelLabel = computed(() => 'Отмена')

    const leaveConfirmResetLabel = computed(() => (
      agreementHasVotes(editingAgreement.value) ? 'Обнулить и сохранить' : ''
    ))

    function resetAgreementEditorSession() {
      agreementEditorDirty.value = false
      leaveConfirmOpen.value = false
      leaveTarget.value = 'list'
      const agreement = editingAgreement.value
      if (agreement && isLaunchedAgreement(agreement)) {
        setAgreementEditBaseline(agreement)
      }
    }

    function onAgreementEditorEdited() {
      if (isLaunchedAgreement(editingAgreement.value)) {
        agreementEditorDirty.value = true
      }
    }

    function onAgreementEditorLeaveCancelled() {
      leaveTarget.value = 'list'
    }

    function onEditorRequestLeave() {
      leaveTarget.value = 'list'
      if (!editorNeedsLeaveConfirmation()) {
        leaveEditorTo('list')
        return
      }
      leaveConfirmOpen.value = true
    }

    function onLeaveConfirmPrimary() {
      leaveConfirmOpen.value = false
      agreementEditorRef.value?.flushOpenEditorsBeforeLeave?.()
      if (editingAgreement.value) {
        setAgreementEditBaseline(editingAgreement.value)
      }
      agreementEditorDirty.value = false
      persist()
      leaveEditorTo(leaveTarget.value)
    }

    function onLeaveConfirmSecondary() {
      if (agreementHasVotes(editingAgreement.value)) {
        leaveConfirmOpen.value = false
        agreementEditorRef.value?.flushOpenEditorsBeforeLeave?.()
        const agreement = editingAgreement.value
        if (agreement) {
          const participantIds = resetAgreementVotes(agreement)
          if (participantIds.length) {
            pushVoteResetNotifications(notificationSectionsByAccount.value, {
              agreement,
              participantIds,
            })
          }
          setAgreementEditBaseline(agreement)
        }
        agreementEditorDirty.value = false
        persist()
        leaveEditorTo(leaveTarget.value)
        return
      }
      onLeaveConfirmDismiss()
    }

    function onLeaveConfirmDismiss() {
      leaveConfirmOpen.value = false
      onAgreementEditorLeaveCancelled()
    }

    function onAgreementEditorBack() {
      leaveEditorTo(leaveTarget.value)
    }

    function leaveEditorTo(target) {
      leaveTarget.value = 'list'
      if (target === 'list') {
        goToList()
        return
      }
      if (target === 'settings') {
        goToSettings()
        return
      }
      if (target === 'notifications') {
        currentView.value = 'notifications'
        return
      }
      if (target === 'create-agreement') {
        currentView.value = 'create-agreement'
        return
      }
      if (target === 'switch-account') {
        accountOpen.value = true
      }
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

    function editorNeedsLeaveConfirmation() {
      if (currentView.value !== 'agreement-editor' || !editingAgreement.value?.isOwner) {
        return false
      }
      if (!isLaunchedAgreement(editingAgreement.value)) {
        return false
      }
      if (agreementEditorRef.value?.hasOpenUnsavedOverlay?.()) {
        return true
      }
      return agreementEditorDirty.value
    }

    function tryLeaveAgreementEditor(nextView = 'list') {
      if (currentView.value !== 'agreement-editor' || !editingAgreement.value?.isOwner) {
        return true
      }
      leaveTarget.value = nextView
      if (!editorNeedsLeaveConfirmation()) {
        leaveTarget.value = 'list'
        return true
      }
      leaveConfirmOpen.value = true
      return false
    }

    function onNavigate(view) {
      if (!tryLeaveAgreementEditor(view)) {
        return
      }
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

    function acknowledgeAgreementUrgent(agreement) {
      if (!agreement?.isUrgent || agreement.urgentAcknowledged) {
        return
      }
      agreement.urgentAcknowledged = true
      persist()
    }

    function onOpenAgreement(target) {
      const id = typeof target === 'object' ? target.id : target
      const item = agreements.value.find((agreement) => agreement.id === id)
      if (!item) {
        return
      }
      acknowledgeAgreementUrgent(item)
      searchTargetSectionId.value = typeof target === 'object' ? target.sectionId || null : null
      viewTransitionAgreementId.value = id
      const open = () => {
        ensureAgreementSections(item)
        editingAgreementId.value = id
        resetAgreementEditorSession()
        currentView.value = 'agreement-editor'
      }
      if (!document.startViewTransition) {
        open()
        viewTransitionAgreementId.value = null
        return
      }
      const transition = document.startViewTransition(() => {
        open()
        return nextTick()
      })
      transition.finished.finally(() => {
        viewTransitionAgreementId.value = null
      })
    }

    function onEditAgreement(id) {
      const item = agreements.value.find((agreement) => agreement.id === id)
      if (!item || item.status !== 'draft') {
        console.info('[concord] edit agreement', id)
        return
      }
      ensureAgreementSections(item)
      editingAgreementId.value = id
      resetAgreementEditorSession()
      currentView.value = 'agreement-editor'
    }

    function onDuplicateAgreement(id) {
      console.info('[concord] duplicate agreement', id)
    }

    function startCreateAgreement() {
      if (!tryLeaveAgreementEditor('create-agreement')) {
        return
      }
      currentView.value = 'create-agreement'
    }

    function onSaveAgreementDraft(form) {
      const nextNumber = getNextAgreementNumber(agreements.value)
      const draft = createDraftAgreement(form, nextNumber)
      agreements.value.unshift(draft)
      newlyCreatedAgreementId.value = draft.id
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
      onAgreementEditorEdited()
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
      persist()
      onAgreementEditorEdited()
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
      onAgreementEditorEdited()
    }

    function onDeleteAgreementSection(sectionId) {
      const agreement = agreements.value.find((item) => item.id === editingAgreementId.value)
      if (!agreement?.sections?.length) {
        return
      }
      agreement.sections = agreement.sections.filter((section) => section.id !== sectionId)
      persist()
      onAgreementEditorEdited()
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

function onAgreementEditorUpdated() {
      // Persistence is handled by the debounced agreements watcher.
    }

    function onLaunchAgreement() {
      const agreement = agreements.value.find((item) => item.id === editingAgreementId.value)
      if (!agreement) {
        return
      }
      if (!agreement.createdAt) {
        agreement.createdAt = formatDateRu()
      }
      agreement.status = 'awaiting'
      setAgreementEditBaseline(agreement)
      persist()
      goToList()
    }

    function onResetAgreementVotes({ participantIds }) {
      const agreement = agreements.value.find((item) => item.id === editingAgreementId.value)
      if (!agreement || !participantIds?.length) {
        return
      }
      pushVoteResetNotifications(notificationSectionsByAccount.value, {
        agreement,
        participantIds,
      })
      persist()
    }

    function onUpdateAgreementSection({
      sectionId,
      title,
      participantIds,
      groupIds,
      leaderId,
      startDate,
      deadline,
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
      const voterIds = collectSectionVoterIds(section, profileGroups.value)
      section.voterIds = voterIds
      section.total = voterIds.length
      // Keep voting pending row in sync with new headcount while draft/zero votes.
      if (!section.votes?.length) {
        section.votingStats = {
          approved: 0,
          rejected: 0,
          pending: voterIds.length ? 100 : 0,
        }
        section.voted = 0
      }
      if (leaderId !== undefined) {
        section.leaderId = leaderId
      }
      if (startDate !== undefined) {
        section.startDate = startDate
      }
      if (deadline !== undefined) {
        section.deadline = deadline
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
          // Re-show the urgency mark until the agreement is opened again.
          agreement.urgentAcknowledged = !settings.isImportant
        }
      }
      persist()
      onAgreementEditorEdited()
    }

    function onCreateAccount() {
      console.info('[concord] create new account')
    }

    function onNotifications() {
      if (!tryLeaveAgreementEditor('notifications')) {
        return
      }
      currentView.value = 'notifications'
    }

    function onSwitchAccount() {
      if (!tryLeaveAgreementEditor('switch-account')) {
        return
      }
      accountOpen.value = true
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
      splashVisible,
      splashReady,
      onSplashDone,
      agreements,
      agreementsLoading,
      filterSections,
      currentView,
      viewTransitionAgreementId,
      newlyCreatedAgreementId,
      editorTitle,
      editorName,
      editorFilters,
      pickerOpen,
      accountOpen,
      activeAccountId,
      activeAccountInitial,
      activeAccountAvatarUrl,
      activeAccountLabel,
      navActive,
      showBottomNav,
      goToFilterSettings,
      goToList,
      onAgreementEditorBack,
      onEditorRequestLeave,
      onAgreementEditorEdited,
      leaveTarget,
      leaveConfirmOpen,
      leaveConfirmTitle,
      leaveConfirmMessage,
      leaveConfirmConfirmLabel,
      leaveConfirmCancelLabel,
      leaveConfirmResetLabel,
      onLeaveConfirmPrimary,
      onLeaveConfirmSecondary,
      onLeaveConfirmDismiss,
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
      startCreateAgreement,
      onSaveAgreementDraft,
      onAddAgreementBlock,
      onDeleteAgreementBlock,
      onAddAgreementSection,
      onDeleteAgreementSection,
      onLaunchAgreement,
      onAgreementEditorUpdated,
      onResetAgreementVotes,
      onUpdateAgreementSection,
      onVote,
      editingAgreement,
      editingAgreementId,
      agreementEditorRef,
      searchTargetSectionId,
      onCreateAccount,
      onNotifications,
      onSwitchAccount,
      notificationSections,
      unreadNotificationsCount,
      otherAccountsNotificationsBadge,
      accountNotificationBadges,
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
