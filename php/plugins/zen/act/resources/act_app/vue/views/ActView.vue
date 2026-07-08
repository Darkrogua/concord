<template>
  <div
    class="tg-act-view"
    :class="{
      'tg-act-view--site': isSiteView,
      'tg-act-view--boot': !pageReady,
    }"
  >
    <template v-if="!pageReady">
      <main class="tg-main tg-main--no-tab tg-main--boot">
        <AsyncRegion
          :loading="true"
          variant="page"
          loader="spinner"
          :shell-delay-ms="2000"
          :shell-fade-ms="400"
        />
      </main>
    </template>

    <template v-else>
      <header v-if="showActChrome" ref="headerRef" class="tg-header">
      <template v-if="!shareOpen">
        <button type="button" class="tg-header__back" aria-label="Назад" @click="goBack">
          ←
        </button>
        <div v-if="nameEditing" class="tg-header-title-edit" @click.stop>
          <input
            ref="nameInputRef"
            v-model="nameDraft"
            class="tg-header-title-edit__input"
            type="text"
            maxlength="255"
            aria-label="Название акта"
            :disabled="savingName"
            @keydown.enter.prevent="saveActName"
            @keydown.escape.prevent="cancelNameEdit"
          >
          <button
            type="button"
            class="tg-header-title-edit__save"
            aria-label="Сохранить название"
            :disabled="savingName"
            @click.stop="saveActName"
          >
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path
                d="M20 6L9 17l-5-5"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </button>
        </div>
        <h1
          v-else
          :class="['tg-header__title', { 'tg-header__title--editable': isMine && act }]"
          @click="startNameEdit"
        >
          {{ act?.name || 'Акт' }}
        </h1>
        <button
          v-if="act"
          type="button"
          class="tg-header__action"
          aria-label="Поделиться"
          @click.stop="openShare"
        >
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
              d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
            />
            <path
              d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
            />
          </svg>
        </button>
        <span v-else style="width:36px" />
      </template>
      <div v-else class="tg-header-share" @click.stop>
        <input
          ref="shareInputRef"
          class="tg-header-share__input"
          type="text"
          :value="shareUrl"
          readonly
          aria-label="Ссылка на акт"
          @focus="selectShareUrl"
        >
        <button
          type="button"
          class="tg-header-share__copy"
          :aria-label="copyFeedback || 'Копировать ссылку'"
          @click.stop="copyShareUrl"
        >
          <svg v-if="!copyFeedback" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <rect x="9" y="9" width="13" height="13" rx="2" stroke="currentColor" stroke-width="1.8" />
            <path
              d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"
              stroke="currentColor"
              stroke-width="1.8"
            />
          </svg>
          <span v-else class="tg-header-share__done" aria-hidden="true">✓</span>
        </button>
      </div>
    </header>

    <main :class="['tg-main', isSiteView ? 'tg-main--site' : 'tg-main--no-tab']">
      <p v-if="error" class="tg-error" :class="{ 'tg-error--site': isSiteView }">{{ error }}</p>
      <template v-else-if="act">
        <ActControlPanel
          v-if="showActPanel"
          :blocks-count="blocksCount"
          :states-count="statesCount"
          :is-mine="isMine"
          :owner-login="owner?.login || ''"
          :preview-url="previewUrl"
          :show-tags="showTagsBar"
          :tags-count="myTags.length"
          :reorder-enabled="reorderEnabled"
          @open-states="openStates"
          @open-access="openActAccess"
          @open-tags="openTags"
          @toggle-reorder="toggleReorder"
        />

        <p v-if="showOwnerPanel && nameError" class="tg-error tg-error--inline tg-act-errors">{{ nameError }}</p>
        <p v-if="showOwnerPanel && blocksError" class="tg-error tg-error--inline tg-act-errors">{{ blocksError }}</p>

        <BlockList
          :blocks="blocks"
          :act-id="uuid"
          :viewer="isGuestView ? 'guest' : 'owner'"
          :view-as-login="viewAsLogin"
          :can-add="showOwnerPanel"
          :adding="creatingBlock"
          :reordering="reordering"
          :reorder-enabled="reorderEnabled"
          :edit-block-id="editBlockId"
          @add="openAddBlockModal"
          @save="saveBlock"
          @delete="deleteBlock"
          @reorder="reorderBlocks"
          @settings="openBlockSettings"
          @sign-checklist-item="signChecklistItem"
        />
      </template>
    </main>

    <aside
      v-if="isPreview"
      class="tg-preview-bar"
      :class="{ 'tg-preview-bar--expanded': previewBarExpanded }"
    >
      <button
        type="button"
        class="tg-preview-bar__tab"
        :aria-expanded="previewBarExpanded"
        :aria-label="previewBarExpanded ? 'Скрыть панель предпросмотра' : 'Показать панель предпросмотра'"
        @click="togglePreviewBar"
      >
        <span class="tg-preview-bar__tab-icon" aria-hidden="true">{{ previewBarExpanded ? '‹' : '›' }}</span>
        <span class="tg-preview-bar__tab-label">Просмотр</span>
      </button>
      <div class="tg-preview-bar__panel-wrap">
        <div class="tg-preview-bar__panel">
          <label class="tg-preview-bar__viewer">
            <span class="tg-preview-bar__viewer-label">Смотреть как:</span>
            <select
              class="tg-input tg-preview-bar__select"
              aria-label="Смотреть как"
              :value="previewAs"
              :disabled="audiencesLoading"
              @change="onPreviewAsChange"
            >
              <option v-for="item in audiences" :key="item.login" :value="item.login">
                {{ item.display_name }}
              </option>
            </select>
          </label>
          <router-link class="tg-preview-bar__exit" :to="editActRoute">К редактированию</router-link>
        </div>
      </div>
    </aside>
    </template>

    <AddBlockModal
      :open="addBlockOpen"
      :creating="creatingBlock"
      @close="closeAddBlockModal"
      @select="createBlock"
    />

    <BlockSettingsModal
      :open="Boolean(settingsBlock)"
      :act-id="uuid"
      :block="settingsBlock"
      :saving="settingsSaving"
      :error="settingsError"
      @close="closeBlockSettings"
      @save="saveBlockSettings"
    />

    <ActTagsModal
      :open="tagsOpen"
      :act-id="uuid"
      :tags="myTags"
      :saving="tagsSaving"
      :error="tagsError"
      @close="closeTags"
      @save="saveTags"
    />

    <AccessSettingsModal
      :open="actAccessOpen"
      :act-id="uuid"
      resource-type="act"
      :resource-id="uuid"
      title="Доступ к акту"
      title-id="act-access-modal-title"
      :saving="actAccessSaving"
      :error="actAccessError"
      @close="closeActAccess"
      @save="saveActAccess"
    />

    <ActStatesModal
      :open="statesOpen"
      :snapshots="snapshots"
      :loading="statesLoading"
      :error="statesError"
      :restoring-key="restoringKey"
      :merging="statesMerging"
      :user-timezone="userTimezone"
      @close="closeStates"
      @restore="restoreState"
      @merge="mergeStates"
    />
  </div>
