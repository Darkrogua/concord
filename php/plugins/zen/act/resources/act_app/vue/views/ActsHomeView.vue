<template>
  <div>
    <header class="tg-header">
      <h1 class="tg-header__title">Мои акты</h1>
      <button type="button" class="tg-header__action tg-header__action--accent" aria-label="Создать акт" @click="openCreate">
        +
      </button>
    </header>

    <main class="tg-main">
      <AsyncRegion :loading="loading && !initialLoaded" variant="page" pattern="acts">
        <p v-if="error && acts.length === 0" class="tg-error">{{ error }}</p>
        <div v-else-if="initialLoaded && acts.length === 0 && !hasActiveFilters" class="tg-empty">
          Пока нет актов.<br>
          Нажмите «+», чтобы создать первый.
        </div>
        <template v-else>
          <ActsListToolbar
            :view-mode="viewMode"
            :filter-count="activeCount"
            :presets="presets"
            :active-preset-id="activePresetId || ''"
            :matching-preset-id="matchingPresetId || ''"
            @open-filters="openFilterHub"
            @toggle-view="toggleViewMode"
            @toggle-preset="onTogglePreset"
            @delete-preset="onDeletePreset"
          />
          <div v-if="initialLoaded && acts.length === 0 && hasActiveFilters" class="tg-empty">
            Ничего не найдено.
            <button type="button" class="tg-empty__action" @click="resetFiltersAndReload">
              Сбросить фильтры
            </button>
          </div>
          <div v-else :class="['tg-acts-grid', { 'tg-acts-grid--list': viewMode === 'list' }]">
            <button
              v-for="act in acts"
              :key="act.id"
              type="button"
              :class="['tg-tile', { 'tg-tile--list': viewMode === 'list' }]"
              @click="openAct(act)"
            >
              <span class="tg-tile__title">{{ act.name }}</span>
              <span v-if="act.access === 'shared_act'" class="tg-tile__badge">Общий доступ</span>
              <span v-else-if="act.access === 'shared_block'" class="tg-tile__badge">Доступен блок</span>
            </button>
          </div>
          <div ref="sentinel" class="tg-acts-sentinel" aria-hidden="true" />
          <div v-if="loadingMore" class="tg-loading tg-loading--inline">Загрузка…</div>
          <p v-if="error && acts.length > 0" class="tg-error">{{ error }}</p>
        </template>
      </AsyncRegion>
    </main>

    <AppShell active-tab="acts" />

    <ActsFilterHubModal
      :open="filterHubOpen"
      :items="filterHubItems"
      :active-count="activeCount"
      @close="filterHubOpen = false"
      @toggle="onFilterToggle"
      @configure="openFilterDetail"
      @reset-all="resetFiltersAndReload"
      @save-preset="openPresetSave"
    />

    <ActsFilterPresetSaveModal
      :open="presetSaveOpen"
      :external-error="presetSaveError"
      @close="closePresetSave"
      @save="savePreset"
    />

    <ActsFilterDetailModal
      :open="filterDetailOpen"
      :type="filterDetailType"
      :filters="filters"
      @close="closeFilterDetail"
      @apply="applyFilterDetail"
    />

    <div v-if="showCreate">
      <div class="tg-sheet-backdrop" @click="closeCreate" />
      <div class="tg-sheet">
        <h2 class="tg-sheet__title">Новый акт</h2>
        <form class="tg-sheet__form" @submit.prevent="createAct">
          <label class="tg-field">
            <span class="tg-field__label">Название</span>
            <div class="tg-field__input-row">
              <input
                ref="nameInputRef"
                v-model="newName"
                class="tg-input"
                type="text"
                maxlength="255"
                autofocus
              >
              <button
                v-if="newName"
                type="button"
                class="tg-field__clear"
                aria-label="Очистить"
                @click="clearNewName"
              >
                <span aria-hidden="true">×</span>
              </button>
            </div>
          </label>
          <p v-if="createError" class="tg-error">{{ createError }}</p>
          <div class="tg-sheet__actions">
            <button class="tg-btn tg-btn--block" type="submit" :disabled="creating">
              {{ creating ? '…' : 'Создать' }}
            </button>
            <button class="tg-btn tg-btn--ghost tg-btn--block" type="button" @click="closeCreate">
              Отмена
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAppState } from '../app-state'
import { useActsInfiniteList } from '../composables/useActsInfiniteList'
import { useActsListFilters, cloneFilters } from '../composables/useActsListFilters'
import { useActsFilterPresets } from '../composables/useActsFilterPresets'
import ActsFilterDetailModal from '../components/ActsFilterDetailModal.vue'
import ActsFilterHubModal from '../components/ActsFilterHubModal.vue'
import ActsFilterPresetSaveModal from '../components/ActsFilterPresetSaveModal.vue'
import ActsListToolbar from '../components/ActsListToolbar.vue'
import AppShell from '../components/AppShell.vue'
import AsyncRegion from '../components/AsyncRegion.vue'

