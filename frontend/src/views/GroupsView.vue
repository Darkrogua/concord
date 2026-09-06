<template>
  <ConcordPageShell title="Группы" show-back variant="settings">
    <section class="concord-settings-group">
      <h2 class="concord-settings-group__title">Новая группа</h2>
      <form class="concord-inline-form" @submit.prevent="create">
        <input
          v-model="name"
          class="concord-agreement-form__input"
          type="text"
          placeholder="Название группы"
          required
        >
        <button type="submit" class="concord-page-btn concord-page-btn--primary">Создать</button>
      </form>
    </section>

    <section class="concord-settings-group">
      <h2 class="concord-settings-group__title">Мои группы</h2>
      <article v-for="group in groups" :key="group.id" class="concord-profile-card concord-section-card">
        <h2 class="concord-section-card__title">{{ group.name }}</h2>
        <div class="concord-section-card__body">
          <p class="concord-block__text">
            {{ (group.users || []).map((u) => u.name).join(', ') || 'Участников пока нет' }}
          </p>
          <button type="button" class="concord-page-btn concord-page-btn--danger" @click="remove(group)">
            Удалить
          </button>
        </div>
      </article>

      <div v-if="!groups.length" class="concord-empty-state">
        <p class="concord-empty-state__text">Групп пока нет. Создайте первую, чтобы быстрее добавлять участников.</p>
      </div>
    </section>
  </ConcordPageShell>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import ConcordPageShell from '../components/ConcordPageShell.vue'
import api from '../services/api'

const groups = ref([])
const name = ref('')
const confirm = useConfirm()

async function load() {
  const { data } = await api.get('/api/user-groups')
  groups.value = data.data
}

async function create() {
  if (!name.value.trim()) return
  await api.post('/api/user-groups', { name: name.value })
  name.value = ''
  await load()
}

function remove(group) {
  confirm.require({
    message: `Удалить группу «${group.name}»?`,
    header: 'Удаление',
    acceptLabel: 'Удалить',
    rejectLabel: 'Отмена',
    accept: async () => {
      await api.delete(`/api/user-groups/${group.id}`)
      await load()
    },
  })
}

onMounted(load)
</script>

<style scoped>
.concord-inline-form .concord-page-btn {
  width: auto;
  flex-shrink: 0;
}

.concord-section-card__body .concord-page-btn {
  margin-top: 12px;
  width: auto;
}

.concord-empty-state {
  margin: 24px 0;
  text-align: center;
}

.concord-empty-state__text {
  margin: 0;
  color: var(--concord-text-muted);
}
</style>
