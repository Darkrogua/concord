<template>
  <div class="concord-page">
    <header class="concord-header">
      <h1 class="concord-header__title">Все проекты</h1>
      <div class="concord-header__actions">
        <button type="button" class="concord-icon-btn" aria-label="Поиск" @click="searchOpen = true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.8"/>
            <path d="M16 16l5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </button>
        <button type="button" class="concord-icon-btn" aria-label="Настройки фильтра" @click="settingsOpen = true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="6" r="1.5" fill="currentColor"/>
            <circle cx="12" cy="12" r="1.5" fill="currentColor"/>
            <circle cx="12" cy="18" r="1.5" fill="currentColor"/>
          </svg>
        </button>
      </div>
    </header>

    <div class="concord-tabs-wrap">
      <div
        ref="tabsRef"
        class="concord-tabs"
        :class="{ 'concord-tabs--overflow': showTabsOverflow }"
        role="tablist"
        aria-label="Фильтры списка"
        @scroll="updateTabsOverflow"
      >
        <button
          v-for="tab in visibleTabs"
          :key="tab.id"
          type="button"
          role="tab"
          :class="['concord-tabs__item', { 'concord-tabs__item--active': activeTab === tab.id }]"
          :aria-selected="activeTab === tab.id"
          @click="activeTab = tab.id"
        >
          {{ tab.label }}
        </button>
      </div>
      <span v-if="showTabsOverflow" class="concord-tabs__hint" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </span>
    </div>

    <div class="concord-sort">
      <span>Сортировать по</span>
      <select v-model="sortId" class="concord-sort__select" aria-label="Сортировка">
        <option v-for="opt in sortOptions" :key="opt.id" :value="opt.id">
          {{ opt.label }}
        </option>
      </select>
    </div>

    <main class="concord-list">
      <AgreementCard
        v-for="item in displayedAgreements"
        :key="item.id"
        :agreement="item"
        :expanded="expandedId === item.id"
        @toggle-expand="toggleExpand"
        @open="onOpenAgreement"
        @toggle-favorite="toggleFavorite"
        @duplicate="onDuplicate"
        @edit="onEdit"
      />
      <p v-if="displayedAgreements.length === 0" class="concord-empty">
        Нет согласований в этом разделе.
      </p>
    </main>

    <ConcordBottomNav
      :avatar-initial="activeAccountInitial"
      @create="onCreate"
      @settings="onSettings"
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

    <ConcordSearchOverlay
      :open="searchOpen"
      :query="searchQuery"
      :scope="searchScope"
      @close="closeSearch"
      @update:query="searchQuery = $event"
      @update:scope="searchScope = $event"
    >
      <AgreementCard
        v-for="item in searchResults"
        :key="`search-${item.id}`"
        :agreement="item"
        :expanded="expandedId === item.id"
        @toggle-expand="toggleExpand"
        @open="onOpenAgreement"
        @toggle-favorite="toggleFavorite"
        @duplicate="onDuplicate"
        @edit="onEdit"
      />
      <p v-if="searchQuery.trim() && searchResults.length === 0" class="concord-empty">
        Ничего не найдено.
      </p>
    </ConcordSearchOverlay>

    <template v-if="settingsOpen">
      <div class="concord-sheet-backdrop" @click="settingsOpen = false" />
      <div class="concord-sheet" role="dialog" aria-label="Быстрые настройки">
        <h2 class="concord-sheet__title">Настройки списка</h2>

        <div class="concord-sheet__group">
          <p class="concord-sheet__label">Вкладки</p>
          <div class="concord-sheet__options">
            <button
              v-for="tab in allTabs"
              :key="tab.id"
              type="button"
              :class="['concord-sheet__chip', { 'concord-sheet__chip--active': enabledTabIds.includes(tab.id) }]"
              @click="toggleTabVisibility(tab.id)"
            >
              {{ tab.label }}
            </button>
          </div>
        </div>

        <div class="concord-sheet__group">
          <p class="concord-sheet__label">Мои согласования</p>
          <div class="concord-sheet__options">
            <button
              type="button"
              :class="['concord-sheet__chip', { 'concord-sheet__chip--active': quickFilter === 'overdue' }]"
              @click="quickFilter = quickFilter === 'overdue' ? '' : 'overdue'"
            >
              Просроченные
            </button>
            <button
              type="button"
              :class="['concord-sheet__chip', { 'concord-sheet__chip--active': quickFilter === 'comments' }]"
              @click="quickFilter = quickFilter === 'comments' ? '' : 'comments'"
            >
              С комментариями
            </button>
          </div>
        </div>

        <div class="concord-sheet__group">
          <p class="concord-sheet__label">Сортировка</p>
          <div class="concord-sheet__options">
            <button
              type="button"
              :class="['concord-sheet__chip', { 'concord-sheet__chip--active': sortId === 'activity' }]"
              @click="sortId = 'activity'"
            >
              По активности
            </button>
            <button
              type="button"
              :class="['concord-sheet__chip', { 'concord-sheet__chip--active': sortId === 'deadline' }]"
              @click="sortId = 'deadline'"
            >
              По сроку
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import AgreementCard from '../concord/AgreementCard.vue'
import ConcordSearchOverlay from '../concord/ConcordSearchOverlay.vue'
import AccountSwitcherSheet from '../concord/AccountSwitcherSheet.vue'
import ConcordBottomNav from '../concord/ConcordBottomNav.vue'
import CreateProjectSheet from '../concord/CreateProjectSheet.vue'
import {
  DEFAULT_TOP_TABS,
  MOCK_AGREEMENTS,
  SORT_OPTIONS,
  filterAgreements,
} from '../concord/mock-agreements.js'

