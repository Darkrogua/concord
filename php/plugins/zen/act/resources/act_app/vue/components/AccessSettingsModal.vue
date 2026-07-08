<template>
  <EditFieldModal
    :open="open"
    :title="title"
    :title-id="titleId"
    @close="$emit('close')"
  >
    <AsyncRegion :loading="loading" variant="modal" pattern="modal">
      <div class="tg-block-settings">
        <div v-if="showInheritMode" class="tg-access-inherit">
          <p class="tg-access-inherit__title">Наследуется от акта</p>
          <p class="tg-field__hint">Новый блок виден тем же пользователям, что и акт. Можно задать отдельный список.</p>
          <div class="tg-user-picker__chips tg-access-inherit__chips">
            <span
              v-if="inheritedPublic"
              class="tg-chip tg-chip--readonly tg-chip--active"
            >Публичный доступ</span>
            <span
              v-else-if="inheritedUsers.length === 0"
              class="tg-chip tg-chip--readonly"
            >Только владелец</span>
            <span
              v-for="user in inheritedUsers"
              :key="user.login"
              class="tg-chip tg-chip--readonly"
            >
              <span>{{ user.display_name }}</span>
              <span class="tg-chip__login">@{{ user.login }}</span>
            </span>
          </div>
          <button
            class="tg-btn tg-btn--ghost tg-access-inherit__override"
            type="button"
            :disabled="saving"
            @click="enableOverride"
          >
            Задать свой список
          </button>
        </div>

        <template v-else>
          <label class="tg-switch-row">
            <span class="tg-switch-row__body">
              <span class="tg-switch-row__title">{{ publicLabel }}</span>
              <span class="tg-switch-row__hint">{{ publicHint }}</span>
            </span>
            <input v-model="isPublic" class="tg-switch-row__input" type="checkbox" :disabled="saving">
          </label>

          <div class="tg-field">
            <label class="tg-field__label" :for="inputId">Пользователи</label>
            <UserPicker
              v-model="selectedUsers"
              :input-id="inputId"
              :disabled="saving || isPublic"
              :show-all-chip="!isPublic"
            />
            <p class="tg-field__hint">{{ usersHint }}</p>
          </div>

          <div v-if="showRolePicker" class="tg-field">
            <label class="tg-field__label" :for="`${inputId}-role`">Роль для новых пользователей</label>
            <select :id="`${inputId}-role`" v-model="defaultRole" class="tg-input" :disabled="saving">
              <option value="viewer">Просмотр</option>
              <option value="editor">Редактирование</option>
              <option value="signer">Подписание</option>
              <option value="steward">Управление доступом</option>
            </select>
          </div>

          <button
            v-if="canResetToInherit"
            class="tg-btn tg-btn--ghost tg-access-inherit__reset"
            type="button"
            :disabled="saving"
            @click="resetToInherit"
          >
            Наследовать от акта
          </button>
        </template>

        <p v-if="error" class="tg-error tg-error--inline">{{ error }}</p>

        <div class="tg-block-actions">
          <button class="tg-btn" type="button" :disabled="saving || loading" @click="save">
            {{ saving ? '…' : 'Сохранить' }}
          </button>
          <button class="tg-btn tg-btn--ghost" type="button" :disabled="saving" @click="$emit('close')">
            Отмена
          </button>
        </div>
      </div>
    </AsyncRegion>
  </EditFieldModal>
</template>

<script>
import { computed, ref, watch } from 'vue'
import { useAppState } from '../app-state'
import EditFieldModal from './EditFieldModal.vue'
import AsyncRegion from './AsyncRegion.vue'
import UserPicker from './UserPicker.vue'

const PUBLIC = '@public'
const AUTH = '@authenticated'

const mapUserGrants = (grants) => grants
  .filter((g) => g.login !== PUBLIC && g.login !== AUTH)
  .map((g) => ({
    login: g.login,
    display_name: g.display_name || g.login,
    role: g.role || 'viewer',
  }))

const hasPublicGrant = (grants) => grants.some((g) => g.login === PUBLIC)

