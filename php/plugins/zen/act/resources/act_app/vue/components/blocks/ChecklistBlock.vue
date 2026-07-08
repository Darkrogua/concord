<template>
  <section v-if="mode === 'site'" class="tg-site-checklist tg-checklist">
    <header class="tg-checklist__header">
      <h2 class="tg-checklist__title">{{ block.name || 'Чеклист' }}</h2>
    </header>

    <p v-if="originalDescription" class="tg-checklist__description">{{ originalDescription }}</p>

    <div v-if="originalItems.length > 0" class="tg-checklist__items">
      <div v-for="item in originalItems" :key="item.id" class="tg-checklist__item">
        <div class="tg-checklist__marker" aria-hidden="true">{{ item.signature ? '✓' : '' }}</div>
        <div class="tg-checklist__body">
          <div class="tg-checklist__text">{{ item.text || 'Пункт без текста' }}</div>
          <div class="tg-checklist__meta">
            <span v-if="item.signer">Подписант: @{{ item.signer.login }}</span>
            <span v-else>Подписант не назначен</span>
            <span v-if="item.signature" class="tg-checklist__signature">
              Подписано @{{ item.signature.login }} {{ formatSignedAt(item.signature.signed_at) }}
            </span>
          </div>
        </div>
        <button
          v-if="canSign(item)"
          class="tg-btn tg-btn--ghost"
          type="button"
          :disabled="signingItemId === item.id"
          @click="signItem(item)"
        >
          {{ signingItemId === item.id ? '…' : 'Подписать' }}
        </button>
      </div>
    </div>
    <p v-if="signError" class="tg-error tg-error--inline">{{ signError }}</p>
  </section>

  <article v-else :class="['tg-block-card', 'tg-checklist', { 'tg-block-card--editing': isEdit }]">
    <header v-if="viewer === 'owner'" class="tg-block-card__header">
      <BlockTitleEdit
        :name="block.name"
        type-label="Чеклист"
        fallback="Чеклист"
        :editable="viewer === 'owner'"
        @save-name="saveName"
      />
      <span v-if="isEdit && dirty" class="tg-block-card__status">Есть изменения</span>
    </header>
    <header v-else class="tg-checklist__header">
      <h2 class="tg-checklist__title">{{ block.name || 'Чеклист' }}</h2>
    </header>

    <template v-if="isEdit">
      <label class="tg-field">
        <span class="tg-field__label">Описание</span>
        <textarea
          v-model="description"
          class="tg-block-textarea"
          rows="2"
          :disabled="saving"
          placeholder="Что нужно согласовать…"
        />
      </label>

      <div class="tg-checklist-editor">
        <div v-for="item in items" :key="item.id" class="tg-checklist-editor__item">
          <div class="tg-checklist-editor__main">
            <input
              v-model="item.text"
              class="tg-input"
              type="text"
              :disabled="saving || isSigned(item)"
              placeholder="Пункт чеклиста"
            >
            <select
              v-model="item.signerLogin"
              class="tg-input"
              :disabled="saving || isSigned(item) || signerOptions.length === 0"
            >
              <option value="">Без подписанта</option>
              <option v-for="user in signerOptions" :key="user.login" :value="user.login">
                {{ user.display_name }} (@{{ user.login }})
              </option>
            </select>
          </div>
          <div class="tg-checklist-editor__side">
            <span v-if="isSigned(item)" class="tg-checklist__signed">Подписан</span>
            <button
              v-else
              class="tg-btn tg-btn--ghost tg-btn--icon"
              type="button"
              :disabled="saving"
              aria-label="Удалить пункт"
              @click="removeItem(item)"
            >
              ×
            </button>
          </div>
        </div>
      </div>

      <p v-if="signerOptions.length === 0" class="tg-field__hint">
        Нет пользователей с доступом к блоку. Добавьте их в настройках акта или блока.
      </p>
      <p v-if="error" class="tg-error tg-error--inline">{{ error }}</p>

      <div class="tg-block-actions tg-block-actions--compact">
        <button class="tg-btn tg-btn--ghost" type="button" :disabled="saving" @click="addItem">
          Добавить пункт
        </button>
        <button class="tg-btn" type="button" :disabled="saving || !dirty" @click="save">
          {{ saving ? '…' : 'Сохранить' }}
        </button>
        <button class="tg-btn tg-btn--ghost" type="button" :disabled="saving" @click="cancelEdit">
          Готово
        </button>
        <button class="tg-btn tg-btn--danger" type="button" :disabled="saving" @click="remove">
          Удалить
        </button>
      </div>
    </template>

    <template v-else>
      <p v-if="originalDescription" class="tg-checklist__description">{{ originalDescription }}</p>
      <p v-else-if="viewer === 'owner'" class="tg-block-content tg-block-content--empty">Описание не задано</p>

      <div v-if="originalItems.length > 0" class="tg-checklist__items">
        <div v-for="item in originalItems" :key="item.id" class="tg-checklist__item">
          <div class="tg-checklist__marker" aria-hidden="true">{{ item.signature ? '✓' : '' }}</div>
          <div class="tg-checklist__body">
            <div class="tg-checklist__text">{{ item.text || 'Пункт без текста' }}</div>
            <div class="tg-checklist__meta">
              <span v-if="item.signer">Подписант: @{{ item.signer.login }}</span>
              <span v-else>Подписант не назначен</span>
              <span v-if="item.signature" class="tg-checklist__signature">
                Подписано @{{ item.signature.login }} {{ formatSignedAt(item.signature.signed_at) }}
              </span>
            </div>
          </div>
          <button
            v-if="canSign(item)"
            class="tg-btn tg-btn--ghost"
            type="button"
            :disabled="signingItemId === item.id"
            @click="signItem(item)"
          >
            {{ signingItemId === item.id ? '…' : 'Подписать' }}
          </button>
        </div>
      </div>
      <p v-else class="tg-block-content tg-block-content--empty">Пункты чеклиста пока не добавлены</p>
      <p v-if="signError" class="tg-error tg-error--inline">{{ signError }}</p>

      <div v-if="viewer === 'owner'" class="tg-block-actions tg-block-actions--compact">
        <BlockSettingsButton @click="$emit('settings', block)" />
        <BlockEditButton @click="startEdit" />
      </div>
    </template>
  </article>
