<template>
  <div class="concord-page">
    <ConcordPageHeader>
      <template #left>
        <h1 class="concord-page-header__brand concord-page-header__brand--mobile">
          <ConcordLogoMark size="sm" />
          <span class="concord-page-header__brand-name">Concord</span>
        </h1>
      </template>
      <template #right>
        <div class="concord-page-header__actions">
          <button type="button" class="concord-icon-btn" aria-label="Создать" @click="router.push('/agreements/create')">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M12 6v12M6 12h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
          </button>
        </div>
      </template>
    </ConcordPageHeader>

    <div class="concord-list-tabs-sticky">
      <div class="concord-tabs-wrap">
        <div class="concord-tabs" role="tablist" aria-label="Фильтры списка">
          <button
            v-for="tab in tabs"
            :key="tab.value"
            type="button"
            role="tab"
            :class="['concord-tabs__item', { 'concord-tabs__item--active': group === tab.value }]"
            :aria-selected="group === tab.value"
            @click="selectTab(tab.value)"
          >
            {{ tab.label }}
          </button>
        </div>
      </div>
    </div>

    <main v-if="loading" class="concord-list concord-card-skeleton" aria-label="Загрузка…">
      <article v-for="n in 3" :key="n" class="concord-card concord-card-skeleton__card" aria-hidden="true">
        <div class="concord-card-skeleton__header">
          <span class="concord-card-skeleton__bone concord-card-skeleton__bone--number" />
          <span class="concord-card-skeleton__bone concord-card-skeleton__bone--status" />
        </div>
        <span class="concord-card-skeleton__bone concord-card-skeleton__bone--title" />
      </article>
    </main>

    <main v-else class="concord-list">
      <AgreementCard
        v-for="item in cards"
        :key="item.id"
        :agreement="item"
        @open="openAgreement"
        @toggle-favorite="favorite"
      />

      <div v-if="!cards.length" class="concord-empty-state">
        <div class="concord-empty-state__icon" aria-hidden="true">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
            <path d="M4 6.5h16M4 12h16M4 17.5h10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          </svg>
        </div>
        <h2 class="concord-empty-state__title">Список пуст</h2>
        <p class="concord-empty-state__text">
          {{ emptyText }}
        </p>
        <button
          type="button"
          class="concord-agreement-form__submit concord-empty-state__action"
          @click="router.push('/agreements/create')"
        >
          Создать согласование
        </button>
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import ConcordPageHeader from '../components/concord/ConcordPageHeader.vue'
import ConcordLogoMark from '../components/concord/ConcordLogoMark.vue'
import AgreementCard from '../components/concord/AgreementCard.vue'
import { normalizeAgreement } from '../components/concord/agreement-card-utils.js'
import api from '../services/api'

const router = useRouter()
const items = ref([])
const loading = ref(false)
const group = ref('incoming')

const tabs = [
  { label: 'Входящие', value: 'incoming' },
  { label: 'Исходящие', value: 'outgoing' },
  { label: 'Избранное', value: 'favorites' },
  { label: 'Архив', value: 'archive' },
  { label: 'Все', value: 'all' },
]

const cards = computed(() => items.value.map(normalizeAgreement))

const emptyText = computed(() => {
  const messages = {
    incoming: 'Во входящих пока нет согласований, которые ждут вашего решения.',
    outgoing: 'Вы ещё не создавали согласований — начните с первого.',
    favorites: 'Добавляйте согласования в избранное, чтобы быстро находить их здесь.',
    archive: 'В архиве пока пусто.',
    all: 'Создайте первое согласование, чтобы собрать голоса участников.',
  }
  return messages[group.value] || 'Пока нет согласований в этом списке.'
})

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/api/agreements', { params: { group: group.value, sort: 'newest' } })
    items.value = data.data
  } finally {
    loading.value = false
  }
}

function selectTab(value) {
  group.value = value
  load()
}

function openAgreement(id) {
  router.push(`/agreements/${id}`)
}

async function favorite(id) {
  await api.post(`/api/agreements/${id}/favorite`)
  await load()
}

onMounted(load)
</script>

<style scoped>
.concord-page-header__brand--mobile {
  display: flex;
}

@media (min-width: 960px) {
  .concord-page-header__brand--mobile {
    display: none;
  }
}
</style>
