<template>
  <ConcordAuthShell title="Сброс пароля" subtitle="Отправим ссылку на email">
    <form class="concord-auth-form concord-agreement-form" @submit.prevent="submit">
      <label class="concord-agreement-form__field">
        <span class="concord-agreement-form__label">Email</span>
        <input
          v-model="email"
          class="concord-agreement-form__input"
          type="email"
          name="email"
          autocomplete="email"
          required
        >
      </label>

      <p v-if="done" class="concord-auth-success" role="status">
        Если email есть в системе, письмо отправлено.
      </p>

      <button class="concord-agreement-form__submit" type="submit" :disabled="loading">
        {{ loading ? 'Отправляем…' : 'Отправить ссылку' }}
      </button>

      <div class="concord-auth-links">
        <router-link to="/login">Назад ко входу</router-link>
      </div>
    </form>
  </ConcordAuthShell>
</template>

<script setup>
import { ref } from 'vue'
import ConcordAuthShell from '../components/concord/ConcordAuthShell.vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const email = ref('')
const loading = ref(false)
const done = ref(false)

async function submit() {
  loading.value = true
  try {
    await auth.forgotPassword(email.value)
    done.value = true
  } finally {
    loading.value = false
  }
}
</script>