</template>

<script>
import { computed, onMounted, ref, watch } from 'vue'
import { useAppState } from '../../app-state'
import BlockEditButton from './BlockEditButton.vue'
import BlockSettingsButton from './BlockSettingsButton.vue'
import BlockTitleEdit from './BlockTitleEdit.vue'

const cloneItems = (items) => (Array.isArray(items) ? items : []).map((item) => ({
  id: String(item?.id || `item-${Date.now()}-${Math.random().toString(36).slice(2)}`),
  text: String(item?.text || ''),
  signerLogin: item?.signer?.login ? String(item.signer.login) : '',
  signature: item?.signature || null,
}))

export default {
  name: 'ChecklistBlock',
  components: {
    BlockEditButton,
    BlockSettingsButton,
    BlockTitleEdit,
  },
  props: {
    actId: {
      type: String,
      default: '',
    },
    block: {
      type: Object,
      required: true,
    },
    viewer: {
      type: String,
      default: 'guest',
      validator: (value) => ['owner', 'guest'].includes(value),
    },
    mode: {
      type: String,
      default: 'edit',
      validator: (value) => ['edit', 'site'].includes(value),
    },
    viewAsLogin: {
      type: String,
      default: '',
    },
    startInEditMode: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['save', 'delete', 'settings', 'sign-checklist-item'],
  setup(props, { emit }) {
    const { state, api } = useAppState()
    const description = ref(props.block?.data?.description || '')
    const items = ref(cloneItems(props.block?.data?.items))
    const editing = ref(props.startInEditMode)
    const saving = ref(false)
    const error = ref('')
    const signingItemId = ref('')
    const signError = ref('')
    const signerOptions = ref([])

    const originalDescription = computed(() => props.block?.data?.description || '')
    const originalItems = computed(() => Array.isArray(props.block?.data?.items) ? props.block.data.items : [])
    const signerByLogin = computed(() => new Map(signerOptions.value.map((user) => [user.login, user])))
    const isEdit = computed(() => props.viewer === 'owner' && editing.value)
    const draftSignature = computed(() => JSON.stringify({
      description: description.value,
      items: items.value.map((item) => ({
        id: item.id,
        text: item.text,
        signerLogin: item.signerLogin,
      })),
    }))
    const originalSignature = computed(() => JSON.stringify({
      description: originalDescription.value,
      items: originalItems.value.map((item) => ({
        id: item.id,
        text: item.text || '',
        signerLogin: item.signer?.login ? String(item.signer.login) : '',
      })),
    }))
    const dirty = computed(() => draftSignature.value !== originalSignature.value)

    const loadSignerOptions = () => {
      if (!props.actId || !props.block?.id) {
        signerOptions.value = []
        return
      }
      api({
        api: `Access:get?act_id=${encodeURIComponent(props.actId)}&resource_type=block&resource_id=${encodeURIComponent(props.block.id)}`,
        then: (res) => {
          if (!res?.ok) {
            signerOptions.value = []
            return
          }
          const options = Array.isArray(res.data?.signer_options) ? res.data.signer_options : []
          signerOptions.value = options
            .filter((user) => user.login && user.login !== '@public' && user.login !== '@authenticated')
            .map((user) => ({
              login: user.login,
              display_name: user.display_name || user.login,
              role: user.role || 'viewer',
            }))
        },
        catch: () => {
          signerOptions.value = []
        },
      })
    }

    const resetDraft = () => {
      description.value = props.block?.data?.description || ''
      items.value = cloneItems(props.block?.data?.items)
      error.value = ''
      signError.value = ''
    }

    watch(
      () => props.block,
      () => {
        resetDraft()
        if (!props.startInEditMode) {
          editing.value = false
        }
        loadSignerOptions()
      },
      { immediate: true }
    )

    watch(
      () => props.startInEditMode,
      (value) => {
        if (value) {
          editing.value = true
        }
      }
    )

    onMounted(() => {
      loadSignerOptions()
    })

    const isSigned = (item) => Boolean(item.signature)
    const startEdit = () => {
      editing.value = true
      loadSignerOptions()
    }
    const cancelEdit = () => {
      resetDraft()
      editing.value = false
    }
    const addItem = () => {
      items.value = [
        ...items.value,
        {
          id: `item-${Date.now()}-${Math.random().toString(36).slice(2)}`,
          text: '',
          signerLogin: '',
          signature: null,
        },
      ]
    }
    const removeItem = (item) => {
      items.value = items.value.filter((entry) => entry.id !== item.id)
    }
    const itemPayload = (item) => {
      const login = item.signerLogin ? String(item.signerLogin) : ''
      const option = login ? signerByLogin.value.get(login) : null
      return {
        id: item.id,
        text: item.text,
        signer: login
          ? {
            login,
            display_name: option?.display_name || login,
          }
          : null,
        signature: item.signature,
      }
    }
    const save = () => {
      if (saving.value) {
        return
      }
      saving.value = true
      error.value = ''
      emit('save', {
        block: props.block,
        data: {
          ...(props.block.data || {}),
          type: 'checklist',
          description: description.value,
          items: items.value.map(itemPayload),
        },
        done: () => {
          saving.value = false
          editing.value = false
        },
        fail: (message) => {
          saving.value = false
          error.value = message || 'Не удалось сохранить чеклист'
        },
      })
    }
    const saveName = ({ name, done, fail }) => {
      emit('save', {
        block: props.block,
        data: props.block.data || { type: 'checklist', description: '', items: [] },
        name,
        done,
        fail,
      })
    }
    const remove = () => {
      if (!saving.value) {
        emit('delete', props.block)
      }
    }
    const currentUserLogin = () => {
      const login = state.user?.login || state.user?.username || ''
      return String(login).trim().toLowerCase()
    }
    const canSign = (item) => {
      if (props.mode !== 'site' || item.signature || signingItemId.value) {
        return false
      }
      const userLogin = currentUserLogin()
      const signerLogin = String(item.signer?.login || '').trim().toLowerCase()
      return userLogin !== '' && signerLogin !== '' && userLogin === signerLogin
    }
    const signItem = (item) => {
      if (!canSign(item)) {
        return
      }
      signingItemId.value = item.id
      signError.value = ''
      emit('sign-checklist-item', {
        block: props.block,
        item,
        done: () => {
          signingItemId.value = ''
        },
        fail: (message) => {
          signingItemId.value = ''
          signError.value = message || 'Не удалось подписать пункт'
        },
      })
    }
    const formatSignedAt = (value) => {
      if (!value) {
        return ''
      }
      try {
        return new Intl.DateTimeFormat('ru-RU', {
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit',
        }).format(new Date(value))
      } catch {
        return value
      }
    }

    return {
      description,
      items,
      saving,
      error,
      signingItemId,
      signError,
      originalDescription,
      originalItems,
      signerOptions,
      isEdit,
      dirty,
      isSigned,
      startEdit,
      cancelEdit,
      addItem,
      removeItem,
      save,
      saveName,
      remove,
      canSign,
      signItem,
      formatSignedAt,
    }
  },
}
</script>
