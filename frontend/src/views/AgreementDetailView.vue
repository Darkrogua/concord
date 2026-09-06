<template>
  <ConcordPageShell v-if="item" :title="item.title" show-back>
    <p class="concord-meta">{{ item.status_label }} · {{ formatDateTime(item.deadline) }}</p>
    <p>{{ item.description }}</p>
    <div class="concord-form-actions mb-3">
      <Button v-if="item.is_author && item.status === 'draft'" label="Редактировать" @click="router.push(`/agreements/${item.id}/edit`)" />
      <Button v-if="item.is_author && item.public_token" outlined label="Ссылка" @click="copyShare" />
    </div>
    <section v-for="section in item.sections" :key="section.id" class="concord-card concord-card--flat">
      <h2 class="concord-card__title">{{ section.name }}</h2>
      <div v-for="block in section.blocks" :key="block.id" class="mb-2">
        <h3 v-if="block.title">{{ block.title }}</h3>
        <pre v-if="block.type === 'code'" class="concord-code">{{ block.content?.body }}</pre>
        <p v-else style="white-space: pre-wrap">{{ block.content?.body }}</p>
      </div>
      <h3>Голос</h3>
      <div v-if="section.can_vote" class="concord-form-actions">
        <Button label="Да" @click="vote(section, 'yes')" />
        <Button label="Нет" outlined severity="danger" @click="rejecting = section.id" />
      </div>
      <div v-if="rejecting === section.id" class="concord-form">
        <Textarea v-model="comment" rows="3" class="w-full" placeholder="Почему?" />
        <Button label="Отправить отказ" @click="vote(section, 'no')" />
      </div>
      <p v-else-if="section.own_vote">Ваш голос: {{ section.own_vote === 'yes' ? 'да' : 'нет' }}</p>
    </section>
  </ConcordPageShell>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from 'primevue/button'
import Textarea from 'primevue/textarea'
import ConcordPageShell from '../components/ConcordPageShell.vue'
import api from '../services/api'
import { formatDateTime } from '../utils/format'

const route = useRoute()
const router = useRouter()
const item = ref(null)
const rejecting = ref(null)
const comment = ref('')

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

<style scoped>
.concord-meta { color: var(--concord-text-muted); margin-top: 0; }
.concord-form-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.concord-card--flat { padding: 1rem; margin-bottom: 0.75rem; }
.concord-code { white-space: pre-wrap; background: var(--concord-surface-muted); padding: 0.75rem; border-radius: var(--concord-radius-xs); }
.concord-form { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem; }
.w-full { width: 100%; }
.mb-2 { margin-bottom: 0.5rem; }
.mb-3 { margin-bottom: 1rem; }
</style>
