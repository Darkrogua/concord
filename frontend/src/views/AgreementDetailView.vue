<template>
  <ConcordPageShell v-if="item" :title="item.title" show-back>
    <div class="concord-detail-meta">
      <span class="concord-detail-badge" :class="statusClass">{{ item.status_label }}</span>
      <span class="concord-detail-badge concord-detail-badge--active">
        {{ formatDateTime(item.deadline) }}
      </span>
    </div>

    <p v-if="item.description" class="concord-detail-description">{{ item.description }}</p>

    <div class="concord-detail-actions">
      <button
        v-if="item.is_author && item.status === 'draft'"
        type="button"
        class="concord-page-btn concord-page-btn--primary"
        @click="router.push(`/agreements/${item.id}/edit`)"
      >
        Редактировать
      </button>
      <button
        v-if="item.is_author && item.public_token"
        type="button"
        class="concord-page-btn concord-page-btn--secondary"
        @click="copyShare"
      >
        Скопировать ссылку
      </button>
    </div>

    <section v-for="section in item.sections" :key="section.id" class="concord-profile-card concord-section-card">
      <h2 class="concord-section-card__title">{{ section.name }}</h2>
      <div class="concord-section-card__body">
        <article v-for="block in section.blocks" :key="block.id" class="concord-block">
          <h3 v-if="block.title" class="concord-block__title">{{ block.title }}</h3>
          <pre v-if="block.type === 'code'" class="concord-block__code">{{ block.content?.body }}</pre>
          <p v-else class="concord-block__text">{{ block.content?.body }}</p>
        </article>

        <div class="concord-vote-panel">
          <h3 class="concord-vote-panel__title">Ваше решение</h3>
          <div v-if="rejecting === section.id">
            <textarea
              v-model="comment"
              class="concord-agreement-form__input concord-agreement-form__textarea"
              rows="3"
              placeholder="Почему вы не согласны?"
            />
            <div class="concord-vote-panel__actions">
              <button type="button" class="concord-page-btn concord-page-btn--danger" @click="vote(section, 'no')">
                Отправить отказ
              </button>
              <button type="button" class="concord-page-btn concord-page-btn--secondary" @click="rejecting = null">
                Отмена
              </button>
            </div>
          </div>
          <div v-else-if="section.can_vote" class="concord-vote-panel__actions">
            <button type="button" class="concord-page-btn concord-page-btn--primary" @click="vote(section, 'yes')">
              Да
            </button>
            <button type="button" class="concord-page-btn concord-page-btn--danger" @click="rejecting = section.id">
              Нет
            </button>
          </div>
          <p v-else-if="section.own_vote" class="concord-vote-panel__status">
            Ваш голос: {{ section.own_vote === 'yes' ? 'согласен' : 'не согласен' }}
          </p>
          <p v-else class="concord-vote-panel__status">Голосование недоступно</p>
        </div>
      </div>
    </section>
  </ConcordPageShell>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ConcordPageShell from '../components/ConcordPageShell.vue'
import api from '../services/api'
import { formatDateTime } from '../utils/format'

const route = useRoute()
const router = useRouter()
const item = ref(null)
const rejecting = ref(null)
const comment = ref('')

const statusClass = computed(() => {
  if (!item.value) return ''
  if (item.value.status === 'draft') return 'concord-detail-badge--draft'
  if (item.value.status === 'approved' || item.value.status === 'completed') return 'concord-detail-badge--done'
  return 'concord-detail-badge--active'
})

async function load() {
  const { data } = await api.get(`/api/agreements/${route.params.id}`)
  item.value = data.data
}

async function vote(section, choice) {
  await api.post(`/api/sections/${section.id}/vote`, {
    vote: choice,
    comment: choice === 'no' ? comment.value : null,
  })
  rejecting.value = null
  comment.value = ''
  await load()
}

async function copyShare() {
  await navigator.clipboard.writeText(`${window.location.origin}/share/${item.value.public_token}`)
}

onMounted(load)
</script>
