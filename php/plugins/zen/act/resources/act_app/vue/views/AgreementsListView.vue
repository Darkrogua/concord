<template>
  <div class="concord-page" :style="listPageStyle">
    <header
      ref="listHeaderRef"
      class="concord-header concord-header--scroll-reveal"
      :class="{ 'concord-header--scroll-reveal-hidden': !headerVisible }"
    >
      <h1 class="concord-header__title">Все согласования</h1>
      <div class="concord-header__actions">
        <button type="button" class="concord-icon-btn" aria-label="Поиск" @click="searchOpen = true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.8"/>
            <path d="M16 16l5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </button>
        <button
          type="button"
          class="concord-icon-btn concord-sort-toggle"
          :class="{
            'concord-sort-toggle--active': sortActive,
            'concord-sort-toggle--asc': sortActive && sortOrder === 'asc',
          }"
          :aria-label="sortAriaLabel"
          @click="toggleSortOrder"
        >
          <svg class="concord-sort-toggle__icon" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          <svg
            v-if="sortActive"
            class="concord-sort-toggle__arrow"
            width="10"
            height="10"
            viewBox="0 0 12 12"
            fill="none"
            aria-hidden="true"
          >
            <path
              d="M6 2.5v7"
              stroke="currentColor"
              stroke-width="1.6"
              stroke-linecap="round"
            />
            <path
              :d="sortOrder === 'asc' ? 'M3.5 7.5 6 10 8.5 7.5' : 'M3.5 4.5 6 2 8.5 4.5'"
              stroke="currentColor"
              stroke-width="1.6"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
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

    <div
      class="concord-list-header-spacer"
      aria-hidden="true"
      :style="{ height: `${listHeaderHeight}px` }"
    />

    <div
      class="concord-list-tabs-sticky"
      :class="{ 'concord-list-tabs-sticky--header-visible': headerVisible }"
    >
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
      <button
        v-if="showTabsOverflow"
        type="button"
        class="concord-tabs__hint"
        aria-label="Прокрутить вкладки вправо"
        title="Листайте вправо"
        @click="scrollTabsRight"
      >
        <span class="concord-tabs__hint-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
      </button>
    </div>
    </div>

    <div v-if="loading" class="concord-list concord-card-skeleton" aria-label="Загрузка согласований">
      <div v-for="n in 3" :key="n" class="concord-card-skeleton__item" />
    </div>

    <TransitionGroup v-else name="concord-card-list" tag="main" class="concord-list">
      <template v-for="(group, groupIndex) in groupedAgreements" :key="group.label">
        <div
          v-if="shouldShowDateSeparator(group, groupIndex)"
          :key="`date-${group.label}`"
          class="concord-list__date-separator"
        >
          <span class="concord-list__date-pill">{{ group.label }}</span>
        </div>
        <AgreementCard
          v-for="item in group.items"
          :key="item.id"
          :agreement="item"
          :groups="groups"
          :contacts="contacts"
          :view-transition-name="item.id === openingAgreementId ? 'concord-agreement-card' : ''"
          :is-new="item.id === newAgreementId"
          @open="onOpenAgreement"
          @toggle-favorite="toggleFavorite"
          @duplicate="onDuplicate"
          @edit="onEdit"
        />
      </template>
      <p v-if="displayedAgreements.length === 0" key="empty" class="concord-empty">
        Нет согласований в этом разделе.
      </p>
    </TransitionGroup>

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
        :groups="groups"
        :contacts="contacts"
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
import { useTabsScrollHint } from '../composables/useTabsScrollHint.js'
import {
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
    groups: {
      type: Array,
      default: () => [],
    },
    contacts: {
      type: Array,
      default: () => [],
    },
    loading: {
      type: Boolean,
      default: false,
    },
    openingAgreementId: {
      type: [String, Number],
      default: null,
    },
    newAgreementId: {
      type: [String, Number],
      default: null,
    },
  },
  emits: ['open-agreement', 'edit-agreement', 'duplicate-agreement', 'toggle-favorite', 'open-filter-settings'],
  setup(props, { emit }) {
    const activeTab = ref('agreements')
    const sortActive = ref(false)
    const sortOrder = ref('desc')
    const searchOpen = ref(false)
    const searchQuery = ref('')
    const searchScope = ref('content')
    const listHeaderRef = ref(null)
    const listHeaderHeight = ref(65)
    const headerVisible = ref(true)
    let lastScrollY = 0
    let scrollFrame = null
    let chromeResizeObserver = null
    const {
      tabsRef,
      showTabsOverflow,
      updateTabsOverflow,
      scrollTabsRight,
    } = useTabsScrollHint()

    const allTabs = computed(() => buildTopTabsFromSections(props.filterSections, props.agreements))
    const visibleTabs = computed(() => allTabs.value)

    const sortAriaLabel = computed(() => {
      if (!sortActive.value) {
        return 'Сортировка'
      }
      return sortOrder.value === 'asc' ? 'Сортировка: сначала старые' : 'Сортировка: сначала новые'
    })

    const listPageStyle = computed(() => ({
      '--concord-list-header-height': `${listHeaderHeight.value}px`,
    }))

    function updateListHeaderHeight() {
      const measured = listHeaderRef.value?.offsetHeight || 0
      listHeaderHeight.value = measured || 65
    }

    function setupHeaderResizeObserver() {
      chromeResizeObserver?.disconnect()
      updateListHeaderHeight()
      if (!listHeaderRef.value || typeof ResizeObserver === 'undefined') {
        return
      }
      chromeResizeObserver = new ResizeObserver(() => {
        updateListHeaderHeight()
      })
      chromeResizeObserver.observe(listHeaderRef.value)
    }

    function onScroll() {
      if (scrollFrame) {
        return
      }
      scrollFrame = requestAnimationFrame(() => {
        scrollFrame = null
        const currentY = window.scrollY || document.documentElement.scrollTop || 0
        if (currentY <= 4) {
          headerVisible.value = true
        } else if (currentY > lastScrollY + 6) {
          headerVisible.value = false
        } else if (currentY < lastScrollY - 6) {
          headerVisible.value = true
        }
        lastScrollY = currentY
      })
    }

    watch(visibleTabs, () => {
      nextTick(updateTabsOverflow)
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
      return sortActive.value ? sortAgreements(list, sortOrder.value) : list
    })

    const groupedAgreements = computed(() => {
      const order = sortActive.value ? sortOrder.value : 'desc'
      return groupAgreementsByDate(displayedAgreements.value, order)
    })

    const searchResults = computed(() => {
      const list = filterAgreements(props.agreements, activeTab.value, searchQuery.value, searchScope.value)
      return sortActive.value ? sortAgreements(list, sortOrder.value) : list
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

    function toggleSortOrder() {
      if (!sortActive.value) {
        sortActive.value = true
        sortOrder.value = 'desc'
        return
      }
      sortOrder.value = sortOrder.value === 'desc' ? 'asc' : 'desc'
    }

    function shouldShowDateSeparator(group, groupIndex) {
      if (group.label === 'Черновики') {
        return false
      }
      if (sortActive.value && sortOrder.value === 'asc') {
        return true
      }
      const firstDatedIndex = groupedAgreements.value.findIndex((item) => item.label !== 'Черновики')
      return groupIndex !== firstDatedIndex
    }

    onMounted(() => {
      nextTick(() => {
        setupHeaderResizeObserver()
      })
      window.addEventListener('scroll', onScroll, { passive: true })
    })

    onBeforeUnmount(() => {
      chromeResizeObserver?.disconnect()
      window.removeEventListener('scroll', onScroll)
      if (scrollFrame) {
        cancelAnimationFrame(scrollFrame)
      }
    })

    return {
      activeTab,
      sortActive,
      sortOrder,
      sortAriaLabel,
      listHeaderRef,
      listHeaderHeight,
      headerVisible,
      listPageStyle,
      visibleTabs,
      searchOpen,
      searchQuery,
      searchScope,
      tabsRef,
      showTabsOverflow,
      updateTabsOverflow,
      scrollTabsRight,
      displayedAgreements,
      groupedAgreements,
      searchResults,
      toggleFavorite,
      closeSearch,
      onOpenAgreement,
      onEdit,
      onDuplicate,
      toggleSortOrder,
      shouldShowDateSeparator,
    }
  },
}
</script>