const VIEW_MODE_KEY = 'act_list_view_mode'

function defaultActName() {
  const now = new Date()
  const pad = (value) => String(value).padStart(2, '0')
  return `act-${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}-${pad(now.getHours())}${pad(now.getMinutes())}`
}

export default {
  name: 'ActsHomeView',
  components: {
    AppShell,
    ActsListToolbar,
    ActsFilterHubModal,
    ActsFilterDetailModal,
    ActsFilterPresetSaveModal,
    AsyncRegion,
  },
  setup() {
    const router = useRouter()
    const { api } = useAppState()
    const {
      filters,
      activeCount,
      hasActiveFilters,
      ownersSummary,
      createdSummary,
      sortSummary,
      tagsSummary,
      toQueryParams,
      resetAll,
      setFilterEnabled,
      replaceFilters,
    } = useActsListFilters()

    const {
      presets,
      activePresetId,
      createPreset,
      togglePreset,
      syncActiveFromFilters,
      getMatchingPresetId,
      deletePreset,
    } = useActsFilterPresets()

    const matchingPresetId = computed(() => getMatchingPresetId(filters))

    const {
      acts,
      hasMore,
      loading,
      loadingMore,
      error,
      initialLoaded,
      sentinel,
      loadFirst,
      prependAct,
      reconnectObserver,
    } = useActsInfiniteList(() => toQueryParams())

    const showCreate = ref(false)
    const newName = ref('')
    const nameInputRef = ref(null)
    const creating = ref(false)
    const createError = ref('')
    const viewMode = ref(localStorage.getItem(VIEW_MODE_KEY) === 'list' ? 'list' : 'tiles')
    const filterHubOpen = ref(false)
    const filterDetailOpen = ref(false)
    const filterDetailType = ref('')
    const presetSaveOpen = ref(false)
    const presetSaveError = ref('')
    const presetSnapshot = ref(null)

    const filterHubItems = computed(() => ([
      {
        type: 'owners',
        label: 'Владелец акта',
        summary: ownersSummary.value,
        enabled: filters.owners.enabled,
      },
      {
        type: 'created',
        label: 'Создан',
        summary: createdSummary.value,
        enabled: filters.created.enabled,
      },
      {
        type: 'tags',
        label: 'Теги',
        summary: tagsSummary.value,
        enabled: filters.tags.enabled,
      },
      {
        type: 'sortCreated',
        label: 'Сортировка по созданию',
        summary: sortSummary.value,
        enabled: filters.sortCreated.enabled,
      },
    ]))

    const reloadList = async () => {
      await loadFirst()
      await nextTick()
      reconnectObserver()
    }

    const toggleViewMode = () => {
      viewMode.value = viewMode.value === 'tiles' ? 'list' : 'tiles'
      localStorage.setItem(VIEW_MODE_KEY, viewMode.value)
    }

    const openFilterHub = () => {
      filterHubOpen.value = true
    }

    const openFilterDetail = (type) => {
      filterDetailType.value = type
      filterDetailOpen.value = true
    }

    const closeFilterDetail = () => {
      filterDetailOpen.value = false
      filterDetailType.value = ''
    }

    const onFilterToggle = async (type, enabled) => {
      setFilterEnabled(type, enabled)
      if (!enabled) {
        await reloadList()
      }
    }

    const applyFilterDetail = async (payload) => {
      if (payload.type === 'owners') {
        filters.owners.enabled = true
        filters.owners.users = payload.owners.users
      } else if (payload.type === 'created') {
        filters.created.enabled = true
        filters.created.from = payload.created.from
        filters.created.to = payload.created.to
      } else if (payload.type === 'sortCreated') {
        filters.sortCreated.enabled = true
        filters.sortCreated.order = payload.sortCreated.order
      } else if (payload.type === 'tags') {
        filters.tags.enabled = true
        filters.tags.items = payload.tags.items
        filters.tags.ops = payload.tags.ops
      }
      closeFilterDetail()
      filterHubOpen.value = false
      await reloadList()
    }

    const resetFiltersAndReload = async () => {
      resetAll()
      activePresetId.value = null
      filterHubOpen.value = false
      closeFilterDetail()
      await reloadList()
    }

    const openPresetSave = () => {
      presetSnapshot.value = cloneFilters(filters)
      presetSaveError.value = ''
      filterHubOpen.value = false
      presetSaveOpen.value = true
    }

    const closePresetSave = () => {
      presetSaveOpen.value = false
      presetSaveError.value = ''
      presetSnapshot.value = null
    }

    const savePreset = async ({ name, color }) => {
      presetSaveError.value = ''
      try {
        const preset = createPreset(name, color, presetSnapshot.value || filters)
        replaceFilters(preset.filters)
        activePresetId.value = preset.id
        closePresetSave()
        await reloadList()
      } catch (err) {
        presetSaveError.value = err?.message || 'Не удалось сохранить пресет'
      }
    }

    const onTogglePreset = async (id) => {
      togglePreset(id, {
        replaceFilters,
        resetAll,
        onReload: reloadList,
      })
    }

    const onDeletePreset = (preset) => {
      if (!preset?.id) {
        return
      }

      const confirmed = window.confirm(`Удалить пресет «${preset.name}»?`)
      if (!confirmed) {
        return
      }

      deletePreset(preset.id)
    }

    const openAct = (act) => {
      router.push({ name: 'act', params: { uuid: act.id } })
    }

    const closeCreate = () => {
      showCreate.value = false
      newName.value = ''
      createError.value = ''
    }

    const openCreate = () => {
      newName.value = defaultActName()
      createError.value = ''
      showCreate.value = true
    }

    const clearNewName = async () => {
      newName.value = ''
      createError.value = ''
      await nextTick()
      nameInputRef.value?.focus()
    }

    const createAct = () => {
      if (creating.value) {
        return
      }
      const name = newName.value.trim()
      if (!name) {
        createError.value = 'Введите название'
        return
      }
      creating.value = true
      createError.value = ''
      api({
        api: 'Acts:create',
        data: { name },
        then: (res) => {
          creating.value = false
          if (res?.ok && res.data?.act) {
            prependAct(res.data.act)
            closeCreate()
            openAct(res.data.act)
            return
          }
          createError.value = res?.errors?.[0]?.message || 'Не удалось создать акт'
        },
        catch: () => {
          creating.value = false
          createError.value = 'Ошибка сети'
        },
      })
    }

    watch(filters, () => {
      syncActiveFromFilters(filters)
    }, { deep: true })

    watch(sentinel, () => {
      reconnectObserver()
    })

    onMounted(() => {
      syncActiveFromFilters(filters)
      reloadList()
    })

    return {
      acts,
      hasMore,
      loading,
      loadingMore,
      error,
      initialLoaded,
      sentinel,
      showCreate,
      newName,
      nameInputRef,
      creating,
      createError,
      viewMode,
      filters,
      activeCount,
      hasActiveFilters,
      filterHubOpen,
      filterDetailOpen,
      filterDetailType,
      filterHubItems,
      presets,
      activePresetId,
      matchingPresetId,
      presetSaveOpen,
      presetSaveError,
      toggleViewMode,
      openFilterHub,
      openFilterDetail,
      closeFilterDetail,
      onFilterToggle,
      applyFilterDetail,
      resetFiltersAndReload,
      openPresetSave,
      closePresetSave,
      savePreset,
      onTogglePreset,
      onDeletePreset,
      openAct,
      openCreate,
      clearNewName,
      closeCreate,
      createAct,
    }
  },
}
</script>
