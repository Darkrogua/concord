<template>
  <div class="concord-page">
    <header class="concord-header">
      <h1 class="concord-header__title">Все согласования</h1>
      <div class="concord-header__actions">
        <button type="button" class="concord-icon-btn" aria-label="Поиск" @click="searchOpen = true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.8"/>
            <path d="M16 16l5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </button>
        <button type="button" class="concord-icon-btn" aria-label="Настройки фильтров" @click="$emit('open-filter-settings')">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="6" r="1.5" fill="currentColor"/>
            <circle cx="12" cy="12" r="1.5" fill="currentColor"/>
            <circle cx="12" cy="18" r="1.5" fill="currentColor"/>
          </svg>
        </button>
      </div>
    </header>

    <div class="concord-tabs-wrap" :class="{ 'concord-tabs-wrap--scrollable': showTabsOverflow }">
      <div
        ref="tabsRef"
        class="concord-tabs"
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
          <span v-if="tab.badge" class="concord-tabs__badge">{{ tab.badge }}</span>
        </button>
      </div>
      <div
        v-if="showTabsOverflow"
        class="concord-tabs__hint"
        aria-hidden="true"
        title="Листайте вправо"
      >
        <span class="concord-tabs__hint-icon">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
      </div>
    </div>

    <div class="concord-sort">
      <span class="concord-sort__group">{{ groupLabel }}</span>
      <select v-model="sortId" class="concord-sort__select" aria-label="Сортировка">
        <option v-for="opt in sortOptions" :key="opt.id" :value="opt.id">
          {{ opt.label }}
        </option>
      </select>
    </div>

    <main class="concord-list">
      <template v-for="group in groupedAgreements" :key="group.label">
        <h2 class="concord-list__date-group">{{ group.label }}</h2>
        <AgreementCard
          v-for="item in group.items"
          :key="item.id"
          :agreement="item"
          @open="onOpenAgreement"
          @toggle-favorite="toggleFavorite"
          @duplicate="onDuplicate"
          @edit="onEdit"
        />
      </template>
      <p v-if="displayedAgreements.length === 0" class="concord-empty">
        Нет согласований в этом разделе.
      </p>
    </main>

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
        @open="onOpenAgreement"
        @toggle-favorite="toggleFavorite"
        @duplicate="onDuplicate"
        @edit="onEdit"
      />
      <p v-if="searchQuery.trim() && searchResults.length === 0" class="concord-empty">
        Ничего не найдено.
      </p>
    </ConcordSearchOverlay>
  </div>
</template>

<script>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import AgreementCard from '../concord/AgreementCard.vue'
import ConcordSearchOverlay from '../concord/ConcordSearchOverlay.vue'
import {
  SORT_OPTIONS,
  buildTopTabsFromSections,
  filterAgreements,
  groupAgreementsByDate,
  sortAgreements,
} from '../concord/mock-agreements.js'

export default {
  name: 'AgreementsListView',
  components: { AgreementCard, ConcordSearchOverlay },
  props: {
    agreements: {
      type: Array,
      required: true,
    },
    filterSections: {
      type: Array,
      required: true,
    },
  },
  emits: ['open-agreement', 'edit-agreement', 'duplicate-agreement', 'toggle-favorite', 'open-filter-settings'],
  setup(props, { emit }) {
    const activeTab = ref('agreements')
    const groupLabel = ref('Сегодня')
    const sortId = ref('favorites')
    const searchOpen = ref(false)
    const searchQuery = ref('')
    const searchScope = ref('content')
    const tabsRef = ref(null)
    const showTabsOverflow = ref(false)
    let tabsResizeObserver = null

    const allTabs = computed(() => buildTopTabsFromSections(props.filterSections, props.agreements))
    const sortOptions = SORT_OPTIONS
    const visibleTabs = computed(() => allTabs.value)

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
          tabsResizeObserver = new ResizeObserver(updateTabsOverflow)
          tabsResizeObserver.observe(tabsRef.value)
        }
      })
      window.addEventListener('resize', updateTabsOverflow)
    })

    onBeforeUnmount(() => {
      window.removeEventListener('resize', updateTabsOverflow)
      tabsResizeObserver?.disconnect()
    })

    watch(
      () => props.filterSections.map((section) => section.tabId || section.id).join(','),
      () => {
        const tabIds = props.filterSections.map((section) => section.tabId || section.id)
        if (tabIds.length && !tabIds.includes(activeTab.value)) {
          activeTab.value = tabIds[0]
        }
      },
      { immediate: true }
    )

    const displayedAgreements = computed(() => {
      const list = filterAgreements(props.agreements, activeTab.value, '')
      return sortAgreements(list, sortId.value)
    })

    const groupedAgreements = computed(() => groupAgreementsByDate(displayedAgreements.value))

    const searchResults = computed(() => {
      const list = filterAgreements(props.agreements, activeTab.value, searchQuery.value, searchScope.value)
      return sortAgreements(list, sortId.value)
    })

    function toggleFavorite(id) {
      emit('toggle-favorite', id)
    }

    function closeSearch() {
      searchOpen.value = false
      searchQuery.value = ''
    }

    function onOpenAgreement(id) {
      emit('open-agreement', id)
    }

    function onEdit(id) {
      emit('edit-agreement', id)
    }

    function onDuplicate(id) {
      emit('duplicate-agreement', id)
    }

    return {
      activeTab,
      groupLabel,
      sortId,
      sortOptions,
      visibleTabs,
      searchOpen,
      searchQuery,
      searchScope,
      tabsRef,
      showTabsOverflow,
      updateTabsOverflow,
      displayedAgreements,
      groupedAgreements,
      searchResults,
      toggleFavorite,
      closeSearch,
      onOpenAgreement,
      onEdit,
      onDuplicate,
    }
  },
}
</script>
