<template>
  <div v-if="pickerMode" class="concord-page concord-page--groups concord-page--groups-picker">
    <header class="concord-header concord-header--editor concord-header--groups">
      <button type="button" class="concord-icon-btn" aria-label="Назад" @click="onDone">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <h1 class="concord-header__title">Группы</h1>
      <ConcordGroupHeaderActions
        :show-count="false"
        @save="onDone"
      />
    </header>

    <main class="concord-groups concord-groups--picker">
      <div
        v-for="group in filteredGroups"
        :key="group.id"
        class="concord-groups-picker__row"
        role="button"
        tabindex="0"
        :aria-pressed="isSelected(group.id)"
        @click="toggleGroup(group.id)"
        @keydown.enter.prevent="toggleGroup(group.id)"
        @keydown.space.prevent="toggleGroup(group.id)"
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
        <span class="concord-notifications__group-title">{{ group.title }}</span>
        <span
          class="concord-groups-picker__checkbox"
          :class="{ 'concord-groups-picker__checkbox--checked': isSelected(group.id) }"
          aria-hidden="true"
        >
          <svg v-if="isSelected(group.id)" viewBox="0 0 16 16" width="12" height="12" fill="none">
            <path d="M3.5 8.2 6.4 11 12.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
      </div>

      <button
        type="button"
        class="concord-settings__edit concord-notifications__group-add"
        @click="$emit('create-group')"
      >
        <span class="concord-settings__edit-plus" aria-hidden="true">+</span>
        Создать группу
      </button>
    </main>
  </div>

  <div v-else class="concord-page concord-page--notifications">
    <header class="concord-notifications__profile concord-notifications__profile--subpage">
      <button type="button" class="concord-notifications__back" aria-label="Назад" @click="$emit('back')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M14 6 8 12l6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>

      <div class="concord-notifications__profile-main">
        <img
          v-if="profile.avatarUrl"
          :src="profile.avatarUrl"
          alt=""
          class="concord-notifications__avatar concord-notifications__avatar--image"
        >
        <span v-else class="concord-notifications__avatar" aria-hidden="true">{{ profile.avatarInitial }}</span>
        <h1 class="concord-notifications__name">{{ fullName }}</h1>
      </div>
    </header>

    <main class="concord-notifications">
      <section class="concord-accordion concord-accordion--static">
        <h2 class="concord-accordion__title">Группы</h2>

        <div class="concord-accordion__body concord-accordion__body--open">
          <div class="concord-profile-card">
            <button
              v-for="group in filteredGroups"
              :key="group.id"
              type="button"
              class="concord-notifications__group-row concord-notifications__group-row--clickable"
              @click="$emit('edit-group', group.id)"
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
              <span class="concord-notifications__group-title">{{ group.title }}</span>
              <div class="concord-notifications__group-actions">
                <button
                  type="button"
                  class="concord-notifications__group-action-btn"
                  aria-label="Удалить группу"
                  @click.stop="askDeleteGroup(group)"
                >
                  <ConcordGroupDeleteIcon />
                </button>
              </div>
            </button>
          </div>

          <button
            type="button"
            class="concord-settings__edit concord-notifications__group-add"
            @click="$emit('create-group')"
          >
            <span class="concord-settings__edit-plus" aria-hidden="true">+</span>
            Создать группу
          </button>
        </div>
      </section>
    </main>

    <ConcordConfirmSheet
      :open="Boolean(pendingDeleteGroup)"
      title="Удалить группу?"
      :message="deleteConfirmMessage"
      confirm-label="Удалить"
      cancel-label="Отмена"
      @confirm="confirmDeleteGroup"
      @cancel="cancelDeleteGroup"
    />
  </div>
</template>

<script>
import { computed, ref, toRef, watch } from 'vue'
import ConcordGroupDeleteIcon from '../concord/ConcordGroupDeleteIcon.vue'
import ConcordGroupHeaderActions from '../concord/ConcordGroupHeaderActions.vue'
import ConcordConfirmSheet from '../concord/ConcordConfirmSheet.vue'
import { useConcordProfile } from '../composables/useConcordProfile.js'

export default {
  name: 'GroupsManageView',
  components: { ConcordGroupDeleteIcon, ConcordGroupHeaderActions, ConcordConfirmSheet },
  props: {
    accountId: {
      type: String,
      default: '1',
    },
    groups: {
      type: Array,
      required: true,
    },
    pickerMode: {
      type: Boolean,
      default: false,
    },
    initialSelectedIds: {
      type: Array,
      default: () => [],
    },
  },
  emits: ['back', 'edit-group', 'delete-group', 'create-group', 'confirm'],
  setup(props, { emit }) {
    const { profile } = useConcordProfile(toRef(props, 'accountId'))
    const searchQuery = ref('')
    const selectedGroupIds = ref([...props.initialSelectedIds])
    const pendingDeleteGroup = ref(null)

    const fullName = computed(() => `${profile.value.firstName} ${profile.value.lastName}`.trim())

    watch(
      () => props.initialSelectedIds.join(','),
      () => {
        selectedGroupIds.value = [...props.initialSelectedIds]
      }
    )

    const filteredGroups = computed(() => {
      const query = searchQuery.value.trim().toLowerCase()
      if (!query) {
        return props.groups
      }
      return props.groups.filter((group) => group.title.toLowerCase().includes(query))
    })

    const deleteConfirmMessage = computed(() => {
      if (!pendingDeleteGroup.value) {
        return ''
      }
      return `Группа «${pendingDeleteGroup.value.title}» будет удалена без возможности восстановления.`
    })

    function groupMemberCount(group) {
      return group.memberCount || group.memberIds?.length || 0
    }

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

    function askDeleteGroup(group) {
      pendingDeleteGroup.value = group
    }

    function cancelDeleteGroup() {
      pendingDeleteGroup.value = null
    }

    function confirmDeleteGroup() {
      if (!pendingDeleteGroup.value) {
        return
      }
      emit('delete-group', pendingDeleteGroup.value.id)
      pendingDeleteGroup.value = null
    }

    function onDone() {
      if (props.pickerMode) {
        emit('confirm', [...selectedGroupIds.value])
      }
    }

    return {
      profile,
      fullName,
      searchQuery,
      filteredGroups,
      pendingDeleteGroup,
      deleteConfirmMessage,
      groupMemberCount,
      isSelected,
      toggleGroup,
      onDone,
      askDeleteGroup,
      cancelDeleteGroup,
      confirmDeleteGroup,
    }
  },
}
</script>
