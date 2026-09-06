<template>
  <ConcordPageShell title="Профиль" variant="settings">
    <section class="concord-settings-group">
      <h2 class="concord-settings-group__title">Аккаунт</h2>
      <div class="concord-profile-card">
        <div class="concord-agreement-form__card-body">
          <label class="concord-agreement-form__field">
            <span class="concord-agreement-form__label">Имя</span>
            <input v-model="name" class="concord-agreement-form__input" type="text" name="name">
          </label>

          <label class="concord-notifications__toggle-row concord-agreement-form__urgency-row">
            <span class="concord-agreement-form__urgency-label">Тёмная тема</span>
            <span class="concord-agreement-form__toggle-trail">
              <span>{{ dark ? 'Вкл' : 'Выкл' }}</span>
              <input v-model="dark" type="checkbox" class="concord-toggle">
            </span>
          </label>
        </div>
      </div>
      <button type="button" class="concord-page-btn concord-page-btn--primary" @click="save">
        Сохранить
      </button>
    </section>

    <section class="concord-settings-group">
      <h2 class="concord-settings-group__title">Подписи</h2>
      <div class="concord-profile-card">
        <div
          v-for="signature in auth.user?.signatures || []"
          :key="signature.id"
          class="concord-notifications__row concord-notifications__row--field"
        >
          <span class="concord-notifications__row-label">{{ signature.name }}</span>
          <span v-if="signature.is_active" class="concord-detail-badge concord-detail-badge--done">активна</span>
          <button
            v-else
            type="button"
            class="concord-page-btn concord-page-btn--secondary"
            @click="auth.activateSignature(signature.id)"
          >
            Сделать активной
          </button>
        </div>
        <p v-if="!(auth.user?.signatures || []).length" class="concord-agreement-form__hint">
          Подписей пока нет.
        </p>
      </div>

      <form class="concord-inline-form" @submit.prevent="addSignature">
        <input
          v-model="newSignature"
          class="concord-agreement-form__input"
          type="text"
          placeholder="Новая подпись"
        >
        <button type="submit" class="concord-page-btn concord-page-btn--secondary">Добавить</button>
      </form>
    </section>

    <section class="concord-settings-group">
      <router-link to="/groups" class="concord-settings-link">
        Группы участников
      </router-link>
    </section>

    <button type="button" class="concord-page-btn concord-page-btn--danger" @click="logout">
      Выйти
    </button>
  </ConcordPageShell>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import ConcordPageShell from '../components/ConcordPageShell.vue'
import { useAuthStore } from '../stores/auth'
import api from '../services/api'

const auth = useAuthStore()
const router = useRouter()
const name = ref(auth.user?.name || '')
const dark = ref(auth.user?.theme === 'dark')
const newSignature = ref('')

watch(() => auth.user, (user) => {
  if (!user) return
  name.value = user.name
  dark.value = user.theme === 'dark'
})

async function save() {
  await auth.updateProfile({ name: name.value, theme: dark.value ? 'dark' : 'light' })
}

async function addSignature() {
  if (!newSignature.value.trim()) return
  await api.post('/api/signatures', { name: newSignature.value })
  newSignature.value = ''
  await auth.fetchUser()
}

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<style scoped>
.concord-settings-link {
  display: flex;
  align-items: center;
  min-height: var(--concord-settings-row-height);
  padding: 0 var(--concord-settings-row-padding-x);
  border: 1px solid var(--concord-card-border);
  border-radius: var(--concord-radius-sm);
  background: var(--concord-card-bg);
  color: var(--concord-primary);
  font-weight: var(--concord-weight-medium);
  text-decoration: none;
}

.concord-page-btn {
  width: 100%;
}

.concord-inline-form .concord-page-btn {
  width: auto;
  flex-shrink: 0;
}

.concord-notifications__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--concord-divider);
}

.concord-notifications__row:last-child {
  border-bottom: none;
}

.concord-notifications__row-label {
  font-weight: var(--concord-weight-medium);
}

.concord-notifications__row .concord-page-btn {
  width: auto;
  min-height: 32px;
  padding: 0 12px;
  font-size: var(--concord-text-caption);
}
</style>