export default {
  name: 'AccessSettingsModal',
  components: { EditFieldModal, AsyncRegion, UserPicker },
  props: {
    open: {
      type: Boolean,
      default: false,
    },
    title: {
      type: String,
      default: 'Настройки доступа',
    },
    titleId: {
      type: String,
      default: 'access-settings-modal-title',
    },
    actId: {
      type: String,
      required: true,
    },
    resourceType: {
      type: String,
      default: 'act',
    },
    resourceId: {
      type: String,
      default: '',
    },
    saving: {
      type: Boolean,
      default: false,
    },
    error: {
      type: String,
      default: '',
    },
    publicLabel: {
      type: String,
      default: 'Публичный доступ',
    },
    publicHint: {
      type: String,
      default: 'Если включено, ресурс виден всем (в рамках прав родителя).',
    },
    usersHint: {
      type: String,
      default: 'Если выбрать пользователей, доступ будет только у них.',
    },
    showRolePicker: {
      type: Boolean,
      default: false,
    },
    defaultRoleValue: {
      type: String,
      default: 'viewer',
    },
  },
  emits: ['close', 'save'],
  setup(props, { emit }) {
    const { api } = useAppState()
    const loading = ref(false)
    const isPublic = ref(true)
    const selectedUsers = ref([])
    const defaultRole = ref(props.defaultRoleValue)
    const inheritsFromAct = ref(false)
    const customOverride = ref(false)
    const inheritedUsers = ref([])
    const inheritedPublic = ref(false)
    const inheritedEffectiveGrants = ref([])
    const inputId = `access-settings-${props.resourceType}`

    const showInheritMode = computed(() => (
      props.resourceType === 'block' && inheritsFromAct.value && !customOverride.value
    ))

    const canResetToInherit = computed(() => (
      props.resourceType === 'block' && (customOverride.value || !inheritsFromAct.value)
    ))

    const applyGrantsToForm = (grants) => {
      isPublic.value = props.resourceType === 'act'
        ? (grants.length === 0 || hasPublicGrant(grants))
        : hasPublicGrant(grants)
      selectedUsers.value = mapUserGrants(grants)
      if (selectedUsers.value.length > 0) {
        defaultRole.value = selectedUsers.value[0].role || props.defaultRoleValue
      }
    }

    const applyInheritedState = (effectiveGrants, inheritedFrom) => {
      inheritedEffectiveGrants.value = Array.isArray(effectiveGrants) ? [...effectiveGrants] : []
      inheritsFromAct.value = props.resourceType === 'block' && inheritedFrom === 'act'
      if (inheritsFromAct.value) {
        inheritedPublic.value = hasPublicGrant(effectiveGrants)
        inheritedUsers.value = mapUserGrants(effectiveGrants)
        customOverride.value = false
        return
      }

      inheritsFromAct.value = false
      customOverride.value = true
      applyGrantsToForm(Array.isArray(effectiveGrants) ? effectiveGrants : [])
    }

    const load = () => {
      if (!props.open || !props.actId) {
        return
      }
      loading.value = true
      const resourceId = props.resourceId || props.actId
      api({
        api: `Access:get?act_id=${encodeURIComponent(props.actId)}&resource_type=${encodeURIComponent(props.resourceType)}&resource_id=${encodeURIComponent(resourceId)}`,
        then: (res) => {
          loading.value = false
          if (!res?.ok) {
            return
          }
          const grants = Array.isArray(res.data?.grants) ? res.data.grants : []
          const effectiveGrants = Array.isArray(res.data?.effective_grants) ? res.data.effective_grants : grants
          const inheritedFrom = res.data?.inherited_from ?? null

          if (props.resourceType === 'block' && inheritedFrom === 'act') {
            applyInheritedState(effectiveGrants, inheritedFrom)
            return
          }

          inheritedEffectiveGrants.value = [...effectiveGrants]
          inheritsFromAct.value = false
          customOverride.value = true
          applyGrantsToForm(grants)
        },
        catch: () => {
          loading.value = false
        },
      })
    }

    const buildGrants = () => {
      const grants = []
      if (isPublic.value) {
        grants.push({ login: PUBLIC, role: 'viewer' })
      }
      const role = defaultRole.value || 'viewer'
      for (const user of selectedUsers.value) {
        grants.push({
          login: user.login,
          role: user.role || role,
        })
      }
      return grants
    }

    const enableOverride = () => {
      customOverride.value = true
      isPublic.value = inheritedPublic.value
      selectedUsers.value = inheritedUsers.value.map((user) => ({ ...user }))
      if (selectedUsers.value.length > 0) {
        defaultRole.value = selectedUsers.value[0].role || props.defaultRoleValue
      }
    }

    const resetToInherit = () => {
      customOverride.value = false
      inheritsFromAct.value = true
      inheritedPublic.value = hasPublicGrant(inheritedEffectiveGrants.value)
      inheritedUsers.value = mapUserGrants(inheritedEffectiveGrants.value)
      isPublic.value = false
      selectedUsers.value = []
    }

    const save = () => {
      const grants = showInheritMode.value ? [] : buildGrants()
      emit('save', {
        grants,
        resource_type: props.resourceType,
        resource_id: props.resourceId || props.actId,
      })
    }

    watch(() => props.open, (open) => {
      if (open) {
        defaultRole.value = props.defaultRoleValue
        customOverride.value = false
        inheritsFromAct.value = false
        load()
      }
    })

    return {
      loading,
      isPublic,
      selectedUsers,
      defaultRole,
      inputId,
      showInheritMode,
      canResetToInherit,
      inheritedUsers,
      inheritedPublic,
      enableOverride,
      resetToInherit,
      save,
    }
  },
}
</script>