const DEFAULT_TAB_IDS = DEFAULT_TOP_TABS.map((t) => t.id)

export default {
  name: 'ProjectsHomeView',
  components: { AgreementCard, ConcordSearchOverlay, AccountSwitcherSheet, ConcordBottomNav, CreateProjectSheet },
  setup() {
    const agreements = ref(MOCK_AGREEMENTS.map((item) => ({ ...item })))
    const activeTab = ref('agreements')
    const enabledTabIds = ref([...DEFAULT_TAB_IDS])
    const sortId = ref('oldest')
    const expandedId = ref(null)
    const searchOpen = ref(false)
    const searchQuery = ref('')
    const searchScope = ref('content')
    const settingsOpen = ref(false)
    const accountOpen = ref(false)
    const createOpen = ref(false)
    const activeAccountId = ref('1')
    const quickFilter = ref('')
    const tabsRef = ref(null)
    const showTabsOverflow = ref(false)
    let tabsResizeObserver = null

    const activeAccountInitial = computed(() => {
      const map = { 1: 'А', 2: 'И', 3: 'В' }
      return map[activeAccountId.value] || 'А'
    })

    const allTabs = DEFAULT_TOP_TABS
    const sortOptions = SORT_OPTIONS

    const visibleTabs = computed(() =>
      allTabs.filter((tab) => enabledTabIds.value.includes(tab.id))
    )

    watch(visibleTabs, (tabs) => {
      if (!tabs.some((t) => t.id === activeTab.value) && tabs.length > 0) {
        activeTab.value = tabs[0].id
      }
      nextTick(updateTabsOverflow)
    })

    function updateTabsOverflow() {
      const el = tabsRef.value
      if (!el) {
        showTabsOverflow.value = false
        return
      }

      const hasOverflow = el.scrollWidth > el.clientWidth + 1
      const canScrollRight = el.scrollLeft + el.clientWidth < el.scrollWidth - 1
      showTabsOverflow.value = hasOverflow && canScrollRight
    }

    onMounted(() => {
      nextTick(() => {
        updateTabsOverflow()

        if (typeof ResizeObserver !== 'undefined' && tabsRef.value) {
          tabsResizeObserver = new ResizeObserver(() => {
            updateTabsOverflow()
          })
          tabsResizeObserver.observe(tabsRef.value)
        }
      })

      window.addEventListener('resize', updateTabsOverflow)
    })

    onBeforeUnmount(() => {
      window.removeEventListener('resize', updateTabsOverflow)
      tabsResizeObserver?.disconnect()
      tabsResizeObserver = null
    })

    const baseList = computed(() => {
      let list = filterAgreements(agreements.value, activeTab.value, '')

      if (quickFilter.value === 'overdue') {
        list = list.filter((item) => item.status === 'overdue')
      }

      return list
    })

    const displayedAgreements = computed(() => sortList([...baseList.value]))

    const searchResults = computed(() => {
      const list = filterAgreements(agreements.value, activeTab.value, searchQuery.value, searchScope.value)
      return sortList(list)
    })

    function sortList(list) {
      if (sortId.value === 'newest') {
        return list.reverse()
      }
      return list
    }

    function toggleExpand(id) {
      expandedId.value = expandedId.value === id ? null : id
    }

    function toggleFavorite(id) {
      const item = agreements.value.find((a) => a.id === id)
      if (item) {
        item.isFavorite = !item.isFavorite
      }
    }

    function toggleTabVisibility(tabId) {
      if (enabledTabIds.value.includes(tabId)) {
        if (enabledTabIds.value.length <= 1) {
          return
        }
        enabledTabIds.value = enabledTabIds.value.filter((id) => id !== tabId)
      } else {
        enabledTabIds.value = [...enabledTabIds.value, tabId]
      }
    }

    function closeSearch() {
      searchOpen.value = false
      searchQuery.value = ''
    }

    function onOpenAgreement(id) {
      // TODO: экран согласования
      console.info('[concord] open agreement', id)
    }

    function onDuplicate(id) {
      console.info('[concord] duplicate', id)
    }

    function onEdit(id) {
      console.info('[concord] edit', id)
    }

    function onCreate() {
      createOpen.value = true
    }

    function onCreateOption(type) {
      console.info('[concord] create agreement', type)
    }

    function onCreateAccount() {
      console.info('[concord] create new account')
    }

    function onSettings() {
      console.info('[concord] settings')
    }

    function onNotifications() {
      console.info('[concord] notifications')
    }

    return {
      agreements,
      activeTab,
      enabledTabIds,
      allTabs,
      visibleTabs,
      sortId,
      sortOptions,
      expandedId,
      searchOpen,
      searchQuery,
      searchScope,
      settingsOpen,
      accountOpen,
      createOpen,
      activeAccountId,
      activeAccountInitial,
      quickFilter,
      tabsRef,
      showTabsOverflow,
      updateTabsOverflow,
      displayedAgreements,
      searchResults,
      toggleExpand,
      toggleFavorite,
      toggleTabVisibility,
      closeSearch,
      onOpenAgreement,
      onDuplicate,
      onEdit,
      onCreate,
      onCreateOption,
      onCreateAccount,
      onSettings,
      onNotifications,
    }
  },
}
</script>