</template>

<script>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAppState } from '../app-state'
import { formatUtcCompactTimestamp } from '../timezone'
import ActControlPanel from '../components/ActControlPanel.vue'
import AsyncRegion from '../components/AsyncRegion.vue'
import ActTagsModal from '../components/ActTagsModal.vue'
import AccessSettingsModal from '../components/AccessSettingsModal.vue'
import ActStatesModal from '../components/ActStatesModal.vue'
import AddBlockModal from '../components/blocks/AddBlockModal.vue'
import BlockList from '../components/blocks/BlockList.vue'
import BlockSettingsModal from '../components/blocks/BlockSettingsModal.vue'
import { defaultBlockName, defaultBlockPayload } from '../components/blocks/block-types'
import { useActPreview } from '../composables/useActPreview.js'
import { useBlockReorderPreference } from '../composables/useBlockReorderPreference.js'

const isPreviewQuery = (value) => {
  if (value === undefined || value === null || value === '') {
    return false
  }
  if (value === '0' || value === 'false') {
    return false
  }
  return true
}

export default {
  name: 'ActView',
  components: { ActControlPanel, AsyncRegion, ActTagsModal, AccessSettingsModal, ActStatesModal, AddBlockModal, BlockList, BlockSettingsModal },
  props: {
    uuid: {
      type: String,
      required: true,
    },
  },
  setup(props) {
    const route = useRoute()
    const router = useRouter()
    const { state, api } = useAppState()
    const { reorderEnabled, toggleReorder } = useBlockReorderPreference()
    const act = ref(null)
    const owner = ref(null)
    const isMine = ref(false)
    const loading = ref(true)
    const error = ref('')
    const blocks = ref([])
    const blocksLoading = ref(false)
    const blocksError = ref('')
    const contentLoading = computed(() => loading.value || blocksLoading.value)
    const pageReady = computed(() => {
      if (contentLoading.value) {
        return false
      }
      if (error.value) {
        return true
      }
      return act.value !== null
    })
    const creatingBlock = ref(false)
    const shareOpen = ref(false)
    const shareInputRef = ref(null)
    const headerRef = ref(null)
    const copyFeedback = ref('')
    const nameEditing = ref(false)
    const nameDraft = ref('')
    const nameInputRef = ref(null)
    const savingName = ref(false)
    const nameError = ref('')
    const blocksCount = ref(0)
    const statesCount = ref(0)
    const statesOpen = ref(false)
    const statesLoading = ref(false)
    const statesError = ref('')
    const snapshots = ref([])
    const restoringKey = ref('')
    const statesMerging = ref(false)
    const addBlockOpen = ref(false)
    const settingsBlock = ref(null)
    const settingsSaving = ref(false)
    const settingsError = ref('')
    const actAccessOpen = ref(false)
    const actAccessSaving = ref(false)
    const actAccessError = ref('')
    const editBlockId = ref('')
    const reordering = ref(false)
    const myTags = ref([])
    const tagsOpen = ref(false)
    const tagsSaving = ref(false)
    const tagsError = ref('')
    const defaultDocumentTitle = document.title

    const isPreview = computed(() => isMine.value && isPreviewQuery(route.query.preview))
    const previewBarExpanded = ref(false)
    const togglePreviewBar = () => {
      previewBarExpanded.value = !previewBarExpanded.value
    }
    const preview = useActPreview(computed(() => props.uuid), { isPreview })
    const {
      previewAs,
      audiences,
      audiencesLoading,
      ensureReady: ensurePreviewReady,
      setPreviewAs,
      querySuffix: previewQuerySuffix,
      viewAsLogin,
    } = preview

    const shareUrl = computed(() => `${window.location.origin}/act_${props.uuid}`)
    const isGuestView = computed(() => !isMine.value || isPreview.value)
    const showOwnerPanel = computed(() => isMine.value && !isPreview.value)
    const showTagsBar = computed(() => Boolean(state.user && act.value && !isPreview.value))
    const showActPanel = computed(() => showOwnerPanel.value || showTagsBar.value)
    const isSiteView = computed(() => Boolean(act.value && !loading.value && isGuestView.value))
    const showActChrome = computed(() => showOwnerPanel.value)
    const previewUrl = computed(() => {
      const path = router.resolve({
        name: 'act',
        params: { uuid: props.uuid },
        query: { preview: 1 },
      }).href
      return `${window.location.origin}${path}`
    })
    const editActRoute = computed(() => ({
      name: 'act',
      params: { uuid: props.uuid },
    }))
    const userTimezone = computed(() => state.user?.timezone || '')

    const closeShare = () => {
      shareOpen.value = false
      copyFeedback.value = ''
    }

    const openShare = () => {
      cancelNameEdit()
      shareOpen.value = true
      copyFeedback.value = ''
      window.requestAnimationFrame(() => {
        shareInputRef.value?.focus()
        shareInputRef.value?.select()
      })
    }

    const selectShareUrl = (event) => {
      event.target?.select?.()
    }

    const copyShareUrl = async () => {
      const url = shareUrl.value

      try {
        if (navigator.clipboard?.writeText) {
          await navigator.clipboard.writeText(url)
        } else {
          throw new Error('clipboard unavailable')
        }
        copyFeedback.value = 'Скопировано'
      } catch {
        const input = shareInputRef.value
        if (input) {
          input.focus()
          input.select()
          try {
            document.execCommand('copy')
            copyFeedback.value = 'Скопировано'
          } catch {
            copyFeedback.value = 'Выделите вручную'
          }
        }
      }

      if (copyFeedback.value === 'Скопировано') {
        window.setTimeout(() => {
          copyFeedback.value = ''
        }, 2000)
      }
    }

    const onDocumentClick = (event) => {
      const header = headerRef.value
      if (!header) {
        return
      }

      const path = typeof event.composedPath === 'function' ? event.composedPath() : []
      const insideHeader = path.includes(header) || header.contains(event.target)

      if (nameEditing.value && !insideHeader) {
        cancelNameEdit()
      }

      if (!shareOpen.value) {
        return
      }

      if (insideHeader) {
        return
      }

      closeShare()
    }

    const onDocumentKeydown = (event) => {
      if (nameEditing.value && event.key === 'Escape') {
        cancelNameEdit()
        return
      }

      if (shareOpen.value && event.key === 'Escape') {
        closeShare()
      }
    }

    const startNameEdit = () => {
      if (!isMine.value || !act.value || shareOpen.value || nameEditing.value) {
        return
      }

      closeShare()
      nameError.value = ''
      nameDraft.value = act.value.name || ''
      nameEditing.value = true
      window.requestAnimationFrame(() => {
        nameInputRef.value?.focus()
        nameInputRef.value?.select()
      })
    }

    const cancelNameEdit = () => {
      nameEditing.value = false
      nameDraft.value = act.value?.name || ''
      nameError.value = ''
    }

    const saveActName = () => {
      if (!isMine.value || !act.value || savingName.value) {
        return
      }

      const name = nameDraft.value.trim()
      if (!name) {
        nameError.value = 'Введите название'
        return
      }

      if (name === act.value.name) {
        cancelNameEdit()
        return
      }

      savingName.value = true
      nameError.value = ''
      api({
        api: 'Acts:update',
        data: {
          id: props.uuid,
          name,
        },
        then: (res) => {
          savingName.value = false
          if (res?.ok && res.data?.act) {
            act.value = { ...act.value, ...res.data.act }
            nameEditing.value = false
            if (isMine.value) {
              loadStates()
            }
            return
          }
          nameError.value = res?.errors?.[0]?.message || 'Не удалось сохранить название'
        },
        catch: () => {
          savingName.value = false
          nameError.value = 'Ошибка сети при сохранении названия'
        },
      })
    }

    const previewQuery = () => (isPreviewQuery(route.query.preview) ? previewQuerySuffix() : '')

    const loadBlocks = () => {
      blocksLoading.value = true
      blocksError.value = ''
      api({
        api: `Blocks:list?act_id=${encodeURIComponent(props.uuid)}${previewQuery()}`,
        then: (res) => {
          blocksLoading.value = false
          if (res?.ok) {
            blocks.value = res.data?.blocks || []
            blocksCount.value = res.data?.count ?? blocks.value.length
            return
          }
          blocksError.value = res?.errors?.[0]?.message || 'Не удалось загрузить блоки'
        },
        catch: () => {
          blocksLoading.value = false
          blocksError.value = 'Ошибка сети при загрузке блоков'
        },
      })
    }

    const loadStates = ({ forModal = false } = {}) => {
      if (!isMine.value) {
        return
      }

      if (forModal) {
        statesLoading.value = true
      }
      statesError.value = ''
      api({
        api: `Acts:listStates?id=${encodeURIComponent(props.uuid)}`,
        then: (res) => {
          statesLoading.value = false
          if (res?.ok) {
            snapshots.value = res.data?.snapshots || []
            statesCount.value = res.data?.count ?? snapshots.value.length
            return
          }
          statesError.value = res?.errors?.[0]?.message || 'Не удалось загрузить версии'
        },
        catch: () => {
          statesLoading.value = false
          statesError.value = 'Ошибка сети при загрузке версий'
        },
      })
    }

    const openStates = () => {
      statesOpen.value = true
      loadStates({ forModal: true })
    }

    const closeStates = () => {
      statesOpen.value = false
      statesError.value = ''
      restoringKey.value = ''
      statesMerging.value = false
    }

    const mergeStates = ({ from_index, to_index }) => {
      if (statesMerging.value || restoringKey.value) {
        return
      }

      statesMerging.value = true
      statesError.value = ''
      api({
        api: 'Acts:mergeStates',
        data: {
          id: props.uuid,
          from_index,
          to_index,
        },
        then: (res) => {
          statesMerging.value = false
          if (res?.ok) {
            loadStates({ forModal: true })
            loadAct()
            return
          }
          statesError.value = res?.errors?.[0]?.message || 'Не удалось слить версии'
        },
        catch: () => {
          statesMerging.value = false
          statesError.value = 'Ошибка сети при слиянии версий'
        },
      })
    }

    const restoreState = (item) => {
      if (!item?.snapshot_key || restoringKey.value) {
        return
      }

      const label = formatUtcCompactTimestamp(item.timestamp, userTimezone.value) || item.snapshot_key
      if (!window.confirm(`Откатить акт к версии ${label}? Будут восстановлены поля акта и блоки.`)) {
        return
      }

      restoringKey.value = item.snapshot_key
      statesError.value = ''
      api({
        api: 'Acts:restore',
        data: {
          id: props.uuid,
          snapshot_key: item.snapshot_key,
        },
        then: (res) => {
          restoringKey.value = ''
          if (res?.ok && res.data?.act) {
            act.value = { ...act.value, ...res.data.act }
            closeStates()
            loadBlocks()
            loadStates()
            return
          }
          statesError.value = res?.errors?.[0]?.message || 'Не удалось откатить версию'
        },
        catch: () => {
          restoringKey.value = ''
          statesError.value = 'Ошибка сети при откате версии'
        },
      })
    }

    const loadAct = async () => {
      loading.value = true
      act.value = null
      owner.value = null
      isMine.value = false
      error.value = ''
      blocks.value = []
      blocksCount.value = 0
      myTags.value = []
      statesCount.value = 0
      snapshots.value = []
      editBlockId.value = ''
      closeStates()
      closeAddBlockModal()
      closeBlockSettings()
      actAccessOpen.value = false
      actAccessError.value = ''

      if (isPreviewQuery(route.query.preview)) {
        await ensurePreviewReady(props.uuid)
      }

      api({
        api: `Acts:show?id=${encodeURIComponent(props.uuid)}${previewQuery()}`,
        then: (res) => {
          loading.value = false
          if (res?.ok && res.data?.act) {
            act.value = res.data.act
            owner.value = res.data.owner
            isMine.value = Boolean(res.data.is_mine)
            myTags.value = Array.isArray(res.data.my_tags) ? res.data.my_tags : []
            statesCount.value = res.data.stats?.snapshots_count ?? 0
            loadBlocks()
            if (isMine.value && statesCount.value === 0) {
              loadStates()
            }
            return
          }
          error.value = res?.errors?.[0]?.message || 'Акт не найден'
        },
        catch: () => {
          loading.value = false
          error.value = 'Ошибка сети'
        },
      })
    }

    const onPreviewAsChange = (event) => {
      const login = event.target?.value
      if (!login || login === previewAs.value) {
        return
      }
      setPreviewAs(login)
      loadAct()
    }

    const openAddBlockModal = () => {
      if (creatingBlock.value) {
        return
      }
      addBlockOpen.value = true
    }

    const closeAddBlockModal = () => {
      if (!creatingBlock.value) {
        addBlockOpen.value = false
      }
    }

    const openBlockSettings = (block) => {
      if (!isMine.value || settingsSaving.value) {
        return
      }
      settingsError.value = ''
      settingsBlock.value = block
    }

    const closeBlockSettings = () => {
      if (settingsSaving.value) {
        return
      }
      settingsBlock.value = null
      settingsError.value = ''
    }

    const openActAccess = () => {
      if (!isMine.value || actAccessSaving.value) {
        return
      }
      actAccessError.value = ''
      actAccessOpen.value = true
    }

    const openTags = () => {
      if (!showTagsBar.value || tagsSaving.value) {
        return
      }
      tagsError.value = ''
      tagsOpen.value = true
    }

    const closeTags = () => {
      if (tagsSaving.value) {
        return
      }
      tagsOpen.value = false
      tagsError.value = ''
    }

    const saveTags = (tags) => {
      if (tagsSaving.value) {
        return
      }
      tagsSaving.value = true
      tagsError.value = ''
      api({
        api: 'Tags:set',
        data: {
          act_id: props.uuid,
          tags,
        },
        then: (res) => {
          tagsSaving.value = false
          if (res?.ok) {
            myTags.value = Array.isArray(res.data?.tags) ? res.data.tags : []
            tagsOpen.value = false
            return
          }
          tagsError.value = res?.errors?.[0]?.message || 'Не удалось сохранить теги'
        },
        catch: () => {
          tagsSaving.value = false
          tagsError.value = 'Ошибка сети при сохранении тегов'
        },
      })
    }

    const closeActAccess = () => {
      if (actAccessSaving.value) {
        return
      }
      actAccessOpen.value = false
      actAccessError.value = ''
    }

    const saveAccessGrants = ({ grants, resource_type, resource_id }, {
      savingRef,
      errorRef,
      onSuccess,
    }) => {
      if (savingRef.value) {
        return
      }
      savingRef.value = true
      errorRef.value = ''
      api({
        api: 'Access:set',
        data: {
          act_id: props.uuid,
          resource_type,
          resource_id,
          grants,
        },
        then: (res) => {
          savingRef.value = false
          if (res?.ok) {
            onSuccess?.()
            return
          }
          errorRef.value = res?.errors?.[0]?.message || 'Не удалось сохранить настройки доступа'
        },
        catch: () => {
          savingRef.value = false
          errorRef.value = 'Ошибка сети при сохранении доступа'
        },
      })
    }

    const saveActAccess = (payload) => {
      saveAccessGrants(payload, {
        savingRef: actAccessSaving,
        errorRef: actAccessError,
        onSuccess: () => {
          actAccessOpen.value = false
          loadBlocks()
        },
      })
    }

    const saveBlockSettings = (payload) => {
      if (!settingsBlock.value) {
        return
      }
      saveAccessGrants(payload, {
        savingRef: settingsSaving,
        errorRef: settingsError,
        onSuccess: () => {
          settingsBlock.value = null
          loadBlocks()
          if (isMine.value) {
            loadStates()
          }
        },
      })
    }

    const createBlock = (type) => {
      if (creatingBlock.value) {
        return
      }

      creatingBlock.value = true
      blocksError.value = ''
      api({
        api: 'Blocks:create',
        data: {
          act_id: props.uuid,
          name: defaultBlockName(type),
          data: defaultBlockPayload(type),
        },
        then: (res) => {
          creatingBlock.value = false
          if (res?.ok && res.data?.block) {
            const block = res.data.block
            blocks.value = [...blocks.value, block]
            blocksCount.value = blocks.value.length
            editBlockId.value = block.id
            addBlockOpen.value = false
            window.setTimeout(() => {
              editBlockId.value = ''
            }, 0)
            if (isMine.value) {
              loadStates()
            }
            return
          }
          blocksError.value = res?.errors?.[0]?.message || 'Не удалось создать блок'
        },
        catch: () => {
          creatingBlock.value = false
          blocksError.value = 'Ошибка сети при создании блока'
        },
      })
    }

    const replaceBlock = (block) => {
      blocks.value = blocks.value.map((item) => (item.id === block.id ? block : item))
    }

    const applyBlockOrder = (blockIds) => {
      const byId = new Map(blocks.value.map((block) => [block.id, block]))
      blocks.value = blockIds.map((id) => byId.get(id)).filter(Boolean)
    }

    const reorderBlocks = (blockIds) => {
      if (!isMine.value || reordering.value || !Array.isArray(blockIds) || blockIds.length < 2) {
        return
      }

      const previous = blocks.value.map((block) => block.id)
      if (previous.join('|') === blockIds.join('|')) {
        return
      }

      reordering.value = true
      blocksError.value = ''
      applyBlockOrder(blockIds)

      api({
        api: 'Blocks:reorder',
        data: {
          act_id: props.uuid,
          block_ids: blockIds,
        },
        then: (res) => {
          reordering.value = false
          if (res?.ok) {
            if (Array.isArray(res.data?.blocks)) {
              blocks.value = res.data.blocks
            }
            if (isMine.value) {
              loadStates()
            }
            return
          }
          blocksError.value = res?.errors?.[0]?.message || 'Не удалось изменить порядок блоков'
          loadBlocks()
        },
        catch: () => {
          reordering.value = false
          blocksError.value = 'Ошибка сети при изменении порядка блоков'
          loadBlocks()
        },
      })
    }

    const saveBlock = ({ block, data, name, done, fail }) => {
      api({
        api: 'Blocks:update',
        data: {
          act_id: props.uuid,
          id: block.id,
          name: (name ?? block.name) || 'Текст',
          data,
        },
        then: (res) => {
          if (res?.ok && res.data?.block) {
            replaceBlock(res.data.block)
            if (isMine.value) {
              loadStates()
            }
            done?.()
            return
          }
          fail?.(res?.errors?.[0]?.message || 'Не удалось сохранить блок')
        },
        catch: () => {
          fail?.('Ошибка сети при сохранении блока')
        },
      })
    }

    const signChecklistItem = ({ block, item, done, fail }) => {
      if (!block?.id || !item?.id) {
        fail?.('Пункт чеклиста не найден')
        return
      }

      api({
        api: 'Blocks:signChecklistItem',
        data: {
          act_id: props.uuid,
          id: block.id,
          item_id: item.id,
        },
        then: (res) => {
          if (res?.ok && res.data?.block) {
            replaceBlock(res.data.block)
            done?.()
            return
          }
          fail?.(res?.errors?.[0]?.message || 'Не удалось подписать пункт')
        },
        catch: () => {
          fail?.('Ошибка сети при подписании пункта')
        },
      })
    }

    const deleteBlock = (block) => {
      if (!window.confirm('Удалить блок?')) {
        return
      }

      blocksError.value = ''
      api({
        api: 'Blocks:delete',
        data: {
          act_id: props.uuid,
          id: block.id,
        },
        then: (res) => {
          if (res?.ok) {
            blocks.value = blocks.value.filter((item) => item.id !== block.id)
            blocksCount.value = blocks.value.length
            if (isMine.value) {
              loadStates()
            }
            return
          }
          blocksError.value = res?.errors?.[0]?.message || 'Не удалось удалить блок'
        },
        catch: () => {
          blocksError.value = 'Ошибка сети при удалении блока'
        },
      })
    }

    const goBack = () => {
      if (state.user) {
        router.push({ name: 'acts' })
      } else if (owner.value?.login) {
        router.push({ name: 'profile', params: { login: owner.value.login } })
      } else {
        router.push({ name: 'auth' })
      }
    }

    watch(isSiteView, (site) => {
      document.documentElement.classList.toggle('act-site-view', site)
      if (site && act.value?.name) {
        document.title = act.value.name
      } else if (!site) {
        document.title = defaultDocumentTitle
      }
    })

    watch(
      () => act.value?.name,
      (name) => {
        if (isSiteView.value && name) {
          document.title = name
        }
      }
    )

    watch(isPreview, async (value) => {
      if (value) {
        await ensurePreviewReady(props.uuid)
      }
      if (act.value && !loading.value) {
        loadBlocks()
      }
    })

    onMounted(() => {
      loadAct()
      document.addEventListener('click', onDocumentClick)
      document.addEventListener('keydown', onDocumentKeydown)
    })

    onBeforeUnmount(() => {
      document.removeEventListener('click', onDocumentClick)
      document.removeEventListener('keydown', onDocumentKeydown)
      document.documentElement.classList.remove('act-site-view')
      document.title = defaultDocumentTitle
    })

    watch(() => props.uuid, () => {
      closeShare()
      cancelNameEdit()
      loadAct()
    })

    return {
      act,
      owner,
      isMine,
      isPreview,
      previewBarExpanded,
      togglePreviewBar,
      previewAs,
      audiences,
      audiencesLoading,
      viewAsLogin,
      onPreviewAsChange,
      isGuestView,
      showOwnerPanel,
      showActPanel,
      showTagsBar,
      myTags,
      tagsOpen,
      tagsSaving,
      tagsError,
      openTags,
      closeTags,
      saveTags,
      isSiteView,
      showActChrome,
      pageReady,
      previewUrl,
      editActRoute,
      loading,
      contentLoading,
      error,
      blocks,
      blocksLoading,
      blocksError,
      creatingBlock,
      shareOpen,
      shareInputRef,
      headerRef,
      copyFeedback,
      shareUrl,
      nameEditing,
      nameDraft,
      nameInputRef,
      savingName,
      nameError,
      blocksCount,
      statesCount,
      statesOpen,
      statesLoading,
      statesError,
      snapshots,
      restoringKey,
      statesMerging,
      addBlockOpen,
      settingsBlock,
      settingsSaving,
      settingsError,
      actAccessOpen,
      actAccessSaving,
      actAccessError,
      editBlockId,
      reordering,
      reorderEnabled,
      toggleReorder,
      userTimezone,
      openAddBlockModal,
      closeAddBlockModal,
      openBlockSettings,
      closeBlockSettings,
      openActAccess,
      closeActAccess,
      saveActAccess,
      saveBlockSettings,
      createBlock,
      openStates,
      closeStates,
      restoreState,
      mergeStates,
      openShare,
      closeShare,
      selectShareUrl,
      copyShareUrl,
      startNameEdit,
      cancelNameEdit,
      saveActName,
      goBack,
      createBlock,
      saveBlock,
      signChecklistItem,
      deleteBlock,
      reorderBlocks,
    }
  },
}
</script>

<style scoped>
</style>
